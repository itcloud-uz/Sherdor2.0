<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BotApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-BOT-TOKEN');
        $validToken = config('services.bot.secret_key', env('BOT_SECRET_KEY', 'default_secret_key_for_dev'));

        if (!$token || $token !== $validToken) {
            return response()->json(['error' => 'Unauthorized. Invalid Bot Token.'], 401);
        }

        return $next($request);
    }
}
