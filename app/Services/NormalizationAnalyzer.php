<?php

namespace App\Services;

/**
 * NormalizationAnalyzer
 * ============================================================
 * Orkestrasi lengkap algoritma normalisasi Demba (2013)
 * dari input skema 1NF hingga output 3NF.
 *
 * Alur:
 *   INPUT: Relasi R(atribut) + FD set F (canonical form)
 *
 *   Step 0 – Preprocessing
 *     A2: Hapus implied extraneous attributes → F'
 *     A3: Hitung minimal cover Fm dari F'
 *
 *   Step 1 – Temukan candidate keys dari Fm
 *     CandidateKeyFinder → candidateKeys[]
 *
 *   Step 2 – Klasifikasi FD (A4)
 *     DependencyClassifier → Ff (full), Fp (partial)
 *
 *   Step 3 – Dekomposisi ke 2NF (A5)
 *     SecondNFDecomposer → relations2NF[]
 *
 *   Step 4 – Dekomposisi ke 3NF (A6)
 *     ThirdNFDecomposer → relations3NF[]
 *
 *   OUTPUT: NormalizationResult (lihat class di bawah)
 */
class NormalizationAnalyzer
{
    public function __construct(
        // private readonly ClosureCalculator         $closureCalc,
        private readonly ExtraneousAttributeRemover $extrRemover,
        private readonly MinimalCoverCalculator    $minimalCover,
        private readonly CandidateKeyFinder        $keyFinder,
        private readonly DependencyClassifier      $classifier,
        private readonly SecondNFDecomposer        $decomp2NF,
        // private readonly ThirdNFDecomposer         $decomp3NF,
    ) {}

