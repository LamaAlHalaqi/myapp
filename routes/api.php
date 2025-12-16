<?php

use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Endpoint عام للاختبار
Route::get('/test', function () {
    return response()->json(['message' => 'API is working in Laravel 12!']);
});

Route::get('/test', function () {
    return response()->json(['server' => request()->server('SERVER_PORT')]);
});

    Route::post('register', [UserController::class, 'register']);
    Route::post('verify_otp', [UserController::class, 'verify']);
    Route::post('login', [UserController::class, 'login']);
    Route::get('agencies', [ComplaintController::class, 'getAgencies']);
    Route::post('admin/login', [AdminController::class, 'adminLogin']); // تسجيل دخول خاص للأدمن
Route::middleware('auth:sanctum')->group(function(){
    // تسجيل الخروج
    Route::post('/logout', [UserController::class, 'logout']);

    // الشكاوى العامة
Route::post('complaints', [ComplaintController::class,'submit']);
Route::post('complaints/{id}/lock', [ComplaintController::class,'lock']);
    Route::post('complaints/{id}/unlock', [ComplaintController::class,'unlock']);
    Route::post('complaints/{id}/status', [ComplaintController::class,'updateStatus']);
    Route::get('complaints', [ComplaintController::class,'allComplaints']);
     // الموظفون: عرض الشكاوى الخاصة بجهتهم
 Route::get('agency/complaints', [ComplaintController::class,'getAgencyComplaints']);
 Route::get('user/complaints', [ComplaintController::class,'getUserComplaints']);
 Route::delete('complaints/{id}', [ComplaintController::class,'deleteComplaint']);
// تفاصيل الشكوى مع الملاحظات والطلبات
    Route::get('complaints/{id}', [ComplaintController::class,'getComplaintDetails']);

    // الملاحظات والطلبات (للموظفين والمستخدمين)
    Route::post('complaints/{id}/notes', [ComplaintController::class,'addNote']);
    Route::post('complaints/{id}/request-information', [ComplaintController::class,'requestInformation']);
    Route::post('information-requests/{requestId}/respond', [ComplaintController::class,'respondToInformationRequest']);


});






// ============ Endpoints الإدارة (محمية بـ auth:sanctum فقط، والتحقق من role في Controller) ============
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function(){
    // إدارة الشكاوى
    Route::get('/showcomplaints', [AdminController::class, 'indexComplaints'])->name('admin.complaints');

    // إدارة الموظفين والمستخدمين
    Route::post('/employees', [AdminController::class, 'createEmployee'])->name('admin.create_employee');
    Route::get('/employees', [AdminController::class, 'manageEmployees'])->name('admin.manage_employees');
    Route::get('/users', [AdminController::class, 'manageUsers'])->name('admin.manage_users');

    // الإحصائيات والسجلات
    Route::get('/statistics', [AdminController::class, 'viewStatistics'])->name('admin.statistics');

    // تصدير التقارير
    Route::get('/export-reports', [AdminController::class, 'exportReports'])->name('admin.export_reports');
});
use App\Http\Controllers\AgencyController;

Route::middleware('auth:sanctum')->group(function () {
  //  Route::get('/agencies', [AgencyController::class, 'index']); // عرض الجهات
    Route::post('/agencies', [AgencyController::class, 'store']); // إضافة جهة
    Route::put('/agencies/{id}', [AgencyController::class, 'update']); // تعديل جهة
    Route::delete('/agencies/{id}', [AgencyController::class, 'destroy']); // حذف جهة
});
