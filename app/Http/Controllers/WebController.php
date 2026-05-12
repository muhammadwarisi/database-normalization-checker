<?php

namespace App\Http\Controllers;

use App\Services\SQLParser;               // <-- Ganti DBMLParser
use App\Services\FunctionalDependency;
use App\Services\NormalizationAnalyzer;
use Illuminate\Http\Request;

class WebController extends Controller
{
    public function __construct(
        private readonly SQLParser            $parser,   // <-- Ganti
        private readonly NormalizationAnalyzer $analyzer,
    ) {}

    // GET /
    public function index()
    {
        return redirect()->route('upload');
    }

    // GET /upload
    public function upload()
    {
        return view('upload');
    }

    // POST /parse
    // Terima SQL (bukan DBML) → parse → tampilkan form input FD
    public function parseTables(Request $request)
    {
        $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'sql_file'     => ['required', 'file', 'mimes:sql,txt', 'max:10240'],
        ]);

        try {
            $sqlContent = $request->file('sql_file')->get();
            $parsed = $this->parser->parse($sqlContent);
            $tables = $parsed['tables'];
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['sql_file' => $e->getMessage()]);
        }

        session([
            'project_name' => $request->input('project_name'),
            'tables'       => $tables,
        ]);

        return view('fd-input', compact('tables'));
    }

    // POST /analyze
    // Terima FD dari form → analisis → simpan → redirect results
    public function analyze(Request $request)
    {
        $tables      = session('tables');
        $sqlText     = session('sql_text');      // <-- bisa dipakai jika butuh, tidak wajib
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

            // Jika tidak ada FD → skip, anggap tidak bisa dianalisis
            if (empty($fds)) {
                $analysisResult['tables'][] = [
                    'name'     => $tableName,
                    'columns'  => $table['columns'],
                    'analysis' => [
                        '1NF'             => ['status' => true,  'message' => 'Diasumsikan dalam 1NF'],
                        '2NF'             => ['status' => null,  'message' => 'Tidak ada functional dependencies yang diberikan, dilewati'],
                        'recommendations' => ['Berikan functional dependencies untuk mengaktifkan analisis 2NF.'],
                        'candidate_keys'  => [],
                        'partial_deps'    => [],
                        'decomposed_relations' => [],
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

            $recommendations = [];

            if (!$result->is2NF) {
                foreach ($result->partialDependencies as $fd) {
                    $recommendations[] = 'Ketergantungan parsial ditemukan: ' . (string) $fd
                        . ' — pindahkan ke tabel terpisah dengan kunci (' . implode(', ', $fd->lhs) . ')';
                }
            }

            if (!$result->is2NF) {
                $issuesCount++;
            } else {
                $passedTables++;
            }

            // Format decomposed relations untuk ditampilkan di view
            $decomposedRelations = [];
            foreach ($result->relations2NF as $relName => $rel) {
                $decomposedRelations[] = [
                    'name'        => $relName,
                    'attributes'  => $rel['attributes'],
                    'primary_key' => $rel['primaryKey'],
                    'fds'         => array_map(fn($fd) => (string) $fd, $rel['dependencies']),
                ];
            }

            $analysisResult['tables'][] = [
                'name'     => $tableName,
                'columns'  => $table['columns'],
                'analysis' => [
                    '1NF' => [
                        'status'  => true,
                        'message' => 'Tabel berada dalam 1NF',
                    ],
                    '2NF' => [
                        'status'  => $result->is2NF,
                        'message' => $result->is2NF
                            ? 'Tabel berada dalam 2NF'
                            : 'Tabel melanggar 2NF — ketergantungan parsial ditemukan',
                    ],
                    'candidate_keys'       => $result->candidateKeys,
                    'partial_deps'         => array_map(fn($fd) => (string) $fd, $result->partialDependencies),
                    'full_deps'            => array_map(fn($fd) => (string) $fd, $result->fullDependencies),
                    'minimal_cover'        => array_map(fn($fd) => (string) $fd, $result->minimalCover),
                    'decomposed_relations' => $decomposedRelations,
                    'recommendations'      => $recommendations,
                ],
            ];
        }

        // Store analysis result in session (no database storage)
        session([
            'analysis_result' => $analysisResult,
            'tables_count'    => count($tables),
            'issues_count'    => $issuesCount,
            'passed_tables'   => $passedTables,
            'project_name'    => $projectName,
        ]);

        session()->forget(['sql_text', 'tables']);

        return redirect()->route('results');
    }

    // GET /results
    public function results()
    {
        $analysisResult = session('analysis_result');
        if (empty($analysisResult)) {
            return redirect()->route('upload')
                ->withErrors(['session' => 'Session expired. Please upload SQL again.']);
        }

        // Create a temporary object to pass to view with session data
        $project = (object) [
            'name'              => session('project_name', 'Unnamed Project'),
            'tables_count'      => session('tables_count', 0),
            'issues_count'      => session('issues_count', 0),
            'passed_tables'     => session('passed_tables', 0),
            'analysis_result'   => $analysisResult,
            'created_at'        => now(),
        ];

        return view('results', compact('project'));
    }

    // GET /visualize
    public function visualize()
    {
        $analysisResult = session('analysis_result');
        if (empty($analysisResult)) {
            return redirect()->route('upload')
                ->withErrors(['session' => 'Session expired. Please upload SQL again.']);
        }

        // Create a temporary object to pass to view with session data
        $project = (object) [
            'name'              => session('project_name', 'Unnamed Project'),
            'tables_count'      => session('tables_count', 0),
            'issues_count'      => session('issues_count', 0),
            'passed_tables'     => session('passed_tables', 0),
            'analysis_result'   => $analysisResult,
            'created_at'        => now(),
        ];

        // Generate visualization data from session
        $tables = $analysisResult['tables'] ?? [];
        $nodes = [];
        $edges = [];
        $allTableNames = array_column($tables, 'name');

        foreach ($tables as $table) {
            $nodes[] = [
                'id' => $table['name'],
                'label' => $table['name'],
                'fields' => array_map(fn($col) => $col['name'], $table['columns'])
            ];

            // Detect foreign keys (columns ending with _id)
            foreach ($table['columns'] as $col) {
                $referencedTable = $this->detectReferencedTable($col['name'], $allTableNames);
                if ($referencedTable) {
                    $edges[] = [
                        'from' => $table['name'],
                        'to' => $referencedTable,
                        'via' => $col['name'],
                    ];
                }
            }
        }

        $visualizationData = [
            'nodes' => $nodes,
            'edges' => $edges,
        ];

        return view('visualize', compact('project', 'visualizationData'));
    }

    // ----------------------------------------------------------------
    // Helper
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

            if (empty($lhs) || $rhs === '') {
                continue;
            }

            // Expand jika RHS koma-separated: "a, b" → dua FD terpisah
            $rhsItems = array_values(array_filter(
                array_map('trim', explode(',', $rhs)),
                fn($v) => $v !== ''
            ));

            foreach ($rhsItems as $rhsAttr) {
                $fds[] = new FunctionalDependency($lhs, $rhsAttr);
            }
        }

        return $fds;
    }

    private function detectReferencedTable(string $columnName, array $tableNames)
    {
        if (!str_ends_with($columnName, '_id')) {
            return null;
        }

        $base = substr($columnName, 0, -3);

        // 1) Cek exact match
        if (in_array($base, $tableNames)) {
            return $base;
        }

        // 2) Cek plural (product → products)
        if (in_array($base . 's', $tableNames)) {
            return $base . 's';
        }

        // 3) Cek plural irregular (category → categories)
        if (in_array($base . 'ies', $tableNames)) {
            return $base . 'ies';
        }

        // 4) Cek singular (users → user)
        if (in_array(rtrim($base, 's'), $tableNames)) {
            return rtrim($base, 's');
        }

        return null;
    }
}
