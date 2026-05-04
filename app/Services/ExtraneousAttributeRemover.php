<?php

namespace App\Services;

/**
 * Algoritma A2 (Demba, 2013)
 * ============================================================
 * Menghapus IMPLIED EXTRANEOUS ATTRIBUTES dari sekumpulan FD.
 *
 * Definisi kunci dari paper:
 *   - ro  = atribut yang HANYA muncul di RHS
 *   - lo  = atribut yang HANYA muncul di LHS
 *   - lr  = atribut yang muncul di keduanya (LHS dan RHS)
 *
 * Lemma 1 & 2: Implied extraneous attribute B pasti ∈ lr.
 * Sehingga kita HANYA perlu memeriksa atribut yang ada di lr,
 * bukan semua atribut (lebih efisien dari algoritma Diederich 1988).
 *
 * B adalah implied extraneous dalam X→A jika:
 *   B ∈ (Y-B)⁺  (dengan Y adalah LHS saat ini dan H = F tanpa X→A + (Y-B)→A)
 *
 * Output: partially left-reduced set of dependencies (F').
 * Non-implied extraneous attributes akan dihapus di A3.
 */
class ExtraneousAttributeRemover
{
    public function __construct(
        private readonly ClosureCalculator $closureCalc,
    ) {}

    /**
     * @param  FunctionalDependency[] $dependencies  F (canonical form)
     * @return FunctionalDependency[] G  (partially left-reduced)
     */
    public function remove(array $dependencies): array
    {
        // Langkah 1: Hitung ro, lo, lr
        [$ro, $lo, $lr] = $this->categorizeSets($dependencies);

        $G = $dependencies;

        foreach ($G as $idx => $fd) {
            if (count($fd->lhs) <= 1) {
                // LHS tunggal tidak bisa dikurangi
                continue;
            }

            $Y = $fd->lhs; // salinan LHS yang akan kita kurangi

            foreach ($Y as $B) {
                // Lemma 2: hanya periksa atribut B yang ada di lr
                if (!in_array($B, $lr, true)) {
                    continue;
                }

                // Buat H = G - (X→A) + ((Y-B)→A)
                $YminusB  = array_values(array_diff($Y, [$B]));
                $newFd    = $fd->withLhs($YminusB);
                $H        = $this->replaceFd($G, $idx, $newFd);

                // Cek apakah B ∈ (Y-B)⁺ under H
                $closure  = $this->closureCalc->compute($YminusB, $H);

                if (in_array($B, $closure, true)) {
                    // B adalah implied extraneous → hapus B dari Y
                    $Y    = $YminusB;
                    $G[$idx] = $newFd;
                }
            }
        }

        // Langkah 2: Hapus FD duplikat
        $G = $this->removeDuplicates($G);

        return array_values($G);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Kategorisasi atribut menjadi ro, lo, lr.
     *
     * ro = hanya muncul di RHS
     * lo = hanya muncul di LHS
     * lr = muncul di keduanya
     *
     * @param  FunctionalDependency[] $deps
     * @return array{0: string[], 1: string[], 2: string[]}
     */
    private function categorizeSets(array $deps): array
    {
        $onlyLhs = [];
        $onlyRhs = [];

        foreach ($deps as $fd) {
            foreach ($fd->lhs as $attr) {
                $onlyLhs[$attr] = true;
            }
            $onlyRhs[$fd->rhs] = true;
        }

        $lhsSet = array_keys($onlyLhs);
        $rhsSet = array_keys($onlyRhs);

        $lo = array_values(array_diff($lhsSet, $rhsSet)); // hanya LHS
        $ro = array_values(array_diff($rhsSet, $lhsSet)); // hanya RHS
        $lr = array_values(array_intersect($lhsSet, $rhsSet)); // keduanya

        return [$ro, $lo, $lr];
    }

    /**
     * Ganti FD pada index $idx dengan $newFd di array $G.
     *
     * @param  FunctionalDependency[] $G
     * @return FunctionalDependency[]
     */
    private function replaceFd(array $G, int $idx, FunctionalDependency $newFd): array
    {
        $H        = $G;
        $H[$idx]  = $newFd;

        return $H;
    }

    /**
     * Hapus FD yang duplikat (structural equality).
     *
     * @param  FunctionalDependency[] $deps
     * @return FunctionalDependency[]
     */
    private function removeDuplicates(array $deps): array
    {
        $unique = [];

        foreach ($deps as $fd) {
            $isDup = false;
            foreach ($unique as $existing) {
                if ($fd->equals($existing)) {
                    $isDup = true;
                    break;
                }
            }
            if (!$isDup) {
                $unique[] = $fd;
            }
        }

        return $unique;
    }
}