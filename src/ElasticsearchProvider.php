<?php

namespace AshrafAkon\Elasticsearch;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ElasticsearchProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-scout-elastic.php' => config_path('laravel-scout-elastic.php'),
        ]);

        app(EngineManager::class)->extend('elasticsearch', function ($app) {
            return new ElasticsearchEngine(
                Elasticsearch::client()
            );
        });
    }
}
