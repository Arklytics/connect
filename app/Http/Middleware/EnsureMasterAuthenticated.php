<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMasterAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('master_id')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
