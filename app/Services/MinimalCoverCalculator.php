<?php

namespace App\Services;

/**
 * Algoritma A3 (Demba, 2013)
 * ============================================================
 * Menghapus REDUNDANT DEPENDENCIES untuk menghasilkan minimal cover Fm.
 *
 * Ide utama (dari paper):
 *   Sebuah FD f1: X→A adalah redundan dalam F jika terdapat FD lain
 *   f2: Y→A di F sehingga X ⊆ Y⁺(F \ {f1}).
 *   Artinya, A masih bisa diturunkan dari Y tanpa menggunakan f1.
 *
 * Pseudocode asli:
 *   Fm := F
 *   for each X→A ∈ Fm
 *       while there exists Y→A ∈ Fm
 *           G := Fm - (Y→A)
 *           if X ⊆ Y_G⁺ then
 *               Fm := G
 *
 * Catatan: Non-implied extraneous attributes juga terhapus di sini
 *          (disebutkan di paper Demba bagian 2).
 *
 * Output: Fm = minimal cover
 */
class MinimalCoverCalculator
{
    public function __construct(
        private readonly ClosureCalculator $closureCalc,
    ) {}

    /**
     * @param  FunctionalDependency[] $dependencies  Partially left-reduced (output A2)
     * @return FunctionalDependency[] Fm  (minimal cover)
     */
    public function compute(array $dependencies): array
    {
        $Fm = array_values($dependencies);
        $total = count($Fm);

        for ($i = 0; $i < count($Fm); $i++) {
            $anchor = $Fm[$i];
            // while loop: cari selama masih ada Y→A yang redundan terhadap anchor ini
            while (true) {
                $found = false;
                for ($j = 0; $j < count($Fm); $j++) {
                    if ($i === $j) continue;
                    $candidate = $Fm[$j];
                    if ($candidate->rhs !== $anchor->rhs) continue;

                    // G = Fm tanpa candidate
                    $G = $this->removeFdAtIndex($Fm, $j);
                    $closureY = $this->closureCalc->compute($candidate->lhs, $G);

                    // Cek subset menggunakan array_diff (sama seperti kode Anda)
                    $isSubset = count(array_diff($anchor->lhs, $closureY)) === 0;

                    if ($isSubset) {
                        // Hapus candidate
                        array_splice($Fm, $j, 1);
                        // Jika indeks j lebih kecil dari i, maka anchor bergeser ke kiri
                        if ($j < $i) $i--;
                        $found = true;
                        break; // keluar dari for, ulangi while dengan Fm baru
                    }
                }
                if (!$found) break; // tidak ada lagi yang redundan untuk anchor ini
            }
        }

        return array_values($Fm);
    }

    /**
     * Hapus FD pada index tertentu dari array dan re-index.
     *
     * @param  FunctionalDependency[] $deps
     * @param  int                    $idx
     * @return FunctionalDependency[]
     */
    private function removeFdAtIndex(array $deps, int $idx): array
    {
        unset($deps[$idx]);
        return array_values($deps);
    }
}
