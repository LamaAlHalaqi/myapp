<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class AdminController extends Controller
{
    /**
     * تسجيل دخول الأدمن
     */
    public function adminLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // محاولة تسجيل الدخول
        if (!Auth::attempt($validated)) {
            return response()->json([
                'message' => 'بيانات تسجيل الدخول غير صحيحة'
            ], 401);
        }

        $admin = Auth::user();

        // تحقق من أن المستخدم أدمن
        if ($admin->role !== 'admin') {
            Auth::logout();
            return response()->json([
                'message' => 'هذا الحساب ليس حساب إدارة. فقط حسابات الأدمن يمكنها الوصول.'
            ], 403);
        }

        // تحقق من تفعيل الحساب
        if (!$admin->is_verified) {
            Auth::logout();
            return response()->json([
                'message' => 'حساب الأدمن غير مفعّل'
            ], 403);
        }

        // إنشاء التوكن
        $token = $admin->createToken('admin_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل دخول الأدمن بنجاح ✅',
            'user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
            'access_token' => $token,
        ]);
    }

    /**
     * عرض جميع الشكاوى مع إمكانية الفلترة والبحث
     */
    public function indexComplaints(Request $request)
    {
        // التحقق من أن المستخدم أدمن
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول'], 403);
        }

        $query = Complaint::with('user')->latest();

        // فلترة حسب الحالة (status)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // بحث في النص
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        $complaints = $query->get();

        return response()->json([
            'message' => 'قائمة الشكاوى',
            'data' => $complaints,
        ]);
    }

    /**
     * إنشاء حساب موظف جديد وتحديد صلاحياته والجهة التي ينتمي إليها
     */
    public function createEmployee(Request $request)
    {
        // التحقق من أن المستخدم أدمن
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:employee,admin',
            'agency_id' => 'required|exists:agencies,id', // إلزامي لتحديد الجهة
        ]);

        $employee = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'agency_id' => $validated['agency_id'], // ربط الموظف بالجهة
            'is_verified' => true, // الموظفون المُنشأون من قبل الأدمن مفعّلون مباشرة
        ]);

        return response()->json([
            'message' => 'تم إنشاء حساب الموظف بنجاح',
            'data' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'email' => $employee->email,
                'role' => $employee->role,
                'agency_id' => $employee->agency_id,
                'is_verified' => $employee->is_verified,
            ],
        ], 201);
    }

     public function manageEmployees(Request $request)
    {
        // التحقق من أن المستخدم أدمن
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول'], 403);
        }

        $action = $request->query('action'); // عرض أو تعديل
        $userId = $request->query('user_id');

        // عرض جميع الموظفين
        if (!$action) {
            $users = User::select('id', 'name', 'email', 'role', 'is_verified', 'created_at')
                         ->where('role', 'employee')
                         ->get();

            return response()->json([
                'message' => 'قائمة الموظفين',
                'data' => $users,
            ]);
        }
    }
    /**
     * إدارة حسابات المستخدمين (عرض، تفعيل، تعطيل، حذف)
     */
    public function manageUsers(Request $request)
    {
        // التحقق من أن المستخدم أدمن
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول'], 403);
        }

        $action = $request->query('action'); // عرض أو تعديل
        $userId = $request->query('user_id');

        // عرض جميع المستخدمين
        if (!$action) {
            $users = User::select('id', 'name', 'email', 'role', 'is_verified', 'created_at')
                         ->where('role', 'user')
                         ->get();

            return response()->json([
                'message' => 'قائمة المستخدمين',
                'data' => $users,
            ]);
        }

        // تفعيل / تعطيل المستخدم
        if ($action === 'toggle_verify') {
            $user = User::findOrFail($userId);
            $user->update(['is_verified' => !$user->is_verified]);

            return response()->json([
                'message' => 'تم تحديث حالة المستخدم',
                'data' => $user,
            ]);
        }

        // حذف المستخدم
        if ($action === 'delete') {
            $user = User::findOrFail($userId);
            $user->delete();

            return response()->json([
                'message' => 'تم حذف المستخدم بنجاح',
            ]);
        }

        return response()->json(['message' => 'إجراء غير معروف'], 400);
    }



    /**
     * عرض الإحصائيات والسجلات
     */
    public function viewStatistics(Request $request)
{
    // التحقق من الصلاحية
    if (auth()->user()->role !== 'admin') {
        return response()->json(['message' => 'ليس لديك صلاحية للوصول'], 403);
    }

    $stats = [
        'total_users'        => User::count(),
        'verified_users'     => User::where('is_verified', true)->count(),
        'unverified_users'   => User::where('is_verified', false)->count(),
        'total_complaints'   => Complaint::count(),
        'pending_complaints' => Complaint::where('status', 'in_progress')->count(),
        'resolved_complaints'=> Complaint::where('status', 'done')->count(),
        'users_by_role'      => User::selectRaw('role, count(*) as count')
                                    ->groupBy('role')
                                    ->get(),
    ];

    return response()->json([
        'message' => 'الإحصائيات والسجلات',
        'data'    => $stats,
    ]);
}



/**
 * 🔥 تصدير التقارير (CSV + JSON) بكفاءة عالية
 */
public function exportReports(Request $request)
{
    // الصلاحية
    if (auth()->user()->role !== 'admin') {
        return response()->json(['message' => 'ليس لديك صلاحية للوصول'], 403);
    }

    $type        = $request->query('type', 'json');       // json أو csv
    $report_type = $request->query('report', 'complaints'); // complaints أو users

    /**
     * 1) ------------------------ JSON EXPORT ------------------------
     */
    if ($type === 'json') {

        if ($report_type === 'complaints') {
            $data = Complaint::with('user')->get();
        } else {
            $data = User::all();
        }

        return response()->json([
            'message' => 'تم إنشاء التقرير',
            'report_type' => $report_type,
            'data' => $data
        ]);
    }


    /**
     * 2) ------------------------ CSV EXPORT ------------------------
     */
    if ($type === 'csv') {

        $filename = $report_type . '_report_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($report_type) {
            $filename = "users.csv";
$headers = [
    "Content-Type" => "text/csv; charset=UTF-8",
    "Content-Disposition" => "attachment; filename=\"$filename\"",
];


            // فتح إخراج الملف المباشر
            $handle = fopen('php://output', 'w');

            // كتابة BOM لتحسين العربية في Excel
            fwrite($handle, "\xEF\xBB\xBF");

            /**
             * --- تقرير الشكاوى ---
             */
            if ($report_type === 'complaints') {

                // رؤوس الأعمدة
                fputcsv($handle, ['ID','الموضوع','الوصف','المستخدم','الحالة','التاريخ']);

                Complaint::with('user')->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->title,
                            $row->description,
                            optional($row->user)->name,
                            $row->status,
                            $row->created_at->toDateTimeString()
                        ],';');
                    }
                });

            }
            /**
             * --- تقرير المستخدمين ---
             */
            else {

                fputcsv($handle, ['ID','name','email','role','verified?'], ',');

                User::chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->name,
                            $row->email,
                            $row->role,
                            $row->is_verified ? 'نعم' : 'لا',
                          //  $row->created_at->toDateTimeString()
                        ],',');
                    }
                });
            }


            fclose($handle);


        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    return response()->json(['message' => 'نوع التصدير غير مدعوم'], 400);
}
}
