<?php

namespace CarlosOCarvalho\Auditing\Traits;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;


trait ElasticSearchAuditable
{
    public function esAudits($page = 1, $perPage = 10, $sort = 'latest')
    {
        $client = ClientBuilder::create()->setHosts(Config::get('audit.drivers.es.client.hosts', ['localhost:9200']))->build();
        $index = Config::get('audit.drivers.es.index', 'laravel_auditing');
        $type = $this->typeElastic == null ? Config::get('audit.drivers.es.type', 'audits') : $this->typeElastic;

        $from = ($page - 1) * $perPage;
        $order = $sort === 'latest' ? 'desc' : 'asc';

        $params = [
            'index' => $index,
            'type' => $type,
            'size' => $perPage,
            'from' => $from,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'auditable_id' => $this->id
                                ]
                            ]
                        ],
                        /*'filter' => [

                              [
                                'term' => [
                                     'auditable_type' => 'app-entities-schedule'
                                ]
                              ]
                        ]*/
                    ]
                ],
                /*'sort' => [
                    'created_at' => [
                        'order' => $order
                    ]
                ],*/
                'track_scores' => true
            ]
        ];

        //dd($params);
        $results = $client->search($params);
        $hits = $results['hits'];

        $collection = Collection::make();

        foreach ($hits['hits'] as $key => $result) {
            $audit['id'] = $result['_id'];
            $audit = array_merge($audit, $result['_source']);
            $audit['score'] = $result['_score'];

            $collection->put($key, $audit);
        }

        return [
            'total' => $hits['total'],
            'per_page' => $perPage,
            'data' => $collection
        ];
    }

    public function getEsAuditsAttribute()
    {
        return $this->esAudits();
    }
}
