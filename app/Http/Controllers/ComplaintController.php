<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Repositories\ComplaintRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    protected $repo;

    public function __construct(ComplaintRepository $repo)
    {
        $this->repo = $repo;
    }

    // تقديم شكوى جديدة
    public function submit(Request $request)
    {
        // Validation
        $request->validate([
            'agency_id' => 'required|exists:agencies,id',
            'type' => 'nullable|in:خدمة,مرفق,سلوك,آخر',
            'location' => 'required|in:دمشق,حلب,درعا,حمص,الرقة,دير الزور,اللاذقية,طرطوس,ادلب,السويداء,القنيطرة,الحسكة,ريف دمشق,حماة',
            'description' => 'required|string',
            'attachments.*' => 'file|mimes:jpg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        // توليد رقم مرجعي آمن
        $reference = 'REF-' . strtoupper(Str::random(10));

        // إنشاء الشكوى
        $complaint = $this->repo->createComplaint([
            'user_id' => auth()->id(),
            'agency_id' => $request->agency_id,
            'type' => $request->type,
            'location' => $request->location,
            'description' => $request->description,
            'reference' => $reference
        ]);

        // رفع المرفقات
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('complaints', 'public');
                $complaint->attachments()->create([
                    'path' => $path,
                    'mime' => $file->getClientMimeType()
                ]);
            }
        }

        // حذف الكاش لتحديث البيانات
        Cache::forget("user_complaints_" . auth()->id());

        return response()->json([
            'message' => 'تم تقديم الشكوى بنجاح',
            'reference' => $reference
        ], 201);
    }

    // قفل الشكوى
    public function lock($id)
    {
        $complaint = $this->repo->findById($id);
        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }

        if (!$this->repo->lock($complaint)) {
            return response()->json(['message' => 'الشكوى مقفلة بواسطة موظف آخر'], 409);
        }

        return response()->json(['message' => 'تم قفل الشكوى بنجاح'], 200);
    }

    // فتح الشكوى
    public function unlock($id)
    {
        $complaint = $this->repo->findById($id);
        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }

        $this->repo->unlock($complaint);
        return response()->json(['message' => 'تم فتح الشكوى بنجاح'], 200);
    }

    // تعديل حالة الشكوى
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:new,in_progress,done,rejected',
            'note' => 'nullable|string'
        ]);

        $complaint = $this->repo->findById($id);
        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }

        $this->repo->updateStatus($complaint, $request->status);

        // حذف الكاش بعد التحديث
        Cache::forget("user_complaints_" . auth()->id());

        return response()->json(['message' => 'تم تحديث حالة الشكوى بنجاح'], 200);
    }

    // عرض الشكاوى مع Caching
    public function allComplaints()
    {
        $userId = auth()->id();
        $complaints = Cache::remember("user_complaints_{$userId}", 60, function () use ($userId) {
            return Complaint::where('user_id', $userId)
                ->with(['attachments', 'logs'])
                ->get();
        });

        return response()->json($complaints, 200);
    }
}
