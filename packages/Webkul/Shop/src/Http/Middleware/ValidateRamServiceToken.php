<?php

namespace Webkul\Shop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates RAM service token for server-to-server API calls.
 *
 * Uses Bearer token authentication against shared service token.
 * Stateless - does not require session or user authentication.
 *
 * @see WI #192, #159
 */
class ValidateRamServiceToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        if (!$this->isValidToken($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing service token',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Extract Bearer token from Authorization header.
     */
    protected function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate token against configured service token.
     */
    protected function isValidToken(?string $token): bool
    {
        if (!$token) {
            return false;
        }

        $serviceToken = config('services.ram.service_token');

        if (!$serviceToken) {
            return false;
        }

        return hash_equals($serviceToken, $token);
    }
}
