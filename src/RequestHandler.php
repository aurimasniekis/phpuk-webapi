<?php

namespace App;

use BenTools\QueryString\QueryString;
use Elasticsearch\ClientBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\JsonResponse;

class RequestHandler implements RequestHandlerInterface
{
    private $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()->setHosts(['159.65.95.247:9200'])->build();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryString = QueryString::factory($request->getUri()->getQuery());

        $searchQuery = $this->defaultQueryParams();
        if ($queryString->hasParam('q')) {
            $searchQuery = $this->searchQueryParams($queryString->getParam('q'));
        }

        $response = $this->client->search($searchQuery);

        return new JsonResponse($this->mapResult($response));
    }

    private function mapResult(array $response): array
    {
        $output = [
            'metadata' => [
                'db_took' => $response['took'],
                'request_took' => round(
                    (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000,
                    4
                ),
                'result_count' => $response['hits']['total'] ?? 0,
            ],
            'data' => [],
        ];

        $mapResult = function ($row) {
            return [
                'id' => $row['_id'],
                'first_name' => $row['_source']['FirstName'],
                'last_name' => $row['_source']['LastName'],
                'designation' => $row['_source']['Designation'],
                'salary' => $row['_source']['Salary'],
                'date_of_joining' => $row['_source']['DateOfJoining'],
                'address' => $row['_source']['Address'],
                'gender' => $row['_source']['Gender'],
                'age' => $row['_source']['Age'],
                'marital_status' => $row['_source']['MaritalStatus'],
                'interests' => $row['_source']['Interests'],
            ];
        };

        $output['data'] = array_map($mapResult, $response['hits']['hits']);

        return $output;
    }

    private function defaultQueryParams(): array
    {
        return [
            'index' => 'companydatabase',
            'type'  => 'employees',
            'body'  => [
                'query' => [
                    'match_all' => new \stdClass(),
                ],
            ],
        ];
    }

    private function searchQueryParams($queryString): array
    {
        return [
            'index' => 'companydatabase',
            'type'  => 'employees',
            'body'  => [
                'query' => [
                    'multi_match' => [
                        'query' => $queryString,
                        'fields' => [
                            'FirstName',
                            'LastName',
                            'Designation',
                            'Interests',
                        ],
                    ],
                ],
            ],
        ];
    }
}
