<?php

namespace AshrafAkon\Elasticsearch\Commands;

use AshrafAkon\Elasticsearch\Elasticsearch;
use Illuminate\Console\Command;

class CreateIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scout:create-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create index required for Scout';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = Elasticsearch::client();

        if (!$client->indices()->exists(['index' => config('scout.elasticsearch.index')])) {
            $params = [
                'index' => config('scout.elasticsearch.index'),
            ];
            $client->indices()->create($params);
        }
    }
}
