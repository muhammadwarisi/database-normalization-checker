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
        $parser = new Parser($sqlText);
        $tables = [];

        // Kelompokkan INSERT statements per tabel untuk sample data (tidak dipakai di output, tapi bisa disimpan)
        $inserts = $this->collectInsertStatements($parser);

        foreach ($parser->statements as $stmt) {
            if ($stmt instanceof CreateStatement && $stmt->name !== null) {
                $tableName = $stmt->name->table;
                $columns = $this->parseColumnsFromCreate($stmt);
                $pkColumns = $this->extractPrimaryKeyColumns($stmt, $columns);
                $nonPkColumns = array_values(array_diff(
                    array_column($columns, 'name'),
                    $pkColumns
                ));

                // Tambahkan sample data jika ada (opsional, untuk deteksi multi-value nanti)
                $sampleData = $inserts[$tableName] ?? [];

                $tables[] = [
                    'name'           => $tableName,
                    'columns'        => $columns,
                    'pk_columns'     => $pkColumns,
                    'non_pk_columns' => $nonPkColumns,
                    'suggested_fds'  => $this->generateFunctionalDependencies($pkColumns, $nonPkColumns),
                    'data'           => $sampleData, // tambahan untuk 1NF checker
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

            // Kolom biasa (bukan constraint)
            if ($field->name !== null) {
                $colName = $field->name;
                $colType = $this->normalizeType($field->type ? $field->type->name : 'unknown');
                $isPk = $this->isColumnPrimaryKey($field);
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
     * Ambil daftar primary key columns (dari kolom dengan flag PK atau dari constraint PRIMARY KEY).
     *
     * @param CreateStatement $stmt
     * @param array $columns Hasil parseColumnsFromCreate
     * @return string[]
     */
    private function extractPrimaryKeyColumns(CreateStatement $stmt, array $columns): array
    {
        $pkColumns = [];

        // Cek dari kolom yang punya flag pk = true
        foreach ($columns as $col) {
            if ($col['pk']) {
                $pkColumns[] = $col['name'];
            }
        }

        // Cek dari constraint PRIMARY KEY (misal: PRIMARY KEY (id, email))
        if (isset($stmt->fields) && is_array($stmt->fields)) {
            foreach ($stmt->fields as $field) {
                if ($field instanceof CreateDefinition && $field->key !== null && $field->key === 'PRIMARY KEY') {
                    // $field->references adalah array of Expression
                    if (isset($field->references) && is_array($field->references)) {
                        foreach ($field->references as $ref) {
                            if ($ref instanceof Expression && $ref->column !== null) {
                                $pkColumns[] = $ref->column;
                            }
                        }
                    }
                }
            }
        }

        return array_values(array_unique($pkColumns));
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
                if (strtoupper($opt) === 'PRIMARY KEY') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Cek apakah kolom memiliki UNIQUE flag.
     */
    private function isColumnUnique(CreateDefinition $field): bool
    {
        if ($field->options && isset($field->options->options)) {
            foreach ($field->options->options as $opt) {
                if (strtoupper($opt) === 'UNIQUE') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Cek apakah kolom memiliki NOT NULL flag.
     */
    private function isColumnNotNull(CreateDefinition $field): bool
    {
        if ($field->options && isset($field->options->options)) {
            foreach ($field->options->options as $opt) {
                if (strtoupper($opt) === 'NOT NULL') {
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