<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\ComplaintNote;
use App\Models\InformationRequest;
use App\Repositories\ComplaintRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class ComplaintController extends Controller
{
    protected $repo;
    protected $lockDurationMinutes = 15; // المهلة15 دقائق


    public function __construct(ComplaintRepository $repo)
    {
        $this->repo = $repo;
    }

    // تقديم شكوى جديدة
    public function submit(Request $request)
    {
        $request->validate([
            'agency_id' => 'required|exists:agencies,id',
            'type' => 'nullable|in:خدمة,مرفق,سلوك,آخر', 'location' => 'required|in:دمشق,حلب,درعا,حمص,الرقة,دير الزور,اللاذقية,طرطوس,ادلب,السويداء,القنيطرة,الحسكة,ريف دمشق,حماة',
            'description' => 'required|string',
            'attachments.*' => 'file|mimes:jpg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        $reference = 'REF-' . strtoupper(Str::random(10));

        $attachmentsResponse = [];

        DB::transaction(function () use ($request, $reference, &$attachmentsResponse) {

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

                    $attachment = $complaint->attachments()->create([
                        'path' => $path,
                        'mime' => $file->getClientMimeType()
                    ]);

                    $attachmentsResponse[] = [
                        'id' => $attachment->id,
                        'mime' => $attachment->mime,
                        'url' => asset('storage/' . $path)
                    ];
                }
            }

            Cache::forget("user_complaints_" . auth()->id());
        });

        return response()->json([
            'message' => 'تم تقديم الشكوى بنجاح',
            'reference' => $reference,
            'attachments' => $attachmentsResponse
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
          if (auth()->user()->role !== 'employee') {
        return response()->json(['message' => 'ليس لديك صلاحية لتغيير حالة الشكوى'], 403);
    }
        $request->validate([
            'status' => 'required|in:new,in_progress,done,rejected',
            'note' => 'nullable|string'
        ]);

        $complaint = $this->repo->findById($id);
        $this->checkLockTimeout($complaint);

        // منع تعديل الشكوى إذا محجوزة من موظف آخر
        if ($complaint->is_locked && $complaint->locked_by !== auth()->id()) {
            return response()->json([
                'message' => 'لا يمكن تعديل الشكوى لأنها قيد المعالجة من قبل موظف آخر'
            ], 409);
        }

        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }
             if (auth()->user()->role === 'employee' && $complaint->agency_id !== auth()->user()->agency_id) {
        return response()->json(['message' => 'لا يمكنك تعديل شكوى ليست ضمن جهتك'], 403);
    }


        $this->repo->updateStatus($complaint, $request->status);

        // حفظ الملاحظة إذا وُجدت
        if ($request->has('note') && $request->note) {
            ComplaintNote::create([
                'complaint_id' => $complaint->id,
                'user_id' => auth()->id(),
                'note' => $request->note,
                'type' => 'user_visible' // الملاحظة مرئية للمستخدم
            ]);
        }

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

    // عرض تفاصيل الشكوى مع الملاحظات والطلبات
    public function getComplaintDetails($id)
    {
        $complaint = Complaint::with(['attachments', 'logs', 'notes', 'informationRequests'])
            ->find($id);

        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }

        // التحقق من أن المستخدم صاحب الشكوى أو موظف/أدمن
        if ($complaint->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            if (auth()->user()->role === 'employee' && $complaint->agency_id !== auth()->user()->agency_id) {
                return response()->json(['message' => 'ليس لديك صلاحية الوصول'], 403);
            }
        }

        // إضافة رابط العرض وتصنيف نوع المرفق
        if ($complaint->attachments) {
            $complaint->attachments->transform(function($attachment) {
                $attachment->url = asset('storage/' . $attachment->path);

                if(str_contains($attachment->mime, 'image/')) {
                    $attachment->type = 'image';
                } elseif(str_contains($attachment->mime, 'pdf')) {
                    $attachment->type = 'pdf';
                } elseif(str_contains($attachment->mime, 'word') || str_contains($attachment->mime, 'excel') || str_contains($attachment->mime, 'msword') || str_contains($attachment->mime, 'vnd.openxmlformats-officedocument')) {
                    $attachment->type = 'document';
                } else {
                    $attachment->type = 'other';
                }

                return $attachment;
            });
        }

        return response()->json([
            'message' => 'تفاصيل الشكوى',
            'data' => $complaint
        ]);
    }


    // إضافة ملاحظة (للموظفين فقط)
    public function addNote(Request $request, $id)
    {
        // تحقق من أن المستخدم موظف أو admin
        if (auth()->user()->role !== 'employee' && auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'فقط الموظفون يمكنهم إضافة ملاحظات'], 403);
        }

        $request->validate([
            'note' => 'required|string',
            'type' => 'nullable|in:internal,user_visible'
        ]);

        $complaint = Complaint::find($id);
        $this->checkLockTimeout($complaint);

        // منع إضافة ملاحظة إذا الشكوى محجوزة من موظف آخر
        if ($complaint->is_locked && $complaint->locked_by !== auth()->id()) {
            return response()->json([
                'message' => 'لا يمكنك إضافة ملاحظة لأن الشكوى قيد المعالجة من موظف آخر'
            ], 409);
        }

        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }

        // للموظفين: تحقق من أنهم موظفون في نفس الجهة
        if (auth()->user()->role === 'employee' && $complaint->agency_id !== auth()->user()->agency_id) {
            return response()->json(['message' => 'لا يمكنك إضافة ملاحظات لشكوى جهة أخرى'], 403);
        }

        $note = ComplaintNote::create([
            'complaint_id' => $complaint->id,
            'user_id' => auth()->id(),
            'note' => $request->note,
            'type' => $request->input('type', 'internal')
        ]);

        return response()->json([
            'message' => 'تم إضافة الملاحظة بنجاح',
            'data' => $note
        ], 201);
    }

    // طلب معلومات إضافية (للموظفين فقط)
    public function requestInformation(Request $request, $id)
    {
        // تحقق من أن المستخدم موظف أو admin
        if (auth()->user()->role !== 'employee' && auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'فقط الموظفون يمكنهم طلب معلومات'], 403);
        }

        $request->validate([
            'request_message' => 'required|string'
        ]);

        $complaint = Complaint::find($id);
        $this->checkLockTimeout($complaint);

        // منع طلب معلومات إذا الشكوى محجوزة من موظف آخر
        if ($complaint->is_locked && $complaint->locked_by !== auth()->id()) {
            return response()->json([
                'message' => 'لا يمكنك طلب معلومات لأن الشكوى قيد المعالجة من موظف آخر'
            ], 409);
        }

        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }

        // للموظفين: تحقق من أنهم موظفون في نفس الجهة
        if (auth()->user()->role === 'employee' && $complaint->agency_id !== auth()->user()->agency_id) {
            return response()->json(['message' => 'لا يمكنك طلب معلومات لشكوى جهة أخرى'], 403);
        }

        $infoRequest = InformationRequest::create([
            'complaint_id' => $complaint->id,
            'requested_by' => auth()->id(),
            'request_message' => $request->request_message,
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'تم طلب المعلومات بنجاح',
            'data' => $infoRequest
        ], 201);
    }

    // رد المستخدم على طلب معلومات
    public function respondToInformationRequest(Request $request, $requestId)
    {
        $infoRequest = InformationRequest::find($requestId);

        if (!$infoRequest) {
            return response()->json(['message' => 'الطلب غير موجود'], 404);
        }

        // تحقق من أن المستخدم صاحب الشكوى
        if ($infoRequest->complaint->user_id !== auth()->id()) {
            return response()->json(['message' => 'ليس لديك صلاحية الرد على هذا الطلب'], 403);
        }

        $request->validate([
            'user_response' => 'required|string'
        ]);

        $infoRequest->update([
            'user_response' => $request->user_response,
            'status' => 'answered',
            'answered_at' => now()
        ]);

        return response()->json([
            'message' => 'تم إرسال الرد بنجاح',
            'data' => $infoRequest
        ]);
    }

    // الموظفون: عرض الشكاوى الخاصة بجهتهم فقط
    public function getAgencyComplaints()
    {
        // التحقق من أن المستخدم موظف
        if (auth()->user()->role !== 'employee') {
            return response()->json(['message' => 'هذا الـ endpoint للموظفين فقط'], 403);
        }

        $complaints = Complaint::where('agency_id', auth()->user()->agency_id)
                             ->with(['user', 'attachments', 'logs', 'notes', 'informationRequests'])
                             //->latest()
                             ->get();

        return response()->json([
            'message' => 'الشكاوى الخاصة بجهتك',
            'data' => $complaints
        ]);
    }

    public function getUserComplaints()
{
    // تأكدي أن المستخدم له صلاحية (اختياري حسب نظامك)
    if (auth()->user()->role !== 'user') {
        return response()->json(['message' => 'هذا الـ endpoint للمستخدمين فقط'], 403);
    }

    $complaints = Complaint::where('user_id', auth()->id())
                            ->with(['attachments', 'logs', 'notes', 'informationRequests'])
                            ->get();

    return response()->json([
        'message' => 'الشكاوى الخاصة بك',
        'data' => $complaints
    ]);
}
    public function deleteComplaint($id)
    {
        $user = auth()->user();

        // جلب الشكوى
        $complaint = Complaint::with('attachments')->find($id);

        if (!$complaint) {
            return response()->json(['message' => 'الشكوى غير موجودة'], 404);
        }

        // صلاحيات الحذف
        if ($user->role === 'user') {
            if ($complaint->user_id !== $user->id) {
                return response()->json(['message' => 'غير مسموح بحذف شكوى لا تخصك'], 403);
            }
        } elseif ($user->role === 'employee') {
            return response()->json(['message' => 'ليس لديك صلاحية لحذف الشكاوى'], 403);
        }
        // admin يمكنه الحذف

        DB::transaction(function () use ($complaint) {
            // حذف المرفقات من التخزين وقاعدة البيانات
            foreach ($complaint->attachments as $attachment) {
                if (Storage::exists($attachment->path)) {
                    Storage::delete($attachment->path);
                }
                $attachment->delete();
            }

            // حذف الشكوى نفسها
            $complaint->delete();
        });

        return response()->json([
            'message' => 'تم حذف الشكوى بنجاح'
        ]);
    }



        public function getAgencies()
    {
        $agencies = DB::table('agencies')->select('id', 'name')->get();
        return response()->json($agencies);
    }
    protected function checkLockTimeout(Complaint $complaint)
    {
        if ($complaint->is_locked && $complaint->locked_at) {
            $lockedAt = $complaint->locked_at;
            if (now()->diffInMinutes($lockedAt) >= $this->lockDurationMinutes) {
                // انتهاء المهلة → فك القفل تلقائياً
                $complaint->update([
                    'is_locked' => false,
                    'locked_by' => null,
                    'locked_at' => null
                ]);
            }
        }
    }

}
