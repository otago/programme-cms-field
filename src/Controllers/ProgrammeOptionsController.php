<?php

namespace Otago\ProgrammeCmsField\Controllers;

use Otago\ProgrammeCmsField\Services\ProgrammeOptionsService;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;

/**
 * Admin-only proxy that exposes ProgrammeOptionsService as a JSON API.
 *
 * Route: /admin/programme-options/list
 *
 * Query params:
 *   q       – search term (title contains)
 *   id      – fetch a single programme by ID (label hydration)
 *   limit   – page size (default 25)
 *   offset  – pagination offset (default 0)
 *
 * Response:
 *   { "items": [{"value":"123","label":"Programme Name"},...], "hasNextPage": false, "error": null }
 */
class ProgrammeOptionsController extends Controller
{
    private static $allowed_actions = ['list'];

    private static $url_handlers = ['list' => 'list'];

    protected function init()
    {
        parent::init();
        if (!Permission::check('CMS_ACCESS')) {
            $this->httpError(403, 'Forbidden');
        }
    }

    public function list(HTTPRequest $request): HTTPResponse
    {
        $q      = trim((string) ($request->getVar('q') ?? ''));
        $id     = trim((string) ($request->getVar('id') ?? ''));
        $limit  = max(1, (int) ($request->getVar('limit') ?? 25));
        $offset = max(0, (int) ($request->getVar('offset') ?? 0));

        $svc = ProgrammeOptionsService::singleton();

        if ($id !== '') {
            $one    = $svc->fetchById($id);
            $status = $one['ok'] ? 200 : ($one['http'] ?? 500);
            $item   = $one['item'] ?? null;
            return $this->json(
                [
                    'items'       => $item ? [$item] : [],
                    'hasNextPage' => false,
                    'error'       => $one['error'] ?? null,
                ],
                $status,
            );
        }

        $result = $svc->fetch($q, $limit, $offset);
        $status = $result['ok'] ? 200 : ($result['http'] ?? 500);
        return $this->json(
            [
                'items'       => $result['items'] ?? [],
                'hasNextPage' => (bool) ($result['hasNextPage'] ?? false),
                'error'       => $result['error'] ?? null,
            ],
            $status,
        );
    }

    private function json(array $payload, int $status = 200): HTTPResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $res  = HTTPResponse::create($body, $status);
        $res->addHeader('Content-Type', 'application/json; charset=utf-8');
        return $res;
    }
}
