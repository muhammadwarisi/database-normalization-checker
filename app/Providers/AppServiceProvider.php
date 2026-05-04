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
            $closure = new \App\Services\ClosureCalculator();
            return new \App\Services\NormalizationAnalyzer(
                new \App\Services\ExtraneousAttributeRemover($closure),
                new \App\Services\MinimalCoverCalculator($closure),
                new \App\Services\CandidateKeyFinder($closure),
                new \App\Services\DependencyClassifier($closure),
                new \App\Services\SecondNFDecomposer($closure),
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
