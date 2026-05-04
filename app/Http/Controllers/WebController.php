<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DBMLParser;
use App\Services\FunctionalDependency;
use App\Services\NormalizationAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebController extends Controller
{
    public function __construct(
        private readonly DBMLParser            $parser,
        private readonly NormalizationAnalyzer $analyzer,
    ) {}

    // GET /
    public function index()
    {
        $projects = Project::orderBy('created_at', 'desc')->limit(10)->get();
        return view('welcome', compact('projects'));
    }

    // GET /upload
    public function upload()
    {
        return view('upload');
    }

    // POST /parse
    // Terima DBML → parse → tampilkan form input FD
    public function parseTables(Request $request)
    {
        $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'dbml_text'    => ['required', 'string'],
        ]);

        try {
            $tables = $this->parser->parse($request->input('dbml_text'));
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['dbml_text' => $e->getMessage()]);
        }

        session([
            'project_name' => $request->input('project_name'),
            'dbml_text'    => $request->input('dbml_text'),
            'tables'       => $tables,
        ]);

        return view('fd-input', compact('tables'));
    }

    // POST /analyze
    // Terima FD dari form → analisis → simpan → redirect results
    public function analyze(Request $request)
    {
        $tables      = session('tables');
        $dbmlText    = session('dbml_text');
        $projectName = session('project_name');

        if (empty($tables)) {
            return redirect()->route('upload')
                ->withErrors(['session' => 'Session expired. Please upload DBML again.']);
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
                        '1NF'             => ['status' => true,  'message' => 'Assumed in 1NF'],
                        '2NF'             => ['status' => null,  'message' => 'No functional dependencies provided, skipped'],
                        'recommendations' => ['Provide functional dependencies to enable 2NF analysis.'],
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
                attributes:   $attributes,
                dependencies: $fds,
                primaryKey:   $pkColumns,
            );

            $recommendations = [];

            if (!$result->is2NF) {
                foreach ($result->partialDependencies as $fd) {
                    $recommendations[] = 'Partial dependency found: ' . (string) $fd
                        . ' — move to a separate table with key (' . implode(', ', $fd->lhs) . ')';
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
                        'message' => 'Table is in 1NF',
                    ],
                    '2NF' => [
                        'status'  => $result->is2NF,
                        'message' => $result->is2NF
                            ? 'Table is in 2NF'
                            : 'Table violates 2NF — partial dependencies found',
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

        $project = Project::create([
            'project_id'      => (string) Str::uuid(),
            'name'            => $projectName,
            'dbml_text'       => $dbmlText,
            'analysis_result' => $analysisResult,
            'tables_count'    => count($tables),
            'issues_count'    => $issuesCount,
            'passed_tables'   => $passedTables,
            'status'          => 'analyzed',
        ]);

        session()->forget(['project_name', 'dbml_text', 'tables']);

        return redirect()->route('results', $project->project_id);
    }

    // GET /results/{project_id}
    public function results(string $projectId)
    {
        $project = Project::where('project_id', $projectId)->firstOrFail();
        return view('results', compact('project'));
    }

    // GET /visualize/{project_id}
    public function visualize(string $projectId)
    {
        $project = Project::where('project_id', $projectId)->firstOrFail();
        return view('visualize', compact('project'));
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
}