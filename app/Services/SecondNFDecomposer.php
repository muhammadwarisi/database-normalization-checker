<?php

namespace App\Services;

/**
 * Algoritma A5 (Demba, 2013)
 * ============================================================
 * Dekomposisi relasi 1NF menjadi sekumpulan relasi 2NF.
 *
 * 2NF: Relasi dalam 2NF jika dalam 1NF DAN tidak ada non-key attribute
 *      yang secara parsial bergantung pada ANY candidate key.
 *
 * Pseudocode asli:
 *   G := Fp, Xf := atribut dalam Ff
 *   R2NF := ∅
 *   for each Y→A ∈ G do
 *       if Y is a proper subset of a candidate key then
 *           buat RY(Y ∪ Y⁺_G)
 *           pilih Y sebagai primary key dari RY
 *           Xf := Xf - {B} untuk setiap non-key attribute B ∈ Y⁺_G
 *           hapus dari G semua FD dengan LHS ⊆ Y⁺_G
 *           R2NF := R2NF ∪ RY(Y ∪ Y⁺_G)
 *   R2NF := R2NF ∪ RK(Xf) di mana K adalah primary key dari R
 *
 * Theorem 2:
 *   a. Jika Fp = ∅ → R sudah dalam 2NF
 *   b. Jika Fp ≠ ∅ → R tidak dalam 2NF
 */
class SecondNFDecomposer
{
    public function __construct(
        private readonly ClosureCalculator $closureCalc,
    ) {}

