<?php

namespace App\Services;

/**
 * Algoritma A1 (Demba, 2013)
 * ============================================================
 * Menghitung X⁺ (closure) dari sekumpulan atribut X terhadap
 * sekumpulan functional dependency F.
 *
 * Pseudocode asli:
 *   X⁺ := X
 *   while there is a fd Y→A ∈ F
 *       if Y ⊆ X⁺ and A ∉ X⁺ then
 *           X⁺ := X⁺ ∪ {A}
 *
 * Digunakan oleh:
 *   - A2 (extraneous attribute check)
 *   - A3 (redundant dependency check)
 *   - A4 (partial/full classification)
 *   - A5 & A6 (decomposition)
 */
class ClosureCalculator
{
    /**
     * Hitung closure X⁺ dari atribut $attributes
     * terhadap sekumpulan FD $dependencies.
     *
     * @param  string[]               $attributes  Himpunan atribut X
     * @param  FunctionalDependency[] $dependencies  Himpunan FD F
     * @return string[]  X⁺ (sorted, unique)
     */
    public function compute(array $attributes, array $dependencies): array
    {
        // X⁺ := X
        $closure = array_values(array_unique($attributes));

        // Iterasi hingga tidak ada perubahan (fixpoint)
        $changed = true;
        while ($changed) {
            $changed = false;

            foreach ($dependencies as $fd) {
                // Cek Y ⊆ X⁺
                $lhsInClosure = count(array_diff($fd->lhs, $closure)) === 0;

                // Cek A ∉ X⁺
                $rhsNotInClosure = !in_array($fd->rhs, $closure, true);

                if ($lhsInClosure && $rhsNotInClosure) {
                    $closure[] = $fd->rhs;
                    $changed   = true;
                }
            }
        }

        sort($closure);

        return array_values(array_unique($closure));
    }

    /**
     * Cek apakah Y → A berlaku di F, yaitu apakah A ∈ Y⁺(F).
     * Berguna sebagai shorthand di algoritma lain.
     *
     * @param  string[]               $lhs
     * @param  string                 $rhs
     * @param  FunctionalDependency[] $dependencies
     */
    public function holds(array $lhs, string $rhs, array $dependencies): bool
    {
        $closure = $this->compute($lhs, $dependencies);

        return in_array($rhs, $closure, true);
    }
}