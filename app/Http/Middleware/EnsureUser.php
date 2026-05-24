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
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (auth()->user()->role !== 'user') {
            if (auth()->user()->role === 'admin') {
                return redirect()->route('admin.dashboard')->with('error', 'Akses ditolak. Halaman tersebut hanya untuk User.');
            }
            abort(403, 'Akses ditolak. Halaman ini hanya untuk User.');
        }

        return $next($request);
    }
}
