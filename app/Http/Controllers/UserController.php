<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{

   public function register(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8',

    ]);

    $otp = rand(100000, 999999);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
         'role' => 'user',
        'otp' => $otp,
    ]);

    Mail::raw("رمز التحقق الخاص بك هو: $otp", function ($message) use ($user) {
        $message->to($user->email)
                ->subject('رمز التحقق من البريد الإلكتروني');
    });

    return response()->json([
        'message' => 'تم إنشاء الحساب بنجاح، تم إرسال رمز التحقق إلى بريدك الإلكتروني.',

    ], 201);
}


 public function verify(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required'
    ]);

    $user = User::where('email', $request->email)
                ->where('otp', $request->otp)
                ->first();

    if (!$user) {
        return response()->json(['message' => 'رمز التحقق غير صحيح أو منتهي.'], 400);
    }

    // تحديث حالة الحساب
    $user->update([
        'is_verified' => true,
        'email_verified_at' => now(),
        'otp' => null,
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'تم التحقق من الحساب بنجاح ✅',
        'access_token' => $token,
        'role' => $user->role,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ]);
}
 public function login(Request $request)
    {
        // ✅ التحقق من صحة البيانات
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);


        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'بيانات تسجيل الدخول غير صحيحة'
            ], 401);
        }

        // ✅ جلب المستخدم الحالي بعد التحقق
        $user = Auth::user();

        // تحقق من تفعيل الحساب
        if (!$user->is_verified) {
            // تسجيل الخروج في حال الحساب غير مفعل
            Auth::logout();

            return response()->json([
                'message' => 'الحساب غير مفعّل. يرجى إدخال رمز التفعيل المرسل إلى بريدك الإلكتروني.'
            ], 403);
        }

        // إنشاء توكن باستخدام Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
        ]);
    }

     public function logout(Request $request)
{
    $user = $request->user(); // المستخدم الحالي

    if (!$user) {
        return response()->json([
            'message' => 'المستخدم غير مسجل دخول.'
        ], 401);
    }

    // حذف التوكن الحالي فقط
    $user->currentAccessToken()->delete();

    return response()->json([
        'message' => 'تم تسجيل الخروج بنجاح.'
    ], 200);
}

}
