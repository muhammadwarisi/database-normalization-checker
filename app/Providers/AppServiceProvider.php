<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // app/Providers/AppServiceProvider.php → method register()

        $this->app->singleton(\App\Services\NormalizationAnalyzer::class, function ($app) {
            $closure   = new \App\Services\ClosureCalculator();
            $keyFinder = new \App\Services\CandidateKeyFinder($closure); // ← sudah ada
            return new \App\Services\NormalizationAnalyzer(
                new \App\Services\ExtraneousAttributeRemover($closure),
                new \App\Services\MinimalCoverCalculator($closure),
                $keyFinder,
                new \App\Services\DependencyClassifier($closure),
                new \App\Services\SecondNFDecomposer($closure),
                new \App\Services\ThirdNFDecomposer($closure, $keyFinder), // ← tambah $keyFinder
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
