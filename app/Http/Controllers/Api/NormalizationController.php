<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SQLParser;                      // Ganti DBMLParser
use App\Services\NormalizationAnalyzer;
use Illuminate\Http\Request;

class NormalizationController extends Controller
{
    private SQLParser $parser;                    // Ganti tipe
    private NormalizationAnalyzer $analyzer;

    public function __construct(SQLParser $parser, NormalizationAnalyzer $analyzer)
    {
        $this->parser = $parser;
        $this->analyzer = $analyzer;
    }

    /**
     * Upload SQL dump (CREATE TABLE + optional INSERT) dan analisis normalisasi.
     */
    public function uploadSql(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'sql_text'     => 'required|string',      // field diganti dari dbml_text
        ]);

        try {
            // Parse SQL
            $parsed = $this->parser->parse($request->input('sql_text'));
            $tables = $parsed['tables'];               // SQLParser mengembalikan array dengan key 'tables'

            // Analyze normalization (sama seperti sebelumnya)
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
                'error' => 'Malformed SQL: ' . $e->getMessage(),
            ], 400);
        }
    }

    // Optional: tetap support endpoint lama dengan nama uploadDbml (deprecated)
    public function uploadDbml(Request $request)
    {
        // Redirect ke method baru, atau beri pesan error
        return response()->json([
            'error' => 'This endpoint now expects SQL. Please use POST /api/upload-sql with field "sql_text".',
        ], 400);
    }

    public function getResults(string $projectId)
    {
        return response()->json([
            'error' => 'Projects are not persisted. Use POST /api/upload-sql for analysis.',
        ], 404);
    }

    public function listProjects()
    {
        return response()->json([], 200);
    }

    public function analyzeOnly(Request $request)
    {
        try {
            $parsed = $this->parser->parse($request->input('sql_text', ''));
            $tables = $parsed['tables'];
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