<?php

namespace App\Services;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Components\Expression;

class SQLParser
{
    /**
     * Parse SQL text dan return array tabel dengan format SAMA seperti DBMLParser.
     *
     * Output per tabel:
     * [
     *   'name'            => 'users',
     *   'columns'         => [['name' => 'id', 'type' => 'int', 'pk' => true, ...], ...],
     *   'pk_columns'      => ['id'],
     *   'non_pk_columns'  => ['name', 'email'],
     *   'suggested_fds'   => [['lhs' => ['id'], 'rhs' => 'name'], ...],
     * ]
     *
     * @param string $sqlText Isi file .sql (CREATE TABLE + optional INSERT)
     * @return array
     * @throws \Exception
     */
    public function parse(string $sqlText): array
    {
        $sqlText = preg_replace('/^\xEF\xBB\xBF/', '', $sqlText);

        // Validasi format yang didukung
        $lowerSql = strtolower($sqlText);
        if (str_contains($lowerSql, 'sqlite')) {
            throw new \Exception('SQLite format is not supported. Please use MySQL or PostgreSQL.');
        }

        // Cek apakah ada CREATE TABLE sama sekali
        if (!preg_match('/CREATE\s+TABLE/i', $sqlText)) {
            throw new \Exception('No CREATE TABLE statements found. Please make sure your SQL file contains table definitions.');
        }
        // Preprocessing
        $sqlText = preg_replace('/^\xEF\xBB\xBF/', '', $sqlText);
        $sqlText = preg_replace('/^\/\*.*?\*\/;?\s*/ms', '', $sqlText);
        $sqlText = preg_replace('/^SET\s+\w.*?;/mi', '', $sqlText);
        $sqlText = preg_replace('/^START\s+TRANSACTION;/mi', '', $sqlText);
        // Konversi PostgreSQL → MySQL agar bisa di-parse
        $sqlText = $this->convertPostgresToMysql($sqlText);

        $parser = new Parser($sqlText);
        $tables = [];
        $inserts = $this->collectInsertStatements($parser);

        // ── Ambil PK dari ALTER TABLE ──────────────────────────────────
        $alterPks = $this->extractPrimaryKeysFromAlter($sqlText);

        foreach ($parser->statements as $stmt) {
            if ($stmt instanceof CreateStatement && $stmt->name !== null) {
                $tableName   = $stmt->name->table;
                $columns     = $this->parseColumnsFromCreate($stmt);

                // Gabungkan PK dari kolom + ALTER TABLE
                $pkFromCol   = array_column(array_filter($columns, fn($c) => $c['pk']), 'name');
                $pkFromAlter = $alterPks[$tableName] ?? [];
                $pkColumns   = array_values(array_unique(array_merge($pkFromCol, $pkFromAlter)));

                // Tandai kolom yang masuk PK
                foreach ($columns as &$col) {
                    if (in_array($col['name'], $pkColumns)) {
                        $col['pk']       = true;
                        $col['not_null'] = true;
                    }
                }
                unset($col);

                $nonPkColumns = array_values(array_diff(
                    array_column($columns, 'name'),
                    $pkColumns
                ));

                $tables[] = [
                    'name'           => $tableName,
                    'columns'        => $columns,
                    'pk_columns'     => $pkColumns,
                    'non_pk_columns' => $nonPkColumns,
                    'suggested_fds'  => $this->generateFunctionalDependencies($pkColumns, $nonPkColumns),
                    'data'           => $inserts[$tableName] ?? [],
                ];
            }
        }

        if (empty($tables)) {
            throw new \Exception('No CREATE TABLE statements found in SQL.');
        }

        return ['tables' => $tables];
    }

    /**
     * Ekstrak definisi kolom dari CREATE TABLE statement.
     *
     * @param CreateStatement $stmt
     * @return array
     */
    private function parseColumnsFromCreate(CreateStatement $stmt): array
    {
        $columns = [];

        if (!isset($stmt->fields) || !is_array($stmt->fields)) {
            return $columns;
        }

        foreach ($stmt->fields as $field) {
            if (!$field instanceof CreateDefinition) {
                continue;
            }

            if ($field->name !== null) {
                $colName  = trim($field->name, '"');
                $colType  = $this->normalizeType($field->type ? $field->type->name : 'unknown');
                $isPk     = $this->isColumnPrimaryKey($field);
                $isUnique = $this->isColumnUnique($field);
                $isNotNull = $this->isColumnNotNull($field) || $isPk;

                $columns[] = [
                    'name'     => $colName,
                    'type'     => $colType,
                    'pk'       => $isPk,
                    'unique'   => $isUnique,
                    'not_null' => $isNotNull,
                ];
            }
        }

        return $columns;
    }

    /**
     * Ekstrak PRIMARY KEY dari ALTER TABLE statements menggunakan regex.
     * Contoh: ALTER TABLE `users` ADD PRIMARY KEY (`id`);
     *
     * @return array<string, string[]>  [tableName => [col1, col2, ...]]
     */
    private function extractPrimaryKeysFromAlter(string $sqlText): array
    {
        $pks = [];

        preg_match_all(
            '/ALTER\s+TABLE\s+"?`?(\w+)`?"?\s+.*?ADD\s+PRIMARY\s+KEY\s*\(([^)]+)\)/si',
            $sqlText,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $tableName = $match[1];
            $colList   = $match[2];
            preg_match_all('/"?`?(\w+)`?"?/', $colList, $colMatches);
            $pks[$tableName] = $colMatches[1];
        }

        return $pks;
    }

