<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\DBMLParser;
use App\Services\NormalizationAnalyzer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NormalizationController extends Controller
{
    private DBMLParser $parser;
    private NormalizationAnalyzer $analyzer;

    public function __construct(DBMLParser $parser, NormalizationAnalyzer $analyzer)
    {
        $this->parser = $parser;
        $this->analyzer = $analyzer;
    }

    public function uploadDbml(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'dbml_text' => 'required|string',
        ]);

        try {
            // Parse DBML
            $tables = $this->parser->parse($request->dbml_text);

            // Analyze normalization
            $analysis = $this->analyzer->analyze($tables);

            // Count issues
            $issuesCount = 0;
            $passedTables = 0;

            foreach ($analysis as $tableAnalysis) {
                $tableIssues = count($tableAnalysis['analysis']['recommendations']);
                $issuesCount += $tableIssues;

                if ($tableIssues === 0) {
                    $passedTables++;
                }
            }

            // Create project
            $projectId = 'proj-' . Str::random(8);

            $project = Project::create([
                'project_id' => $projectId,
                'name' => $request->project_name,
                'dbml_text' => $request->dbml_text,
                'analysis_result' => ['tables' => $analysis],
                'tables_count' => count($tables),
                'issues_count' => $issuesCount,
                'passed_tables' => $passedTables,
                'status' => 'analyzed',
            ]);

            return response()->json([
                'project_id' => $projectId,
                'status' => 'analyzed',
                'summary' => [
                    'tables_count' => count($tables),
                    'issues_count' => $issuesCount,
                    'passed_tables' => $passedTables,
                ],
                'result_url' => "/results/{$projectId}",
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Malformed DBML: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function getResults(string $projectId)
    {
        $project = Project::where('project_id', $projectId)->first();

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        return response()->json([
            'project_id' => $project->project_id,
            'project_name' => $project->name,
            'created_at' => $project->created_at->toIso8601String(),
            'tables' => $project->analysis_result['tables'] ?? [],
            'summary' => [
                'total_tables' => $project->tables_count,
                'total_issues' => $project->issues_count,
                'passed_tables' => $project->passed_tables,
            ],
        ]);
    }

    public function listProjects()
    {
        $projects = Project::orderBy('created_at', 'desc')->get();

        return response()->json($projects->map(fn($p) => [
            'project_id' => $p->project_id,
            'name' => $p->name,
            'created_at' => $p->created_at->toIso8601String(),
            'short_summary' => $p->short_summary,
        ]));
    }

    public function analyzeOnly(Request $request)
    {
        try {
            $tables = $this->parser->parse($request->input('dbml_text', ''));
            $analysis = $this->analyzer->analyze($tables);

            return response()->json([
                'result' => 'analysis-only',
                'tables' => $analysis,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function visualize(string $projectId)
    {
        $project = Project::where('project_id', $projectId)->first();

        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $tables = $project->analysis_result['tables'] ?? [];
        $nodes = [];
        $edges = [];

        foreach ($tables as $table) {
            $nodes[] = [
                'id' => $table['name'],
                'label' => $table['name'],
                'fields' => array_map(fn($col) => $col['name'], $table['columns'])
            ];

            // Detect foreign keys (columns ending with _id)
            foreach ($table['columns'] as $col) {
                $referencedTable = $this->detectReferencedTable($col['name'], $table['name']);
                if ($referencedTable) {
                    $edges[] = [
                        'from' => $table['name'],
                        'to' => $referencedTable,
                        'via' => $col['name'],
                    ];
                }
            }
        }

        return response()->json([
            'nodes' => array_unique($nodes),
            'edges' => $edges,
        ]);
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
