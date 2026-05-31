<?php

namespace App\Services;

use PhpMyAdmin\SqlParser\Parser;

class SQLParser
{
    /**
     * Parse SQL text dan return array tabel.
     *
     * @param string $sqlText Isi file .sql (CREATE TABLE + optional INSERT)
     * @return array
     * @throws \Exception
     */
    public function parse(string $sqlText): array
    {
        // Hapus BOM
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
        $sqlText = $this->preprocessSql($sqlText);

        // Konversi PostgreSQL ke MySQL
        $sqlText = $this->convertPostgresToMysql($sqlText);

        // ⭐ EKSTRAK CREATE TABLE DENGAN BALANCED PARENTHESES
        $tables = $this->extractTablesBalanced($sqlText);

        // Ambil INSERT statements untuk sample data
        $parser = new Parser($sqlText);
        $inserts = $this->collectInsertStatements($parser);

        // Gabungkan data sample dengan tabel hasil regex
        foreach ($tables as &$table) {
            $tableName = $table['name'];
            $table['data'] = $inserts[$tableName] ?? [];
        }

        if (empty($tables)) {
            throw new \Exception('No CREATE TABLE statements found in SQL.');
        }

        return ['tables' => $tables];
    }

    /**
     * Ekstrak CREATE TABLE dengan balanced parentheses matching
     * Method ini membaca karakter per karakter untuk menemukan kurung buka dan tutup
     *
     * @param string $sqlText
     * @return array
     */
    private function extractTablesBalanced(string $sqlText): array
    {
        $tables = [];

        // ⭐ TAMBAH INI: Extract PRIMARY KEYS dari ALTER TABLE
        $primaryKeysFromAlter = $this->extractPrimaryKeysFromAlter($sqlText);

        // Cari semua posisi "CREATE TABLE"
        $pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?(\w+)`?\s*\(/i';
        preg_match_all($pattern, $sqlText, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $idx => $match) {
            $tableName = $matches[1][$idx][0];
            $startPos = $match[1];

            // Cari kurung buka '(' setelah CREATE TABLE
            $openPos = strpos($sqlText, '(', $startPos);
            if ($openPos === false) continue;

            // Cari kurung tutup yang seimbang
            $closePos = $this->findMatchingClosingBracket($sqlText, $openPos);
            if ($closePos === false) continue;

            // Extract body antara kurung
            $tableBody = substr($sqlText, $openPos + 1, $closePos - $openPos - 1);

            // Parse kolom dari body
            $columns = $this->parseColumnsFromBody($tableBody);

            // Ekstrak PRIMARY KEY dari CREATE TABLE (inline/constraint)
            $pkFromCreate = $this->extractPrimaryKeyFromBody($tableBody);

            // ⭐ AMBIL PRIMARY KEY DARI ALTER TABLE
            $pkFromAlter = $primaryKeysFromAlter[$tableName] ?? [];

            // ⭐ GABUNGKAN SEMUA SUMBER PK
            $pkColumns = array_values(array_unique(array_merge($pkFromCreate, $pkFromAlter)));

            // Tandai kolom yang termasuk PK
            foreach ($columns as &$col) {
                if (in_array($col['name'], $pkColumns)) {
                    $col['pk'] = true;
                    $col['not_null'] = true;
                }
            }
            unset($col);

            // Jika tidak ada PK sama sekali, cek apakah ada kolom bernama 'id'
            if (empty($pkColumns)) {
                foreach ($columns as &$col) {
                    if ($col['name'] === 'id' && !$col['pk']) {
                        $pkColumns = ['id'];
                        $col['pk'] = true;
                        $col['not_null'] = true;
                        break;
                    }
                }
                unset($col);
            }

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
                'data'           => [],
            ];
        }

        return $tables;
    }

    /**
     * Mencari posisi kurung tutup yang seimbang dengan kurung buka di posisi $openPos
     *
     * @param string $sqlText
     * @param int $openPos
     * @return int|false
     */
    private function findMatchingClosingBracket(string $sqlText, int $openPos): int|false
    {
        $depth = 1;
        $len = strlen($sqlText);

        for ($i = $openPos + 1; $i < $len; $i++) {
            $char = $sqlText[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return false;
    }

    /**
     * Preprocessing SQL: hapus komentar dan SET statements
     */
    private function preprocessSql(string $sqlText): string
    {
        // Hapus komentar block /* ... */
        $sqlText = preg_replace('/\/\*.*?\*\//s', '', $sqlText);

        // Hapus komentar satu baris --
        $sqlText = preg_replace('/--[^\n]*\n/', "\n", $sqlText);

        // Hapus komentar satu baris #
        $sqlText = preg_replace('/^#[^\n]*\n/m', "\n", $sqlText);

        // Hapus SET statements
        $sqlText = preg_replace('/^SET\s+\w.*?;$/mi', '', $sqlText);

        // Hapus START TRANSACTION
        $sqlText = preg_replace('/^START\s+TRANSACTION;$/mi', '', $sqlText);

        // Hapus COMMIT
        $sqlText = preg_replace('/^COMMIT;$/mi', '', $sqlText);

        return $sqlText;
    }

    /**
     * Parse kolom dari body CREATE TABLE.
     *
     * @param string $tableBody
     * @return array
     */
    private function parseColumnsFromBody(string $tableBody): array
    {
        $columns = [];

        // Split berdasarkan baris
        $lines = explode("\n", $tableBody);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Skip constraint lines
            if (preg_match('/^(PRIMARY|FOREIGN|KEY|INDEX|CONSTRAINT|UNIQUE)\s+/i', $line)) {
                continue;
            }

            // Skip baris PRIMARY KEY
            if (preg_match('/^PRIMARY\s+KEY/i', $line)) {
                continue;
            }

            // Hapus trailing comma
            $line = rtrim($line, ',');

            // Parse: `column_name` type [options]
            if (preg_match('/^`?([a-zA-Z0-9_]+)`?\s+([a-zA-Z][a-zA-Z0-9_()]*)/', $line, $colMatch)) {
                $colName = $colMatch[1];
                $colTypeRaw = $colMatch[2];
                $colType = $this->normalizeType($colTypeRaw);

                $isPkInline = stripos($line, 'PRIMARY KEY') !== false;
                $isNotNull = stripos($line, 'NOT NULL') !== false || $isPkInline;
                $isUnique = stripos($line, 'UNIQUE') !== false;

                $columns[] = [
                    'name'     => $colName,
                    'type'     => $colType,
                    'pk'       => $isPkInline,
                    'unique'   => $isUnique,
                    'not_null' => $isNotNull,
                ];
            }
        }

        return $columns;
    }

    /**
     * Ekstrak PRIMARY KEY dari body CREATE TABLE.
     *
     * @param string $tableBody
     * @return string[]
     */
    private function extractPrimaryKeyFromBody(string $tableBody): array
    {
        $pkColumns = [];

        // Gabungkan semua baris jadi satu untuk memudahkan regex
        $bodyOneLine = preg_replace('/\s+/', ' ', $tableBody);

        // Pattern untuk PRIMARY KEY ( `col1`, `col2` )
        if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $bodyOneLine, $matches)) {
            $colList = $matches[1];
            preg_match_all('/`?([a-zA-Z0-9_]+)`?/', $colList, $colMatches);
            $pkColumns = $colMatches[1];
        }

        return array_values(array_unique($pkColumns));
    }

    /**
     * Konversi PostgreSQL ke MySQL
     */
    private function convertPostgresToMysql(string $sql): string
    {
        $sql = preg_replace('/"(\w+)"/', '`$1`', $sql);
        $sql = preg_replace('/\buuid\b/i', 'varchar(36)', $sql);
        $sql = preg_replace('/\bserial\b/i', 'int NOT NULL AUTO_INCREMENT', $sql);
        $sql = preg_replace('/\bbigserial\b/i', 'bigint NOT NULL AUTO_INCREMENT', $sql);
        $sql = preg_replace('/\bboolean\b/i', 'tinyint(1)', $sql);
        $sql = preg_replace('/\btext\b/i', 'longtext', $sql);
        $sql = preg_replace('/\bnumeric\b/i', 'decimal', $sql);
        $sql = preg_replace('/\bsmallint\b/i', 'smallint', $sql);
        $sql = preg_replace('/\bjson\b/i', 'json', $sql);
        $sql = preg_replace('/\btimestamp\b/i', 'timestamp', $sql);
        $sql = preg_replace('/\benum\([^)]+\)/i', 'varchar(255)', $sql);
        $sql = preg_replace('/DEFAULT\s+now\(\)/i', 'DEFAULT CURRENT_TIMESTAMP', $sql);

        return $sql;
    }

    /**
     * Normalisasi tipe data
     */
    private function normalizeType(string $type): string
    {
        if (!$type) return 'unknown';
        $cleaned = preg_replace('/\(\d+\)/', '', $type);
        $cleaned = preg_replace('/\s+unsigned/i', '', $cleaned);
        return strtolower(trim($cleaned));
    }

    /**
     * Kumpulkan INSERT statements per tabel
     */
    private function collectInsertStatements(Parser $parser): array
    {
        $inserts = [];

        foreach ($parser->statements as $stmt) {
            if ($stmt instanceof \PhpMyAdmin\SqlParser\Statements\InsertStatement) {
                if ($stmt->into === null || $stmt->into->dest === null) {
                    continue;
                }

                $tableName = $stmt->into->dest->table;
                if (!isset($inserts[$tableName])) {
                    $inserts[$tableName] = [];
                }
                if (count($inserts[$tableName]) >= 20) continue;

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
    //  HEURISTIK FD
    // ============================================================

    private function generateFunctionalDependencies(array $pkColumns, array $nonPkColumns): array
    {
        $fds = [];

        if (count($pkColumns) <= 1) {
            foreach ($nonPkColumns as $col) {
                $fds[] = ['lhs' => $pkColumns, 'rhs' => $col];
            }
            return $fds;
        }

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

    /**
     * Ekstrak PRIMARY KEY dari ALTER TABLE statements
     * 
     * Contoh: 
     *   ALTER TABLE `cache` ADD PRIMARY KEY (`key`);
     *   ALTER TABLE `users` ADD PRIMARY KEY (`id`);
     *
     * @param string $sqlText
     * @return array<string, string[]> [tableName => [col1, col2, ...]]
     */
    private function extractPrimaryKeysFromAlter(string $sqlText): array
    {
        $primaryKeys = [];

        // Pattern untuk ALTER TABLE ... ADD PRIMARY KEY (...)
        $pattern = '/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+PRIMARY\s+KEY\s*\(([^)]+)\)/i';

        preg_match_all($pattern, $sqlText, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $tableName = $match[1];
            $columnList = $match[2];

            // Extract column names (supports backtick and plain names)
            preg_match_all('/`?([a-zA-Z0-9_]+)`?/', $columnList, $colMatches);
            $primaryKeys[$tableName] = $colMatches[1];
        }

        return $primaryKeys;
    }
}
