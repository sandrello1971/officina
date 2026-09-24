<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404 sulle rotte dei moduli che l'ente corrente non ha (config/modules.php).
 * Agganciato al NOME di rotta, non al path: admin e learn stanno alla radice.
 */
class EnforceTenantModules
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = (string) ($request->route()?->getName() ?? '');

        if ($name !== '' && ($module = self::moduleFor($name)) && ! module_enabled($module)) {
            abort(404);
        }

        return $next($request);
    }

    public static function moduleFor(string $routeName): ?string
    {
        foreach (config('modules.routes', []) as $module => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return $module;
                }
            }
        }

        return null;
    }
}