    /**
     * Entry point utama.
     *
     * @param  string                 $relationName  Nama relasi (misal: "ClientRental")
     * @param  string[]               $attributes    Semua atribut relasi
     * @param  FunctionalDependency[] $dependencies  FD dalam canonical form (singleton RHS)
     * @param  string[]|null          $primaryKey    Primary key pilihan; null = otomatis
     * @return NormalizationResult
     */
    public function normalize(
        string  $relationName,
        array   $attributes,
        array   $dependencies,
        ?array  $primaryKey = null
    ): NormalizationResult {
        $steps = [];

        // ----------------------------------------------------------------
        // Step 0a: Hapus implied extraneous attributes (A2)
        // ----------------------------------------------------------------
        $fPrime = $this->extrRemover->remove($dependencies);
        $steps['preprocessing_extraneous'] = [
            'input'  => $this->fdsToString($dependencies),
            'output' => $this->fdsToString($fPrime),
            'note'   => 'Implied extraneous attributes removed (Algorithm A2)',
        ];

        // ----------------------------------------------------------------
        // Step 0b: Hitung minimal cover (A3)
        // ----------------------------------------------------------------
        $Fm = $this->minimalCover->compute($fPrime);
        $steps['minimal_cover'] = [
            'input'  => $this->fdsToString($fPrime),
            'output' => $this->fdsToString($Fm),
            'note'   => 'Redundant dependencies removed (Algorithm A3)',
        ];

        // ----------------------------------------------------------------
        // Step 1: Temukan candidate keys
        // ----------------------------------------------------------------
        $candidateKeys = $this->keyFinder->findAll($attributes, $Fm);
        $steps['candidate_keys'] = [
            'keys' => array_map(fn($k) => implode(', ', $k), $candidateKeys),
            'note' => 'Candidate keys identified',
        ];

        // Pilih primary key
        if ($primaryKey === null) {
            $primaryKey = $candidateKeys[0] ?? $attributes;
        }

        // ----------------------------------------------------------------
        // Step 2: Klasifikasi FD menjadi Ff dan Fp (A4)
        // ----------------------------------------------------------------
        $classified = $this->classifier->classify($Fm, $candidateKeys);
        $Ff         = $classified['full'];
        $Fp         = $classified['partial'];

        $steps['classify_dependencies'] = [
            'full'    => $this->fdsToString($Ff),
            'partial' => $this->fdsToString($Fp),
            'note'    => 'Dependencies classified into full (Ff) and partial (Fp) (Algorithm A4)',
        ];

        // ----------------------------------------------------------------
        // Step 3: Dekomposisi ke 2NF (A5)
        // ----------------------------------------------------------------
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

        // ----------------------------------------------------------------
        // Step 4: Dekomposisi ke 3NF (A6)
        // ----------------------------------------------------------------
        // $result3NF = $this->decomp3NF->decomposeAll($result2NF['relations']);

        // $steps['decompose_3nf'] = [
        //     'relations' => array_map(
        //         fn($r) => [
        //             'attributes' => $r['attributes'],
        //             'primaryKey' => $r['primaryKey'],
        //             'fds'        => $this->fdsToString($r['dependencies']),
        //         ],
        //         $result3NF['allRelations']
        //     ),
        //     'report' => array_map(
        //         fn($r) => [
        //             'is3NF'          => $r['is3NF'],
        //             'transitiveDeps' => $this->fdsToString($r['transitiveDeps']),
        //         ],
        //         $result3NF['report']
        //     ),
        //     'note' => 'Relations decomposed into 3NF (Algorithm A6)',
        // ];

        // ----------------------------------------------------------------
        // Susun hasil akhir
        // ----------------------------------------------------------------
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
            // is3NF: $this->allAre3NF($result3NF['report']),
            // relations3NF: $result3NF['allRelations'],
            steps: $steps,
        );
    }

    /**
     * Batch analyze untuk multiple tables dari DBML Parser
     * Format: array of ['name' => string, 'columns' => array]
     *
     * @param array $tables
     * @return array Array of analysis results compatible dengan blade template
     */
    public function analyze(array $tables): array
    {
        $results = [];

        foreach ($tables as $table) {
            $tableName = $table['name'];
            $columns = $table['columns'];
            $attributes = array_column($columns, 'name');

            // Ekstrak PK dan simple FD dari column attributes
            $primaryKey = array_values(
                array_filter($columns, fn($col) => $col['pk'] ?? false)
            );
            $primaryKey = !empty($primaryKey)
                ? array_column($primaryKey, 'name')
                : [reset($attributes)]; // Default: first column sebagai PK

            // Generate simple functional dependencies
            // (Asumsi: setiap non-PK column depends on PK)
            $dependencies = [];
            foreach ($attributes as $attr) {
                if (!in_array($attr, $primaryKey)) {
                    $dependencies[] = new FunctionalDependency($primaryKey, $attr);
                }
            }

            // Normalize
            try {
                $normResult = $this->normalize($tableName, $attributes, $dependencies, $primaryKey);

                // Transform ke format blade template
                $results[] = [
                    'name'    => $tableName,
                    'columns' => $columns,
                    'analysis' => $this->transformToBladeFormat($normResult, $attributes),
                ];
            } catch (\Exception $e) {
                // Jika error, set default safe analysis
                $results[] = [
                    'name'    => $tableName,
                    'columns' => $columns,
                    'analysis' => [
                        'recommendations' => ['Terjadi kesalahan saat menganalisis: ' . $e->getMessage()],
                        '1NF' => ['status' => false],
                        '2NF' => ['status' => false],
                    ],
                ];
            }
        }

        return $results;
    }

    /**
     * Transform NormalizationResult ke format yang blade template harapkan
     *
     * @param NormalizationResult $result
     * @param array $attributes
     * @return array
     */
    private function transformToBladeFormat(NormalizationResult $result, array $attributes): array
    {
        $recommendations = [];

        // 1NF Check (selalu pass untuk DBML - assumptions struktur tabelnya sudah atomic)
        $is1NF = true;

        // 2NF Check
        $is2NF = $result->is2NF;

        // Generate recommendations berdasarkan normalization status
        if (!$is2NF) {
            $recommendations[] = 'Tabel memiliki ketergantungan parsial pada kunci utama. Pisahkan berdasarkan ketergantungan parsial.';
            foreach ($result->partialDependencies as $fd) {
                $recommendations[] = "  • " . (string)$fd;
            }
        }

        // if (count($result->fullDependencies) > 0) {
        //     // Check untuk transitive dependencies (simple check)
        //     $hasTransitiveDeps = $this->hasTransitiveDependencies($result->fullDependencies);
        //     if ($hasTransitiveDeps) {
        //         $recommendations[] = 'Table has potential transitive dependencies. Consider 3NF decomposition.';
        //     }
        // }

        return [
            'recommendations' => $recommendations,
            '1NF' => ['status' => $is1NF],
            '2NF' => ['status' => $is2NF],
            'details' => [
                'primary_key' => $result->primaryKey,
                'candidate_keys' => $result->candidateKeys,
                'partial_dependencies' => array_map(fn($fd) => (string)$fd, $result->partialDependencies),
                'full_dependencies' => array_map(fn($fd) => (string)$fd, $result->fullDependencies),
            ]
        ];
    }

    /**
     * Simple check untuk transitive dependencies
     */
    private function hasTransitiveDependencies(array $fullDeps): bool
    {
        // Simplified: if any FD chain exists (A->B->C), return true
        // In real implementation, would use closure calculator
        return count($fullDeps) > 1;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** @param FunctionalDependency[] $fds */
    private function fdsToString(array $fds): array
    {
        return array_map(fn($fd) => (string) $fd, $fds);
    }

    /** @param array<string, array{is3NF: bool}> $report */
    private function allAre3NF(array $report): bool
    {
        foreach ($report as $r) {
            if (!$r['is3NF']) return false;
        }
        return true;
    }
}

// ============================================================
// NormalizationResult — Value Object hasil normalisasi
// ============================================================

/**
 * Membawa semua hasil dan langkah-langkah normalisasi.
 * Bisa di-return langsung ke controller, atau di-cast ke array/JSON.
 */
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
        // public bool   $is3NF,
        /** @var array<string, array{attributes: string[], primaryKey: string[], dependencies: FunctionalDependency[]}> */
        // public array  $relations3NF,
        /** @var array<string, mixed> */
        public array  $steps,
    ) {}

    /**
     * Konversi ke array (untuk JSON response / view).
     */
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
            'originalRelation'     => $this->originalRelation,
            'originalAttributes'   => $this->originalAttributes,
            'originalDependencies' => array_map($fdToArr, $this->originalDependencies),
            'minimalCover'         => array_map($fdToArr, $this->minimalCover),
            'candidateKeys'        => $this->candidateKeys,
            'primaryKey'           => $this->primaryKey,
            'fullDependencies'     => array_map($fdToArr, $this->fullDependencies),
            'partialDependencies'  => array_map($fdToArr, $this->partialDependencies),
            'is2NF'                => $this->is2NF,
            'relations2NF'         => array_map($relToArr, $this->relations2NF),
            // 'is3NF'                => $this->is3NF,
            // 'relations3NF'         => array_map($relToArr, $this->relations3NF),
            'steps'                => $this->steps,
        ];
    }
}