<?php

namespace App\Http\Controllers;

use App\Services\SQLParser;
use App\Services\FunctionalDependency;
use App\Services\NormalizationAnalyzer;
use Illuminate\Http\Request;

class WebController extends Controller
{
    public function __construct(
        private readonly SQLParser             $parser,
        private readonly NormalizationAnalyzer $analyzer,
    ) {}

    public function index()
    {
        return redirect()->route('upload');
    }

    public function upload()
    {
        return view('upload');
    }

    public function parseTables(Request $request)
    {
        $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'sql_file'     => ['required', 'file', 'mimes:sql,txt', 'max:10240'],
        ]);

        try {
            $sqlContent = $request->file('sql_file')->get();
            $parsed     = $this->parser->parse($sqlContent);
            $tables     = $parsed['tables'];
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['sql_file' => $e->getMessage()]);
        }

        session([
            'project_name' => $request->input('project_name'),
            'tables'       => $tables,
        ]);

        return view('fd-input', compact('tables'));
    }

    public function analyze(Request $request)
    {
        $tables      = session('tables');
        $projectName = session('project_name');

        if (empty($tables)) {
            return redirect()->route('upload')
                ->withErrors(['session' => 'Session expired. Please upload SQL again.']);
        }

        $analysisResult = ['tables' => []];
        $issuesCount    = 0;
        $passedTables   = 0;

        foreach ($tables as $table) {
            $tableName  = $table['name'];
            $attributes = array_map(fn($c) => $c['name'], $table['columns']);
            $pkColumns  = $table['pk_columns'];
            $rawFds     = $request->input("fds.{$tableName}", []);
            $fds        = $this->buildFunctionalDependencies($rawFds);

            // Skip jika tidak ada FD
            if (empty($fds)) {
                $analysisResult['tables'][] = [
                    'name'     => $tableName,
                    'columns'  => $table['columns'],
                    'analysis' => [
                        '1NF'                  => ['status' => true,  'message' => 'Diasumsikan dalam 1NF'],
                        '2NF'                  => ['status' => null,  'message' => 'Tidak ada functional dependencies, dilewati'],
                        '3NF'                  => ['status' => null,  'message' => 'Tidak ada functional dependencies, dilewati'],
                        'candidate_keys'       => [],
                        'minimal_cover'        => [],
                        'partial_deps'         => [],
                        'full_deps'            => [],
                        'transitive_deps'      => [],
                        'decomposed_2nf'       => [],
                        'decomposed_3nf'       => [],
                        'recommendations'      => ['Berikan functional dependencies untuk mengaktifkan analisis 2NF dan 3NF.'],
                    ],
                ];
                $issuesCount++;
                continue;
            }

            $result = $this->analyzer->normalize(
                relationName: $tableName,
                attributes: $attributes,
                dependencies: $fds,
                primaryKey: $pkColumns,
            );

            // Hitung issues: melanggar 2NF atau 3NF
            $hasIssue = !$result->is2NF || !$result->is3NF;
            if ($hasIssue) {
                $issuesCount++;
            } else {
                $passedTables++;
            }

            // Rekomendasi
            $recommendations = [];
            if (!$result->is2NF) {
                foreach ($result->partialDependencies as $fd) {
                    $recommendations[] = '[2NF] Ketergantungan parsial: ' . (string) $fd
                        . ' — pisahkan ke tabel dengan kunci (' . implode(', ', $fd->lhs) . ')';
                }
            }
            if (!$result->is3NF) {
                foreach ($result->transitiveDependencies as $fd) {
                    if (is_object($fd) && property_exists($fd, 'lhs')) {
                        $recommendations[] = '[3NF] Ketergantungan transitif: ' . (string) $fd
                            . ' — pisahkan ke tabel dengan kunci (' . implode(', ', $fd->lhs) . ')';
                    } else {
                        // Jika $fd sudah string
                        $recommendations[] = '[3NF] Ketergantungan transitif: ' . (string) $fd;
                    }
                }
            }

            // Format relasi hasil dekomposisi 2NF
            $decomposed2NF = [];
            foreach ($result->relations2NF as $relName => $rel) {
                $nonPk = array_values(array_diff($rel['attributes'], $rel['primaryKey']));
                $decomposed2NF[] = [
                    'name'        => $relName,
                    'attributes'  => $rel['attributes'],
                    'primary_key' => $rel['primaryKey'],
                    'non_pk'      => $nonPk,
                    'fds'         => array_map(fn($fd) => (string) $fd, $rel['dependencies']),
                ];
            }

            // Format relasi hasil dekomposisi 3NF
            $decomposed3NF = [];
            foreach ($result->relations3NF as $relName => $rel) {
                $nonPk = array_values(array_diff($rel['attributes'], $rel['primaryKey']));
                $decomposed3NF[] = [
                    'name'        => $relName,
                    'attributes'  => $rel['attributes'],
                    'primary_key' => $rel['primaryKey'],
                    'non_pk'      => $nonPk,
                    'fds'         => array_map(fn($fd) => (string) $fd, $rel['dependencies']),
                ];
            }

            $analysisResult['tables'][] = [
                'name'    => $tableName,
                'columns' => $table['columns'],
                'analysis' => [
                    '1NF' => [
                        'status'  => true,
                        'message' => 'Tabel berada dalam 1NF',
                    ],
                    '2NF' => [
                        'status'  => $result->is2NF,
                        'message' => $result->is2NF
                            ? 'Tabel memenuhi 2NF'
                            : 'Tabel melanggar 2NF — terdapat ketergantungan parsial',
                    ],
                    '3NF' => [
                        'status'  => $result->is3NF,
                        'message' => $result->is3NF
                            ? 'Tabel memenuhi 3NF'
                            : 'Tabel melanggar 3NF — terdapat ketergantungan transitif',
                    ],
                    'candidate_keys'  => $result->candidateKeys,
                    'minimal_cover'   => array_map(fn($fd) => (string) $fd, $result->minimalCover),
                    'partial_deps'    => array_map(fn($fd) => (string) $fd, $result->partialDependencies),
                    'full_deps'       => array_map(fn($fd) => (string) $fd, $result->fullDependencies),
                    'transitive_deps' => array_map(fn($fd) => (string) $fd, $result->transitiveDependencies),
                    'decomposed_2nf'  => $decomposed2NF,
                    'decomposed_3nf'  => $decomposed3NF,
                    'recommendations' => $recommendations,
                ],
            ];
        }

        session([
            'analysis_result' => $analysisResult,
            'tables_count'    => count($tables),
            'issues_count'    => $issuesCount,
            'passed_tables'   => $passedTables,
            'project_name'    => $projectName,
        ]);

        session()->forget(['tables']);

        return redirect()->route('results');
    }

    public function results()
    {
        $analysisResult = session('analysis_result');
        if (empty($analysisResult)) {
            return redirect()->route('upload')
                ->withErrors(['session' => 'Session expired. Please upload SQL again.']);
        }

        $project = (object) [
            'name'            => session('project_name', 'Unnamed Project'),
            'tables_count'    => session('tables_count', 0),
            'issues_count'    => session('issues_count', 0),
            'passed_tables'   => session('passed_tables', 0),
            'analysis_result' => $analysisResult,
            'created_at'      => now(),
        ];

        return view('results', compact('project'));
    }

    public function visualize()
    {
        $analysisResult = session('analysis_result');
        if (empty($analysisResult)) {
            return redirect()->route('upload')
                ->withErrors(['session' => 'Session expired. Please upload SQL again.']);
        }

        $project = (object) [
            'name'            => session('project_name', 'Unnamed Project'),
            'tables_count'    => session('tables_count', 0),
            'issues_count'    => session('issues_count', 0),
            'passed_tables'   => session('passed_tables', 0),
            'analysis_result' => $analysisResult,
            'created_at'      => now(),
        ];

        $tables         = $analysisResult['tables'] ?? [];
        $nodes          = [];
        $edges          = [];
        $allTableNames  = array_column($tables, 'name');

        foreach ($tables as $table) {
            $nodes[] = [
                'id'     => $table['name'],
                'label'  => $table['name'],
                'fields' => array_map(fn($col) => $col['name'], $table['columns']),
            ];
            foreach ($table['columns'] as $col) {
                $ref = $this->detectReferencedTable($col['name'], $allTableNames);
                if ($ref) {
                    $edges[] = ['from' => $table['name'], 'to' => $ref, 'via' => $col['name']];
                }
            }
        }

        $visualizationData = ['nodes' => $nodes, 'edges' => $edges];

        return view('visualize', compact('project', 'visualizationData'));
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function buildFunctionalDependencies(array $rawFds): array
    {
        $fds = [];
        foreach ($rawFds as $item) {
            $lhs = array_values(array_filter(
                (array) ($item['lhs'] ?? []),
                fn($v) => trim($v) !== ''
            ));
            $rhs = trim($item['rhs'] ?? '');
            if (empty($lhs) || $rhs === '') continue;

            foreach (array_filter(array_map('trim', explode(',', $rhs))) as $rhsAttr) {
                $fds[] = new FunctionalDependency($lhs, $rhsAttr);
            }
        }
        return $fds;
    }

    private function detectReferencedTable(string $columnName, array $tableNames): ?string
    {
        if (!str_ends_with($columnName, '_id')) return null;
        $base = substr($columnName, 0, -3);
        foreach ([$base, $base . 's', $base . 'ies', rtrim($base, 's')] as $candidate) {
            if (in_array($candidate, $tableNames)) return $candidate;
        }
        return null;
    }
}
