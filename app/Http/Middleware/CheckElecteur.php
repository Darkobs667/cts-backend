<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckElecteur
{
    /**
     * Restrict voter-only endpoints using the authenticated JWT user.
     * Never rely on a role submitted by the client.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if (!$user || $user->role !== 'electeur') {
            return response()->json([
                'error' => 'Accès réservé aux électeurs.',
            ], 403);
        }

        return $next($request);
    }
}
