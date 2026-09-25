<?php

namespace App\Http\Controllers;

use App\Models\CourseApproval;
use App\Models\CourseCategory;
use App\Models\CourseDocument;
use App\Models\CourseRequest;
use App\Models\NotificationOutbox;
use App\Models\OfficerReview;
use App\Models\ProjectType;
use App\Models\RequestStatusHistory;
use App\Services\DepartmentDirectory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use setasign\Fpdi\Fpdi;

class PortalController extends Controller
{
    public function __construct(private readonly DepartmentDirectory $departmentDirectory)
    {
    }

    public function login(Request $request)
    {
        if ($request->session()->has('portal.actor')) {
            return redirect()->route($this->homeRoute($request->session()->get('portal.actor.role', 'user')));
        }

        return view('portal.login');
    }

    public function enter(Request $request)
    {
        $credentials = $request->validate([
            'buasri_id' => 'required|string|max:100',
            'password'  => 'required|string|max:255',
        ]);

        $matched = null;
        foreach (config('course-workflow.actors') as $role => $configured) {
            if (
                is_array($configured)
                && ! blank($configured['password'] ?? null)
                && hash_equals((string) $configured['buasri_id'], $credentials['buasri_id'])
                && hash_equals((string) $configured['password'], $credentials['password'])
            ) {
                $matched = array_merge($configured, ['role' => $role]);
                break;
            }
        }

        if (! $matched) {
            return back()->withInput($request->only('buasri_id'))
                ->withErrors(['buasri_id' => 'Buasri ID หรือรหัสผ่านไม่ถูกต้อง']);
        }

        $request->session()->regenerate();
        $request->session()->put('portal.actor', $matched);

        return redirect()->route($this->homeRoute($matched['role']));
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function index(Request $request, string $role = 'user')
    {
        $query = CourseRequest::query()->with($this->recordRelations());

        if ($role === 'user') {
            $query->where('requester_pers_id', $this->actor($request)['pers_id'])
                  ->orderByDesc('created_at');
        } elseif ($role === 'officer') {
            $query->whereIn('status', ['UNDER_OFFICER_REVIEW', 'PENDING_APPROVAL', 'PENDING_COURSE_ID', 'COURSE_ID_RECORDED', 'REJECTED'])
                  ->orderByRaw("CASE WHEN [status] = 'UNDER_OFFICER_REVIEW' THEN 0 WHEN [status] = 'REJECTED' THEN 2 ELSE 1 END")
                  ->orderByRaw("CASE WHEN [status] = 'UNDER_OFFICER_REVIEW' THEN [created_at] END ASC")
                  ->orderByDesc('created_at');
        } else {
            $query->whereIn('status', ['PENDING_APPROVAL', 'PENDING_COURSE_ID', 'COURSE_ID_RECORDED', 'REJECTED'])
          ->orderByRaw("CASE WHEN [status] = 'PENDING_APPROVAL' THEN 0 WHEN [status] = 'REJECTED' THEN 2 ELSE 1 END")
                  ->orderByRaw("CASE WHEN [status] = 'PENDING_APPROVAL' THEN [created_at] END ASC")
                  ->orderByDesc('created_at');
        }

        $records = $query->get()->map(fn(CourseRequest $courseRequest) => $this->toRecord($courseRequest));
        if ($role === 'officer') {
            $records = $records->filter(fn(array $record) => $record['status'] !== 'PENDING_APPROVAL'
                || $record['student_roster_needs_officer_attention'])->values();
        }
        $statuses = config('course-workflow.statuses');

        return view('portal.requests', compact('records', 'role', 'statuses'));
    }

    public function form(Request $request, int $step = 1)
    {
        abort_unless(in_array($step, [1, 2, 3, 4], true), 404);

        $data = $request->session()->get('portal.draft', []);
        $data['requester_unit'] = $this->personDepartmentName((int) $this->actor($request)['pers_id']);

        return view('portal.form-steps', [
            'step' => $step,
            'data' => $data,
            'steps' => config('course-workflow.steps'),
            'projectTypes' => ProjectType::query()->where('is_active', true)->orderByRaw("CASE WHEN project_type_code = 'OTHER' THEN 1 ELSE 0 END")->orderBy('project_type_code')->pluck('project_type_name_th', 'project_type_code'),
            'categories' => CourseCategory::query()->where('is_active', true)->orderByRaw("CASE WHEN category_code = 'OTHER' THEN 1 ELSE 0 END")->orderBy('category_code')->pluck('category_name_th', 'category_code'),
            'departments' => $this->departmentDirectory->options(),
        ]);
    }

    public function saveStep(Request $request, int $step)
    {
        abort_unless(in_array($step, [1, 2, 3, 4], true), 404);
        $required = $request->input('navigation') === 'back' ? 'nullable' : 'required';

        $rules = match ($step) {
            1 => [
                'target_dept_id' => [$required, 'integer', Rule::in($this->departmentDirectory->options()->keys()->all())],
                'project_name' => "$required|string|max:500",
                'project_type' => [$required, Rule::in(ProjectType::query()->where('is_active', true)->pluck('project_type_code')->all())],
                'project_other' => ($required === 'required' ? 'required_if:project_type,OTHER|' : '') . 'nullable|string|max:500',
                'coordinator_first' => "$required|string|max:100",
                'coordinator_last' => "$required|string|max:100",
                'coordinator_position' => "$required|string|max:200",
                'coordinator_phone' => "$required|string|max:50",
                'coordinator_email' => "$required|email|max:254",
            ],
            2 => [
                'course_th' => "$required|string|max:500",
                'course_en' => "$required|string|max:500",
                'instructors' => "$required|array|min:1|max:20",
                'instructors.*.first' => "$required|string|max:100",
                'instructors.*.last' => "$required|string|max:100",
                'instructors.*.email' => "$required|email|max:254",
                'category' => [$required, Rule::in(CourseCategory::query()->where('is_active', true)->pluck('category_code')->all())],
                'category_other' => ($required === 'required' ? 'required_if:category,OTHER|' : '') . 'nullable|string|max:500',
                'description' => "$required|string|max:5000",
            ],
            3 => [
                'learning' => [$required, Rule::in(['เปิดแบบตามวงรอบ (Phase/Batch-based)', 'แบบเปิดตามกรอบระยะเวลาของโครงการ (Event / Project-based)'])],
                'activity_round' => ($required === 'required' ? 'required_if:learning,เปิดแบบตามวงรอบ (Phase/Batch-based)|' : '') . 'nullable|string|max:500',
                'activity_phase' => ($required === 'required' ? 'required_if:learning,เปิดแบบตามวงรอบ (Phase/Batch-based)|' : '') . 'nullable|string|max:250',
                'starts_at' => "$required|date",
                'ends_at' => "$required|date|after_or_equal:starts_at",
                'enrollment' => [$required, Rule::in(['ใช้รหัสผ่าน (Enrollment Key)', 'ผู้ดูแลระบบนำเข้ารายชื่อ', 'อื่น ๆ (ระบุ)'])],
                'enrollment_other' => ($required === 'required' ? 'required_if:enrollment,อื่น ๆ (ระบุ)|' : '') . 'nullable|string|max:500',
                'expected_students' => "$required|integer|min:1|max:1000000",
            ],
            default => [],
        };
        $rules['additional_documents'] = 'nullable|array|max:5';
        $rules['additional_documents.*'] = 'file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10240';
        $rules['remove_additional'] = 'nullable|array';
        $rules['remove_additional.*'] = 'integer|min:0';

        $validated = $request->validate($rules, [
            'required' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
            'email' => 'กรุณาระบุอีเมลให้ถูกต้อง',
            'after_or_equal' => 'วันที่สิ้นสุดกิจกรรมต้องไม่ก่อนวันที่เริ่มต้น',
            'mimes' => 'ประเภทไฟล์ไม่ถูกต้อง',
            'max' => 'ข้อมูลหรือไฟล์มีขนาดเกินที่กำหนด',
        ]);
        $removeAdditional = $validated['remove_additional'] ?? [];
        unset($validated['additional_documents'], $validated['remove_additional']);

        if (isset($validated['target_dept_id'])) {
            $validated['target_dept_id'] = (int) $validated['target_dept_id'];
            $validated['unit'] = $this->departmentDirectory->name($validated['target_dept_id']);
        }

        if (isset($validated['learning']) && $validated['learning'] !== 'เปิดแบบตามวงรอบ (Phase/Batch-based)') {
            $validated['activity_round'] = null;
            $validated['activity_phase'] = null;
        }

        $existingDraft = $request->session()->get('portal.draft', []);
        $data = array_replace($existingDraft, $validated);
        if ($step <= 3) {
            $data['unsigned_pdf_downloaded_at'] = null;
        }
        $data['requester_unit'] = $this->personDepartmentName((int) $this->actor($request)['pers_id']);
        if (isset($validated['category'])) {
            $data['category_label'] = CourseCategory::where('category_code', $validated['category'])->value('category_name_th');
        }
        if (isset($validated['project_type'])) {
            $data['project_type_label'] = ProjectType::where('project_type_code', $validated['project_type'])->value('project_type_name_th');
        }
        $additionalFiles = $this->draftAdditionalFiles($existingDraft);
        $removedDocumentIds = $existingDraft['removed_additional_document_ids'] ?? [];
        $pendingFilesToDelete = [];
        rsort($removeAdditional);
        foreach (array_unique($removeAdditional) as $index) {
            if (! isset($additionalFiles[$index])) continue;
            $file = $additionalFiles[$index];
            if (($file['additional_pending'] ?? false) === true) {
                $pendingFilesToDelete[] = $file;
            } elseif (isset($file['document_id'])) {
                $removedDocumentIds[] = (int) $file['document_id'];
            }
            unset($additionalFiles[$index]);
        }
        $additionalFiles = array_values($additionalFiles);

        $incomingAdditional = array_values(array_filter((array) $request->file('additional_documents', [])));
        if (count($additionalFiles) + count($incomingAdditional) > 5) {
            throw ValidationException::withMessages([
                'additional_documents' => 'อัปโหลดเอกสารเพิ่มเติมได้ไม่เกิน 5 ไฟล์',
            ]);
        }
        foreach ($pendingFilesToDelete as $file) {
            $this->deletePendingFile($file, 'additional');
        }
        foreach ($incomingAdditional as $file) {
            $additionalFiles[] = $this->storeDraftFile($file, 'additional');
        }
        $data['additional_files'] = $additionalFiles;
        $data['removed_additional_document_ids'] = array_values(array_unique($removedDocumentIds));
        unset($data['additional_path'], $data['additional_name'], $data['additional_mime'], $data['additional_size'], $data['additional_pending']);
        $request->session()->put('portal.draft', $data);

        if ($request->input('navigation') === 'back') {
            $request->session()->forget("portal.completed.$step");

            return redirect()->route($step === 1 ? 'requests.index' : 'requests.form', $step === 1 ? [] : $step - 1);
        }
        if ($step < 4) {
            $request->session()->put("portal.completed.$step", true);

            return redirect()->route('requests.form', $step + 1);
        }
        foreach ([1, 2, 3] as $completed) {
            if (! $request->session()->get("portal.completed.$completed")) {
                return redirect()->route('requests.form', $completed)->withErrors(['form' => 'กรุณากรอกข้อมูลส่วนนี้ให้ครบก่อนส่งคำร้อง']);
            }
        }

        $request->validate(['signed_document' => 'nullable|file|mimes:pdf|max:10240']);
        $signed = $request->hasFile('signed_document')
            ? $this->storeDraftFile($request->file('signed_document'), 'signed')
            : [];

        $deletedAdditionalPaths = [];
        try {
            $courseRequest = DB::connection('course133')->transaction(function () use ($request, $data, $signed, &$deletedAdditionalPaths) {
                $actor = $this->actor($request);
                $courseRequest = isset($data['id'])
                    ? CourseRequest::query()->lockForUpdate()->findOrFail($data['id'])
                    : new CourseRequest(['request_no' => $this->nextRequestNumber()]);

                if ($courseRequest->exists) {
                    abort_unless((int) $courseRequest->requester_pers_id === (int) $actor['pers_id'], 403);
                    abort_unless(in_array($courseRequest->status, ['RETURNED_FOR_REVISION', 'PENDING_SIGNED_DOCUMENT'], true), 409);
                }

                $status = isset($signed['signed_path']) ? 'UNDER_OFFICER_REVIEW' : 'PENDING_SIGNED_DOCUMENT';
                $courseRequest->fill($this->requestAttributes($data, $actor['pers_id'], $status));
                $courseRequest->save();

                $courseRequest->instructors()->delete();
                foreach ($data['instructors'] as $instructor) {
                    $courseRequest->instructors()->create([
                        'pers_id' => null,
                        'instructor_name' => trim($instructor['first'] . ' ' . $instructor['last']),
                        'instructor_email' => $instructor['email'],
                    ]);
                }

                if ($courseRequest->exists && ! empty($data['removed_additional_document_ids'])) {
                    $documents = $courseRequest->documents()
                        ->where('document_type', 'ADDITIONAL_DOCUMENT')
                        ->whereIn('document_id', $data['removed_additional_document_ids'])
                        ->get();
                    foreach ($documents as $document) {
                        $deletedAdditionalPaths[] = $document->storage_key;
                        $document->delete();
                    }
                }
                $needsStudentRoster = $data['enrollment'] === 'ผู้ดูแลระบบนำเข้ารายชื่อ'
                    && ! $courseRequest->documents()->where('document_type', 'STUDENT_ROSTER')->exists();
                foreach ($this->draftAdditionalFiles($data) as $additional) {
                    if (($additional['additional_pending'] ?? false) && isset($additional['additional_path'])) {
                        $storeAsStudentRoster = $needsStudentRoster && $this->isStudentRosterFile($additional);
                        $this->createDocument(
                            $courseRequest,
                            $storeAsStudentRoster ? 'STUDENT_ROSTER' : 'ADDITIONAL_DOCUMENT',
                            $additional,
                            'additional',
                            $actor['pers_id'],
                        );
                        if ($storeAsStudentRoster) {
                            $needsStudentRoster = false;
                        }
                    }
                }
                if (isset($signed['signed_path'])) {
                    $this->createDocument($courseRequest, 'SIGNED_FORM', $signed, 'signed', $actor['pers_id']);
                }

                $this->addHistory($courseRequest, $status, $actor['pers_id'], 'REQUESTER');
                if ($status === 'UNDER_OFFICER_REVIEW') {
                    $this->queueNotification($courseRequest, 'OFFICER_REVIEW_REQUIRED', config('course-workflow.actors.officer'));
                }

                return $courseRequest;
            });
        } catch (Throwable $exception) {
            $this->deletePendingFiles(array_merge($data, $signed));
            throw $exception;
        }

        foreach ($deletedAdditionalPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        $request->session()->forget(['portal.draft', 'portal.completed']);

        return redirect()->route('requests.index')->with('success', $courseRequest->status === 'UNDER_OFFICER_REVIEW'
            ? 'ส่งคำร้องเรียบร้อยแล้ว'
            : 'บันทึกคำร้องแล้ว<br>กรุณาอัปโหลดเอกสารที่ลงนามเพื่อส่งตรวจสอบ');
    }

    public function clearStep(Request $request, int $step)
    {
        $keys = match ($step) {
            1 => ['target_dept_id', 'unit', 'project_name', 'project_type', 'project_other', 'coordinator_first', 'coordinator_last', 'coordinator_position', 'coordinator_phone', 'coordinator_email'],
            2 => ['course_th', 'course_en', 'instructors', 'category', 'category_other', 'description'],
            3 => ['learning', 'activity_round', 'activity_phase', 'starts_at', 'ends_at', 'enrollment', 'enrollment_other', 'expected_students'],
            default => [],
        };
        $draft = $request->session()->get('portal.draft', []);
        foreach ($keys as $key) {
            unset($draft[$key]);
        }
        $request->session()->put('portal.draft', $draft);
        return back();
    }

    public function discard(Request $request)
    {
        $this->deletePendingFiles($request->session()->get('portal.draft', []));
        $request->session()->forget(['portal.draft', 'portal.completed']);

        return redirect()->route('requests.index');
    }

    public function edit(Request $request, int $id)
    {
        $courseRequest = $this->findAuthorized($request, $id, 'user');
        abort_unless(in_array($courseRequest->status, ['RETURNED_FOR_REVISION', 'PENDING_SIGNED_DOCUMENT'], true), 409);

        $draft = $this->toRecord($courseRequest);
        foreach (['officer_decision', 'officer_name', 'officer_reviewed_at', 'approval_decision', 'approver_name', 'approval_comment', 'approval_decided_at'] as $field) {
            $draft[$field] = null;
        }
        $request->session()->put('portal.draft', $draft);
        foreach ([1, 2, 3] as $step) {
            $request->session()->put("portal.completed.$step", true);
        }

        return redirect()->route('requests.form', 1);
    }

    public function detail(Request $request, int $id, string $role = 'user')
    {
        $courseRequest = $this->findAuthorized($request, $id, $role);
        $record = $this->toRecord($courseRequest);

        return view($role === 'user' ? 'portal.request-detail' : 'portal.detail', compact('record', 'role'));
    }

    public function upload(Request $request, int $id)
    {
        $request->validate(['signed_document' => 'required|file|mimes:pdf|max:10240'], [
            'required' => 'กรุณาเลือกเอกสารที่ลงนามแล้ว',
            'mimes' => 'กรุณาเลือกไฟล์ PDF เท่านั้น',
        ]);
        $stored = $this->storeDraftFile($request->file('signed_document'), 'signed');

        try {
            DB::connection('course133')->transaction(function () use ($request, $id, $stored) {
                $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
                $actor = $this->actor($request);
                abort_unless((int) $courseRequest->requester_pers_id === (int) $actor['pers_id'], 403);
                abort_unless($courseRequest->status === 'PENDING_SIGNED_DOCUMENT', 409);

                $this->createDocument($courseRequest, 'SIGNED_FORM', $stored, 'signed', $actor['pers_id']);
                $courseRequest->update(['status' => 'UNDER_OFFICER_REVIEW', 'submitted_at' => now()]);
                $this->addHistory($courseRequest, 'UNDER_OFFICER_REVIEW', $actor['pers_id'], 'REQUESTER');
                $this->queueNotification($courseRequest, 'OFFICER_REVIEW_REQUIRED', config('course-workflow.actors.officer'));
            });
        } catch (Throwable $exception) {
            $this->deletePendingFiles($stored);
            throw $exception;
        }

        $redirect = $request->input('redirect_to') === 'detail'
            ? redirect()->route('requests.show', $id)
            : redirect()->route('requests.index');

        return $redirect->with('success', 'อัปโหลดเอกสารสำเร็จ<br>ส่งคำร้องให้เจ้าหน้าที่ตรวจสอบแล้ว');
    }

    public function uploadStudentRoster(Request $request, int $id)
    {
        $request->validate(['student_roster' => 'required|file|mimes:xls,xlsx,csv|max:10240'], [
            'required' => 'กรุณาเลือกไฟล์รายชื่อผู้เรียน',
            'mimes' => 'กรุณาเลือกไฟล์ XLS, XLSX หรือ CSV',
            'max' => 'ไฟล์รายชื่อผู้เรียนต้องมีขนาดไม่เกิน 10 MB',
        ]);
        $stored = $this->storeDraftFile($request->file('student_roster'), 'roster');
        $isUpdate = false;

        try {
            DB::connection('course133')->transaction(function () use ($request, $id, $stored, &$isUpdate) {
                $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
                $actor = $this->actor($request);
                abort_unless((int) $courseRequest->requester_pers_id === (int) $actor['pers_id'], 403);
                abort_unless($courseRequest->enrollment_method === 'ผู้ดูแลระบบนำเข้ารายชื่อ', 409);

                $existing = $courseRequest->documents()
                    ->where('document_type', 'STUDENT_ROSTER')
                    ->latest('uploaded_at')
                    ->latest('document_id')
                    ->first();
                abort_if($existing?->roster_acknowledged_at !== null, 409, 'เจ้าหน้าที่รับทราบรายชื่อแล้ว กรุณาติดต่อเจ้าหน้าที่เพื่อเปิดให้แก้ไข');

                $isUpdate = $existing !== null;
                $this->createDocument($courseRequest, 'STUDENT_ROSTER', $stored, 'roster', $actor['pers_id']);
                $this->queueNotification(
                    $courseRequest,
                    $isUpdate ? 'STUDENT_ROSTER_UPDATED' : 'STUDENT_ROSTER_SUBMITTED',
                    config('course-workflow.actors.officer')
                );
            });
        } catch (Throwable $exception) {
            $this->deletePendingFile($stored, 'roster');
            throw $exception;
        }

        return redirect()->to(route('requests.show', $id) . '#student-roster')
            ->with('success', $isUpdate ? 'อัปเดตรายชื่อผู้เรียนสำเร็จ<br>แจ้งเจ้าหน้าที่ตรวจสอบไฟล์ใหม่แล้ว' : 'อัปโหลดรายชื่อผู้เรียนสำเร็จ<br>แจ้งเจ้าหน้าที่ตรวจสอบแล้ว');
    }

    public function acknowledgeStudentRoster(Request $request, int $id)
    {
        $returnToOfficerList = false;

        DB::connection('course133')->transaction(function () use ($request, $id, &$returnToOfficerList) {
            $courseRequest = $this->findAuthorized($request, $id, 'officer');
            $actor = $this->actor($request);
            $returnToOfficerList = $courseRequest->status === 'PENDING_APPROVAL';
            $studentRoster = $courseRequest->documents()
                ->where('document_type', 'STUDENT_ROSTER')
                ->latest('uploaded_at')
                ->latest('document_id')
                ->lockForUpdate()
                ->firstOrFail();

            $studentRoster->update([
                'roster_acknowledged_at' => now(),
                'roster_acknowledged_by_pers_id' => $actor['pers_id'],
            ]);

            $recipient = config('course-workflow.actors.user');
            $recipient['pers_id'] = (int) $courseRequest->requester_pers_id;
            $this->queueNotification($courseRequest, 'STUDENT_ROSTER_ACKNOWLEDGED', $recipient);
        });

        $redirect = $returnToOfficerList
            ? redirect()->route('officer.reviews')
            : redirect()->route('officer.show', $id);

        return $redirect
            ->with('success', 'รับทราบรายชื่อผู้เรียนแล้ว<br>ระบบล็อกการแก้ไขไฟล์ของผู้ยื่นคำร้อง');
    }

    public function reopenStudentRoster(Request $request, int $id)
    {
        DB::connection('course133')->transaction(function () use ($request, $id) {
            $courseRequest = $this->findAuthorized($request, $id, 'officer');
            $studentRoster = $courseRequest->documents()
                ->where('document_type', 'STUDENT_ROSTER')
                ->latest('uploaded_at')
                ->latest('document_id')
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($studentRoster->roster_acknowledged_at === null, 409);
            $studentRoster->update([
                'roster_acknowledged_at' => null,
                'roster_acknowledged_by_pers_id' => null,
            ]);

            $recipient = config('course-workflow.actors.user');
            $recipient['pers_id'] = (int) $courseRequest->requester_pers_id;
            $this->queueNotification($courseRequest, 'STUDENT_ROSTER_REOPENED', $recipient);
        });

        return redirect()->route('officer.show', $id)
            ->with('success', 'เปิดให้แก้ไขรายชื่อผู้เรียนแล้ว<br>แจ้งผู้ยื่นคำร้องเรียบร้อย');
    }

    public function review(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['pass', 'return'])],
            'reason' => 'required_if:decision,return|nullable|string|max:3000',
            'signature_file' => 'required_if:decision,pass|nullable|image|mimes:png,jpeg,jpg|max:2048',
        ]);

        DB::connection('course133')->transaction(function () use ($request, $id, $data) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($courseRequest->status === 'UNDER_OFFICER_REVIEW', 409);
            abort_unless($courseRequest->documents()->where('document_type', 'SIGNED_FORM')->where('mime_type', 'application/pdf')->exists(), 409);

            $actor = $this->actor($request);
            $passed = $data['decision'] === 'pass';
            $status = $passed ? 'PENDING_APPROVAL' : 'RETURNED_FOR_REVISION';

            if ($passed && $request->hasFile('signature_file')) {
                $signaturePath = $request->file('signature_file')->store('temp_signatures', 'local');
                $signatureAbsPath = Storage::disk('local')->path($signaturePath);

                $signedDoc = $courseRequest->documents()->where('document_type', 'SIGNED_FORM')->latest('uploaded_at')->first();
                if ($signedDoc) {
                    $pdfAbsPath = Storage::disk('local')->path($signedDoc->storage_key);

                    if (file_exists($pdfAbsPath)) {
                        if (! defined('FPDF_FONTPATH')) {
                            define('FPDF_FONTPATH', resource_path('fonts/fpdf').DIRECTORY_SEPARATOR);
                        }

                        $pdf = new Fpdi();
                        $pageCount = $pdf->setSourceFile($pdfAbsPath);
                        $continuedApprovalComment = null;
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $templateId = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($templateId);

                            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                            $pdf->useTemplate($templateId);

                            if ($pageNo === $pageCount) {
                                $scaleX = $size['width'] / 210;
                                $scaleY = $size['height'] / 297;

                                // 1. Keep the checkmark inside the officer checkbox.
                                $pdf->SetLineWidth(0.4);
                                $pdf->SetDrawColor(0, 0, 0);
                                $pdf->Line(30.4 * $scaleX, 150 * $scaleY, 31.4 * $scaleX, 151.1 * $scaleY);
                                $pdf->Line(31.4 * $scaleX, 151.1 * $scaleY, 32.9 * $scaleX, 148.6 * $scaleY);

                                // 2. Center the signature on the signature line without touching the date row.
                                $signatureBoxX = 44.5 * $scaleX;
                                $signatureBoxY = 154.5 * $scaleY;
                                $signatureBoxWidth = 51.5 * $scaleX;
                                $signatureBoxHeight = 10 * $scaleY;
                                $signatureInfo = getimagesize($signatureAbsPath);
                                $signatureRatio = $signatureInfo && $signatureInfo[1] > 0
                                    ? $signatureInfo[0] / $signatureInfo[1]
                                    : 3;
                                $signatureWidth = min($signatureBoxWidth, $signatureBoxHeight * $signatureRatio);
                                $signatureHeight = min($signatureBoxHeight, $signatureWidth / $signatureRatio);
                                $signatureX = $signatureBoxX + (($signatureBoxWidth - $signatureWidth) / 2);
                                $signatureY = $signatureBoxY + ($signatureBoxHeight - $signatureHeight);
                                $pdf->Image($signatureAbsPath, $signatureX, $signatureY, $signatureWidth, $signatureHeight);

                                // 3. Stamp Date
                                $pdf->AddFont('Sarabun', '', 'Sarabun-Regular.php');
                                $pdf->SetFont('Sarabun', '', 9);
                                $pdf->SetTextColor(0, 0, 0);
                                $dateStr = iconv('UTF-8', 'CP874//IGNORE', now()->locale('th')->translatedFormat('j F Y'));
                                $pdf->Text(43 * $scaleX, 173.3 * $scaleY, $dateStr);
                            }
                        }
                        $pdf->Output('F', $pdfAbsPath);
                    }
                }
                Storage::disk('local')->delete($signaturePath);
            }

            OfficerReview::create([
                'request_id' => $courseRequest->request_id,
                'officer_pers_id' => $actor['pers_id'],
                'decision' => $passed ? 'PASSED' : 'RETURNED',
                'return_reason' => $passed ? null : $data['reason'],
                'reviewed_at' => now(),
            ]);
            $courseRequest->update(['status' => $status]);
            $this->addHistory($courseRequest, $status, $actor['pers_id'], 'OFFICER');
            $this->queueNotification($courseRequest, $passed ? 'APPROVAL_REQUIRED' : 'REQUEST_RETURNED', $passed
                ? config('course-workflow.actors.approver')
                : config('course-workflow.actors.user'));
        });

        return redirect()->route('officer.reviews')->with('success', $data['decision'] === 'pass'
            ? 'ส่งเอกสารให้ผู้มีอำนาจพิจารณาแล้ว'
            : 'ส่งเหตุผลกลับให้ผู้ยื่นคำร้องแล้ว');
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return', 'reject'])],
            'reason' => 'required_if:decision,return,reject|nullable|string|max:3000',
            'signature_file' => 'required_if:decision,approve,reject|nullable|image|mimes:png,jpeg,jpg|max:2048',
        ]);

        DB::connection('course133')->transaction(function () use ($request, $id, $data) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($courseRequest->status === 'PENDING_APPROVAL', 409);

            $actor = $this->actor($request);
            $status = match ($data['decision']) {
                'approve' => 'PENDING_COURSE_ID',
                'return' => 'RETURNED_FOR_REVISION',
                'reject' => 'REJECTED',
            };
            $approvalDecision = match ($data['decision']) {
                'approve' => 'APPROVED',
                'return' => 'RETURNED',
                'reject' => 'REJECTED',
            };

            if (in_array($data['decision'], ['approve', 'reject'], true) && $request->hasFile('signature_file')) {
                $signaturePath = $request->file('signature_file')->store('temp_signatures', 'local');
                $signatureAbsPath = Storage::disk('local')->path($signaturePath);

                $signedDoc = $courseRequest->documents()->where('document_type', 'SIGNED_FORM')->latest('uploaded_at')->first();
                if ($signedDoc) {
                    $pdfAbsPath = Storage::disk('local')->path($signedDoc->storage_key);

                    if (file_exists($pdfAbsPath)) {
                        if (! defined('FPDF_FONTPATH')) {
                            define('FPDF_FONTPATH', resource_path('fonts/fpdf').DIRECTORY_SEPARATOR);
                        }

                        $pdf = new Fpdi();
                        $pageCount = $pdf->setSourceFile($pdfAbsPath);
                        $continuedApprovalComment = null;
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $templateId = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($templateId);

                            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                            $pdf->useTemplate($templateId);

                            if ($pageNo === $pageCount) {
                                $scaleX = $size['width'] / 210;
                                $scaleY = $size['height'] / 297;

                                // Mark the approver decision checkbox.
                                $checkboxX = $data['decision'] === 'approve' ? 114.8 : 135.4;
                                $pdf->SetLineWidth(0.4);
                                $pdf->SetDrawColor(0, 0, 0);
                                $pdf->Line($checkboxX * $scaleX, 149 * $scaleY, ($checkboxX + 1) * $scaleX, 150.1 * $scaleY);
                                $pdf->Line(($checkboxX + 1) * $scaleX, 150.1 * $scaleY, ($checkboxX + 2.5) * $scaleX, 147.6 * $scaleY);

                                // Center the approver signature on its signature line.
                                $signatureBoxX = 129 * $scaleX;
                                $signatureBoxY = 164.5 * $scaleY;
                                $signatureBoxWidth = 51.5 * $scaleX;
                                $signatureBoxHeight = 10 * $scaleY;
                                $signatureInfo = getimagesize($signatureAbsPath);
                                $signatureRatio = $signatureInfo && $signatureInfo[1] > 0
                                    ? $signatureInfo[0] / $signatureInfo[1]
                                    : 3;
                                $signatureWidth = min($signatureBoxWidth, $signatureBoxHeight * $signatureRatio);
                                $signatureHeight = min($signatureBoxHeight, $signatureWidth / $signatureRatio);
                                $signatureX = $signatureBoxX + (($signatureBoxWidth - $signatureWidth) / 2);
                                $signatureY = $signatureBoxY + ($signatureBoxHeight - $signatureHeight);
                                $pdf->Image($signatureAbsPath, $signatureX, $signatureY, $signatureWidth, $signatureHeight);

                                $pdf->AddFont('Sarabun', '', 'Sarabun-Regular.php');
                                $pdf->SetFont('Sarabun', '', 9);
                                $pdf->SetTextColor(0, 0, 0);

                                if ($data['decision'] === 'reject') {
                                    $comment = trim((string) preg_replace('/\s+/u', ' ', $data['reason']));
                                    $commentMaxWidth = 51.5 * $scaleX;
                                    $commentFontSize = 7.5;
                                    $pdf->SetFont('Sarabun', '', $commentFontSize);
                                    $remainingComment = $comment;
                                    $commentLines = [];

                                    for ($lineIndex = 0; $lineIndex < 2 && $remainingComment !== ''; $lineIndex++) {
                                        $encodedRemaining = iconv('UTF-8', 'CP874//IGNORE', $remainingComment);
                                        if ($pdf->GetStringWidth($encodedRemaining) <= $commentMaxWidth) {
                                            $commentLines[] = $encodedRemaining;
                                            $remainingComment = '';
                                            break;
                                        }

                                        $low = 0;
                                        $high = mb_strlen($remainingComment);
                                        $fittedLength = 0;
                                        while ($low <= $high) {
                                            $middle = intdiv($low + $high, 2);
                                            $candidate = rtrim(mb_substr($remainingComment, 0, $middle));
                                            $encodedCandidate = iconv('UTF-8', 'CP874//IGNORE', $candidate);
                                            if ($pdf->GetStringWidth($encodedCandidate) <= $commentMaxWidth) {
                                                $fittedLength = $middle;
                                                $low = $middle + 1;
                                            } else {
                                                $high = $middle - 1;
                                            }
                                        }

                                        $fittedLine = rtrim(mb_substr($remainingComment, 0, $fittedLength));
                                        $lastSpace = mb_strrpos($fittedLine, ' ');
                                        if ($lastSpace !== false && $lastSpace >= (int) floor($fittedLength * 0.6)) {
                                            $fittedLength = $lastSpace;
                                            $fittedLine = rtrim(mb_substr($remainingComment, 0, $fittedLength));
                                        }
                                        $commentLines[] = iconv('UTF-8', 'CP874//IGNORE', $fittedLine);
                                        $remainingComment = ltrim(mb_substr($remainingComment, $fittedLength));
                                    }

                                    $continuedApprovalComment = $remainingComment !== '' ? $remainingComment : null;

                                    $commentStartY = count($commentLines) > 1 ? 158.5 : 161.5;
                                    foreach ($commentLines as $lineIndex => $commentLine) {
                                        $pdf->Text(129 * $scaleX, ($commentStartY + ($lineIndex * 3.5)) * $scaleY, $commentLine);
                                    }
                                    $pdf->SetFont('Sarabun', '', 9);
                                }

                                $dateStr = iconv('UTF-8', 'CP874//IGNORE', now()->locale('th')->translatedFormat('j F Y'));
                                $pdf->Text(127.5 * $scaleX, 183.3 * $scaleY, $dateStr);
                            }
                        }

                        if ($continuedApprovalComment !== null) {
                            $continuationX = 15 * $scaleX;
                            $continuationY = 198 * $scaleY;
                            $continuationWidth = 180 * $scaleX;
                            $continuationTitle = iconv('UTF-8', 'CP874//IGNORE', '* ข้อคิดเห็นกรณีไม่อนุมัติ (ต่อ):');

                            $pdf->SetFont('Sarabun', '', 8);
                            $pdf->SetTextColor(75, 85, 99);
                            $pdf->Text($continuationX, $continuationY, $continuationTitle);

                            $titleWidth = $pdf->GetStringWidth($continuationTitle);
                            $firstLineMaxWidth = $continuationWidth - $titleWidth - (2 * $scaleX);
                            $low = 0;
                            $high = mb_strlen($continuedApprovalComment);
                            $firstLineLength = 0;
                            while ($low <= $high) {
                                $middle = intdiv($low + $high, 2);
                                $candidate = rtrim(mb_substr($continuedApprovalComment, 0, $middle));
                                $encodedCandidate = iconv('UTF-8', 'CP874//IGNORE', $candidate);
                                if ($pdf->GetStringWidth($encodedCandidate) <= $firstLineMaxWidth) {
                                    $firstLineLength = $middle;
                                    $low = $middle + 1;
                                } else {
                                    $high = $middle - 1;
                                }
                            }

                            $firstContinuationLine = rtrim(mb_substr($continuedApprovalComment, 0, $firstLineLength));
                            $lastSpace = mb_strrpos($firstContinuationLine, ' ');
                            if ($lastSpace !== false && $lastSpace >= (int) floor($firstLineLength * 0.6)) {
                                $firstLineLength = $lastSpace;
                                $firstContinuationLine = rtrim(mb_substr($continuedApprovalComment, 0, $firstLineLength));
                            }

                            $pdf->SetTextColor(17, 24, 39);
                            $pdf->Text(
                                $continuationX + $titleWidth + (2 * $scaleX),
                                $continuationY,
                                iconv('UTF-8', 'CP874//IGNORE', $firstContinuationLine)
                            );

                            $remainingContinuation = ltrim(mb_substr($continuedApprovalComment, $firstLineLength));
                            if ($remainingContinuation !== '') {
                                $pdf->SetXY($continuationX, $continuationY + (2 * $scaleY));
                                $pdf->MultiCell(
                                    $continuationWidth,
                                    4.5 * $scaleY,
                                    iconv('UTF-8', 'CP874//IGNORE', $remainingContinuation),
                                    0,
                                    'L'
                                );
                            }
                        }

                        $pdf->Output('F', $pdfAbsPath);
                    }
                }
                Storage::disk('local')->delete($signaturePath);
            }

            CourseApproval::create([
                'request_id' => $courseRequest->request_id,
                'approver_pers_id' => $actor['pers_id'],
                'decision' => $approvalDecision,
                'comment' => $data['decision'] === 'approve' ? null : $data['reason'],
                'decided_at' => now(),
            ]);
            $courseRequest->update(['status' => $status]);
            $this->addHistory($courseRequest, $status, $actor['pers_id'], 'APPROVER');
            $notificationType = match ($data['decision']) {
                'approve' => 'COURSE_ID_REQUIRED',
                'return' => 'REQUEST_RETURNED',
                'reject' => 'REQUEST_REJECTED',
            };
            $recipient = $data['decision'] === 'approve'
                ? config('course-workflow.actors.officer')
                : config('course-workflow.actors.user');
            $this->queueNotification($courseRequest, $notificationType, $recipient);
        });

        $message = match ($data['decision']) {
            'approve' => 'อนุมัติคำร้องเรียบร้อยแล้ว',
            'return' => 'ส่งคำร้องกลับให้ผู้ยื่นคำร้องแก้ไขแล้ว',
            'reject' => 'บันทึกผลไม่อนุมัติ และส่งเหตุผลให้ผู้ยื่นคำร้องแล้ว',
        };

        return redirect()->route('approver.reviews')->with('success', $message);
    }

    public function courseId(Request $request, int $id)
    {
        $data = $request->validate(['course_id' => 'required|string|max:100|regex:/^[a-zA-Z0-9_-]+$/']);

        DB::connection('course133')->transaction(function () use ($request, $id, $data) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($courseRequest->status === 'PENDING_COURSE_ID', 409);
            abort_unless($courseRequest->approved_pdf_downloaded_at !== null, 409, 'กรุณาดาวน์โหลด PDF ที่อนุมัติแล้วก่อนบันทึก Course ID');
            $actor = $this->actor($request);

            $courseRequest->update([
                'course_id' => $data['course_id'],
                'recorded_by_pers_id' => $actor['pers_id'],
                'status' => 'COURSE_ID_RECORDED',
            ]);
            $this->addHistory($courseRequest, 'COURSE_ID_RECORDED', $actor['pers_id'], 'OFFICER');
            $this->queueNotification($courseRequest, 'COURSE_ID_RECORDED', config('course-workflow.actors.user'));
        });

        return redirect()->route('officer.reviews')->with('success', 'บันทึก Course ID สำเร็จ ผู้ยื่นคำร้องสามารถดูรหัสรายวิชาได้แล้ว');
    }

    public function document(Request $request, string $id = 'draft')
    {
        $record = $this->documentRecord($request, $id);

        if ($request->query('preview') === '1') {
            return Pdf::loadView('portal.document-pdf', compact('record'))
                ->setPaper('a4', 'portrait')
                ->stream();
        }

        $downloadUrl = route('requests.document.download', ['id' => $id]);
        return view('portal.document', compact('record', 'downloadUrl'));
    }

    public function downloadDocument(Request $request, string $id = 'draft')
    {
        $record = $this->documentRecord($request, $id);
        $projectName = $record['project_name'] ?? 'โครงการขออนุมัติ';
        $safeName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $projectName);
        $filename = "{$safeName}.pdf";

        $response = Pdf::loadView('portal.document-pdf', compact('record'))
            ->setPaper('a4', 'portrait')
            ->download($filename);

        if ($id === 'draft') {
            $request->session()->put('portal.draft.unsigned_pdf_downloaded_at', now()->toDateTimeString());
        } elseif ($this->actor($request)['role'] === 'user') {
            CourseRequest::query()
                ->whereKey((int) $id)
                ->where('requester_pers_id', (int) $this->actor($request)['pers_id'])
                ->where('status', 'PENDING_SIGNED_DOCUMENT')
                ->whereNull('unsigned_pdf_downloaded_at')
                ->update(['unsigned_pdf_downloaded_at' => now()]);
        }

        return $response;
    }

    public function attachment(Request $request, int $id, string $kind)
    {
        $type = match ($kind) {
            'signed' => 'SIGNED_FORM',
            'additional' => 'ADDITIONAL_DOCUMENT',
            'roster' => 'STUDENT_ROSTER',
            default => abort(404),
        };
        $courseRequest = $this->findAuthorized($request, $id, $this->actor($request)['role']);
        $documents = $courseRequest->documents()->where('document_type', $type);
        if (in_array($kind, ['additional', 'roster'], true) && $request->filled('document')) {
            $documents->where('document_id', $request->integer('document'));
        }
        $document = $documents->latest('uploaded_at')->latest('document_id')->firstOrFail();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        $response = $disk->download($document->storage_key, $document->original_filename);

        if ($kind === 'signed'
            && $this->actor($request)['role'] === 'officer'
            && $courseRequest->status === 'PENDING_COURSE_ID'
            && $courseRequest->approved_pdf_downloaded_at === null) {
            $courseRequest->update(['approved_pdf_downloaded_at' => now()]);
        }

        return $response;
    }

    private function findAuthorized(Request $request, int $id, string $role): CourseRequest
    {
        $courseRequest = CourseRequest::query()->with($this->recordRelations())->findOrFail($id);

        if ($role === 'user') {
            abort_unless((int) $courseRequest->requester_pers_id === (int) $this->actor($request)['pers_id'], 403);
        } elseif ($role === 'officer') {
            $latestRoster = $courseRequest->documents
                ->where('document_type', 'STUDENT_ROSTER')
                ->sortByDesc(fn ($document) => sprintf('%s-%020d', $document->uploaded_at?->format('Y-m-d H:i:s.u'), $document->document_id))
                ->first();
            $canReviewLateRoster = $courseRequest->status === 'PENDING_APPROVAL'
                && $latestRoster !== null
                && $latestRoster->roster_acknowledged_at === null;

            abort_unless(in_array($courseRequest->status, ['UNDER_OFFICER_REVIEW', 'PENDING_COURSE_ID', 'COURSE_ID_RECORDED', 'REJECTED'], true) || $canReviewLateRoster, 404);
        } else {
            abort_unless(in_array($courseRequest->status, ['PENDING_APPROVAL', 'PENDING_COURSE_ID', 'COURSE_ID_RECORDED', 'REJECTED'], true), 404);
        }

        return $courseRequest;
    }

    private function documentRecord(Request $request, string $id): array
    {
        return $id === 'draft'
            ? $request->session()->get('portal.draft', [])
            : $this->toRecord($this->findAuthorized($request, (int) $id, $this->actor($request)['role']));
    }

    private function requestAttributes(array $data, int $requesterId, string $status): array
    {
        return [
            'requester_pers_id' => $requesterId,
            'target_dept_id' => (int) $data['target_dept_id'],
            'course_name_th' => $data['course_th'],
            'course_name_en' => $data['course_en'],
            'status' => $status,
            'submitted_at' => now(),
            'unsigned_pdf_downloaded_at' => $data['unsigned_pdf_downloaded_at'] ?? null,
            'project_name' => $data['project_name'],
            'project_type_code' => $data['project_type'],
            'project_other' => $data['project_type'] === 'OTHER' ? $data['project_other'] : null,
            'coordinator_first_name' => $data['coordinator_first'],
            'coordinator_last_name' => $data['coordinator_last'],
            'coordinator_position' => $data['coordinator_position'],
            'coordinator_phone' => $data['coordinator_phone'],
            'coordinator_email' => $data['coordinator_email'],
            'category_code' => $data['category'],
            'category_other' => $data['category'] === 'OTHER' ? $data['category_other'] : null,
            'course_description' => $data['description'],
            'learning_mode' => $data['learning'],
            'activity_round' => $data['learning'] === 'เปิดแบบตามวงรอบ (Phase/Batch-based)' ? ($data['activity_round'] ?? null) : null,
            'activity_phase' => $data['learning'] === 'เปิดแบบตามวงรอบ (Phase/Batch-based)' ? ($data['activity_phase'] ?? null) : null,
            'starts_on' => $data['starts_at'] ?? null,
            'ends_on' => $data['ends_at'] ?? null,
            'enrollment_method' => $data['enrollment'],
            'enrollment_other' => $data['enrollment'] === 'อื่น ๆ (ระบุ)' ? ($data['enrollment_other'] ?? null) : null,
            'expected_students' => $data['expected_students'],
            'course_id' => null,
            'recorded_by_pers_id' => null,
        ];
    }

    private function toRecord(CourseRequest $courseRequest): array
    {
        $signed = $courseRequest->documents->where('document_type', 'SIGNED_FORM')->sortByDesc('uploaded_at')->first();
        $studentRosters = $courseRequest->documents
            ->where('document_type', 'STUDENT_ROSTER')
            ->sortByDesc(fn ($document) => sprintf('%s-%020d', $document->uploaded_at?->format('Y-m-d H:i:s.u'), $document->document_id))
            ->values();
        $studentRoster = $studentRosters->first();
        $studentRosterVersionCount = $studentRosters->count();
        $studentRosterVersions = $studentRosters->map(fn ($document, $index) => [
            'document_id' => $document->document_id,
            'version' => $studentRosterVersionCount - $index,
            'name' => $document->original_filename,
            'uploaded_at' => $document->uploaded_at?->locale('th')->translatedFormat('j M Y'),
            'acknowledged' => $document->roster_acknowledged_at !== null,
        ])->all();
        $additionalFiles = $courseRequest->documents
            ->where('document_type', 'ADDITIONAL_DOCUMENT')
            ->sortBy('uploaded_at')
            ->map(fn ($document) => [
                'document_id' => $document->document_id,
                'additional_path' => $document->storage_key,
                'additional_name' => $document->original_filename,
                'additional_mime' => $document->mime_type,
                'additional_size' => $document->file_size_bytes,
                'additional_pending' => false,
        ])->values()->all();
        $latestReview = $courseRequest->officerReviews->sortByDesc('reviewed_at')->first();
        $latestApproval = $courseRequest->approvals->sortByDesc('decided_at')->first();
        $returnedByApprover = $courseRequest->status === 'RETURNED_FOR_REVISION'
            && $latestApproval?->decision === 'RETURNED'
            && ($latestReview?->reviewed_at === null || $latestApproval->decided_at->greaterThanOrEqualTo($latestReview->reviewed_at));
        $reason = match ($courseRequest->status) {
            'RETURNED_FOR_REVISION' => $returnedByApprover
                ? $latestApproval->comment
                : $latestReview?->return_reason,
            'REJECTED' => $latestApproval?->comment,
            default => null,
        };

        return [
            'id' => $courseRequest->request_id,
            'number' => $courseRequest->request_no,
            'requester' => $this->personName($courseRequest->requester_pers_id),
            'requester_unit' => $this->personDepartmentName((int) $courseRequest->requester_pers_id),
            'target_dept_id' => $courseRequest->target_dept_id,
            'unit' => $this->departmentName($courseRequest->target_dept_id),
            'project_name' => $courseRequest->project_name,
            'project_type' => $courseRequest->project_type_code,
            'project_type_label' => $courseRequest->projectType?->project_type_name_th,
            'project_other' => $courseRequest->project_other,
            'coordinator_first' => $courseRequest->coordinator_first_name,
            'coordinator_last' => $courseRequest->coordinator_last_name,
            'coordinator_position' => $courseRequest->coordinator_position,
            'coordinator_phone' => $courseRequest->coordinator_phone,
            'coordinator_email' => $courseRequest->coordinator_email,
            'course_th' => $courseRequest->course_name_th,
            'course_en' => $courseRequest->course_name_en,
            'category' => $courseRequest->category_code,
            'category_label' => $courseRequest->category?->category_name_th,
            'category_other' => $courseRequest->category_other,
            'description' => $courseRequest->course_description,
            'instructors' => $courseRequest->instructors->map(function ($instructor) {
                [$first, $last] = array_pad(preg_split('/\s+/', trim($instructor->instructor_name), 2), 2, '');

                return ['first' => $first, 'last' => $last, 'email' => $instructor->instructor_email];
            })->values()->all(),
            'learning' => $courseRequest->learning_mode,
            'activity_round' => $courseRequest->activity_round,
            'activity_phase' => $courseRequest->activity_phase,
            'starts_at' => $courseRequest->starts_on instanceof \DateTimeInterface ? $courseRequest->starts_on->format('Y-m-d') : null,
            'ends_at' => $courseRequest->ends_on instanceof \DateTimeInterface ? $courseRequest->ends_on->format('Y-m-d') : null,
            'enrollment' => $courseRequest->enrollment_method,
            'enrollment_other' => $courseRequest->enrollment_other,
            'requires_student_roster' => $courseRequest->enrollment_method === 'ผู้ดูแลระบบนำเข้ารายชื่อ',
            'student_roster_name' => $studentRoster?->original_filename,
            'has_student_roster' => $studentRoster !== null,
            'student_roster_versions' => $studentRosterVersions,
            'student_roster_version_count' => $studentRosterVersionCount,
            'student_roster_uploaded_at' => $studentRoster?->uploaded_at?->locale('th')->translatedFormat('j M Y'),
            'student_roster_acknowledged' => $studentRoster?->roster_acknowledged_at !== null,
            'student_roster_acknowledged_at' => $studentRoster?->roster_acknowledged_at?->locale('th')->translatedFormat('j M Y H:i น.'),
            'student_roster_needs_officer_attention' => $studentRoster !== null && $studentRoster->roster_acknowledged_at === null,
            'student_roster_is_update' => $studentRosterVersionCount > 1,
            'expected_students' => $courseRequest->expected_students,
            'status' => $courseRequest->status,
            'unsigned_pdf_downloaded' => $courseRequest->unsigned_pdf_downloaded_at !== null,
            'approved_pdf_downloaded' => $courseRequest->approved_pdf_downloaded_at !== null,
            'submitted_at' => $courseRequest->submitted_at?->toDateString() ?? $courseRequest->created_at?->toDateString(),
            'course_id' => $courseRequest->course_id,
            'reason' => $reason,
            'returned_by' => $courseRequest->status === 'RETURNED_FOR_REVISION'
                ? ($returnedByApprover ? 'APPROVER' : 'OFFICER')
                : null,
            'officer_decision' => $latestReview?->decision,
            'officer_name' => $latestReview ? $this->personName((int) $latestReview->officer_pers_id) : null,
            'officer_reviewed_at' => $latestReview?->reviewed_at?->locale('th')->translatedFormat('j F Y'),
            'approval_decision' => $latestApproval?->decision,
            'approver_name' => $latestApproval ? $this->personName((int) $latestApproval->approver_pers_id) : null,
            'approval_comment' => $latestApproval?->comment,
            'approval_decided_at' => $latestApproval?->decided_at?->format('d/m/Y'),
            'signed_path' => $signed?->storage_key,
            'signed_name' => $signed?->original_filename,
            'additional_files' => $additionalFiles,
            'removed_additional_document_ids' => [],
        ];
    }

    private function createDocument(CourseRequest $courseRequest, string $type, array $data, string $prefix, int $uploaderId): CourseDocument
    {
        return CourseDocument::create([
            'request_id' => $courseRequest->request_id,
            'document_type' => $type,
            'storage_key' => $data[$prefix . '_path'],
            'original_filename' => $data[$prefix . '_name'],
            'mime_type' => $data[$prefix . '_mime'],
            'file_size_bytes' => $data[$prefix . '_size'],
            'uploaded_by_pers_id' => $uploaderId,
            'uploaded_at' => now(),
        ]);
    }

    private function addHistory(CourseRequest $courseRequest, string $status, ?int $personId, string $source): void
    {
        RequestStatusHistory::create([
            'request_id' => $courseRequest->request_id,
            'status' => $status,
            'changed_by_pers_id' => $personId,
            'change_source' => $source,
            'changed_at' => now(),
        ]);
    }

    private function queueNotification(CourseRequest $courseRequest, string $type, array $recipient): void
    {
        NotificationOutbox::create([
            'request_id' => $courseRequest->request_id,
            'notification_type' => $type,
            'recipient_pers_id' => $recipient['pers_id'],
            'recipient_email' => $recipient['email'],
            'delivery_status' => 'PENDING',
            'attempt_count' => 0,
            'created_at' => now(),
        ]);
    }

    private function storeDraftFile(UploadedFile $file, string $prefix): array
    {
        $path = $file->store('course-requests/' . date('Y/m'), 'local');
        abort_if($path === false, 500, 'ไม่สามารถจัดเก็บไฟล์ได้');

        return [
            $prefix . '_path' => $path,
            $prefix . '_name' => $file->getClientOriginalName(),
            $prefix . '_mime' => $prefix === 'signed' ? 'application/pdf' : ($file->getMimeType() ?: $file->getClientMimeType()),
            $prefix . '_size' => $file->getSize(),
            $prefix . '_pending' => true,
        ];
    }

    private function deletePendingFiles(array $data): void
    {
        foreach ($this->draftAdditionalFiles($data) as $additional) {
            $this->deletePendingFile($additional, 'additional');
        }
        $this->deletePendingFile($data, 'signed');
    }

    private function draftAdditionalFiles(array $data): array
    {
        if (isset($data['additional_files']) && is_array($data['additional_files'])) {
            return array_values($data['additional_files']);
        }

        return isset($data['additional_path']) ? [[
            'additional_path' => $data['additional_path'],
            'additional_name' => $data['additional_name'] ?? 'เอกสารเพิ่มเติม',
            'additional_mime' => $data['additional_mime'] ?? 'application/octet-stream',
            'additional_size' => $data['additional_size'] ?? 0,
            'additional_pending' => $data['additional_pending'] ?? false,
        ]] : [];
    }

    private function isStudentRosterFile(array $file): bool
    {
        $extension = Str::lower(pathinfo((string) ($file['additional_name'] ?? ''), PATHINFO_EXTENSION));

        return in_array($extension, ['xls', 'xlsx', 'csv'], true);
    }

    private function deletePendingFile(array $data, string $prefix): void
    {
        if (($data[$prefix . '_pending'] ?? false) && isset($data[$prefix . '_path'])) {
            Storage::disk('local')->delete($data[$prefix . '_path']);
        }
    }

    private function nextRequestNumber(): string
    {
        do {
            $number = 'SWU-' . date('Y') . '-' . (string) Str::upper(Str::random(8));
        } while (CourseRequest::query()->where('request_no', $number)->exists());

        return $number;
    }

    private function actor(Request $request): array
    {
        $actor = $request->session()->get('portal.actor');
        abort_unless(is_array($actor), 401);

        return $actor;
    }

    private function homeRoute(string $role): string
    {
        return match ($role) {
            'officer' => 'officer.reviews',
            'approver' => 'approver.reviews',
            default => 'requests.index',
        };
    }

    private function personName(int $personId): string
    {
        foreach (config('course-workflow.actors') as $actor) {
            if ((int) $actor['pers_id'] === $personId) {
                return $actor['name'];
            }
        }

        return "บุคลากร #$personId";
    }

    private function departmentName(int $departmentId): string
    {
        return $this->departmentDirectory->name($departmentId);
    }

    private function personDepartmentName(int $personId): string
    {
        foreach (config('course-workflow.actors') as $actor) {
            if ((int) ($actor['pers_id'] ?? 0) === $personId && isset($actor['dept_id'])) {
                return $this->departmentName((int) $actor['dept_id']);
            }
        }

        return '—';
    }

    private function recordRelations(): array
    {
        return ['projectType', 'category', 'instructors', 'documents', 'officerReviews', 'approvals'];
    }
}
