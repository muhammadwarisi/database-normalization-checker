<?php

namespace App\Services;

/**
 * Algoritma A4 (Demba, 2013)
 * ============================================================
 * Mengklasifikasikan FD dalam Fm ke dalam:
 *   - Ff  = full dependencies (FD penuh terhadap candidate key)
 *   - Fp  = partial dependencies
 *
 * Definisi (dari paper):
 *   X→A adalah PARTIAL dependency jika:
 *     - A bukan key-attribute (tidak ada dalam candidate key manapun), DAN
 *     - X adalah proper subset dari suatu candidate key
 *       (artinya X ⊂ CK, bukan X = CK)
 *
 *   Jika suatu atribut A transitively bergantung pada partial dependency,
 *   dependency tersebut juga dikategorikan sebagai partial.
 *
 * Pseudocode asli:
 *   Fp := ∅, Ff := Fm
 *   For each X→A ∈ Ff
 *       if X is a proper subset of a candidate key AND A is not a key attribute then
 *           Fp := Fp ∪ {X→A}
 *           Ff := Ff - {X→A}
 *           while there is a fd Z→B ∈ Ff s.t. Z ∈ A⁺ do
 *               Fp := Fp ∪ {Z→B}
 *               Ff := Ff - {Z→B}
 *
 * Lemma 3: Fm = Ff ∪ Fp
 * Lemma 4: Jika tidak ada partial dependency → Fp = ∅
 */
class DependencyClassifier
{
    public function __construct(
        private readonly ClosureCalculator $closureCalc,
    ) {}

    /**
     * @param  FunctionalDependency[] $minimalCover   Fm (output A3)
     * @param  string[][]             $candidateKeys  Daftar candidate key
     * @return array{
     *   full: FunctionalDependency[],
     *   partial: FunctionalDependency[]
     * }
     */
    public function classify(array $minimalCover, array $candidateKeys): array
    {
        // Kumpulkan semua atribut yang merupakan key-attribute
        $keyAttributes = $this->getAllKeyAttributes($candidateKeys);

        $Fp = [];
        $Ff = $minimalCover; // mulai dengan semua sebagai full

        // Iterasi menggunakan index agar bisa memodifikasi $Ff
        $i = 0;
        while ($i < count($Ff)) {
            $fd = $Ff[$i];

            $isProperSubsetOfKey = $this->isProperSubsetOfSomeCandidateKey(
                $fd->lhs,
                $candidateKeys
            );
            $rhsIsNotKeyAttr = !in_array($fd->rhs, $keyAttributes, true);

            if ($isProperSubsetOfKey && $rhsIsNotKeyAttr) {
                // Pindah ke Fp
                $Fp[] = $fd;
                array_splice($Ff, $i, 1); // hapus dari Ff

                // Cari FD yang transitif dari A (RHS dari FD ini)
                // Z→B ∈ Ff such that Z ∈ A⁺ (under current Ff)
                // Artinya: jika A bisa menentukan Z, maka Z→B juga partial
                $this->moveTransitiveDeps($fd->rhs, $Ff, $Fp, $minimalCover);

                // Jangan increment i karena elemen sudah dihapus
                continue;
            }

            $i++;
        }

        return [
            'full'    => array_values($Ff),
            'partial' => array_values($Fp),
        ];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Pindahkan FD Z→B dari Ff ke Fp jika Z ∈ A⁺
     * (bergantung transitif pada atribut A yang sudah partial).
     *
     * @param string                  $pivotAttr  Atribut A (RHS dari partial FD)
     * @param FunctionalDependency[]  $Ff         (dimodifikasi in-place via reference)
     * @param FunctionalDependency[]  $Fp         (dimodifikasi in-place via reference)
     * @param FunctionalDependency[]  $allFm      Semua FD (untuk closure)
     */
    private function moveTransitiveDeps(
        string $pivotAttr,
        array  &$Ff,
        array  &$Fp,
        array  $allFm
    ): void {
        // Hitung A⁺ (closure dari pivot attribute)
        $pivotClosure = $this->closureCalc->compute([$pivotAttr], $allFm);

        $j = 0;
        while ($j < count($Ff)) {
            $fd = $Ff[$j];

            // Cek apakah Z (lhs) ⊆ A⁺
            $lhsInPivotClosure = count(array_diff($fd->lhs, $pivotClosure)) === 0;

            if ($lhsInPivotClosure) {
                $Fp[] = $fd;
                array_splice($Ff, $j, 1);
                // Tidak increment, karena elemen sudah dihapus
                continue;
            }

            $j++;
        }
    }

    /**
     * Cek apakah $lhs adalah proper subset dari setidaknya satu candidate key.
     * Proper subset: $lhs ⊂ CK (subset sejati, bukan sama)
     *
     * @param string[]   $lhs
     * @param string[][] $candidateKeys
     */
    private function isProperSubsetOfSomeCandidateKey(
        array $lhs,
        array $candidateKeys
    ): bool {
        foreach ($candidateKeys as $ck) {
            // lhs ⊆ ck (semua elemen lhs ada di ck)
            $isSubset = count(array_diff($lhs, $ck)) === 0;

            // lhs ≠ ck (bukan sama persis → proper subset)
            $notEqual = count($lhs) !== count($ck)
                     || count(array_diff($lhs, $ck)) > 0
                     || count(array_diff($ck, $lhs)) > 0;

            if ($isSubset && $notEqual) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kumpulkan semua atribut yang terdapat dalam candidate key manapun.
     *
     * @param  string[][] $candidateKeys
     * @return string[]
     */
    private function getAllKeyAttributes(array $candidateKeys): array
    {
        $attrs = [];
        foreach ($candidateKeys as $ck) {
            foreach ($ck as $attr) {
                $attrs[$attr] = true;
            }
        }
        return array_keys($attrs);
    }
}