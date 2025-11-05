<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetJsonCharset
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Если ответ JSON, устанавливаем charset=utf-8
        if ($response instanceof \Illuminate\Http\JsonResponse || 
            $request->expectsJson() ||
            $request->is('api/*')) {
            
            $contentType = $response->headers->get('Content-Type');
            if (str_contains($contentType ?? '', 'application/json')) {
                $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            }
        }

        return $response;
    }
}

