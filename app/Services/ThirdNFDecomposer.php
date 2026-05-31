<?php

namespace App\Services;

/**
 * Algoritma A6 (Demba, 2013)
 * ============================================================
 * Dekomposisi relasi 2NF menjadi relasi 3NF.
 *
 * Berdasarkan jurnal:
 *   Algorithm A6: Decomposes into 3NF
 *   Input: R (1NF relation) dan Ft (transitive dependencies)
 *   Output: set of relations in 3NF
 */
class ThirdNFDecomposer
{
    public function __construct(
        private readonly ClosureCalculator  $closureCalc,
        private readonly CandidateKeyFinder $keyFinder,  // ← tambah ini
    ) {}

    /**
     * Dekomposisi satu relasi ke 3NF.
     *
     * @param string $relationName
     * @param string[] $attributes
     * @param FunctionalDependency[] $dependencies Minimal cover Fm
     * @param string[][] $candidateKeys
     * @param string[] $primaryKey
     * @return array{
     *     is3NF: bool,
     *     transitiveDeps: array,
     *     relations: array<string, array{attributes: string[], primaryKey: string[], dependencies: FunctionalDependency[]}>
     * }
     */
    public function decompose(
        string $relationName,
        array $attributes,
        array $dependencies,
        array $candidateKeys,
        array $primaryKey
    ): array {
        // 1. Tentukan semua key attributes
        $keyAttributes = $this->getAllKeyAttributes($candidateKeys);

        // 2. Filter FD transitif: X → A dimana X bukan candidate key dan A bukan key attribute
        $transitiveDeps = [];
        foreach ($dependencies as $fd) {
            $isCandidateKey = $this->isCandidateKey($fd->lhs, $candidateKeys);
            $isKeyAttr = in_array($fd->rhs, $keyAttributes, true);
            if (!$isCandidateKey && !$isKeyAttr) {
                $transitiveDeps[] = $fd;
            }
        }

        // Lemma 5: jika Ft kosong → sudah 3NF
        if (empty($transitiveDeps)) {
            return [
                'is3NF' => true,
                'transitiveDeps' => [],
                'relations' => [
                    $relationName => [
                        'attributes' => $attributes,
                        'primaryKey' => $primaryKey,
                        'dependencies' => $dependencies,
                    ],
                ],
            ];
        }

        // 3. Proses setiap FD transitif
        $newRelations = [];
        $remainingAttributes = $attributes; // X awal

        foreach ($transitiveDeps as $fd) {
            $Y = $fd->lhs;
            $A = $fd->rhs;

            // Buat relasi baru R_Y
            $relName = 'R_' . implode('_', $this->sortCopy($Y));
            if (!isset($newRelations[$relName])) {
                $newRelations[$relName] = [
                    'attributes' => $this->sortCopy($Y),
                    'primaryKey' => $this->sortCopy($Y),
                    'dependencies' => [],
                ];
            }

            // Tambahkan A ke relasi baru jika belum ada
            if (!in_array($A, $newRelations[$relName]['attributes'], true)) {
                $newRelations[$relName]['attributes'][] = $A;
                sort($newRelations[$relName]['attributes']);
            }
            $newRelations[$relName]['dependencies'][] = $fd;

            // Hapus atribut transitif dari relasi utama (A⁺, kecuali yang di Y)
            $aClosure = $this->closureCalc->compute([$A], $transitiveDeps);
            foreach ($aClosure as $B) {
                if (!in_array($B, $Y, true) && in_array($B, $remainingAttributes, true)) {
                    $remainingAttributes = array_values(array_diff($remainingAttributes, [$B]));
                }
            }
        }

        // Pastikan primary key asli tetap ada di relasi utama
        foreach ($primaryKey as $pkAttr) {
            if (!in_array($pkAttr, $remainingAttributes, true)) {
                $remainingAttributes[] = $pkAttr;
            }
        }
        $remainingAttributes = array_values(array_unique($this->sortCopy($remainingAttributes)));

        // FD untuk relasi utama (selain yang transitif)
        $mainDeps = $this->filterNonTransitiveDeps($dependencies, $transitiveDeps);

        // Tambahkan relasi utama
        $newRelations[$relationName] = [
            'attributes' => $remainingAttributes,
            'primaryKey' => $primaryKey,
            'dependencies' => $mainDeps,
        ];

        return [
            'is3NF' => false,
            'transitiveDeps' => $transitiveDeps,
            'relations' => $newRelations,
        ];
    }

