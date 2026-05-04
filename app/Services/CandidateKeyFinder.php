<?php

namespace App\Services;

/**
 * CandidateKeyFinder
 * ============================================================
 * Mencari semua candidate key dari relasi R berdasarkan
 * sekumpulan FD Fm.
 *
 * Sebuah himpunan K adalah candidate key dari R jika:
 *   1. K⁺ ⊇ semua atribut R  (K menentukan semua atribut → superkey)
 *   2. Tidak ada subset sejati K' ⊂ K yang juga superkey (minimal)
 *
 * Strategi:
 *   - Mulai dari atribut yang HANYA muncul di LHS (lo) → pasti masuk key
 *   - Atribut yang hanya di RHS (ro) → pasti bukan bagian key
 *   - Generate semua kombinasi atribut yang termasuk lo ∪ lr
 *     dari ukuran 1 hingga n, cek apakah merupakan superkey,
 *     filter yang minimal.
 */
class CandidateKeyFinder
{
    public function __construct(
        private readonly ClosureCalculator $closureCalc,
    ) {}

    /**
     * @param  string[]               $attributes  Semua atribut R
     * @param  FunctionalDependency[] $dependencies  Fm
     * @return string[][]  Daftar candidate key, masing-masing berupa array atribut
     */
    public function findAll(array $attributes, array $dependencies): array
    {
        [$lo, $ro] = $this->categorize($dependencies);

        // Atribut di ro tidak bisa menjadi bagian candidate key
        // Atribut di lo HARUS ada di setiap candidate key
        $mustInclude = $lo;
        $candidates  = array_values(array_diff($attributes, $ro, $lo)); // atribut lr

        // Closure dari lo saja
        $loClosure = $this->closureCalc->compute($mustInclude, $dependencies);
        $allAttrs  = sort_copy($attributes);

        // Cek apakah lo sendiri sudah superkey
        if ($this->isSuperkey($mustInclude, $attributes, $dependencies)) {
            return [sort_copy($mustInclude)];
        }

        // Generate kombinasi atribut dari $candidates, urutkan dari kecil ke besar
        $keys = [];

        for ($size = 1; $size <= count($candidates); $size++) {
            $combos = $this->combinations($candidates, $size);

            foreach ($combos as $combo) {
                $keyCandidate = array_values(array_unique(array_merge($mustInclude, $combo)));
                sort($keyCandidate);

                // Cek superkey
                if (!$this->isSuperkey($keyCandidate, $attributes, $dependencies)) {
                    continue;
                }

                // Cek minimal (tidak ada subset yang lebih kecil sudah jadi key)
                if ($this->isMinimal($keyCandidate, $keys)) {
                    $keys[] = $keyCandidate;
                }
            }

            // Optimasi: jika sudah menemukan key di ukuran ini,
            // tidak perlu lanjut ke ukuran lebih besar (semua yang lebih besar
            // pasti tidak minimal kecuali ada multiple key dengan ukuran berbeda)
            // → sebenarnya kita harus tetap lanjut karena bisa ada key lain
        }

        // Fallback: jika tidak ada key ditemukan, seluruh atribut adalah key
        if (empty($keys)) {
            $all = $attributes;
            sort($all);
            $keys[] = $all;
        }

        return $keys;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function isSuperkey(array $key, array $allAttributes, array $deps): bool
    {
        $closure = $this->closureCalc->compute($key, $deps);
        return count(array_diff($allAttributes, $closure)) === 0;
    }

    /**
     * Cek apakah $key minimal (tidak ada candidate key yang sudah ada
     * yang merupakan subset sejati dari $key).
     *
     * @param string[]   $key
     * @param string[][] $existingKeys
     */
    private function isMinimal(array $key, array $existingKeys): bool
    {
        foreach ($existingKeys as $existing) {
            // Jika existing ⊆ key → key tidak minimal
            if (count(array_diff($existing, $key)) === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * Kategorisasi atribut: lo (only LHS), ro (only RHS).
     *
     * @param  FunctionalDependency[] $deps
     * @return array{0: string[], 1: string[]}  [lo, ro]
     */
    private function categorize(array $deps): array
    {
        $lhsSet = [];
        $rhsSet = [];

        foreach ($deps as $fd) {
            foreach ($fd->lhs as $attr) {
                $lhsSet[$attr] = true;
            }
            $rhsSet[$fd->rhs] = true;
        }

        $lhsAttrs = array_keys($lhsSet);
        $rhsAttrs = array_keys($rhsSet);

        $lo = array_values(array_diff($lhsAttrs, $rhsAttrs));
        $ro = array_values(array_diff($rhsAttrs, $lhsAttrs));

        return [$lo, $ro];
    }

    /**
     * Generate semua kombinasi C(n, k) dari array.
     *
     * @param  string[] $array
     * @param  int      $size
     * @return string[][]
     */
    private function combinations(array $array, int $size): array
    {
        if ($size === 0) {
            return [[]];
        }
        if (empty($array)) {
            return [];
        }

        $first  = array_shift($array);
        $withFirst = array_map(
            fn($combo) => array_merge([$first], $combo),
            $this->combinations($array, $size - 1)
        );
        $withoutFirst = $this->combinations($array, $size);

        return array_merge($withFirst, $withoutFirst);
    }

    /** Hapus FD pada index tertentu */
    private function removeFdAtIndex(array $deps, int $idx): array
    {
        unset($deps[$idx]);
        return array_values($deps);
    }
}

// ---------------------------------------------------------------------------
// Helper function (global, atau bisa dipindah ke helper file)
// ---------------------------------------------------------------------------
if (!function_exists('sort_copy')) {
    function sort_copy(array $arr): array
    {
        sort($arr);
        return array_values($arr);
    }
}