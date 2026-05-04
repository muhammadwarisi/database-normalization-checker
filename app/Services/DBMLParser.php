<?php

namespace App\Services;

class DBMLParser
{
    /**
     * Parse DBML text dan return array tabel.
     *
     * Output per tabel:
     * [
     *   'name'            => 'course_enrollments',
     *   'columns'         => [...],
     *   'pk_columns'      => ['student_id', 'course_id'],
     *   'non_pk_columns'  => ['student_name', 'course_name', 'enrollment_date', 'grade'],
     *   'suggested_fds'   => [
     *     ['lhs' => ['student_id'], 'rhs' => 'student_name'],
     *     ['lhs' => ['course_id'],  'rhs' => 'course_name'],
     *     ['lhs' => ['student_id', 'course_id'], 'rhs' => 'enrollment_date'],
     *     ['lhs' => ['student_id', 'course_id'], 'rhs' => 'grade'],
     *   ],
     * ]
     */
    public function parse(string $dbmlText): array
    {
        $tables = [];

        // Hapus komentar
        $dbmlText = preg_replace('/\/\/.*$/m', '', $dbmlText);
        $dbmlText = preg_replace('/\/\*.*?\*\//s', '', $dbmlText);

        // Extract semua block Table { }
        preg_match_all('/Table\s+(\w+)\s*\{([^}]+)\}/s', $dbmlText, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            throw new \Exception('No tables found in DBML.');
        }

        foreach ($matches as $match) {
            $tableName = $match[1];
            $tableBody = $match[2];

            $columns = $this->parseColumns($tableBody);

            $pkColumns = array_values(array_map(
                fn($c) => $c['name'],
                array_filter($columns, fn($c) => $c['pk'])
            ));

            $nonPkColumns = array_values(array_map(
                fn($c) => $c['name'],
                array_filter($columns, fn($c) => !$c['pk'])
            ));

            $tables[] = [
                'name'           => $tableName,
                'columns'        => $columns,
                'pk_columns'     => $pkColumns,
                'non_pk_columns' => $nonPkColumns,
                'suggested_fds'  => $this->generateFunctionalDependencies($pkColumns, $nonPkColumns),
            ];
        }

        return $tables;
    }

    /**
     * Generate FD otomatis menggunakan heuristik prefix.
     *
     * Logika:
     *   - PK tunggal → semua non-PK full dep pada PK tersebut
     *   - PK composite → tiap non-PK dicek apakah prefixnya cocok dengan salah satu PK
     *       cocok  → partial dep pada PK tersebut saja
     *       tidak  → full dep pada seluruh PK
     *
     * Contoh:
     *   PK: [student_id, course_id]
     *   student_name → prefix "student" cocok "student_id" → [student_id] → student_name
     *   course_name  → prefix "course"  cocok "course_id"  → [course_id]  → course_name
     *   grade        → tidak cocok apapun                  → [student_id, course_id] → grade
     *
     * @param  string[] $pkColumns
     * @param  string[] $nonPkColumns
     * @return array{lhs: string[], rhs: string}[]
     */
    public function generateFunctionalDependencies(array $pkColumns, array $nonPkColumns): array
    {
        $fds = [];

        // PK tunggal: semua non-PK full dep, tidak perlu heuristik
        if (count($pkColumns) <= 1) {
            foreach ($nonPkColumns as $col) {
                $fds[] = ['lhs' => $pkColumns, 'rhs' => $col];
            }
            return $fds;
        }

        // PK composite: gunakan heuristik prefix
        // Buat map prefix → pk_column
        // Contoh: "student_id" → prefix "student", "order_id" → prefix "order"
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
                // Partial dependency: hanya bergantung pada satu PK
                $fds[] = ['lhs' => [$matchedPk], 'rhs' => $col];
            } else {
                // Full dependency: bergantung pada seluruh PK
                $fds[] = ['lhs' => $pkColumns, 'rhs' => $col];
            }
        }

        return $fds;
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function parseColumns(string $tableBody): array
    {
        $columns = [];
        $lines   = explode("\n", $tableBody);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (preg_match('/^(\w+)\s+(\w+(?:\([^)]+\))?(?:\[\])?)\s*(.*)$/', $line, $colMatch)) {
                $columnName = $colMatch[1];
                $columnType = $colMatch[2];
                $attributes = $colMatch[3] ?? '';

                $columns[] = [
                    'name'     => $columnName,
                    'type'     => $columnType,
                    'pk'       => $this->hasAttribute($attributes, 'pk'),
                    'unique'   => $this->hasAttribute($attributes, 'unique'),
                    'not_null' => $this->hasAttribute($attributes, 'not null') || $this->hasAttribute($attributes, 'pk'),
                ];
            }
        }

        return $columns;
    }

    /**
     * Ekstrak prefix dari nama kolom dengan menghapus suffix umum.
     *
     * Suffix yang dihapus: _id, _no, _code, _key, _num, _uuid
     *
     * Contoh:
     *   "student_id"  → "student"
     *   "order_no"    → "order"
     *   "product_id"  → "product"
     *   "id"          → "" (tidak ada prefix)
     */
    private function extractPrefix(string $columnName): string
    {
        $suffix  = '/_(id|no|code|key|num|uuid|pk)$/i';
        $prefix  = preg_replace($suffix, '', $columnName);

        // Jika prefix sama dengan nama aslinya (tidak ada suffix), return ''
        return $prefix !== $columnName ? strtolower($prefix) : '';
    }

    /**
     * Cari PK yang prefixnya cocok dengan nama kolom.
     *
     * Contoh:
     *   col = "student_name", prefixMap = ["student" => "student_id", "course" => "course_id"]
     *   → "student_name" diawali "student" → return "student_id"
     *
     * @param  array<string, string> $prefixMap  prefix → pk_column_name
     * @return string|null  nama PK yang cocok, atau null jika tidak ada
     */
    private function findMatchingPk(string $columnName, array $prefixMap): ?string
    {
        $colLower = strtolower($columnName);

        foreach ($prefixMap as $prefix => $pkColumn) {
            if ($prefix === '') continue;

            // Cek apakah nama kolom diawali dengan prefix ini
            if (str_starts_with($colLower, $prefix)) {
                return $pkColumn;
            }
        }

        return null;
    }

    private function hasAttribute(string $attributes, string $attr): bool
    {
        return stripos($attributes, $attr) !== false;
    }
}