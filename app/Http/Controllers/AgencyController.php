<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgencyController extends Controller
{
    // عرض جميع الجهات
    /*public function index()
    {
        $agencies = DB::table('agencies')->get();
        return response()->json([
            'message' => 'قائمة الجهات',
            'data' => $agencies
        ]);
    }*/

    // إضافة جهة جديدة
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'ليس لديك صلاحية'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:agencies,name',
        ]);

        $id = DB::table('agencies')->insertGetId([
            'name' => $request->name,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'message' => 'تم إضافة الجهة بنجاح',
            'agency_id' => $id
        ], 201);
    }

    // تعديل جهة
    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'ليس لديك صلاحية'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:agencies,name,' . $id,
        ]);

        $updated = DB::table('agencies')->where('id', $id)
            ->update([
                'name' => $request->name,
                'updated_at' => now()
            ]);

        if (!$updated) {
            return response()->json(['message' => 'الجهة غير موجودة'], 404);
        }

        return response()->json(['message' => 'تم تعديل الجهة بنجاح']);
    }

    // حذف جهة
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'ليس لديك صلاحية'], 403);
        }

        $deleted = DB::table('agencies')->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json(['message' => 'الجهة غير موجودة'], 404);
        }

        return response()->json(['message' => 'تم حذف الجهة بنجاح']);
    }
}
