<?php

namespace App\Services;

class NormalizationAnalyzer
{
    public function analyze(array $tables): array
    {
        $results = [];

        foreach ($tables as $table) {
            $analysis = [
                'name' => $table['name'],
                'columns' => $table['columns'],
                'analysis' => [
                    '1NF' => $this->check1NF($table),
                    '2NF' => $this->check2NF($table),
                    '3NF' => $this->check3NF($table, $tables),
                    'recommendations' => [],
                ],
            ];

            // Collect recommendations
            $recommendations = array_merge(
                $analysis['analysis']['1NF']['issues'],
                $analysis['analysis']['2NF']['issues'],
                $analysis['analysis']['3NF']['issues']
            );

            $analysis['analysis']['recommendations'] = $recommendations;

            $results[] = $analysis;
        }

        return $results;
    }

    private function check1NF(array $table): array
    {
        $issues = [];
        $hasPK = false;

        // Check for primary key
        foreach ($table['columns'] as $col) {
            if ($col['pk']) {
                $hasPK = true;
                break;
            }
        }

        if (!$hasPK) {
            $issues[] = "❌ Tabel tidak memiliki primary key. Tambahkan kolom ID dengan atribut [pk]";
        }

        // Check for repeating groups (columns with numbers)
        $columnNames = array_column($table['columns'], 'name');
        $baseNames = [];

        foreach ($columnNames as $name) {
            if (preg_match('/^(.+?)(\d+)$/', $name, $matches)) {
                $baseName = $matches[1];
                $baseNames[$baseName][] = $name;
            }
        }

        foreach ($baseNames as $base => $columns) {
            if (count($columns) > 1) {
                $issues[] = "❌ Repeating group terdeteksi: " . implode(', ', $columns) .
                    ". Pisahkan menjadi tabel relasi terpisah";
            }
        }

        // Check for array types
        foreach ($table['columns'] as $col) {
            if ($col['is_array']) {
                $issues[] = "❌ Kolom '{$col['name']}' menggunakan tipe array. Pisahkan menjadi tabel relasi";
            }
        }

        return [
            'status' => empty($issues),
            'issues' => $issues,
        ];
    }

    private function check2NF(array $table): array
    {
        $issues = [];
        $primaryKeys = array_filter($table['columns'], fn($col) => $col['pk']);

        // Auto pass if single PK
        if (count($primaryKeys) <= 1) {
            return ['status' => true, 'issues' => []];
        }

        // Check partial dependencies (simplified simulation)
        $pkNames = array_column($primaryKeys, 'name');

        foreach ($table['columns'] as $col) {
            if ($col['pk']) continue;

            // Check if non-key column name contains substring of any PK
            foreach ($pkNames as $pkName) {
                if (str_contains($col['name'], $pkName) || str_contains($pkName, $col['name'])) {
                    $issues[] = "⚠️ Kemungkinan partial dependency: kolom '{$col['name']}' bergantung pada sebagian PK. " .
                        "Pertimbangkan untuk memisahkan ke tabel baru";
                    break;
                }
            }
        }

        return [
            'status' => empty($issues),
            'issues' => $issues,
        ];
    }

    private function check3NF(array $table, array $allTables): array
    {
        $issues = [];
        $columns = $table['columns'];

        // Ambil semua foreign key di tabel ini
        $foreignKeys = array_filter($columns, fn($col) => str_ends_with($col['name'], '_id'));

        // Jika tidak ada foreign key, 3NF biasanya aman
        if (empty($foreignKeys)) {
            return ['status' => true, 'issues' => []];
        }

        // List kolom non-key
        $nonKeyColumns = array_filter($columns, fn($col) => !$col['pk'] && !str_ends_with($col['name'], '_id'));

        foreach ($foreignKeys as $fk) {
            $refTableName = rtrim($fk['name'], '_id'); // orders.user_id → user

            // Cari tabel referensi yang cocok secara nama
            foreach ($allTables as $otherTable) {
                if (
                    $otherTable['name'] !== $refTableName . 's' &&
                    $otherTable['name'] !== $refTableName &&
                    $otherTable['name'] !== ucfirst($refTableName)
                ) {
                    continue;
                }

                // Ambil kolom non-PK dari tabel referensi
                $refColumns = array_filter($otherTable['columns'], fn($c) => !$c['pk']);

                foreach ($nonKeyColumns as $col) {

                    // Jika nama kolom cocok, kemungkinan transitive dependency
                    if (array_search($col['name'], array_column($refColumns, 'name')) !== false) {
                        $issues[] = "⚠️ Kolom '{$col['name']}' seharusnya direferensikan dari tabel '{$otherTable['name']}', " .
                            "bukan disimpan di tabel '{$table['name']}' secara langsung.";
                    }
                }
            }
        }

        return [
            'status' => empty($issues),
            'issues' => array_unique($issues),
        ];
    }
}
