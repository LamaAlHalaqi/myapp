<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{

    public function handle(Request $request, Closure $next, string $role)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'غير مصرح بالدخول.'], 401);
        }

        // إذا الدور غير مطابق، منع الوصول
        if ($user->role !== $role) {
            return response()->json(['message' => 'ليس لديك الصلاحية للوصول.'], 403);
        }

        return $next($request);
    }
}