    /**
     * Proses semua relasi hasil 2NF.
     *
     * @param array<string, array{attributes: string[], primaryKey: string[], dependencies: FunctionalDependency[]}> $relations2NF
     * @return array{
     *     allRelations: array,
     *     report: array
     * }
     */
    // Ubah decomposeAll — ganti findCandidateKeys internal dengan keyFinder
    public function decomposeAll(array $relations2NF): array
    {
        $allRelations = [];
        $report       = [];

        foreach ($relations2NF as $relName => $relData) {
            $attrs = $relData['attributes'];
            $deps  = $relData['dependencies'];
            $pk    = $relData['primaryKey'];

            // Gunakan CandidateKeyFinder yang sudah teruji, bukan internal
            $cks = $this->keyFinder->findAll($attrs, $deps);
            if (empty($cks)) {
                $cks = [$pk];
            }

            $result = $this->decompose($relName, $attrs, $deps, $cks, $pk);

            $report[$relName] = [
                'is3NF'          => $result['is3NF'],
                'transitiveDeps' => $result['transitiveDeps'],
            ];

            foreach ($result['relations'] as $name => $data) {
                $allRelations[$name] = $data;
            }
        }

        return [
            'allRelations' => $allRelations,
            'report'       => $report,
        ];
    }

    // ---------------------------------------------------------------
    // Helper methods
    // ---------------------------------------------------------------

    private function getAllKeyAttributes(array $candidateKeys): array
    {
        $keys = [];
        foreach ($candidateKeys as $ck) {
            foreach ($ck as $attr) {
                $keys[$attr] = true;
            }
        }
        return array_keys($keys);
    }

    private function isCandidateKey(array $lhs, array $candidateKeys): bool
    {
        $sortedLhs = $this->sortCopy($lhs);
        foreach ($candidateKeys as $ck) {
            if ($this->sortCopy($ck) === $sortedLhs) {
                return true;
            }
        }
        return false;
    }

    private function filterNonTransitiveDeps(array $allDeps, array $transitiveDeps): array
    {
        $result = [];
        foreach ($allDeps as $fd) {
            $isTransitive = false;
            foreach ($transitiveDeps as $tfd) {
                if ($fd->equals($tfd)) {
                    $isTransitive = true;
                    break;
                }
            }
            if (!$isTransitive) {
                $result[] = $fd;
            }
        }
        return $result;
    }

    private function findCandidateKeys(array $attributes, array $deps): array
    {
        // Implementasi sederhana: jika tidak ada FD, semua atribut jadi key
        if (empty($deps)) {
            return [$attributes];
        }

        // Heuristik: atribut yang hanya di LHS (lo) pasti ada di semua candidate keys
        $lhsSet = [];
        $rhsSet = [];
        foreach ($deps as $fd) {
            foreach ($fd->lhs as $a) {
                $lhsSet[$a] = true;
            }
            $rhsSet[$fd->rhs] = true;
        }

        $lo = array_values(array_diff(array_keys($lhsSet), array_keys($rhsSet)));
        $ro = array_values(array_diff(array_keys($rhsSet), array_keys($lhsSet)));
        $candidates = array_values(array_diff($attributes, $ro));

        // Cek apakah lo sendiri sudah superkey
        if ($this->isSuperkey($lo, $attributes, $deps)) {
            return [$this->sortCopy($lo)];
        }

        $keys = [];
        $lr = array_values(array_diff($candidates, $lo));

        for ($size = 1; $size <= count($lr); $size++) {
            foreach ($this->combinations($lr, $size) as $combo) {
                $keyCandidate = $this->sortCopy(array_unique(array_merge($lo, $combo)));
                if (!$this->isSuperkey($keyCandidate, $attributes, $deps)) {
                    continue;
                }
                if ($this->isMinimal($keyCandidate, $keys)) {
                    $keys[] = $keyCandidate;
                }
            }
        }

        return empty($keys) ? [$this->sortCopy($attributes)] : $keys;
    }

    private function isSuperkey(array $key, array $allAttributes, array $deps): bool
    {
        $closure = $this->closureCalc->compute($key, $deps);
        return count(array_diff($allAttributes, $closure)) === 0;
    }

    private function isMinimal(array $key, array $existingKeys): bool
    {
        foreach ($existingKeys as $existing) {
            if (count(array_diff($existing, $key)) === 0) {
                return false;
            }
        }
        return true;
    }

    private function combinations(array $array, int $size): array
    {
        if ($size === 0) return [[]];
        if (empty($array)) return [];
        $first = array_shift($array);
        $withFirst = array_map(fn($c) => array_merge([$first], $c), $this->combinations($array, $size - 1));
        $withoutFirst = $this->combinations($array, $size);
        return array_merge($withFirst, $withoutFirst);
    }

    private function sortCopy(array $arr): array
    {
        sort($arr);
        return array_values(array_unique($arr));
    }
}
