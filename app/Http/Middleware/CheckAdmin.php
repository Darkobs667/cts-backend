<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();
        
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'error' => 'Accès refusé. Privilèges administrateur requis.'
            ], 403);
        }
        
        return $next($request);
    }
}