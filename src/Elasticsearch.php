<?php

namespace AshrafAkon\Elasticsearch;

use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\ElasticsearchService\ElasticsearchPhpHandler;
use Elastic\Elasticsearch\ClientBuilder;

class Elasticsearch
{
    public static function client()
    {
        $provider = config('laravel-scout-elastic.provider', 'elastic');
        $handler = false;

        if ($provider === 'aws') {
            // Default credentials
            $credentials = CredentialProvider::defaultProvider();

            // Set credentials
            if (config('laravel-scout-elastic.credentials.key')) {
                $credentials = CredentialProvider::fromCredentials(
                    new Credentials(
                        config('laravel-scout-elastic.credentials.key'),
                        config('laravel-scout-elastic.credentials.secret'),
                        config('laravel-scout-elastic.credentials.token')
                    )
                );
            }

            // Create a handler (with the region of your Amazon Elasticsearch Service domain)
            $handler = new ElasticsearchPhpHandler(config('laravel-scout-elastic.region', 'us-west-2'), $credentials);
        }

        // Use this handler to create an Elasticsearch-PHP client
        $client = ClientBuilder::create()
            ->setHosts(config('scout.elasticsearch.hosts'));

        if ($handler) {
            $client = $client->setHandler($handler);
        }

        return $client->build();
    }
}