    /**
     * @param  string[]               $attributes    Semua atribut relasi R
     * @param  FunctionalDependency[] $fullDeps      Ff (output A4)
     * @param  FunctionalDependency[] $partialDeps   Fp (output A4)
     * @param  string[][]             $candidateKeys Candidate keys dari R
     * @param  string[]               $primaryKey    Primary key yang dipilih
     * @return array{
     *   is2NF: bool,
     *   relations: array<string, array{attributes: string[], primaryKey: string[], dependencies: FunctionalDependency[]}>
     * }
     */
    public function decompose(
        array $attributes,
        array $fullDeps,
        array $partialDeps,
        array $candidateKeys,
        array $primaryKey
    ): array {
        // Theorem 2a: Jika tidak ada partial dependency → sudah 2NF
        if (empty($partialDeps)) {
            return [
                'is2NF'     => true,
                'relations' => [
                    $this->makeRelationName($primaryKey) => [
                        'attributes'   => $attributes,
                        'primaryKey'   => $primaryKey,
                        'dependencies' => $fullDeps,
                    ],
                ],
            ];
        }

        // Theorem 2b: Ada partial dependency → tidak 2NF, lakukan dekomposisi
        $R2NF = [];

        // Xf = himpunan atribut yang muncul dalam full dependencies
        // (atribut yang akan tinggal di relasi utama)
        $Xf = $this->collectAttributesFromDeps($fullDeps, $candidateKeys, $attributes);

        // G adalah salinan Fp yang akan kita proses
        $G = $partialDeps;

        // Kumpulkan semua FD (Fm = Ff ∪ Fp) untuk closure computation
        $Fm = array_merge($fullDeps, $partialDeps);

        // Proses setiap Y→A dalam G
        $processed = []; // Catat LHS yang sudah diproses
        $i = 0;

        while ($i < count($G)) {
            $fd = $G[$i];
            $Y  = $fd->lhs;

            // Buat key unik untuk grup LHS ini
            $yKey = $this->lhsKey($Y);

            if (isset($processed[$yKey])) {
                $i++;
                continue;
            }

            // Hitung Y⁺_G (closure Y terhadap G)
            $yPlusG = $this->closureCalc->compute($Y, $G);

            // Buat relasi RY dengan atribut = Y ∪ Y⁺_G
            $ryAttributes = array_values(array_unique(array_merge($Y, $yPlusG)));
            sort($ryAttributes);

            // Pisahkan FD yang relevan untuk relasi ini
            $ryDeps = $this->getFdsForRelation($ryAttributes, $Fm);

            $relationName = $this->makeRelationName($Y);
            $R2NF[$relationName] = [
                'attributes'   => $ryAttributes,
                'primaryKey'   => sort_copy($Y),
                'dependencies' => $ryDeps,
            ];

            // Hapus non-key attributes yang sudah masuk RY dari Xf
            foreach ($ryAttributes as $attr) {
                if (!$this->isKeyAttribute($attr, $candidateKeys)) {
                    $Xf = array_values(array_diff($Xf, [$attr]));
                }
            }

            // Hapus dari G semua FD dengan LHS ⊆ Y⁺_G
            foreach ($G as $j => $depG) {
                if (count(array_diff($depG->lhs, $yPlusG)) === 0) {
                    unset($G[$j]);
                }
            }
            $G = array_values($G);

            $processed[$yKey] = true;
            // i tidak perlu di-increment karena elemen mungkin sudah bergeser
        }

        // Tambahkan relasi utama RK dengan atribut yang tersisa di Xf
        // Pastikan primary key tetap ada di Xf
        foreach ($primaryKey as $pkAttr) {
            if (!in_array($pkAttr, $Xf, true)) {
                $Xf[] = $pkAttr;
            }
        }
        // Tambahkan semua key attributes ke Xf
        foreach ($candidateKeys as $ck) {
            foreach ($ck as $attr) {
                if (!in_array($attr, $Xf, true)) {
                    $Xf[] = $attr;
                }
            }
        }
        sort($Xf);
        $Xf = array_values(array_unique($Xf));

        $mainRelDeps = $this->getFdsForRelation($Xf, $Fm);
        $mainKey     = $this->choosePrimaryKey($Xf, $candidateKeys);
        $mainName    = $this->makeRelationName($mainKey);

        $R2NF[$mainName] = [
            'attributes'   => $Xf,
            'primaryKey'   => $mainKey,
            'dependencies' => $mainRelDeps,
        ];

        return [
            'is2NF'     => false,
            'relations' => $R2NF,
        ];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Kumpulkan atribut yang relevan untuk relasi utama (Xf).
     * Xf = semua atribut yang terlibat dalam full dependencies + key attributes.
     *
     * @param  FunctionalDependency[] $fullDeps
     * @param  string[][]             $candidateKeys
     * @param  string[]               $allAttributes
     * @return string[]
     */
    private function collectAttributesFromDeps(
        array $fullDeps,
        array $candidateKeys,
        array $allAttributes
    ): array {
        $attrs = [];

        // Semua key attributes pasti masuk
        foreach ($candidateKeys as $ck) {
            foreach ($ck as $a) {
                $attrs[$a] = true;
            }
        }

        // Atribut di RHS full dependencies
        foreach ($fullDeps as $fd) {
            $attrs[$fd->rhs] = true;
            foreach ($fd->lhs as $a) {
                $attrs[$a] = true;
            }
        }

        return array_keys($attrs);
    }

    /**
     * Ambil semua FD dari Fm yang berlaku untuk relasi dengan atribut $relAttrs.
     * Sebuah FD X→A relevan jika X ⊆ relAttrs DAN A ∈ relAttrs.
     *
     * @param  string[]               $relAttrs
     * @param  FunctionalDependency[] $Fm
     * @return FunctionalDependency[]
     */
    private function getFdsForRelation(array $relAttrs, array $Fm): array
    {
        $result = [];
        foreach ($Fm as $fd) {
            $lhsInRel = count(array_diff($fd->lhs, $relAttrs)) === 0;
            $rhsInRel = in_array($fd->rhs, $relAttrs, true);

            if ($lhsInRel && $rhsInRel) {
                $result[] = $fd;
            }
        }
        return $result;
    }

    private function isKeyAttribute(string $attr, array $candidateKeys): bool
    {
        foreach ($candidateKeys as $ck) {
            if (in_array($attr, $ck, true)) {
                return true;
            }
        }
        return false;
    }

    private function choosePrimaryKey(array $attributes, array $candidateKeys): array
    {
        foreach ($candidateKeys as $ck) {
            if (count(array_diff($ck, $attributes)) === 0) {
                return sort_copy($ck);
            }
        }
        return $attributes; // fallback: semua atribut
    }

    private function makeRelationName(array $lhs): string
    {
        $sorted = $lhs;
        sort($sorted);
        return 'R_' . implode('_', $sorted);
    }

    private function lhsKey(array $lhs): string
    {
        $copy = $lhs;
        sort($copy);
        return implode(',', $copy);
    }
}