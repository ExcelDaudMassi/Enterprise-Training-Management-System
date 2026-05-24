<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
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

        if (auth()->user()->role !== 'admin') {
            if (auth()->user()->role === 'user') {
                return redirect()->route('user.dashboard')->with('error', 'Akses ditolak. Halaman tersebut hanya untuk Admin.');
            }
            abort(403, 'Akses ditolak. Halaman ini hanya untuk Admin.');
        }

        return $next($request);
    }
}
