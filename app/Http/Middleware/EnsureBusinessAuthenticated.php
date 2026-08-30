<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBusinessAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('biz_id')) {
            return redirect()->route('business.login');
        }

        if (!$request->routeIs('business.payments.*') && !$request->routeIs('business.logout')) {
            $db = \Database::connectOrNull();
            if ($db && \PaymentSupport::requiresPayment($db, (int) $request->session()->get('biz_id'))) {
                return redirect()->route('business.payments.index');
            }
        }

        return $next($request);
    }
}
