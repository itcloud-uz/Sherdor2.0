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

        $rawRoles = $user->roles ?? collect();
        $roles = $rawRoles->pluck('name')->toArray();
        $isSuper = in_array('Super_Admin', $roles) || in_array('Admin', $roles);

        if (!$isSuper) {
            $routeName = $request->route()->getName();

            // 1. Dashboard
            if ($routeName === 'admin.dashboard' && !$user->hasPermissionTo('access dashboard')) {
                abort(404, 'Sahifa topilmadi');
            }

            // 2. Financial Reports (Hisobotlar)
            if ($routeName === 'admin.financial.reports' && !$user->hasPermissionTo('access reports')) {
                abort(404, 'Sahifa topilmadi');
            }

            // 3. Facility Manager / Objects
            if ($routeName === 'admin.facility' && !$user->hasPermissionTo('access objects')) {
                abort(404, 'Sahifa topilmadi');
            }

            // 4. Finance Core / Kirim-chiqim / Debtors
            if (str_starts_with($routeName, 'admin.finance')) {
                $tab = $request->query('tab');
                // Jurnal & Tranzaksiya -> Kirim-chiqim
                if (($tab == 2 || $tab == 3) && !$user->hasPermissionTo('access transactions')) {
                    abort(404, 'Sahifa topilmadi');
                }
                // Qarzlar -> Qarzdorlar
                if ($tab == 4 && !$user->hasPermissionTo('access debtors')) {
                    abort(404, 'Sahifa topilmadi');
                }
                // General Finance access
                if (!$user->hasPermissionTo('access finance')) {
                    abort(404, 'Sahifa topilmadi');
                }
            }

            // 5. CRM / Clients / Leads
            if (str_starts_with($routeName, 'admin.crm')) {
                $tab = $request->query('tab');
                // Debtors Radar or Client Debtors -> Qarzdorlar
                if (($tab === 'overdue' || $tab === 'clients_debtors') && !$user->hasPermissionTo('access debtors')) {
                    abort(404, 'Sahifa topilmadi');
                }
                // General CRM access
                if (!$user->hasPermissionTo('access clients')) {
                    abort(404, 'Sahifa topilmadi');
                }
            }

            // 6. HR / Payroll
            if (str_starts_with($routeName, 'admin.hr') && !$user->hasPermissionTo('access hr')) {
                abort(404, 'Sahifa topilmadi');
            }

            // 7. Settings
            if (str_starts_with($routeName, 'admin.settings') && !$user->hasPermissionTo('access settings')) {
                abort(404, 'Sahifa topilmadi');
            }
        }

        if (in_array('Super_Admin', $roles) || in_array('Admin', $roles)) {
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