    private function convertPostgresToMysql(string $sql): string
    {
        // Kutip ganda nama kolom/tabel → backtick
        $sql = preg_replace('/"(\w+)"/', '`$1`', $sql);

        // Tipe PostgreSQL → MySQL
        $sql = preg_replace('/\buuid\b/i',      'varchar(36)', $sql);
        $sql = preg_replace('/\bserial\b/i',    'int NOT NULL AUTO_INCREMENT', $sql);
        $sql = preg_replace('/\bboolean\b/i',   'tinyint(1)', $sql);
        $sql = preg_replace('/\btext\b/i',      'longtext', $sql);
        $sql = preg_replace('/\bnumeric\b/i',   'decimal', $sql);
        $sql = preg_replace('/\bsmallint\b/i',  'smallint', $sql);
        $sql = preg_replace('/\bjson\b/i',      'json', $sql);
        $sql = preg_replace('/\btimestamp\b/i', 'timestamp', $sql);

        // DEFAULT now() → DEFAULT CURRENT_TIMESTAMP
        $sql = preg_replace('/DEFAULT\s+now\(\)/i', 'DEFAULT CURRENT_TIMESTAMP', $sql);

        // Hapus ALTER TABLE FOREIGN KEY (tidak diperlukan untuk parsing kolom)
        $sql = preg_replace('/ALTER\s+TABLE\s+`\w+`\s+ADD\s+FOREIGN\s+KEY[^;]+;/si', '', $sql);

        return $sql;
    }


    /**
     * Normalisasi tipe data (hapus parameter, ubah ke lowercase).
     */
    private function normalizeType($type): string
    {
        if (!$type) return 'unknown';
        // Hilangkan angka dalam kurung (varchar(255) -> varchar)
        $cleaned = preg_replace('/\(\d+\)/', '', $type);
        return strtolower(trim($cleaned));
    }

    /**
     * Cek apakah kolom memiliki PRIMARY KEY flag.
     */
    private function isColumnPrimaryKey(CreateDefinition $field): bool
    {
        if ($field->options && isset($field->options->options)) {
            foreach ($field->options->options as $opt) {
                $val = is_array($opt) ? ($opt['name'] ?? '') : (string) $opt;
                if (strtoupper($val) === 'PRIMARY KEY') {
                    return true;
                }
            }
        }
        return false;
    }

    private function isColumnUnique(CreateDefinition $field): bool
    {
        if ($field->options && isset($field->options->options)) {
            foreach ($field->options->options as $opt) {
                $val = is_array($opt) ? ($opt['name'] ?? '') : (string) $opt;
                if (strtoupper($val) === 'UNIQUE') {
                    return true;
                }
            }
        }
        return false;
    }

    private function isColumnNotNull(CreateDefinition $field): bool
    {
        if ($field->options && isset($field->options->options)) {
            foreach ($field->options->options as $opt) {
                $val = is_array($opt) ? ($opt['name'] ?? '') : (string) $opt;
                if (strtoupper($val) === 'NOT NULL') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Kumpulkan INSERT statements per tabel untuk sample data (maksimal 20 baris per tabel).
     *
     * @param Parser $parser
     * @return array<string, array>  [tableName => [row1, row2, ...]]
     */
    private function collectInsertStatements(Parser $parser): array
    {
        $inserts = [];

        foreach ($parser->statements as $stmt) {
            if ($stmt instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
                $tableName = $stmt->into->dest->table;
                if (!isset($inserts[$tableName])) {
                    $inserts[$tableName] = [];
                }
                if (count($inserts[$tableName]) >= 20) continue; // batasi sample

                // Ambil nilai VALUES
                if (isset($stmt->values) && is_array($stmt->values)) {
                    foreach ($stmt->values as $row) {
                        $rowData = [];
                        if (isset($row->values) && is_array($row->values)) {
                            foreach ($row->values as $val) {
                                $rowData[] = $val->value ?? null;
                            }
                        }
                        if (!empty($rowData)) {
                            $inserts[$tableName][] = $rowData;
                        }
                    }
                }
            }
        }

        return $inserts;
    }

    // ============================================================
    //  HEURISTIK FD (sama persis dengan DBMLParser)
    // ============================================================

    /**
     * Generate FD otomatis menggunakan heuristik prefix.
     * Sama dengan DBMLParser::generateFunctionalDependencies
     */
    private function generateFunctionalDependencies(array $pkColumns, array $nonPkColumns): array
    {
        $fds = [];

        // PK tunggal
        if (count($pkColumns) <= 1) {
            foreach ($nonPkColumns as $col) {
                $fds[] = ['lhs' => $pkColumns, 'rhs' => $col];
            }
            return $fds;
        }

        // PK composite: bangun prefix map
        $prefixMap = [];
        foreach ($pkColumns as $pk) {
            $prefix = $this->extractPrefix($pk);
            if ($prefix !== '') {
                $prefixMap[$prefix] = $pk;
            }
        }

        foreach ($nonPkColumns as $col) {
            $matchedPk = $this->findMatchingPk($col, $prefixMap);
            if ($matchedPk !== null) {
                $fds[] = ['lhs' => [$matchedPk], 'rhs' => $col];
            } else {
                $fds[] = ['lhs' => $pkColumns, 'rhs' => $col];
            }
        }

        return $fds;
    }

    private function extractPrefix(string $columnName): string
    {
        $suffix = '/_(id|no|code|key|num|uuid|pk)$/i';
        $prefix = preg_replace($suffix, '', $columnName);
        return $prefix !== $columnName ? strtolower($prefix) : '';
    }

    private function findMatchingPk(string $columnName, array $prefixMap): ?string
    {
        $colLower = strtolower($columnName);
        foreach ($prefixMap as $prefix => $pkColumn) {
            if ($prefix === '') continue;
            if (str_starts_with($colLower, $prefix)) {
                return $pkColumn;
            }
        }
        return null;
    }
}
