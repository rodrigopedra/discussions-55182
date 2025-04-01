<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenantIdentifier = null;

        // 1️⃣ First, check for X-Tenant header (Postman or API clients)
        if ($request->hasHeader('X-Tenant')) {
            $tenantIdentifier = $request->header('X-Tenant');
        }
        // 2️⃣ Otherwise, try to get tenant from subdomain
        // ADDED: check if it is not an IP, e.g. when using php artisan serve
        elseif (!filter_var($request->getHost(), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 )) {
            $hostParts = explode('.', $request->getHost());
            if (count($hostParts) > 2) { // Ensure it's a subdomain (e.g., tenant.yourdomain.com)
                $tenantIdentifier = $hostParts[0];
            }
        }

        // 3️⃣ Validate tenant identifier
        if (! $tenantIdentifier) {
            // Config::set('database.default', 'master');
            // DB::purge('master');
            // DB::reconnect('master');

            // return $next($request);

            return response()->json(['error' => 'Tenant identifier required (subdomain or X-Tenant header)'], 400);
        }

        // 4️⃣ Fetch tenant from cache or database
        $tenant = Cache::remember("tenant_{$tenantIdentifier}", 3600, function () use ($tenantIdentifier) {
            return Tenant::where('subdomain', $tenantIdentifier)
                ->orWhere('db_name', $tenantIdentifier)
                ->first();
        });

        if (! $tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // 5️⃣ Configure database connection dynamically
        Config::set('database.connections.tenant.database', $tenant->db_name);

        // 6️⃣ Refresh the tenant database connection
        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');

        return $next($request);
    }
}
