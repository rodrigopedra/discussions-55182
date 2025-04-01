<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Illuminate\Contracts\Cache\Repository as CacheStore;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IdentifyTenant
{
    public function __construct(
        private readonly CacheStore $cache,
        private readonly Config $config,
        private readonly DatabaseManager $db,
    ) {}

    public function handle(Request $request, \Closure $next)
    {
        $tenantIdentifier = $this->resolveTenant($request);

        if (is_null($tenantIdentifier)) {
            throw new BadRequestHttpException('Tenant identifier required (subdomain or X-Tenant header)');
        }

        $tenant = $this->fetchTenant($tenantIdentifier);

        if (is_null($tenant)) {
            throw new NotFoundHttpException('Tenant not found');
        }

        $this->configureTenant($tenant);

        return $next($request);
    }

    private function resolveTenant(Request $request): ?string
    {
        // Check for X-Tenant header (API clients)
        if ($request->hasHeader('X-Tenant')) {
            return $request->header('X-Tenant');
        }

        // Skip when requesting from an IP
        if (filter_var($request->getHost(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return null;
        }

        // Try to get tenant from subdomain
        $hostParts = explode('.', $request->getHost());

        // Ensure it's a subdomain (e.g., tenant.example.com)
        if (count($hostParts) > 2) {
            return $hostParts[0];
        }

        return null;
    }

    private function fetchTenant(string $tenantIdentifier): ?Tenant
    {
        return $this->cache->remember(
            key: 'tenant_' . $tenantIdentifier,
            ttl: 3600,
            callback: fn () => Tenant::query()->firstWhere(fn (Builder $builder) => $builder
                ->orWhere('subdomain', $tenantIdentifier)
                ->orWhere('db_name', $tenantIdentifier),
            ),
        );
    }

    private function configureTenant(Tenant $tenant): void
    {
        $this->config->set('database.connections.tenant.database', $tenant->db_name);

        $this->db->purge('tenant');
        $this->db->reconnect('tenant');
        $this->db->setDefaultConnection('tenant');
    }
}
