<?php

namespace App\Services;

class NormalizationAnalyzer
{
    public function __construct(
        private readonly ExtraneousAttributeRemover $extrRemover,
        private readonly MinimalCoverCalculator     $minimalCover,
        private readonly CandidateKeyFinder         $keyFinder,
        private readonly DependencyClassifier       $classifier,
        private readonly SecondNFDecomposer         $decomp2NF,
        private readonly ThirdNFDecomposer          $decomp3NF,
    ) {}

    public function normalize(
        string  $relationName,
        array   $attributes,
        array   $dependencies,
        ?array  $primaryKey = null
    ): NormalizationResult {
        $steps = [];

        // Step 0a: A2 — Hapus implied extraneous attributes
        $fPrime = $this->extrRemover->remove($dependencies);
        $steps['preprocessing_extraneous'] = [
            'input'  => $this->fdsToString($dependencies),
            'output' => $this->fdsToString($fPrime),
            'note'   => 'Implied extraneous attributes removed (Algorithm A2)',
        ];

        // Step 0b: A3 — Minimal cover
        $Fm = $this->minimalCover->compute($fPrime);
        $steps['minimal_cover'] = [
            'input'  => $this->fdsToString($fPrime),
            'output' => $this->fdsToString($Fm),
            'note'   => 'Redundant dependencies removed (Algorithm A3)',
        ];

        // Step 1: Candidate keys
        $candidateKeys = $this->keyFinder->findAll($attributes, $Fm);
        $steps['candidate_keys'] = [
            'keys' => array_map(fn($k) => implode(', ', $k), $candidateKeys),
            'note' => 'Candidate keys identified',
        ];

        if ($primaryKey === null) {
            $primaryKey = $candidateKeys[0] ?? $attributes;
        }

        // Step 2: A4 — Klasifikasi Ff dan Fp
        $classified = $this->classifier->classify($Fm, $candidateKeys);
        $Ff         = $classified['full'];
        $Fp         = $classified['partial'];

        $steps['classify_dependencies'] = [
            'full'    => $this->fdsToString($Ff),
            'partial' => $this->fdsToString($Fp),
            'note'    => 'Dependencies classified into full (Ff) and partial (Fp) (Algorithm A4)',
        ];

        // Step 3: A5 — Dekomposisi ke 2NF
        $result2NF = $this->decomp2NF->decompose(
            $attributes,
            $Ff,
            $Fp,
            $candidateKeys,
            $primaryKey
        );

        $steps['decompose_2nf'] = [
            'is2NF'     => $result2NF['is2NF'],
            'relations' => array_map(
                fn($r) => [
                    'attributes' => $r['attributes'],
                    'primaryKey' => $r['primaryKey'],
                    'fds'        => $this->fdsToString($r['dependencies']),
                ],
                $result2NF['relations']
            ),
            'note' => $result2NF['is2NF']
                ? 'Relation is already in 2NF (Theorem 2a)'
                : 'Relation decomposed into 2NF (Algorithm A5)',
        ];

        // Step 4: A6 — Dekomposisi ke 3NF
        $result3NF = $this->decomp3NF->decomposeAll($result2NF['relations']);

        // Kumpulkan transitive deps sebagai FunctionalDependency object
        // langsung dari report SEBELUM di-convert ke string
        $allTransitiveDeps = [];
        foreach ($result3NF['report'] as $r) {
            foreach ($r['transitiveDeps'] as $fd) {
                $allTransitiveDeps[] = $fd; // ini masih FunctionalDependency object
            }
        }

        $steps['decompose_3nf'] = [
            'relations' => array_map(
                fn($r) => [
                    'attributes' => $r['attributes'],
                    'primaryKey' => $r['primaryKey'],
                    'fds'        => $this->fdsToString($r['dependencies']),
                ],
                $result3NF['allRelations']
            ),
            'report' => array_map(
                fn($r) => [
                    'is3NF'          => $r['is3NF'],
                    'transitiveDeps' => $this->fdsToString($r['transitiveDeps']), // convert ke string hanya untuk steps
                ],
                $result3NF['report']
            ),
            'note' => 'Relations decomposed into 3NF (Algorithm A6)',
        ];

        return new NormalizationResult(
            originalRelation: $relationName,
            originalAttributes: $attributes,
            originalDependencies: $dependencies,
            minimalCover: $Fm,
            candidateKeys: $candidateKeys,
            primaryKey: $primaryKey,
            fullDependencies: $Ff,
            partialDependencies: $Fp,
            is2NF: $result2NF['is2NF'],
            relations2NF: $result2NF['relations'],
            is3NF: $this->allAre3NF($result3NF['report']),
            relations3NF: $result3NF['allRelations'],
            transitiveDependencies: $allTransitiveDeps,
            steps: $steps,
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function fdsToString(array $fds): array
    {
        return array_map(fn($fd) => (string) $fd, $fds);
    }

    private function allAre3NF(array $report): bool
    {
        foreach ($report as $r) {
            if (!$r['is3NF']) return false;
        }
        return true;
    }

    private function collectAllTransitiveDeps(array $report): array
    {
        $all = [];
        foreach ($report as $r) {
            foreach ($r['transitiveDeps'] as $fd) {
                // transitiveDeps di report sudah di-convert ke string oleh decomposeAll
                // kita butuh object aslinya
                if ($fd instanceof \App\Services\FunctionalDependency) {
                    $all[] = $fd;
                }
            }
        }
        return $all;
    }
}

// ============================================================
// NormalizationResult
// ============================================================
readonly class NormalizationResult
{
    public function __construct(
        public string $originalRelation,
        /** @var string[] */
        public array  $originalAttributes,
        /** @var FunctionalDependency[] */
        public array  $originalDependencies,
        /** @var FunctionalDependency[] */
        public array  $minimalCover,
        /** @var string[][] */
        public array  $candidateKeys,
        /** @var string[] */
        public array  $primaryKey,
        /** @var FunctionalDependency[] */
        public array  $fullDependencies,
        /** @var FunctionalDependency[] */
        public array  $partialDependencies,
        public bool   $is2NF,
        /** @var array<string, array{attributes: string[], primaryKey: string[], dependencies: FunctionalDependency[]}> */
        public array  $relations2NF,
        public bool   $is3NF,
        /** @var array<string, array{attributes: string[], primaryKey: string[], dependencies: FunctionalDependency[]}> */
        public array  $relations3NF,
        /** @var FunctionalDependency[] */
        public array  $transitiveDependencies,
        /** @var array<string, mixed> */
        public array  $steps,
    ) {}

    public function toArray(): array
    {
        $fdToArr = fn(FunctionalDependency $fd) => [
            'lhs' => $fd->lhs,
            'rhs' => $fd->rhs,
            'str' => (string) $fd,
        ];

        $relToArr = fn(array $rel) => [
            'attributes'   => $rel['attributes'],
            'primaryKey'   => $rel['primaryKey'],
            'dependencies' => array_map($fdToArr, $rel['dependencies']),
        ];

        return [
            'originalRelation'       => $this->originalRelation,
            'originalAttributes'     => $this->originalAttributes,
            'originalDependencies'   => array_map($fdToArr, $this->originalDependencies),
            'minimalCover'           => array_map($fdToArr, $this->minimalCover),
            'candidateKeys'          => $this->candidateKeys,
            'primaryKey'             => $this->primaryKey,
            'fullDependencies'       => array_map($fdToArr, $this->fullDependencies),
            'partialDependencies'    => array_map($fdToArr, $this->partialDependencies),
            'is2NF'                  => $this->is2NF,
            'relations2NF'           => array_map($relToArr, $this->relations2NF),
            'is3NF'                  => $this->is3NF,
            'relations3NF'           => array_map($relToArr, $this->relations3NF),
            'transitiveDependencies' => array_map($fdToArr, $this->transitiveDependencies),
            'steps'                  => $this->steps,
        ];
    }
}
