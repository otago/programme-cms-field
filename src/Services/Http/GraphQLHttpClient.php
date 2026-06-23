<?php

namespace Otago\ProgrammeCmsField\Services\Http;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * Minimal GraphQL HTTP client.
 * Uses Guzzle when available, falls back to stream context.
 */
class GraphQLHttpClient
{
    private int $timeout;
    private LoggerInterface $logger;

    public function __construct(int $timeout = 20, ?LoggerInterface $logger = null)
    {
        $this->timeout = $timeout;
        $this->logger  = $logger ?: Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * POST a GraphQL query to an endpoint.
     *
     * @param string               $endpoint
     * @param string               $query
     * @param array<string,mixed>  $variables
     * @param array<string,string> $headers
     * @return array{data?:array,errors?:array}
     * @throws Exception
     */
    public function post(
        string $endpoint,
        string $query,
        array $variables = [],
        array $headers = [],
    ): array {
        $mergedHeaders = array_merge(
            [
                'Accept'           => 'application/json',
                'Content-Type'     => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
            $headers,
        );

        $json  = $this->postJSON($endpoint, ['query' => $query, 'variables' => $variables], $mergedHeaders);
        $assoc = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('GraphQL upstream returned invalid JSON', [
                'endpoint'     => self::redactEndpoint($endpoint),
                'json_error'   => json_last_error_msg(),
                'body_preview' => $this->snippet((string) $json),
            ]);
            throw new Exception('Invalid response from upstream service.');
        }

        return is_array($assoc) ? $assoc : [];
    }

    public static function redactEndpoint(string $url): string
    {
        $p      = @parse_url($url) ?: [];
        $scheme = $p['scheme'] ?? '';
        $host   = $p['host'] ?? '';
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $path   = $p['path'] ?? '';
        if ($scheme && $host) {
            return "{$scheme}://{$host}{$port}{$path}";
        }
        return $host ?: $url;
    }

    private function postJSON(string $url, array $payload, array $headers = []): string
    {
        if (class_exists(\GuzzleHttp\Client::class)) {
            $client = new \GuzzleHttp\Client(['timeout' => $this->timeout]);
            try {
                $res  = $client->post($url, ['headers' => $headers, 'json' => $payload]);
                $code = $res->getStatusCode();
                $body = (string) $res->getBody();
                if ($code < 200 || $code >= 300) {
                    $this->logger->warning('GraphQL upstream returned non-2xx', [
                        'endpoint' => self::redactEndpoint($url),
                        'status'   => $code,
                    ]);
                    throw new Exception('Upstream service returned an error.');
                }
                return $body;
            } catch (\Throwable $e) {
                $this->logger->error('GraphQL upstream request failed (Guzzle)', [
                    'endpoint'  => self::redactEndpoint($url),
                    'exception' => get_class($e),
                    'error'     => $e->getMessage(),
                ]);
                throw new Exception('Upstream service request failed.');
            }
        }

        // Stream context fallback
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = "{$k}: {$v}";
        }
        $ctx    = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headerLines),
                'content'       => json_encode($payload),
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        $line   = $http_response_header[0] ?? '';
        if (!preg_match('#HTTP/\d\.\d\s+(\d{3})#', $line, $m)) {
            throw new Exception('Upstream service returned an invalid response.');
        }
        $code = (int) $m[1];
        if ($code < 200 || $code >= 300) {
            throw new Exception('Upstream service returned an error.');
        }
        return $result ?: '';
    }

    private function snippet(string $body, int $max = 2048): string
    {
        return strlen($body) <= $max ? $body : substr($body, 0, $max) . '…[truncated]';
    }
}
