<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditRequest
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->isMethodSafe() || $response->isClientError() || $response->isServerError()) {
            return $response;
        }

        $tenant = $request->attributes->get('tenant');
        $user = $request->user();

        $route = $request->route();
        $controllerClass = method_exists($route, 'getControllerClass') ? $route->getControllerClass() : null;

        $this->auditLogService->record([
            'tenant_id' => $tenant?->id,
            'user_id' => $user?->id,
            'action' => sprintf('%s %s', $request->method(), $request->path()),
            'auditable_type' => $controllerClass ?? 'request',
            'auditable_id' => null,
            'payload' => [
                'request' => $request->except(['password', 'password_confirmation', 'token']),
                'response_status' => $response->getStatusCode(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }
}
