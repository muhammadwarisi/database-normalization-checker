<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DBMLParser;
use App\Services\NormalizationAnalyzer;
use App\Services\ClosureCalculator;
use App\Services\ExtraneousAttributeRemover;
use App\Services\MinimalCoverCalculator;
use App\Services\CandidateKeyFinder;
use App\Services\DependencyClassifier;
use App\Services\SecondNFDecomposer;

class IntegrationTest extends TestCase
{
    /**
     * Test: DBML Parser → NormalizationAnalyzer → Blade Format
     * Ensures complete integration flow
     */
    public function test_integration_dbml_to_analysis(): void
    {
        // Sample DBML
        $dbml = <<<'DBML'
        Table users {
            id int [pk]
            name varchar(255)
            email varchar(255) [unique]
        }
        
        Table posts {
            id int [pk]
            user_id int
            title varchar(255)
            content text
        }
        DBML;

        // Parse DBML
        $parser = new DBMLParser();
        $tables = $parser->parse($dbml);

        $this->assertIsArray($tables);
        $this->assertNotEmpty($tables);
        $this->assertEquals('users', $tables[0]['name']);
        $this->assertEquals('posts', $tables[1]['name']);

        // Analyze using new analyze() method
        $analyzer = $this->app->make(NormalizationAnalyzer::class);
        $analysis = $analyzer->analyze($tables);

        // Verify structure matches blade expectations
        $this->assertIsArray($analysis);
        $this->assertCount(2, $analysis);

        foreach ($analysis as $tableAnalysis) {
            // Check required keys for blade template
            $this->assertArrayHasKey('name', $tableAnalysis);
            $this->assertArrayHasKey('columns', $tableAnalysis);
            $this->assertArrayHasKey('analysis', $tableAnalysis);

            // Check analysis structure
            $this->assertArrayHasKey('recommendations', $tableAnalysis['analysis']);
            $this->assertArrayHasKey('1NF', $tableAnalysis['analysis']);
            $this->assertArrayHasKey('2NF', $tableAnalysis['analysis']);
            $this->assertArrayNotHasKey('3NF', $tableAnalysis['analysis'], '3NF should not be present (user only wants 2NF)');

            // Check 1NF and 2NF have status key
            $this->assertArrayHasKey('status', $tableAnalysis['analysis']['1NF']);
            $this->assertArrayHasKey('status', $tableAnalysis['analysis']['2NF']);

            // Verify types
            $this->assertIsArray($tableAnalysis['analysis']['recommendations']);
            $this->assertIsBool($tableAnalysis['analysis']['1NF']['status']);
            $this->assertIsBool($tableAnalysis['analysis']['2NF']['status']);
        }
    }

    /**
     * Test: Blade can receive and iterate over analysis results
     */
    public function test_blade_can_render_analysis_results(): void
    {
        // This is a simplified check - real blade rendering tested via HTTP
        $dbml = <<<'DBML'
        Table customers {
            id int [pk]
            name varchar(100)
        }
        DBML;

        $parser = new DBMLParser();
        $tables = $parser->parse($dbml);

        $analyzer = $this->app->make(NormalizationAnalyzer::class);
        $analysis = $analyzer->analyze($tables);

        // Simulate what blade does
        $mock_project = (object)[
            'name' => 'Test Project',
            'analysis_result' => ['tables' => $analysis],
            'tables_count' => count($analysis),
            'issues_count' => collect($analysis)->sum(
                fn($t) => count($t['analysis']['recommendations'])
            ),
        ];

        $this->assertEquals('Test Project', $mock_project->name);
        $this->assertCount(1, $mock_project->analysis_result['tables']);

        // Verify first table can be accessed as blade would
        $firstTable = $mock_project->analysis_result['tables'][0];
        $this->assertIsArray($firstTable['analysis']['recommendations']);
        // Note: recommendations can be empty if table is already in 2NF
    }
}
