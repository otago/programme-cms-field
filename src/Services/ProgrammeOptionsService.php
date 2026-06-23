<?php

namespace Otago\ProgrammeCmsField\Services;

use Otago\ProgrammeCmsField\Services\Http\GraphQLHttpClient;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;

/**
 * Fetches programme options from the online.op.ac.nz GraphQL API.
 *
 * Requires the following environment variable:
 *   OP_APPLICATIONS_GRAPHQL_ENDPOINT  e.g. https://online.op.ac.nz/applications-graphql
 *
 * Optional (HTTP Basic Auth):
 *   OP_APPLICATIONS_GRAPHQL_BASIC_USER
 *   OP_APPLICATIONS_GRAPHQL_BASIC_PASS
 */
class ProgrammeOptionsService
{
    private const QUERY = <<<'GRAPHQL'
    query ProgrammeList(
      $limit: Int
      $offset: Int
      $filter: ProgrammeInformationPageFilterFields
    ) {
      readProgrammeInformationPages(
        limit: $limit
        offset: $offset
        filter: $filter
      ) {
        nodes { id title }
        edges { node { id title } }
        pageInfo { hasNextPage }
      }
    }
    GRAPHQL;

    private const QUERY_BY_ID = <<<'GRAPHQL'
    query ProgrammeById($id: ID!) {
      readProgrammeInformationPages(filter: { id: { eq: $id } }) {
        nodes { id title }
        pageInfo { hasNextPage }
      }
    }
    GRAPHQL;

    public static function singleton(): self
    {
        return Injector::inst()->get(self::class);
    }

    private function logger(): LoggerInterface
    {
        return Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * Fetch a page of Programme options.
     *
     * @return array{ok:bool,items?:array<array{value:string,label:string}>,hasNextPage?:bool,error?:string|null,http?:int}
     */
    public function fetch(string $q, int $limit, int $offset): array
    {
        $endpoint = trim((string) Environment::getEnv('OP_APPLICATIONS_GRAPHQL_ENDPOINT'));
        if ($endpoint === '') {
            return ['ok' => true, 'items' => [], 'hasNextPage' => false];
        }

        $vars = [
            'limit'  => $limit,
            'offset' => $offset,
            'filter' => $q !== '' ? ['title' => ['contains' => $q]] : null,
        ];

        try {
            $resp = $this->post($endpoint, self::QUERY, $vars);
        } catch (\Throwable $e) {
            $this->logger()->error('ProgrammeOptionsService: GraphQL request failed', [
                'endpoint'  => GraphQLHttpClient::redactEndpoint($endpoint),
                'exception' => get_class($e),
                'error'     => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => 'Upstream service error', 'http' => 200];
        }

        $conn = $resp['data']['readProgrammeInformationPages'] ?? null;
        if (!is_array($conn)) {
            $this->logger()->warning('ProgrammeOptionsService: GraphQL returned no usable data', [
                'endpoint' => GraphQLHttpClient::redactEndpoint($endpoint),
                'errors'   => $resp['errors'] ?? null,
            ]);
            return ['ok' => false, 'error' => 'Upstream service error', 'http' => 200];
        }

        $items = $this->toItems($conn);
        usort($items, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return [
            'ok'          => true,
            'items'       => $items,
            'hasNextPage' => (bool) ($conn['pageInfo']['hasNextPage'] ?? count($items) >= $limit),
        ];
    }

    /**
     * Fetch a single Programme by ID (for label hydration on existing selections).
     *
     * @return array{ok:bool,item?:array{value:string,label:string}|null,error?:string|null,http?:int}
     */
    public function fetchById(string $id): array
    {
        $endpoint = trim((string) Environment::getEnv('OP_APPLICATIONS_GRAPHQL_ENDPOINT'));
        if ($endpoint === '') {
            return ['ok' => true, 'item' => null];
        }

        try {
            $resp = $this->post($endpoint, self::QUERY_BY_ID, ['id' => $id]);
        } catch (\Throwable $e) {
            $this->logger()->error('ProgrammeOptionsService: GraphQL fetchById failed', [
                'endpoint'  => GraphQLHttpClient::redactEndpoint($endpoint),
                'id'        => $id,
                'exception' => get_class($e),
                'error'     => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => 'Upstream service error', 'http' => 200];
        }

        $conn = $resp['data']['readProgrammeInformationPages'] ?? null;
        if (!is_array($conn)) {
            $this->logger()->warning('ProgrammeOptionsService: fetchById returned no usable data', [
                'endpoint' => GraphQLHttpClient::redactEndpoint($endpoint),
                'id'       => $id,
                'errors'   => $resp['errors'] ?? null,
            ]);
            return ['ok' => false, 'error' => 'Upstream service error', 'http' => 200];
        }

        $items = $this->toItems($conn);
        return ['ok' => true, 'item' => $items[0] ?? null];
    }

    private function post(string $endpoint, string $query, array $variables = []): array
    {
        $headers = [];
        $user = (string) (Environment::getEnv('OP_APPLICATIONS_GRAPHQL_BASIC_USER') ?: '');
        $pass = (string) (Environment::getEnv('OP_APPLICATIONS_GRAPHQL_BASIC_PASS') ?: '');
        if ($user !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode("{$user}:{$pass}");
        }
        return (new GraphQLHttpClient(15))->post($endpoint, $query, $variables, $headers);
    }

    /** @return array<int,array{value:string,label:string}> */
    private function toItems(array $connection): array
    {
        $rows = [];
        if (!empty($connection['nodes']) && is_array($connection['nodes'])) {
            $rows = $connection['nodes'];
        } elseif (!empty($connection['edges']) && is_array($connection['edges'])) {
            foreach ($connection['edges'] as $edge) {
                if (is_array($edge['node'] ?? null)) {
                    $rows[] = $edge['node'];
                }
            }
        }

        $items = [];
        foreach ($rows as $n) {
            $id    = isset($n['id']) ? (string) $n['id'] : null;
            $title = isset($n['title']) ? trim((string) $n['title']) : '';
            if ($id && $title !== '') {
                $items[] = ['value' => $id, 'label' => $title];
            }
        }
        return $items;
    }
}
