<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->role !== 'user') {
            if (auth()->check() && auth()->user()->role === 'admin') {
                return redirect()->route('admin.dashboard')->with('error', 'Admin tidak diperbolehkan mengakses halaman User.');
            }
            abort(403, 'Akses ditolak. Halaman ini hanya untuk User.');
        }

        return $next($request);
    }
}
