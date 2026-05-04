<?php

namespace App\Services;

/**
 * Value Object: merepresentasikan sebuah Functional Dependency (FD)
 * Contoh: X → A  →  lhs = ['X'], rhs = 'A'
 *
 * Paper Demba menggunakan canonical form: setiap FD hanya memiliki
 * satu atribut di sisi kanan (singleton RHS).
 */
class FunctionalDependency
{
    /** @param string[] $lhs Left-hand side (determinant) */
    public function __construct(
        public readonly array  $lhs,
        public readonly string $rhs,
    ) {}

    /**
     * Buat salinan FD dengan LHS yang sudah diganti.
     * @param string[] $newLhs
     */
    public function withLhs(array $newLhs): self
    {
        return new self($newLhs, $this->rhs);
    }

    /** Representasi teks, berguna untuk debugging */
    public function __toString(): string
    {
        return implode(',', $this->lhs) . ' → ' . $this->rhs;
    }

    /** Cek kesamaan struktural dua FD */
    public function equals(self $other): bool
    {
        $lhsA = $this->lhs;
        $lhsB = $other->lhs;
        sort($lhsA);
        sort($lhsB);

        return $lhsA === $lhsB && $this->rhs === $other->rhs;
    }
}