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
            // Redirect admin to admin dashboard if they try to access regular user pages
            return redirect()->route('admin.dashboard')->with('error', 'Akses ditolak. Halaman tersebut hanya untuk User.');
        }

        return $next($request);
    }
}
