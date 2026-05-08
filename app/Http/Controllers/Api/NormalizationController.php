<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DBMLParser;
use App\Services\NormalizationAnalyzer;
use Illuminate\Http\Request;

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

            // Return result directly (no database storage)
            return response()->json([
                'status' => 'analyzed',
                'message' => 'Analysis completed. Data is not persisted - refresh to clear results.',
                'summary' => [
                    'tables_count' => count($tables),
                    'issues_count' => $issuesCount,
                    'passed_tables' => $passedTables,
                ],
                'tables' => $analysis,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Malformed DBML: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function getResults(string $projectId)
    {
        return response()->json([
            'error' => 'Projects are not persisted. Use POST /api/upload-dbml for analysis.',
        ], 404);
    }

    public function listProjects()
    {
        return response()->json([], 200);
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
        return response()->json([
            'error' => 'Projects are not persisted. Visualization is only available during the same session in the web interface.',
        ], 404);
    }

}
