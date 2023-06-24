<?php

namespace AshrafAkon\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;

class ElasticsearchEngine extends Engine
{
    /**
     * The ElasticSearch client.
     *
     * @var Client
     */
    protected $elasticsearch;

    /**
     * Create a new engine instance.
     *
     * @param  Client  $elasticsearch
     * @return void
     */
    public function __construct(Client $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Update the given model in the index.
     *
     * @param  Collection $models
     * @return void
     */
    public function update($models)
    {
        $params = new Bulk();
        $params->index($models);
        $response = $this->elasticsearch->bulk($params->toArray())->asArray();
        if (array_key_exists('errors', $response) && $response['errors']) {
            $error = new \Exception (json_encode($response, JSON_PRETTY_PRINT));
            throw new \Exception ('Bulk update error', $error->getCode(), $error);
        }
        // $params['body'] = [];

        // $models->each(function ($model) use (&$params) {
        //     $params['body'][] = [
        //         'update' => [
        //             '_id' => $model->getKey(),
        //             '_index' => $model->searchableAs(),
        //             '_type' => class_basename($model),
        //         ],
        //     ];
        //     $params['body'][] = [
        //         'doc' => $model->toSearchableArray(),
        //         'doc_as_upsert' => true,
        //     ];
        // });

        // $this->elasticsearch->bulk($params);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  Collection $models
     * @return void
     */
    public function delete($models)
    {
        $params['body'] = [];

        $models->each(function ($model) use (&$params) {
            $params['body'][] = [
                'delete' => [
                    '_id' => $model->getKey(),
                    '_index' => $model->searchableAs(),
                    '_type' => class_basename($model),
                ],
            ];
        });

        $this->elasticsearch->bulk($params);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder $builder
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'size' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder $builder
     * @param  int $perPage
     * @param  int $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        $result = $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'from' => (($page * $perPage) - $perPage),
            'size' => $perPage,
        ]);

        $result['nbPages'] = $result['hits']['total'] / $perPage;

        return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  Builder $builder
     * @param  array $options
     * @return mixed
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $params = [
            'index' => $builder->model->searchableAs(),
            'type' => class_basename($builder->model),
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [['query_string' => ['query' => "*{$builder->query}*"]]],
                    ],
                ],
            ],
        ];

        if ($sort = $this->sort($builder)) {
            $params['body']['sort'] = $sort;
        }

        if (isset($options['from'])) {
            $params['body']['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['body']['size'] = $options['size'];
        }

        if (isset($options['numericFilters']) && count($options['numericFilters'])) {
            $params['body']['query']['bool']['must'] = array_merge(
                $params['body']['query']['bool']['must'],
                $options['numericFilters']
            );
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->elasticsearch,
                $builder->query,
                $params
            );
        }

        return $this->elasticsearch->search($params);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  Builder $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            if (is_array($value)) {
                return ['terms' => [$key => $value]];
            }

            return ['match_phrase' => [$key => $value]];
        })->values()->all();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits']['hits'])->pluck('_id')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder $builder
     * @param  mixed $results
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results['hits']['total'] === 0) {
            return Collection::make();
        }

        $keys = collect($results['hits']['hits'])
            ->pluck('_id')->values()->all();

        $models = $model->getScoutModelsByIds(
            $builder,
            $keys
        )->keyBy(function ($model) {
            return $model->getScoutKey();
        });

        return collect($results['hits']['hits'])->map(function ($hit) use ($model, $models) {
            return isset($models[$hit['_id']]) ? $models[$hit['_id']] : null;
        })->filter()->values();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['hits']['total'];
    }

    /**
     * Generates the sort if theres any.
     *
     * @param  Builder $builder
     * @return array|null
     */
    protected function sort($builder)
    {
        if (count($builder->orders) == 0) {
            return null;
        }

        return collect($builder->orders)->map(function ($order) {
            return [$order['column'] => $order['direction']];
        })->toArray();
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @return void
     */
    public function flush($model)
    {
        $this->elasticsearch->deleteByQuery([
            'index' => $model->searchableAs(),
            'type' => class_basename($model),
            'body' => [
                'query' => [
                    'match_all' => (object) [],
                ],
            ],
        ]);
    }

    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     */
    public function createIndex($name, array $options = [])
    {
        throw new \Error ('Not implemented');
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function deleteIndex($name)
    {
        throw new \Error ('Not implemented');
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        throw new \Error ('Not implemented');
    }
}
