<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleRestrictionsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return $next($request);
        }

        if ($user->hasRole('Operator') || $user->hasRole('Partner')) {
            // Block all DELETE requests
            if ($request->isMethod('DELETE')) {
                abort(403, "Sizda o'chirish huquqi yo'q.");
            }

            // Financial transactions and approvals cannot be modified or approved
            $blockedPostRoutes = [
                'admin.finance.transactions.approve',
                'admin.finance.transactions.reject',
                'admin.approvals.approve',
                'admin.approvals.reject',
            ];
            
            if ($request->isMethod('POST') && in_array($request->route()->getName(), $blockedPostRoutes)) {
                abort(403, "Moliyaviy tranzaksiyalarni tasdiqlash huquqi yo'q.");
            }

            // Block all PUT/PATCH except 'contracts.update'
            if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
                if ($request->route()->getName() !== 'admin.contracts.update') {
                    abort(403, "Siz faqat mijoz shartnomalarini o'zgartira olasiz.");
                }
            }
        }

        return $next($request);
    }
}
