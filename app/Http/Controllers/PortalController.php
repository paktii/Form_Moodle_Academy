<?php

namespace App\Http\Controllers;

use App\Models\ApproverReview;
use App\Models\CourseCategory;
use App\Models\CourseDocument;
use App\Models\CourseRequest;
use App\Models\DeanReview;
use App\Models\FacultyDean;
use App\Models\NotificationOutbox;
use App\Models\OfficerReview;
use App\Models\ProjectType;
use App\Models\RequestStatusHistory;
use App\Models\ServicePortal\WebsitePlatform;
use App\Services\DepartmentDirectory;
use App\Services\DocumentPdfRenderer;
use App\Services\PersonnelDirectory;
use App\Services\PortalIdentityService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class PortalController extends Controller
{
    public function __construct(
        private readonly DepartmentDirectory $departmentDirectory,
        private readonly DocumentPdfRenderer $documentPdfRenderer,
        private readonly PersonnelDirectory $personnelDirectory,
        private readonly PortalIdentityService $portalIdentityService,
    ) {
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
            'login_role' => ['nullable', Rule::in(['officer'])],
        ]);

        $buasriId = Str::lower(Str::before(trim($credentials['buasri_id']), '@'));

        if (! preg_match('/^[a-z0-9._-]+$/', $buasriId)) {
            return back()->withInput($request->only('buasri_id'))
                ->withErrors(['buasri_id' => 'รูปแบบ Buasri ID ไม่ถูกต้อง']);
        }

        try {
            $matched = $this->portalIdentityService->authenticate($buasriId, $credentials['password']);
        } catch (Throwable $exception) {
            logger()->error('Portal authentication dependency failed.', [
                'exception_class' => $exception::class,
            ]);

            return back()->withInput($request->only('buasri_id'))
                ->withErrors(['buasri_id' => 'ระบบยืนยันตัวตนหรือฐานข้อมูลบุคลากรไม่พร้อมใช้งาน']);
        }

        if (! $matched) {
            return back()->withInput($request->only('buasri_id'))
                ->withErrors(['buasri_id' => 'Buasri ID หรือรหัสผ่านไม่ถูกต้อง']);
        }

        $requestedRole = config('course-workflow.dev_role_switcher')
            ? ($credentials['login_role'] ?? null)
            : null;
        if ($requestedRole !== null) {
            if (! in_array($requestedRole, $matched['available_roles'] ?? [], true)) {
                return back()->withInput($request->only('buasri_id', 'login_role'))
                    ->withErrors(['login_role' => 'บัญชีนี้ไม่มีสิทธิ์สำหรับบทบาทที่เลือก']);
            }

            $matched['role'] = $requestedRole;
            $matched['label'] = 'เจ้าหน้าที่';
        }
        unset($matched['available_roles']);

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

    public function deanReviewConfirmation(int $id, ?string $decision = null)
    {
        $courseRequest = CourseRequest::query()->with($this->recordRelations())->findOrFail($id);
        $record = $this->toRecord($courseRequest);
        $linkExpiresAt = now()->addMinutes(30);
        $decisionUrl = URL::temporarySignedRoute(
            'dean.review.decide',
            $linkExpiresAt,
            ['id' => $courseRequest->request_id],
        );
        $documentUrl = URL::temporarySignedRoute(
            'dean.review.document',
            $linkExpiresAt,
            ['id' => $courseRequest->request_id],
        );
        $additionalDocumentUrls = collect($record['additional_files'] ?? [])
            ->mapWithKeys(fn (array $file) => [
                $file['document_id'] => URL::temporarySignedRoute(
                    'dean.review.attachment',
                    $linkExpiresAt,
                    [
                        'id' => $courseRequest->request_id,
                        'document' => $file['document_id'],
                    ],
                ),
            ])
            ->all();
        $studentRosterDocId = $record['student_roster_versions'][0]['document_id'] ?? null;
        $studentRosterUrl = $studentRosterDocId
            ? URL::temporarySignedRoute(
                'dean.review.attachment',
                $linkExpiresAt,
                ['id' => $courseRequest->request_id, 'document' => $studentRosterDocId],
            )
            : null;

        return view('portal.dean-review', [
            'courseRequest' => $courseRequest,
            'record' => $record,
            'decisionUrl' => $decisionUrl,
            'documentUrl' => $documentUrl,
            'additionalDocumentUrls' => $additionalDocumentUrls,
            'studentRosterUrl' => $studentRosterUrl,
            'alreadyProcessed' => $courseRequest->status !== 'PENDING_DEAN_REVIEW',
        ]);
    }

    public function deanReviewDocument(int $id)
    {
        $courseRequest = CourseRequest::query()->with($this->recordRelations())->findOrFail($id);
        $record = $this->toRecord($courseRequest);

        return $this->generatedPdfResponse(
            $record,
            $this->requestDocumentFilename($courseRequest->request_no, $courseRequest->form_type_code),
            false,
        );
    }

    public function deanReviewAttachment(int $id, int $document)
    {
        $courseRequest = CourseRequest::query()->findOrFail($id);
        $courseDocument = $courseRequest->documents()
            ->whereKey($document)
            ->whereIn('document_type_code', ['ADDITIONAL_DOCUMENT', 'STUDENT_ROSTER'])
            ->firstOrFail();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        return $disk->download(
            $courseDocument->storage_key,
            $courseDocument->original_filename,
        );
    }

    public function deanReviewDecision(Request $request, int $id, ?string $decision = null)
    {
        $request->merge(['decision' => $decision ?? $request->input('decision')]);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => 'required_if:decision,reject|nullable|string|max:3000',
        ], [
            'reason.required' => 'กรุณาระบุเหตุผลที่ไม่รับรอง',
        ]);
        $decision = $data['decision'];

        $result = DB::connection('course133')->transaction(function () use ($id, $decision, $data) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);

            if ($courseRequest->status !== 'PENDING_DEAN_REVIEW') {
                return ['request' => $courseRequest, 'processed' => false];
            }

            $deanPersonId = (int) $courseRequest->dean_pers_id;
            abort_unless($deanPersonId > 0, 409);
            $deanActor = $this->personActor($deanPersonId, 'คณบดี');
            $status = $decision === 'approve' ? 'UNDER_OFFICER_REVIEW' : 'REJECTED';

            DeanReview::create([
                'request_id' => $courseRequest->request_id,
                'dean_pers_id' => $deanPersonId,
                'decision' => $decision === 'approve' ? 'APPROVED' : 'REJECTED',
                'comment' => $decision === 'approve' ? null : trim($data['reason']),
                'decided_at' => now(),
            ]);
            $courseRequest->update(['status' => $status]);
            $this->addHistory($courseRequest, $status, $deanPersonId, 'DEAN');

            if ($decision === 'approve') {
                $this->queueRoleNotifications($courseRequest, 'OFFICER_REVIEW_REQUIRED', 'OFFICER', $deanActor);
            } else {
                $this->queueNotification($courseRequest, 'DEAN_REJECTED', $this->requesterRecipient($courseRequest), $deanActor);
            }

            return ['request' => $courseRequest, 'processed' => true];
        });

        $courseRequest = $result['request'];
        $confirmationUrl = URL::temporarySignedRoute(
            'dean.review.confirm',
            now()->addMinutes(30),
            ['id' => $courseRequest->request_id],
        );
        $redirect = redirect()->to($confirmationUrl);

        if (! $result['processed']) {
            return $redirect;
        }

        return $redirect->with('success', $decision === 'approve'
            ? 'รับรองคำร้องเรียบร้อยแล้ว<br>ระบบส่งต่อให้เจ้าหน้าที่ตรวจสอบแล้ว'
            : 'บันทึกผลไม่รับรองเรียบร้อยแล้ว<br>ระบบแจ้งผู้ยื่นคำร้องแล้ว');
    }

    public function home(Request $request)
    {
        $role = $this->actor($request)['role'];

        if ($role !== 'user') {
            return redirect()->route($this->homeRoute($role));
        }

        return $this->index($request, 'user');
    }

    public function index(Request $request, string $role = 'user')
    {
        $query = CourseRequest::query()->with($this->recordRelations());

        if ($role === 'user') {
            $query->where('requester_pers_id', $this->actor($request)['pers_id'])
                  ->orderByDesc('created_at');
        } elseif ($role === 'officer') {
            $query->whereIn('status_code', ['UNDER_OFFICER_REVIEW', 'COMPLETED', 'REJECTED'])
                  ->orderByRaw("CASE WHEN [status_code] = 'UNDER_OFFICER_REVIEW' THEN 0 WHEN [status_code] = 'REJECTED' THEN 2 ELSE 1 END")
                  ->orderByRaw("CASE WHEN [status_code] = 'UNDER_OFFICER_REVIEW' THEN [created_at] END ASC")
                  ->orderByDesc('created_at');
        } else {
            $query->whereIn('status_code', ['COMPLETED', 'REJECTED'])
                  ->orderByDesc('created_at');
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, CourseRequest> $collection */
        $collection = $query->get();
        $records = $collection->map(fn(CourseRequest $courseRequest) => $this->toRecord($courseRequest));
        $statuses = config('course-workflow.statuses');

        return view('portal.requests', compact('records', 'role', 'statuses'));
    }

    public function form(Request $request, int $step = 1)
    {
        abort_unless(in_array($step, [1, 2, 3, 4], true), 404);

        $personId = (int) $this->actor($request)['pers_id'];
        $data = $request->session()->get('portal.draft', []);
        $person = $this->personnelDirectory->findByPersonId($personId);
        if ($person !== null) {
            $data = array_replace($this->personnelDirectory->coordinatorDefaults($person), $data);
        }
        $data['requester_unit'] = $this->personDepartmentName($personId);
        if ($step === 4 && empty($data['id']) && empty($data['request_no'])) {
            $data['request_no'] = $this->nextRequestNumber();
            $request->session()->put('portal.draft.request_no', $data['request_no']);
        }
        $deanOptions = $step === 4
            ? $this->websiteDeanOptionsForTargetDepartment((int) ($data['target_dept_id'] ?? 0))
            : [];
        if ($step === 4 && empty($data['dean_pers_id']) && $deanOptions !== []) {
            $data['dean_pers_id'] = (int) array_key_first($deanOptions);
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        return view('portal.form-steps', [
            'step' => $step,
            'data' => $data,
            'formName' => 'แบบฟอร์มขอสร้างรายวิชาในระบบ SWU Moodle Academy',
            'steps' => config('course-workflow.steps'),
            'projectTypes' => ProjectType::query()->where('is_active', 'Y')->orderByRaw("CASE WHEN project_type_code = 'OTHER' THEN 1 ELSE 0 END")->orderBy('project_type_code')->pluck('project_type_name_th', 'project_type_code'),
            'categories' => CourseCategory::query()->where('is_active', 'Y')->orderByRaw("CASE WHEN category_code = 'OTHER' THEN 1 ELSE 0 END")->orderBy('category_code')->pluck('category_name_th', 'category_code'),
            'departments' => $this->departmentDirectory->options(),
            'subdepartments' => $this->departmentDirectory->subdepartmentOptions(),
            'subdepartmentParents' => $this->departmentDirectory->subdepartmentParents(),
            'deanOptions' => $deanOptions,
        ]);
    }

    public function websiteForm(Request $request, int $step = 1)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $personId = (int) $this->actor($request)['pers_id'];
        $data = $request->session()->get('portal.website_draft', []);
        $person = $this->personnelDirectory->findByPersonId($personId);
        if ($person !== null) {
            $defaults = $this->personnelDirectory->coordinatorDefaults($person);
            $contactDefaults = [
                'contact_first' => $defaults['coordinator_first'] ?? '',
                'contact_last' => $defaults['coordinator_last'] ?? '',
                'contact_position' => $defaults['coordinator_position'] ?? '',
                'contact_phone' => $defaults['coordinator_phone'] ?? '',
                'contact_email' => $defaults['coordinator_email'] ?? '',
            ];
            $data = array_replace($contactDefaults, $data);
        }
        $data['requester_unit'] = $this->personDepartmentName($personId);
        $data['form_type_code'] = 'CC-ITED-01';

        if ($step === 3 && empty($data['id']) && empty($data['request_no'])) {
            $data['request_no'] = $this->nextRequestNumber();
            $request->session()->put('portal.website_draft.request_no', $data['request_no']);
        }

        $deanOptions = $step === 3
            ? $this->websiteDeanOptionsForTargetDepartment((int) ($data['target_dept_id'] ?? 0))
            : [];
        if ($step === 3 && empty($data['dean_pers_id']) && $deanOptions !== []) {
            $data['dean_pers_id'] = (int) array_key_first($deanOptions);
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        return view('portal.website-form-steps', [
            'step' => $step,
            'data' => $data,
            'formName' => 'แบบฟอร์มขอใช้พื้นที่สำหรับจัดทำเว็บไซต์',
            'steps' => config('course-workflow.website_steps'),
            'platforms' => WebsitePlatform::query()->where('is_active', 'Y')->where('platform_code', '<>', 'OTHER')->orderBy('sort_order')->pluck('platform_name_th', 'platform_code'),
            'departments' => $this->departmentDirectory->options(),
            'subdepartments' => $this->departmentDirectory->subdepartmentOptions(),
            'subdepartmentParents' => $this->departmentDirectory->subdepartmentParents(),
            'deanOptions' => $deanOptions,
        ]);
    }

    public function websiteContactChangeForm(Request $request, int $step = 1)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $personId = (int) $this->actor($request)['pers_id'];
        $data = $request->session()->get('portal.website_contact_change_draft', []);
        $person = $this->personnelDirectory->findByPersonId($personId);
        if ($person !== null) {
            $defaults = $this->personnelDirectory->coordinatorDefaults($person);
            $data = array_replace([
                'new_contact_first' => $defaults['coordinator_first'] ?? '',
                'new_contact_last' => $defaults['coordinator_last'] ?? '',
                'new_contact_position' => $defaults['coordinator_position'] ?? '',
                'new_contact_phone' => $defaults['coordinator_phone'] ?? '',
                'new_contact_email' => $defaults['coordinator_email'] ?? '',
            ], $data);
        }
        $data['requester_unit'] = $this->personDepartmentName($personId);
        $data['form_type_code'] = 'CC-ITED-02';

        if ($step === 3 && empty($data['id']) && empty($data['request_no'])) {
            $data['request_no'] = $this->nextRequestNumber();
            $request->session()->put('portal.website_contact_change_draft.request_no', $data['request_no']);
        }

        $deanOptions = $step === 3
            ? $this->websiteDeanOptionsForTargetDepartment((int) ($data['target_dept_id'] ?? 0))
            : [];
        if ($step === 3 && empty($data['dean_pers_id']) && $deanOptions !== []) {
            $data['dean_pers_id'] = (int) array_key_first($deanOptions);
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        return view('portal.website-contact-change-form-steps', [
            'step' => $step,
            'data' => $data,
            'formName' => 'แบบฟอร์มขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์',
            'steps' => config('course-workflow.website_contact_change_steps'),
            'departments' => $this->departmentDirectory->options(),
            'subdepartments' => $this->departmentDirectory->subdepartmentOptions(),
            'subdepartmentParents' => $this->departmentDirectory->subdepartmentParents(),
            'deanOptions' => $deanOptions,
        ]);
    }

    public function saveWebsiteContactChangeStep(Request $request, int $step)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $required = $request->input('navigation') === 'back' ? 'nullable' : 'required';
        $activeDeanPersonIds = $step === 3
            ? $this->activeWebsiteDeanPersonIds((int) $request->session()->get('portal.website_contact_change_draft.target_dept_id', 0))
            : [];
        $deanRequired = $required === 'required' && ($activeDeanPersonIds !== [] || $this->usesTestNotificationRecipient()) ? 'required' : 'nullable';
        $rules = match ($step) {
            1 => [
                'target_dept_id' => [$required, 'integer', Rule::in($this->departmentDirectory->options()->keys()->all())],
                'target_subdept_id' => ['nullable', 'integer', Rule::in($this->departmentDirectory->subdepartmentsFor((int) $request->input('target_dept_id'))->keys()->all())],
                'website_name' => "$required|string|max:500",
                'domain_url' => "$required|string|max:1000",
            ],
            2 => [
                'current_contact_first' => "$required|string|max:100",
                'current_contact_last' => "$required|string|max:100",
                'new_contact_first' => "$required|string|max:100",
                'new_contact_last' => "$required|string|max:100",
                'new_contact_position' => "$required|string|max:200",
                'new_contact_email' => "$required|email|max:254",
                'new_contact_phone' => "$required|string|max:50",
            ],
            3 => ['dean_pers_id' => [$deanRequired, 'integer', Rule::in($activeDeanPersonIds)]],
            default => [],
        };
        $rules['additional_documents'] = 'nullable|array|max:5';
        $rules['additional_documents.*'] = 'file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10240';
        $rules['remove_additional'] = 'nullable|array';
        $rules['remove_additional.*'] = 'integer|min:0';

        $validated = $request->validate($rules, [
            'required' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
            'email' => 'กรุณาระบุอีเมลให้ถูกต้อง',
            'mimes' => 'ประเภทไฟล์ไม่ถูกต้อง',
            'max' => 'ข้อมูลหรือไฟล์มีขนาดเกินที่กำหนด',
            'dean_pers_id.required' => 'กรุณาเลือกคณบดี ผู้อำนวยการ หรือหัวหน้าส่วนงาน',
            'dean_pers_id.in' => 'ผู้รับรองที่เลือกไม่ได้อยู่ในรายชื่อปัจจุบันของส่วนงาน',
        ]);

        if ($step === 2 && $required === 'required') {
            $currentContactName = Str::lower(Str::squish(
                ($validated['current_contact_first'] ?? '').' '.($validated['current_contact_last'] ?? ''),
            ));
            $newContactName = Str::lower(Str::squish(
                ($validated['new_contact_first'] ?? '').' '.($validated['new_contact_last'] ?? ''),
            ));

            if ($currentContactName === $newContactName) {
                throw ValidationException::withMessages([
                    'new_contact_first' => 'ผู้ดูแลเว็บไซต์คนใหม่ต้องไม่เป็นบุคคลเดียวกับผู้ดูแลเว็บไซต์เดิม',
                ]);
            }
        }

        $removeAdditional = $validated['remove_additional'] ?? [];
        unset($validated['additional_documents'], $validated['remove_additional']);

        if (isset($validated['target_dept_id'])) {
            $validated['target_dept_id'] = (int) $validated['target_dept_id'];
            $validated['unit'] = $this->departmentDirectory->name($validated['target_dept_id']);
            if (isset($validated['target_subdept_id'])) {
                $validated['target_subdept_id'] = (int) $validated['target_subdept_id'];
                $validated['subunit'] = $this->departmentDirectory->subdepartmentName($validated['target_subdept_id']);
            } else {
                $validated['target_subdept_id'] = null;
                $validated['subunit'] = null;
            }
        }

        $existingDraft = $request->session()->get('portal.website_contact_change_draft', []);
        if ($step === 1 && isset($validated['target_dept_id']) && (int) ($existingDraft['target_dept_id'] ?? 0) !== (int) $validated['target_dept_id']) {
            unset($existingDraft['dean_pers_id'], $existingDraft['dean_name']);
        }
        $data = array_replace($existingDraft, $validated, ['form_type_code' => 'CC-ITED-02']);
        if (isset($validated['dean_pers_id'])) {
            $data['dean_pers_id'] = (int) $validated['dean_pers_id'];
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        $additionalFiles = $this->draftAdditionalFiles($existingDraft);
        $removedDocumentIds = $existingDraft['removed_additional_document_ids'] ?? [];
        $pendingFilesToDelete = [];
        rsort($removeAdditional);
        foreach (array_unique($removeAdditional) as $index) {
            if (! isset($additionalFiles[$index])) continue;
            $file = $additionalFiles[$index];
            if (($file['additional_pending'] ?? false) === true) $pendingFilesToDelete[] = $file;
            elseif (isset($file['document_id'])) $removedDocumentIds[] = (int) $file['document_id'];
            unset($additionalFiles[$index]);
        }
        $additionalFiles = array_values($additionalFiles);
        $incomingAdditional = array_values(array_filter((array) $request->file('additional_documents', [])));
        if (count($additionalFiles) + count($incomingAdditional) > 5) {
            throw ValidationException::withMessages(['additional_documents' => 'อัปโหลดเอกสารเพิ่มเติมได้ไม่เกิน 5 ไฟล์']);
        }
        foreach ($pendingFilesToDelete as $file) $this->deletePendingFile($file, 'additional');
        foreach ($incomingAdditional as $file) $additionalFiles[] = $this->storeDraftFile($file, 'additional');
        $data['additional_files'] = $additionalFiles;
        $data['removed_additional_document_ids'] = array_values(array_unique($removedDocumentIds));
        $request->session()->put('portal.website_contact_change_draft', $data);

        if ($request->input('navigation') === 'back') {
            $request->session()->forget("portal.website_contact_change_completed.$step");
            return redirect()->route($step === 1 ? 'requests.index' : 'website-contact-change-requests.form', $step === 1 ? [] : $step - 1);
        }
        if ($step < 3) {
            $request->session()->put("portal.website_contact_change_completed.$step", true);
            return redirect()->route('website-contact-change-requests.form', $step + 1);
        }
        foreach ([1, 2] as $completed) {
            if (! $request->session()->get("portal.website_contact_change_completed.$completed")) {
                return redirect()->route('website-contact-change-requests.form', $completed)->withErrors(['form' => 'กรุณากรอกข้อมูลส่วนนี้ให้ครบก่อนส่งคำร้อง']);
            }
        }

        $deletedAdditionalPaths = [];
        try {
            DB::connection('course133')->transaction(function () use ($request, $data, &$deletedAdditionalPaths) {
                $actor = $this->actor($request);
                $courseRequest = isset($data['id']) ? CourseRequest::query()->lockForUpdate()->findOrFail($data['id']) : new CourseRequest(['request_no' => $data['request_no'] ?? $this->nextRequestNumber()]);
                abort_if($courseRequest->exists, 409);
                $this->persistWebsiteContactChangeRequestData($courseRequest, $data, (int) $actor['pers_id'], 'PENDING_DEAN_REVIEW');
                if ($courseRequest->exists && ! empty($data['removed_additional_document_ids'])) {
                    $documents = $courseRequest->documents()->where('document_type_code', 'ADDITIONAL_DOCUMENT')->whereIn('document_id', $data['removed_additional_document_ids'])->get();
                    foreach ($documents as $document) { $deletedAdditionalPaths[] = $document->storage_key; $document->delete(); }
                }
                foreach ($this->draftAdditionalFiles($data) as $additional) {
                    if (($additional['additional_pending'] ?? false) && isset($additional['additional_path'])) $this->createDocument($courseRequest, 'ADDITIONAL_DOCUMENT', $additional, 'additional', $actor['pers_id']);
                }
                $this->addHistory($courseRequest, 'PENDING_DEAN_REVIEW', $actor['pers_id'], 'REQUESTER');
                $this->queueNotification($courseRequest, 'DEAN_REVIEW_REQUIRED', $this->personRecipient((int) $courseRequest->dean_pers_id, 'ผู้รับรอง'), $actor);
            });
        } catch (Throwable $exception) {
            $this->deletePendingFiles($data);
            throw $exception;
        }
        foreach ($deletedAdditionalPaths as $path) Storage::disk('local')->delete($path);
        $request->session()->forget(['portal.website_contact_change_draft', 'portal.website_contact_change_completed']);
        return redirect()->route('requests.index')->with('success', 'ส่งคำร้องเรียบร้อยแล้ว<br>คำร้องอยู่ในสถานะรอการรับรอง');
    }

    public function clearWebsiteContactChangeStep(Request $request, int $step)
    {
        $keys = match ($step) {
            1 => ['target_dept_id', 'unit', 'target_subdept_id', 'subunit', 'website_name', 'domain_url'],
            2 => ['current_contact_first', 'current_contact_last', 'new_contact_first', 'new_contact_last', 'new_contact_position', 'new_contact_email', 'new_contact_phone'],
            3 => ['dean_pers_id', 'dean_name'],
            default => [],
        };
        $draft = $request->session()->get('portal.website_contact_change_draft', []);
        foreach ($keys as $key) unset($draft[$key]);
        $request->session()->put('portal.website_contact_change_draft', $draft);
        return back();
    }

    public function emeetingForm(Request $request, int $step = 1)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $personId = (int) $this->actor($request)['pers_id'];
        $data = $request->session()->get('portal.emeeting_draft', []);
        $person = $this->personnelDirectory->findByPersonId($personId);
        if ($person !== null) {
            $defaults = $this->personnelDirectory->coordinatorDefaults($person);
            $data = array_replace([
                'contact_first' => $defaults['coordinator_first'] ?? '',
                'contact_last' => $defaults['coordinator_last'] ?? '',
                'contact_position' => $defaults['coordinator_position'] ?? '',
                'contact_phone' => $defaults['coordinator_phone'] ?? '',
                'contact_email' => $defaults['coordinator_email'] ?? '',
            ], $data);
        }
        $data['requester_unit'] = $this->personDepartmentName($personId);
        $data['form_type_code'] = 'CC-ITED-05';

        if ($step === 3 && empty($data['id']) && empty($data['request_no'])) {
            $data['request_no'] = $this->nextRequestNumber();
            $request->session()->put('portal.emeeting_draft.request_no', $data['request_no']);
        }

        $deanOptions = $step === 3
            ? $this->websiteDeanOptionsForTargetDepartment((int) ($data['target_dept_id'] ?? 0))
            : [];
        if ($step === 3 && empty($data['dean_pers_id']) && $deanOptions !== []) {
            $data['dean_pers_id'] = (int) array_key_first($deanOptions);
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        return view('portal.emeeting-form-steps', [
            'step' => $step,
            'data' => $data,
            'formName' => 'แบบฟอร์มขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงานบนระบบ e-Meeting',
            'steps' => config('course-workflow.emeeting_steps'),
            'departments' => $this->departmentDirectory->options(),
            'subdepartments' => $this->departmentDirectory->subdepartmentOptions(),
            'subdepartmentParents' => $this->departmentDirectory->subdepartmentParents(),
            'deanOptions' => $deanOptions,
        ]);
    }

    public function saveEmeetingStep(Request $request, int $step)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $required = $request->input('navigation') === 'back' ? 'nullable' : 'required';
        $activeDeanPersonIds = $step === 3
            ? $this->activeWebsiteDeanPersonIds((int) $request->session()->get('portal.emeeting_draft.target_dept_id', 0))
            : [];
        $deanRequired = $required === 'required' && ($activeDeanPersonIds !== [] || $this->usesTestNotificationRecipient()) ? 'required' : 'nullable';
        $rules = match ($step) {
            1 => [
                'target_dept_id' => [$required, 'integer', Rule::in($this->departmentDirectory->options()->keys()->all())],
                'target_subdept_id' => ['nullable', 'integer', Rule::in($this->departmentDirectory->subdepartmentsFor((int) $request->input('target_dept_id'))->keys()->all())],
                'action_type' => [$required, Rule::in(['NEW_ACCESS', 'CHANGE_CONTACT'])],
                'current_contact_first' => $required === 'required'
                    ? ['nullable', 'required_if:action_type,CHANGE_CONTACT', 'string', 'max:100']
                    : ['nullable', 'string', 'max:100'],
                'current_contact_last' => $required === 'required'
                    ? ['nullable', 'required_if:action_type,CHANGE_CONTACT', 'string', 'max:100']
                    : ['nullable', 'string', 'max:100'],
            ],
            2 => [
                'contact_first' => "$required|string|max:100",
                'contact_last' => "$required|string|max:100",
                'contact_position' => "$required|string|max:200",
                'contact_email' => "$required|email|max:254",
                'contact_phone' => "$required|string|max:50",
            ],
            3 => ['dean_pers_id' => [$deanRequired, 'integer', Rule::in($activeDeanPersonIds)]],
            default => [],
        };
        $rules['additional_documents'] = 'nullable|array|max:5';
        $rules['additional_documents.*'] = 'file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10240';
        $rules['remove_additional'] = 'nullable|array';
        $rules['remove_additional.*'] = 'integer|min:0';

        $existingDraft = $request->session()->get('portal.emeeting_draft', []);
        $validated = $request->validate($rules, [
            'required' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
            'required_if' => 'กรุณากรอกข้อมูลผู้ดูแลกรอบการประชุมเดิมให้ครบถ้วน',
            'email' => 'กรุณาระบุอีเมลให้ถูกต้อง',
            'mimes' => 'ประเภทไฟล์ไม่ถูกต้อง',
            'max' => 'ข้อมูลหรือไฟล์มีขนาดเกินที่กำหนด',
            'dean_pers_id.required' => 'กรุณาเลือกคณบดี ผู้อำนวยการ หรือหัวหน้าส่วนงาน',
            'dean_pers_id.in' => 'ผู้รับรองที่เลือกไม่ได้อยู่ในรายชื่อปัจจุบันของส่วนงาน',
        ]);
        $removeAdditional = $validated['remove_additional'] ?? [];
        unset($validated['additional_documents'], $validated['remove_additional']);
        if (isset($validated['target_dept_id'])) {
            $validated['target_dept_id'] = (int) $validated['target_dept_id'];
            $validated['unit'] = $this->departmentDirectory->name($validated['target_dept_id']);
            if (isset($validated['target_subdept_id'])) {
                $validated['target_subdept_id'] = (int) $validated['target_subdept_id'];
                $validated['subunit'] = $this->departmentDirectory->subdepartmentName($validated['target_subdept_id']);
            } else {
                $validated['target_subdept_id'] = null;
                $validated['subunit'] = null;
            }
        }
        if ($step === 1 && isset($validated['target_dept_id']) && (int) ($existingDraft['target_dept_id'] ?? 0) !== (int) $validated['target_dept_id']) {
            unset($existingDraft['dean_pers_id'], $existingDraft['dean_name']);
        }
        if ($step === 1 && ($validated['action_type'] ?? null) === 'NEW_ACCESS') {
            unset($existingDraft['current_contact_first'], $existingDraft['current_contact_last']);
        }
        $data = array_replace($existingDraft, $validated, ['form_type_code' => 'CC-ITED-05']);
        if (isset($validated['dean_pers_id'])) {
            $data['dean_pers_id'] = (int) $validated['dean_pers_id'];
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        $additionalFiles = $this->draftAdditionalFiles($existingDraft);
        $removedDocumentIds = $existingDraft['removed_additional_document_ids'] ?? [];
        $pendingFilesToDelete = [];
        rsort($removeAdditional);
        foreach (array_unique($removeAdditional) as $index) {
            if (! isset($additionalFiles[$index])) continue;
            $file = $additionalFiles[$index];
            if (($file['additional_pending'] ?? false) === true) $pendingFilesToDelete[] = $file;
            elseif (isset($file['document_id'])) $removedDocumentIds[] = (int) $file['document_id'];
            unset($additionalFiles[$index]);
        }
        $additionalFiles = array_values($additionalFiles);
        $incomingAdditional = array_values(array_filter((array) $request->file('additional_documents', [])));
        if (count($additionalFiles) + count($incomingAdditional) > 5) {
            throw ValidationException::withMessages(['additional_documents' => 'อัปโหลดเอกสารเพิ่มเติมได้ไม่เกิน 5 ไฟล์']);
        }
        foreach ($pendingFilesToDelete as $file) $this->deletePendingFile($file, 'additional');
        foreach ($incomingAdditional as $file) $additionalFiles[] = $this->storeDraftFile($file, 'additional');
        $data['additional_files'] = $additionalFiles;
        $data['removed_additional_document_ids'] = array_values(array_unique($removedDocumentIds));
        $request->session()->put('portal.emeeting_draft', $data);

        if ($request->input('navigation') === 'back') {
            $request->session()->forget("portal.emeeting_completed.$step");
            return redirect()->route($step === 1 ? 'requests.index' : 'emeeting-requests.form', $step === 1 ? [] : $step - 1);
        }
        if ($step < 3) {
            $request->session()->put("portal.emeeting_completed.$step", true);
            return redirect()->route('emeeting-requests.form', $step + 1);
        }
        foreach ([1, 2] as $completed) {
            if (! $request->session()->get("portal.emeeting_completed.$completed")) {
                return redirect()->route('emeeting-requests.form', $completed)->withErrors(['form' => 'กรุณากรอกข้อมูลส่วนนี้ให้ครบก่อนส่งคำร้อง']);
            }
        }

        try {
            DB::connection('course133')->transaction(function () use ($request, $data) {
                $actor = $this->actor($request);
                $courseRequest = new CourseRequest(['request_no' => $data['request_no'] ?? $this->nextRequestNumber()]);
                $this->persistEmeetingRequestData($courseRequest, $data, (int) $actor['pers_id'], 'PENDING_DEAN_REVIEW');
                foreach ($this->draftAdditionalFiles($data) as $additional) {
                    if (($additional['additional_pending'] ?? false) && isset($additional['additional_path'])) $this->createDocument($courseRequest, 'ADDITIONAL_DOCUMENT', $additional, 'additional', $actor['pers_id']);
                }
                $this->addHistory($courseRequest, 'PENDING_DEAN_REVIEW', $actor['pers_id'], 'REQUESTER');
                $this->queueNotification($courseRequest, 'DEAN_REVIEW_REQUIRED', $this->personRecipient((int) $courseRequest->dean_pers_id, 'ผู้รับรอง'), $actor);
            });
        } catch (Throwable $exception) {
            $this->deletePendingFiles($data);
            throw $exception;
        }
        $request->session()->forget(['portal.emeeting_draft', 'portal.emeeting_completed']);
        return redirect()->route('requests.index')->with('success', 'ส่งคำร้องเรียบร้อยแล้ว<br>คำร้องอยู่ในสถานะรอการรับรอง');
    }

    public function clearEmeetingStep(Request $request, int $step)
    {
        $keys = match ($step) {
            1 => ['target_dept_id', 'unit', 'target_subdept_id', 'subunit', 'action_type', 'current_contact_first', 'current_contact_last'],
            2 => ['contact_first', 'contact_last', 'contact_position', 'contact_email', 'contact_phone'],
            3 => ['dean_pers_id', 'dean_name'],
            default => [],
        };
        $draft = $request->session()->get('portal.emeeting_draft', []);
        foreach ($keys as $key) unset($draft[$key]);
        $request->session()->put('portal.emeeting_draft', $draft);
        return back();
    }

    public function websiteStorageForm(Request $request, int $step = 1)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $personId = (int) $this->actor($request)['pers_id'];
        $data = $request->session()->get('portal.website_storage_draft', []);
        $person = $this->personnelDirectory->findByPersonId($personId);
        if ($person !== null) {
            $defaults = $this->personnelDirectory->coordinatorDefaults($person);
            $data = array_replace([
                'contact_first' => $defaults['coordinator_first'] ?? '',
                'contact_last' => $defaults['coordinator_last'] ?? '',
                'contact_position' => $defaults['coordinator_position'] ?? '',
                'contact_phone' => $defaults['coordinator_phone'] ?? '',
                'contact_email' => $defaults['coordinator_email'] ?? '',
            ], $data);
        }
        $data['requester_unit'] = $this->personDepartmentName($personId);
        $data['form_type_code'] = 'CC-ITED-04';
        $data['requested_space_gb'] = '1.00';

        if ($step === 3 && empty($data['id']) && empty($data['request_no'])) {
            $data['request_no'] = $this->nextRequestNumber();
            $request->session()->put('portal.website_storage_draft.request_no', $data['request_no']);
        }

        $deanOptions = $step === 3
            ? $this->websiteDeanOptionsForTargetDepartment((int) ($data['target_dept_id'] ?? 0))
            : [];
        if ($step === 3 && empty($data['dean_pers_id']) && $deanOptions !== []) {
            $data['dean_pers_id'] = (int) array_key_first($deanOptions);
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        return view('portal.website-storage-form-steps', [
            'step' => $step,
            'data' => $data,
            'formName' => 'แบบฟอร์มขอเพิ่มพื้นที่บนเว็บไซต์',
            'steps' => config('course-workflow.website_storage_steps'),
            'departments' => $this->departmentDirectory->options(),
            'subdepartments' => $this->departmentDirectory->subdepartmentOptions(),
            'subdepartmentParents' => $this->departmentDirectory->subdepartmentParents(),
            'deanOptions' => $deanOptions,
        ]);
    }

    public function saveWebsiteStorageStep(Request $request, int $step)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $required = $request->input('navigation') === 'back' ? 'nullable' : 'required';
        $activeDeanPersonIds = $step === 3
            ? $this->activeWebsiteDeanPersonIds((int) $request->session()->get('portal.website_storage_draft.target_dept_id', 0))
            : [];
        $deanRequired = $required === 'required' && ($activeDeanPersonIds !== [] || $this->usesTestNotificationRecipient()) ? 'required' : 'nullable';
        $rules = match ($step) {
            1 => [
                'target_dept_id' => [$required, 'integer', Rule::in($this->departmentDirectory->options()->keys()->all())],
                'target_subdept_id' => ['nullable', 'integer', Rule::in($this->departmentDirectory->subdepartmentsFor((int) $request->input('target_dept_id'))->keys()->all())],
                'website_name' => "$required|string|max:500",
                'domain_url' => "$required|string|max:1000",
            ],
            2 => [
                'contact_first' => "$required|string|max:100",
                'contact_last' => "$required|string|max:100",
                'contact_position' => "$required|string|max:200",
                'contact_email' => "$required|email|max:254",
                'contact_phone' => "$required|string|max:50",
            ],
            3 => ['dean_pers_id' => [$deanRequired, 'integer', Rule::in($activeDeanPersonIds)]],
            default => [],
        };
        $rules['additional_documents'] = 'nullable|array|max:5';
        $rules['additional_documents.*'] = 'file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10240';
        $rules['remove_additional'] = 'nullable|array';
        $rules['remove_additional.*'] = 'integer|min:0';

        $validated = $request->validate($rules, [
            'required' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
            'email' => 'กรุณาระบุอีเมลให้ถูกต้อง',
            'mimes' => 'ประเภทไฟล์ไม่ถูกต้อง',
            'max' => 'ข้อมูลหรือไฟล์มีขนาดเกินที่กำหนด',
            'dean_pers_id.required' => 'กรุณาเลือกคณบดี ผู้อำนวยการ หรือหัวหน้าส่วนงาน',
            'dean_pers_id.in' => 'ผู้รับรองที่เลือกไม่ได้อยู่ในรายชื่อปัจจุบันของส่วนงาน',
        ]);

        $removeAdditional = $validated['remove_additional'] ?? [];
        unset($validated['additional_documents'], $validated['remove_additional']);

        if (isset($validated['target_dept_id'])) {
            $validated['target_dept_id'] = (int) $validated['target_dept_id'];
            $validated['unit'] = $this->departmentDirectory->name($validated['target_dept_id']);
            if (isset($validated['target_subdept_id'])) {
                $validated['target_subdept_id'] = (int) $validated['target_subdept_id'];
                $validated['subunit'] = $this->departmentDirectory->subdepartmentName($validated['target_subdept_id']);
            } else {
                $validated['target_subdept_id'] = null;
                $validated['subunit'] = null;
            }
        }

        $existingDraft = $request->session()->get('portal.website_storage_draft', []);
        if ($step === 1 && isset($validated['target_dept_id']) && (int) ($existingDraft['target_dept_id'] ?? 0) !== (int) $validated['target_dept_id']) {
            unset($existingDraft['dean_pers_id'], $existingDraft['dean_name']);
        }
        $data = array_replace($existingDraft, $validated, [
            'form_type_code' => 'CC-ITED-04',
            'requested_space_gb' => '1.00',
        ]);
        if (isset($validated['dean_pers_id'])) {
            $data['dean_pers_id'] = (int) $validated['dean_pers_id'];
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        $additionalFiles = $this->draftAdditionalFiles($existingDraft);
        $removedDocumentIds = $existingDraft['removed_additional_document_ids'] ?? [];
        $pendingFilesToDelete = [];
        rsort($removeAdditional);
        foreach (array_unique($removeAdditional) as $index) {
            if (! isset($additionalFiles[$index])) continue;
            $file = $additionalFiles[$index];
            if (($file['additional_pending'] ?? false) === true) $pendingFilesToDelete[] = $file;
            elseif (isset($file['document_id'])) $removedDocumentIds[] = (int) $file['document_id'];
            unset($additionalFiles[$index]);
        }
        $additionalFiles = array_values($additionalFiles);
        $incomingAdditional = array_values(array_filter((array) $request->file('additional_documents', [])));
        if (count($additionalFiles) + count($incomingAdditional) > 5) {
            throw ValidationException::withMessages(['additional_documents' => 'อัปโหลดเอกสารเพิ่มเติมได้ไม่เกิน 5 ไฟล์']);
        }
        foreach ($pendingFilesToDelete as $file) $this->deletePendingFile($file, 'additional');
        foreach ($incomingAdditional as $file) $additionalFiles[] = $this->storeDraftFile($file, 'additional');
        $data['additional_files'] = $additionalFiles;
        $data['removed_additional_document_ids'] = array_values(array_unique($removedDocumentIds));
        $request->session()->put('portal.website_storage_draft', $data);

        if ($request->input('navigation') === 'back') {
            $request->session()->forget("portal.website_storage_completed.$step");
            return redirect()->route($step === 1 ? 'requests.index' : 'website-storage-requests.form', $step === 1 ? [] : $step - 1);
        }
        if ($step < 3) {
            $request->session()->put("portal.website_storage_completed.$step", true);
            return redirect()->route('website-storage-requests.form', $step + 1);
        }
        foreach ([1, 2] as $completed) {
            if (! $request->session()->get("portal.website_storage_completed.$completed")) {
                return redirect()->route('website-storage-requests.form', $completed)->withErrors(['form' => 'กรุณากรอกข้อมูลส่วนนี้ให้ครบก่อนส่งคำร้อง']);
            }
        }

        $deletedAdditionalPaths = [];
        try {
            DB::connection('course133')->transaction(function () use ($request, $data, &$deletedAdditionalPaths) {
                $actor = $this->actor($request);
                $courseRequest = isset($data['id']) ? CourseRequest::query()->lockForUpdate()->findOrFail($data['id']) : new CourseRequest(['request_no' => $data['request_no'] ?? $this->nextRequestNumber()]);
                abort_if($courseRequest->exists, 409);
                $this->persistWebsiteStorageRequestData($courseRequest, $data, (int) $actor['pers_id'], 'PENDING_DEAN_REVIEW');
                if ($courseRequest->exists && ! empty($data['removed_additional_document_ids'])) {
                    $documents = $courseRequest->documents()->where('document_type_code', 'ADDITIONAL_DOCUMENT')->whereIn('document_id', $data['removed_additional_document_ids'])->get();
                    foreach ($documents as $document) { $deletedAdditionalPaths[] = $document->storage_key; $document->delete(); }
                }
                foreach ($this->draftAdditionalFiles($data) as $additional) {
                    if (($additional['additional_pending'] ?? false) && isset($additional['additional_path'])) $this->createDocument($courseRequest, 'ADDITIONAL_DOCUMENT', $additional, 'additional', $actor['pers_id']);
                }
                $this->addHistory($courseRequest, 'PENDING_DEAN_REVIEW', $actor['pers_id'], 'REQUESTER');
                $this->queueNotification($courseRequest, 'DEAN_REVIEW_REQUIRED', $this->personRecipient((int) $courseRequest->dean_pers_id, 'ผู้รับรอง'), $actor);
            });
        } catch (Throwable $exception) {
            $this->deletePendingFiles($data);
            throw $exception;
        }
        foreach ($deletedAdditionalPaths as $path) Storage::disk('local')->delete($path);
        $request->session()->forget(['portal.website_storage_draft', 'portal.website_storage_completed']);
        return redirect()->route('requests.index')->with('success', 'ส่งคำร้องเรียบร้อยแล้ว<br>คำร้องอยู่ในสถานะรอการรับรอง');
    }

    public function clearWebsiteStorageStep(Request $request, int $step)
    {
        $keys = match ($step) {
            1 => ['target_dept_id', 'unit', 'target_subdept_id', 'subunit', 'website_name', 'domain_url'],
            2 => ['contact_first', 'contact_last', 'contact_position', 'contact_email', 'contact_phone'],
            3 => ['dean_pers_id', 'dean_name'],
            default => [],
        };
        $draft = $request->session()->get('portal.website_storage_draft', []);
        foreach ($keys as $key) unset($draft[$key]);
        $request->session()->put('portal.website_storage_draft', $draft);
        return back();
    }

    public function saveWebsiteStep(Request $request, int $step)
    {
        abort_unless(in_array($step, [1, 2, 3], true), 404);

        $required = $request->input('navigation') === 'back' ? 'nullable' : 'required';
        $activeDeanPersonIds = $step === 3
            ? $this->activeWebsiteDeanPersonIds((int) $request->session()->get('portal.website_draft.target_dept_id', 0))
            : [];
        $deanRequired = $required === 'required'
            && ($activeDeanPersonIds !== [] || $this->usesTestNotificationRecipient())
                ? 'required'
                : 'nullable';

        $rules = match ($step) {
            1 => [
                'target_dept_id' => [$required, 'integer', Rule::in($this->departmentDirectory->options()->keys()->all())],
                'target_subdept_id' => ['nullable', 'integer', Rule::in($this->departmentDirectory->subdepartmentsFor((int) $request->input('target_dept_id'))->keys()->all())],
                'website_name' => "$required|string|max:500",
                'site_scope_type' => [$required, Rule::in(['DEPARTMENT', 'PROJECT', 'CONFERENCE'])],
                'service_starts_on' => ($required === 'required' ? 'required_if:site_scope_type,PROJECT,CONFERENCE|' : '').'nullable|date',
                'service_ends_on' => ($required === 'required' ? 'required_if:site_scope_type,PROJECT,CONFERENCE|' : '').'nullable|date|after_or_equal:service_starts_on',
                'domain_url' => "$required|string|max:1000",
                'platform_code' => [$required, Rule::in(WebsitePlatform::query()->where('is_active', 'Y')->where('platform_code', '<>', 'OTHER')->pluck('platform_code')->all())],
                'additional_detail' => 'nullable|string|max:5000',
            ],
            2 => [
                'contact_first' => "$required|string|max:100",
                'contact_last' => "$required|string|max:100",
                'contact_position' => "$required|string|max:200",
                'contact_email' => "$required|email|max:254",
                'contact_phone' => "$required|string|max:50",
            ],
            3 => [
                'dean_pers_id' => [$deanRequired, 'integer', Rule::in($activeDeanPersonIds)],
            ],
            default => [],
        };
        $rules['additional_documents'] = 'nullable|array|max:5';
        $rules['additional_documents.*'] = 'file|mimes:pdf,doc,docx,xls,xlsx,csv|max:10240';
        $rules['remove_additional'] = 'nullable|array';
        $rules['remove_additional.*'] = 'integer|min:0';

        $validated = $request->validate($rules, [
            'required' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
            'required_if' => 'กรุณากรอกข้อมูลให้ครบถ้วน',
            'email' => 'กรุณาระบุอีเมลให้ถูกต้อง',
            'after_or_equal' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
            'mimes' => 'ประเภทไฟล์ไม่ถูกต้อง',
            'max' => 'ข้อมูลหรือไฟล์มีขนาดเกินที่กำหนด',
            'dean_pers_id.required' => 'กรุณาเลือกคณบดี ผู้อำนวยการ หรือหัวหน้าส่วนงาน',
            'dean_pers_id.in' => 'ผู้รับรองที่เลือกไม่ได้อยู่ในรายชื่อปัจจุบันของส่วนงาน',
        ]);

        $removeAdditional = $validated['remove_additional'] ?? [];
        unset($validated['additional_documents'], $validated['remove_additional']);

        if (isset($validated['target_dept_id'])) {
            $validated['target_dept_id'] = (int) $validated['target_dept_id'];
            $validated['unit'] = $this->departmentDirectory->name($validated['target_dept_id']);
            if (isset($validated['target_subdept_id'])) {
                $validated['target_subdept_id'] = (int) $validated['target_subdept_id'];
                $validated['subunit'] = $this->departmentDirectory->subdepartmentName($validated['target_subdept_id']);
            } else {
                $validated['target_subdept_id'] = null;
                $validated['subunit'] = null;
            }
        }
        if (($validated['site_scope_type'] ?? null) === 'DEPARTMENT') {
            $validated['service_starts_on'] = null;
            $validated['service_ends_on'] = null;
        }

        $existingDraft = $request->session()->get('portal.website_draft', []);
        if ($step === 1
            && isset($validated['target_dept_id'])
            && (int) ($existingDraft['target_dept_id'] ?? 0) !== (int) $validated['target_dept_id']) {
            unset($existingDraft['dean_pers_id'], $existingDraft['dean_name']);
        }
        $data = array_replace($existingDraft, $validated, ['form_type_code' => 'CC-ITED-01']);
        if ($step <= 2) {
        }
        if (isset($validated['dean_pers_id'])) {
            $data['dean_pers_id'] = (int) $validated['dean_pers_id'];
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
        }

        $additionalFiles = $this->draftAdditionalFiles($existingDraft);
        $removedDocumentIds = $existingDraft['removed_additional_document_ids'] ?? [];
        $pendingFilesToDelete = [];
        rsort($removeAdditional);
        foreach (array_unique($removeAdditional) as $index) {
            if (! isset($additionalFiles[$index])) {
                continue;
            }
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
            throw ValidationException::withMessages(['additional_documents' => 'อัปโหลดเอกสารเพิ่มเติมได้ไม่เกิน 5 ไฟล์']);
        }
        foreach ($pendingFilesToDelete as $file) {
            $this->deletePendingFile($file, 'additional');
        }
        foreach ($incomingAdditional as $file) {
            $additionalFiles[] = $this->storeDraftFile($file, 'additional');
        }
        $data['additional_files'] = $additionalFiles;
        $data['removed_additional_document_ids'] = array_values(array_unique($removedDocumentIds));
        $request->session()->put('portal.website_draft', $data);

        if ($request->input('navigation') === 'back') {
            $request->session()->forget("portal.website_completed.$step");

            return redirect()->route($step === 1 ? 'requests.index' : 'website-requests.form', $step === 1 ? [] : $step - 1);
        }
        if ($step < 3) {
            $request->session()->put("portal.website_completed.$step", true);

            return redirect()->route('website-requests.form', $step + 1);
        }
        foreach ([1, 2] as $completed) {
            if (! $request->session()->get("portal.website_completed.$completed")) {
                return redirect()->route('website-requests.form', $completed)->withErrors(['form' => 'กรุณากรอกข้อมูลส่วนนี้ให้ครบก่อนส่งคำร้อง']);
            }
        }

        $deletedAdditionalPaths = [];
        try {
            DB::connection('course133')->transaction(function () use ($request, $data, &$deletedAdditionalPaths) {
                $actor = $this->actor($request);
                $courseRequest = isset($data['id'])
                    ? CourseRequest::query()->lockForUpdate()->findOrFail($data['id'])
                    : new CourseRequest(['request_no' => $data['request_no'] ?? $this->nextRequestNumber()]);

                abort_if($courseRequest->exists, 409);

                $this->persistWebsiteRequestData($courseRequest, $data, (int) $actor['pers_id'], 'PENDING_DEAN_REVIEW');

                if ($courseRequest->exists && ! empty($data['removed_additional_document_ids'])) {
                    $documents = $courseRequest->documents()
                        ->where('document_type_code', 'ADDITIONAL_DOCUMENT')
                        ->whereIn('document_id', $data['removed_additional_document_ids'])
                        ->get();
                    foreach ($documents as $document) {
                        $deletedAdditionalPaths[] = $document->storage_key;
                        $document->delete();
                    }
                }
                foreach ($this->draftAdditionalFiles($data) as $additional) {
                    if (($additional['additional_pending'] ?? false) && isset($additional['additional_path'])) {
                        $this->createDocument($courseRequest, 'ADDITIONAL_DOCUMENT', $additional, 'additional', $actor['pers_id']);
                    }
                }
                $this->addHistory($courseRequest, 'PENDING_DEAN_REVIEW', $actor['pers_id'], 'REQUESTER');
                $this->queueNotification(
                    $courseRequest,
                    'DEAN_REVIEW_REQUIRED',
                    $this->personRecipient((int) $courseRequest->dean_pers_id, 'ผู้รับรอง'),
                    $actor,
                );
            });
        } catch (Throwable $exception) {
            $this->deletePendingFiles($data);
            throw $exception;
        }

        foreach ($deletedAdditionalPaths as $path) {
            Storage::disk('local')->delete($path);
        }
        $request->session()->forget(['portal.website_draft', 'portal.website_completed']);

        return redirect()->route('requests.index')->with('success', 'ส่งคำร้องเรียบร้อยแล้ว<br>คำร้องอยู่ในสถานะรอการรับรอง');
    }

    public function clearWebsiteStep(Request $request, int $step)
    {
        $keys = match ($step) {
            1 => ['target_dept_id', 'unit', 'target_subdept_id', 'subunit', 'website_name', 'site_scope_type', 'service_starts_on', 'service_ends_on', 'domain_url', 'platform_code', 'additional_detail'],
            2 => ['contact_first', 'contact_last', 'contact_position', 'contact_email', 'contact_phone'],
            3 => ['dean_pers_id', 'dean_name'],
            default => [],
        };
        $draft = $request->session()->get('portal.website_draft', []);
        foreach ($keys as $key) {
            unset($draft[$key]);
        }
        $request->session()->put('portal.website_draft', $draft);

        return back();
    }

    public function saveStep(Request $request, int $step)
    {
        abort_unless(in_array($step, [1, 2, 3, 4], true), 404);
        $required = $request->input('navigation') === 'back' ? 'nullable' : 'required';
        $activeDeanPersonIds = $step === 4
            ? $this->activeWebsiteDeanPersonIds((int) $request->session()->get('portal.draft.target_dept_id', 0))
            : [];
        $deanRequired = $required === 'required' && $activeDeanPersonIds !== [] ? 'required' : 'nullable';

        $rules = match ($step) {
            1 => [
                'target_dept_id' => [$required, 'integer', Rule::in($this->departmentDirectory->options()->keys()->all())],
                'target_subdept_id' => ['nullable', 'integer', Rule::in($this->departmentDirectory->subdepartmentsFor((int) $request->input('target_dept_id'))->keys()->all())],
                'project_name' => "$required|string|max:500",
                'project_type' => [$required, Rule::in(ProjectType::query()->where('is_active', 'Y')->pluck('project_type_code')->all())],
                'project_other' => ($required === 'required' ? 'required_if:project_type,OTHER|' : '') . 'nullable|string|max:500',
                'coordinator_first' => "$required|string|max:100",
                'coordinator_last' => "$required|string|max:100",
                'coordinator_position' => "$required|string|max:200",
                'coordinator_phone' => "$required|string|max:50",
                'coordinator_email' => "$required|string|max:254",
            ],
            2 => [
                'course_th' => "$required|string|max:500",
                'course_en' => "$required|string|max:500",
                'instructors' => "$required|array|min:1|max:20",
                'instructors.*.first' => "$required|string|max:100",
                'instructors.*.last' => "$required|string|max:100",
                'instructors.*.email' => "$required|string|max:254",
                'category' => [$required, Rule::in(CourseCategory::query()->where('is_active', 'Y')->pluck('category_code')->all())],
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
            4 => [
                'dean_pers_id' => [
                    $deanRequired,
                    'integer',
                    Rule::in($activeDeanPersonIds),
                ],
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
            'dean_pers_id.required' => 'กรุณาเลือกหัวหน้าส่วนงาน คณบดี หรือผู้อำนวยการ',
            'dean_pers_id.in' => 'ผู้รับรองที่เลือกไม่ได้อยู่ในรายชื่อปัจจุบันของส่วนงานเป้าหมาย',
        ]);
        $removeAdditional = $validated['remove_additional'] ?? [];
        unset($validated['additional_documents'], $validated['remove_additional']);

        if (isset($validated['target_dept_id'])) {
            $validated['target_dept_id'] = (int) $validated['target_dept_id'];
            $validated['unit'] = $this->departmentDirectory->name($validated['target_dept_id']);
        }
        if ($step === 1) {
            if (isset($validated['target_subdept_id'])) {
                $validated['target_subdept_id'] = (int) $validated['target_subdept_id'];
                $validated['subunit'] = $this->departmentDirectory->subdepartmentName($validated['target_subdept_id']);
            } else {
                $validated['target_subdept_id'] = null;
                $validated['subunit'] = null;
            }
        }

        if (isset($validated['learning']) && $validated['learning'] !== 'เปิดแบบตามวงรอบ (Phase/Batch-based)') {
            $validated['activity_round'] = null;
            $validated['activity_phase'] = null;
        }

        $existingDraft = $request->session()->get('portal.draft', []);
        if ($step === 1
            && isset($validated['target_dept_id'])
            && (int) ($existingDraft['target_dept_id'] ?? 0) !== (int) $validated['target_dept_id']) {
            unset($existingDraft['dean_pers_id'], $existingDraft['dean_name']);
        }
        $data = array_replace($existingDraft, $validated);
        if ($step <= 3) {
        }
        $data['requester_unit'] = $this->personDepartmentName((int) $this->actor($request)['pers_id']);
        if (isset($validated['category'])) {
            $data['category_label'] = CourseCategory::where('category_code', $validated['category'])->value('category_name_th');
        }
        if (isset($validated['project_type'])) {
            $data['project_type_label'] = ProjectType::where('project_type_code', $validated['project_type'])->value('project_type_name_th');
        }
        if (isset($validated['dean_pers_id'])) {
            $data['dean_pers_id'] = (int) $validated['dean_pers_id'];
            $data['dean_name'] = $this->personName($data['dean_pers_id']);
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

        $deletedAdditionalPaths = [];
        try {
            $courseRequest = DB::connection('course133')->transaction(function () use ($request, $data, &$deletedAdditionalPaths) {
                $actor = $this->actor($request);
                $courseRequest = isset($data['id'])
                    ? CourseRequest::query()->lockForUpdate()->findOrFail($data['id'])
                    : new CourseRequest(['request_no' => $data['request_no'] ?? $this->nextRequestNumber()]);

                abort_if($courseRequest->exists, 409);

                $status = 'PENDING_DEAN_REVIEW';
                $this->persistRequestData($courseRequest, $data, (int) $actor['pers_id'], $status);

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
                        ->where('document_type_code', 'ADDITIONAL_DOCUMENT')
                        ->whereIn('document_id', $data['removed_additional_document_ids'])
                        ->get();
                    foreach ($documents as $document) {
                        $deletedAdditionalPaths[] = $document->storage_key;
                        $document->delete();
                    }
                }
                $needsStudentRoster = $data['enrollment'] === 'ผู้ดูแลระบบนำเข้ารายชื่อ'
                    && ! $courseRequest->documents()->where('document_type_code', 'STUDENT_ROSTER')->exists();
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
                $this->addHistory($courseRequest, $status, $actor['pers_id'], 'REQUESTER');
                $this->queueNotification(
                    $courseRequest,
                    'DEAN_REVIEW_REQUIRED',
                    $this->personRecipient((int) $courseRequest->dean_pers_id, 'ผู้รับรอง'),
                    $actor,
                );

                return $courseRequest;
            });
        } catch (Throwable $exception) {
            $this->deletePendingFiles($data);
            throw $exception;
        }

        foreach ($deletedAdditionalPaths as $path) {
            Storage::disk('local')->delete($path);
        }

        $request->session()->forget(['portal.draft', 'portal.completed']);

        return redirect()->route('requests.index')->with('success', 'ส่งคำร้องเรียบร้อยแล้ว<br>คำร้องอยู่ในสถานะรอการรับรอง');
    }

    public function clearStep(Request $request, int $step)
    {
        $keys = match ($step) {
            1 => ['target_dept_id', 'unit', 'target_subdept_id', 'subunit', 'project_name', 'project_type', 'project_other', 'coordinator_first', 'coordinator_last', 'coordinator_position', 'coordinator_phone', 'coordinator_email'],
            2 => ['course_th', 'course_en', 'instructors', 'category', 'category_other', 'description'],
            3 => ['learning', 'activity_round', 'activity_phase', 'starts_at', 'ends_at', 'enrollment', 'enrollment_other', 'expected_students'],
            4 => ['dean_pers_id', 'dean_name'],
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

    public function detail(Request $request, int $id, string $role = 'user')
    {
        $courseRequest = $this->findAuthorized($request, $id, $role);
        $record = $this->toRecord($courseRequest);

        return view($role === 'user' ? 'portal.request-detail' : 'portal.detail', compact('record', 'role'));
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
                ->where('document_type_code', 'STUDENT_ROSTER')
                    ->latest('uploaded_at')
                    ->latest('document_id')
                    ->first();
                abort_if($existing?->roster_acknowledged_at !== null, 409, 'เจ้าหน้าที่รับทราบรายชื่อแล้ว กรุณาติดต่อเจ้าหน้าที่เพื่อขออัปโหลดรายชื่อใหม่');

                $isUpdate = $existing !== null;
                $this->createDocument($courseRequest, 'STUDENT_ROSTER', $stored, 'roster', $actor['pers_id']);
                $this->queueRoleNotifications(
                    $courseRequest,
                    $isUpdate ? 'STUDENT_ROSTER_UPDATED' : 'STUDENT_ROSTER_SUBMITTED',
                    'OFFICER',
                    $actor
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
                ->where('document_type_code', 'STUDENT_ROSTER')
                ->latest('uploaded_at')
                ->latest('document_id')
                ->lockForUpdate()
                ->firstOrFail();

            /** @var CourseDocument $studentRoster */
            $studentRoster->update([
                'roster_acknowledged_at' => now(),
                'roster_acknowledged_by_pers_id' => $actor['pers_id'],
            ]);

            $this->queueNotification($courseRequest, 'STUDENT_ROSTER_ACKNOWLEDGED', $this->requesterRecipient($courseRequest), $actor);
        });

        $redirect = $returnToOfficerList
            ? redirect()->route('officer.reviews')
            : redirect()->route('officer.show', $id);

        return $redirect
            ->with('success', 'รับทราบรายชื่อผู้เรียนแล้ว<br>ระบบล็อกการแก้ไขไฟล์ของผู้ยื่นคำร้อง');
    }

    public function requestStudentRosterReopen(Request $request, int $id)
    {
        $alreadyRequested = false;

        DB::connection('course133')->transaction(function () use ($request, $id, &$alreadyRequested) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            $actor = $this->actor($request);
            abort_unless((int) $courseRequest->requester_pers_id === (int) $actor['pers_id'], 403);
            abort_unless($courseRequest->enrollment_method === 'ผู้ดูแลระบบนำเข้ารายชื่อ', 409);

            $studentRoster = $courseRequest->documents()
                ->where('document_type_code', 'STUDENT_ROSTER')
                ->latest('uploaded_at')
                ->latest('document_id')
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($studentRoster->roster_acknowledged_at === null, 409, 'ผู้ยื่นคำร้องสามารถอัปโหลดรายชื่อใหม่ได้อยู่แล้ว');

            $alreadyRequested = NotificationOutbox::query()
                ->where('request_id', $courseRequest->request_id)
                ->where('notification_type_code', 'STUDENT_ROSTER_REOPEN_REQUESTED')
                ->where('created_at', '>=', $studentRoster->roster_acknowledged_at)
                ->exists();

            if (! $alreadyRequested) {
                $this->queueRoleNotifications(
                    $courseRequest,
                    'STUDENT_ROSTER_REOPEN_REQUESTED',
                    'OFFICER',
                    $actor
                );
            }
        });

        return redirect()->to(route('requests.show', $id) . '#student-roster')
            ->with('success', $alreadyRequested
                ? 'ส่งคำขอแก้ไขรายชื่อไปยังเจ้าหน้าที่แล้ว กรุณารอเจ้าหน้าที่เปิดสิทธิ์'
                : 'ส่งคำขอแก้ไขรายชื่อไปยังเจ้าหน้าที่เรียบร้อยแล้ว');
    }

    public function reopenStudentRoster(Request $request, int $id)
    {
        DB::connection('course133')->transaction(function () use ($request, $id) {
            $courseRequest = $this->findAuthorized($request, $id, 'officer');
            $actor = $this->actor($request);
            $studentRoster = $courseRequest->documents()
                ->where('document_type_code', 'STUDENT_ROSTER')
                ->latest('uploaded_at')
                ->latest('document_id')
                ->lockForUpdate()
                ->firstOrFail();

            /** @var CourseDocument $studentRoster */
            abort_if($studentRoster->roster_acknowledged_at === null, 409);
            $studentRoster->update([
                'roster_acknowledged_at' => null,
                'roster_acknowledged_by_pers_id' => null,
            ]);

            $this->queueNotification($courseRequest, 'STUDENT_ROSTER_REOPENED', $this->requesterRecipient($courseRequest), $actor);
        });

        return redirect()->route('officer.show', $id)
            ->with('success', 'อนุญาตให้อัปโหลดรายชื่อใหม่แล้ว<br>แจ้งผู้ยื่นคำร้องเรียบร้อย');
    }

    public function updateResult(Request $request, int $id)
    {
        $requestType = CourseRequest::query()->whereKey($id)->value('form_type_code');
        abort_if($requestType === null, 404);

        $isWebsiteRequest = $requestType === 'CC-ITED-01';
        $isWebsiteContactChangeRequest = $requestType === 'CC-ITED-02';
        $isWebsiteStorageRequest = $requestType === 'CC-ITED-04';
        $isEmeetingRequest = $requestType === 'CC-ITED-05';
        $requiresExecutionDetail = $isWebsiteContactChangeRequest || $isWebsiteStorageRequest || $isEmeetingRequest;
        $executionDetailMessage = match (true) {
            $isWebsiteContactChangeRequest => 'กรุณาระบุข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์',
            $isEmeetingRequest => 'กรุณาระบุข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม',
            default => 'กรุณาระบุข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์',
        };
        $data = $request->validate([
            'course_id' => ($isWebsiteRequest || $isWebsiteContactChangeRequest || $isWebsiteStorageRequest || $isEmeetingRequest) ? ['nullable'] : ['required', 'integer', 'min:1'],
            'allocated_domain' => $isWebsiteRequest ? ['required', 'string', 'max:5000'] : ['nullable'],
            'execution_detail' => $requiresExecutionDetail ? ['required', 'string', 'max:5000'] : ['nullable'],
            'notify_requester' => ['nullable', 'boolean'],
        ], [
            'execution_detail.required' => $executionDetailMessage,
        ]);

        DB::connection('course133')->transaction(function () use ($request, $id, $data) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($courseRequest->status === 'COMPLETED', 409);

            $actor = $this->actor($request);

            if ($courseRequest->form_type_code === 'CC-ITED-01') {
                $courseRequest->websiteDetail()->update([
                    'allocated_domain' => trim((string) $data['allocated_domain']),
                ]);
                $this->ensureSignedFormDocument(
                    $courseRequest->fresh($this->recordRelations()),
                    (int) $actor['pers_id'],
                    true,
                );
            } elseif ($courseRequest->form_type_code === 'CC-ITED-02') {
                $courseRequest->websiteContactChangeDetail()->update([
                    'execution_detail' => trim((string) $data['execution_detail']),
                ]);
            } elseif ($courseRequest->form_type_code === 'CC-ITED-04') {
                $courseRequest->websiteStorageDetail()->update([
                    'execution_detail' => trim((string) $data['execution_detail']),
                    'granted_space_gb' => 1,
                ]);
                $this->ensureSignedFormDocument(
                    $courseRequest->fresh($this->recordRelations()),
                    (int) $actor['pers_id'],
                    true,
                );
            } elseif ($courseRequest->form_type_code === 'CC-ITED-05') {
                $courseRequest->emeetingDetail()->update([
                    'execution_detail' => trim((string) $data['execution_detail']),
                ]);
            } else {
                $courseRequest->moodleCourseDetail()->update([
                    'course_id' => (string) (int) $data['course_id'],
                ]);
            }

            if ($request->boolean('notify_requester')) {
                $this->queueNotification(
                    $courseRequest,
                    'REQUEST_RESULT_UPDATED',
                    $this->requesterRecipient($courseRequest),
                    $actor,
                );
            }
        });

        return redirect()->route('officer.show', $id)->with(
            'success',
            $request->boolean('notify_requester')
                ? 'แก้ไขผลการดำเนินการและส่งอีเมลแจ้งผู้ยื่นคำร้องเรียบร้อยแล้ว'
                : 'แก้ไขผลการดำเนินการเรียบร้อยแล้ว',
        );
    }

    public function review(Request $request, int $id)
    {
        $requestType = CourseRequest::query()->whereKey($id)->value('form_type_code');
        $isWebsiteRequest = $requestType === 'CC-ITED-01';
        $isWebsiteContactChangeRequest = $requestType === 'CC-ITED-02';
        $isWebsiteStorageRequest = $requestType === 'CC-ITED-04';
        $isEmeetingRequest = $requestType === 'CC-ITED-05';
        $requiresExecutionDetail = $isWebsiteContactChangeRequest || $isWebsiteStorageRequest || $isEmeetingRequest;
        $executionDetailMessage = match (true) {
            $isWebsiteContactChangeRequest => 'กรุณาระบุข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์',
            $isEmeetingRequest => 'กรุณาระบุข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม',
            default => 'กรุณาระบุข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์',
        };
        $data = $request->validate([
            'decision' => ['required', Rule::in(['pass', 'reject'])],
            'reason' => 'required_if:decision,reject|nullable|string|max:3000',
            'course_id' => ($isWebsiteRequest || $isWebsiteContactChangeRequest || $isWebsiteStorageRequest || $isEmeetingRequest) ? 'nullable' : 'required_if:decision,pass|nullable|integer|min:1',
            'allocated_domain' => $isWebsiteRequest ? 'required_if:decision,pass|nullable|string' : 'nullable',
            'execution_detail' => $requiresExecutionDetail ? 'required_if:decision,pass|nullable|string|max:5000' : 'nullable',
        ], [
            'execution_detail.required_if' => $executionDetailMessage,
        ]);

        DB::connection('course133')->transaction(function () use ($request, $id, $data) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($courseRequest->status === 'UNDER_OFFICER_REVIEW', 409);

            $actor = $this->actor($request);
            $passed = $data['decision'] === 'pass';
            $status = $passed ? 'COMPLETED' : 'REJECTED';

            if ($passed && ! in_array($courseRequest->form_type_code, ['CC-ITED-01', 'CC-ITED-02', 'CC-ITED-04', 'CC-ITED-05'], true)) {
                $hasPriorPassedReview = $courseRequest->officerReviews()
                    ->where('decision', 'APPROVED')
                    ->exists();

                $signedDoc = $this->ensureSignedFormDocument(
                    $hasPriorPassedReview
                        ? $courseRequest->fresh($this->recordRelations())
                        : $courseRequest,
                    (int) $actor['pers_id'],
                    $hasPriorPassedReview,
                );
                if ($signedDoc) {
                    $pdfAbsPath = Storage::disk('local')->path($signedDoc->storage_key);

                    if (file_exists($pdfAbsPath)) {
                        if (! defined('FPDF_FONTPATH')) {
                            define('FPDF_FONTPATH', resource_path('fonts/fpdf').DIRECTORY_SEPARATOR);
                        }

                        $pdf = new Fpdi();
                        $pageCount = $pdf->setSourceFile($pdfAbsPath);
                        $thaiTextItems = [];
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $templateId = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($templateId);

                            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                            $pdf->useTemplate($templateId);

                            if ($pageNo === $pageCount) {
                                $scaleX = $size['width'] / 210;
                                $scaleY = $size['height'] / 297;

                                $isWebsiteRequest = $courseRequest->form_type_code === 'CC-ITED-01';
                                $checkStartX = $isWebsiteRequest ? 20.2 : 30.4;
                                $checkStartY = $isWebsiteRequest ? 213.7 : 130;
                                $signatureBoxWidth = ($isWebsiteRequest ? 50 : 51.5) * $scaleX;
                                $nameTop = 145.7;
                                $dateTop = $isWebsiteRequest ? 236.2 : 155.4;

                                // Keep the checkmark inside the officer checkbox.
                                $pdf->SetLineWidth(0.4);
                                $pdf->SetDrawColor(0, 0, 0);
                                $pdf->Line($checkStartX * $scaleX, $checkStartY * $scaleY, ($checkStartX + 1) * $scaleX, ($checkStartY + 1.1) * $scaleY);
                                $pdf->Line(($checkStartX + 1) * $scaleX, ($checkStartY + 1.1) * $scaleY, ($checkStartX + 2.5) * $scaleX, ($checkStartY - 1.4) * $scaleY);

                                // Stamp the authenticated officer name in place of an uploaded signature.
                                $pdf->AddFont('Sarabun', '', 'Sarabun-Regular.php');
                                $pdf->SetFont('Sarabun', '', 9);
                                $pdf->SetTextColor(0, 0, 0);
                                $officerName = trim((string) ($actor['name'] ?? ''));
                                if ($officerName === '') {
                                    $officerName = $this->personName((int) $actor['pers_id']);
                                }
                                $encodedOfficerName = iconv('UTF-8', 'CP874//IGNORE', $officerName);
                                $officerNameFontSize = 9;
                                while ($officerNameFontSize > 7 && $pdf->GetStringWidth($encodedOfficerName) > (47 * $scaleX)) {
                                    $officerNameFontSize -= 0.5;
                                    $pdf->SetFont('Sarabun', '', $officerNameFontSize);
                                }
                                $thaiTextItems[] = [
                                    'text' => $officerName,
                                    'x' => $isWebsiteRequest ? 49.5 : 44.5,
                                    'top' => $isWebsiteRequest ? 234.2 : 137.2,
                                    'width' => $isWebsiteRequest ? 50 : 51.5,
                                    'font_size' => $officerNameFontSize,
                                    'align' => 'center',
                                ];
                                if (! $isWebsiteRequest) {
                                    $thaiTextItems[] = [
                                        'text' => $officerName,
                                        'x' => 38.5,
                                        'top' => $nameTop,
                                        'width' => 47,
                                        'font_size' => $officerNameFontSize,
                                        'align' => 'center',
                                    ];
                                } else {
                                    $thaiTextItems[] = [
                                        'text' => trim((string) $data['allocated_domain']),
                                        'x' => 49,
                                        'top' => 226.2,
                                        'width' => 140,
                                        'font_size' => 9,
                                        'align' => 'left',
                                    ];
                                }
                                $thaiTextItems[] = [
                                    'text' => $this->thaiBuddhistDate(now()),
                                    'x' => $isWebsiteRequest ? 132 : 38.5,
                                    'top' => $dateTop,
                                    'width' => $isWebsiteRequest ? 58 : 47,
                                    'font_size' => 9,
                                    'align' => 'center',
                                ];
                            }
                        }
                        $this->addUnicodeTextOverlay($pdf, $thaiTextItems, $size['width'], $size['height']);
                        $pdf->Output('F', $pdfAbsPath);
                    }
                }
            }

            if (! $passed && ! in_array($courseRequest->form_type_code, ['CC-ITED-01', 'CC-ITED-02', 'CC-ITED-04', 'CC-ITED-05'], true)) {
                $this->ensureSignedFormDocument(
                    $courseRequest->fresh($this->recordRelations()),
                    (int) $actor['pers_id'],
                    true,
                );
            }

            OfficerReview::create([
                'request_id' => $courseRequest->request_id,
                'officer_pers_id' => $actor['pers_id'],
                'decision' => $passed ? 'APPROVED' : 'REJECTED',
                'rejection_reason' => $passed ? null : $data['reason'],
                'reviewed_at' => now(),
            ]);
            if ($passed) {
                if ($courseRequest->form_type_code === 'CC-ITED-01') {
                    $courseRequest->websiteDetail()->update([
                        'allocated_domain' => trim((string) $data['allocated_domain']),
                    ]);
                } elseif ($courseRequest->form_type_code === 'CC-ITED-02') {
                    $courseRequest->websiteContactChangeDetail()->update([
                        'execution_detail' => trim((string) $data['execution_detail']),
                    ]);
                } elseif ($courseRequest->form_type_code === 'CC-ITED-04') {
                    $courseRequest->websiteStorageDetail()->update([
                        'execution_detail' => trim((string) $data['execution_detail']),
                        'granted_space_gb' => 1,
                    ]);
                } elseif ($courseRequest->form_type_code === 'CC-ITED-05') {
                    $courseRequest->emeetingDetail()->update([
                        'execution_detail' => trim((string) $data['execution_detail']),
                    ]);
                } else {
                    $courseRequest->moodleCourseDetail()->update([
                        'course_id' => (string) (int) $data['course_id'],
                    ]);
                }
                $courseRequest->update([
                    'status_code' => $status,
                    'completed_at' => now(),
                    'completed_by_pers_id' => $actor['pers_id'],
                ]);
            } else {
                $courseRequest->update(['status_code' => $status]);
            }
            $this->addHistory($courseRequest, $status, $actor['pers_id'], 'OFFICER');
            if (in_array($courseRequest->form_type_code, ['CC-ITED-01', 'CC-ITED-02', 'CC-ITED-04', 'CC-ITED-05'], true)) {
                $this->ensureSignedFormDocument(
                    $courseRequest->fresh($this->recordRelations()),
                    (int) $actor['pers_id'],
                    true,
                );
            }
            if ($passed) {
                $this->queueNotification($courseRequest, 'REQUEST_COMPLETED_REQUESTER', $this->requesterRecipient($courseRequest), $actor);
            } else {
                $this->queueNotification($courseRequest, 'REQUEST_REJECTED', $this->requesterRecipient($courseRequest), $actor);
            }
        });

        return redirect()->route('officer.reviews')->with('success', $data['decision'] === 'pass'
            ? ($isWebsiteRequest ? 'อนุมัติและบันทึกโดเมนที่จัดสรรเรียบร้อยแล้ว' : ($isWebsiteContactChangeRequest ? 'อนุมัติและบันทึกข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์เรียบร้อยแล้ว' : ($isEmeetingRequest ? 'อนุมัติและบันทึกข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุมเรียบร้อยแล้ว' : 'อนุมัติและบันทึก Course ID เรียบร้อยแล้ว')))
            : 'บันทึกผลไม่อนุมัติและแจ้งผู้ยื่นคำร้องแล้ว');
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => 'required_if:decision,reject|nullable|string|max:3000',
            'signature_file' => 'required_if:decision,approve,reject|nullable|image|mimes:png,jpeg,jpg|max:2048',
        ]);

        DB::connection('course133')->transaction(function () use ($request, $id, $data) {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($courseRequest->status === 'PENDING_APPROVAL', 409);

            $actor = $this->actor($request);
            $status = match ($data['decision']) {
                'approve' => 'UNDER_OFFICER_REVIEW',
                'reject' => 'REJECTED',
            };
            $approvalDecision = match ($data['decision']) {
                'approve' => 'APPROVED',
                'reject' => 'REJECTED',
            };

            if (in_array($data['decision'], ['approve', 'reject'], true) && $request->hasFile('signature_file')) {
                $signaturePath = $request->file('signature_file')->store('temp_signatures', 'local');
                $signatureAbsPath = Storage::disk('local')->path($signaturePath);

                $signedDoc = $this->ensureSignedFormDocument($courseRequest, (int) $actor['pers_id']);
                if ($signedDoc) {
                    $pdfAbsPath = Storage::disk('local')->path($signedDoc->storage_key);

                    if (file_exists($pdfAbsPath)) {
                        if (! defined('FPDF_FONTPATH')) {
                            define('FPDF_FONTPATH', resource_path('fonts/fpdf').DIRECTORY_SEPARATOR);
                        }

                        $pdf = new Fpdi();
                        $pageCount = $pdf->setSourceFile($pdfAbsPath);
                        $continuedApprovalComment = null;
                        $thaiTextItems = [];
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
                                $checkboxX = $data['decision'] === 'approve' ? 114.6 : 135.2;
                                $pdf->SetLineWidth(0.4);
                                $pdf->SetDrawColor(0, 0, 0);
                                $pdf->Line($checkboxX * $scaleX, 130 * $scaleY, ($checkboxX + 1) * $scaleX, 131.1 * $scaleY);
                                $pdf->Line(($checkboxX + 1) * $scaleX, 131.1 * $scaleY, ($checkboxX + 2.5) * $scaleX, 128.6 * $scaleY);

                                // Center the approver signature on its signature line.
                                $signatureBoxX = 129 * $scaleX;
                                $signatureBoxY = 144.5 * $scaleY;
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
                                    $commentFontSize = 9;
                                    $pdf->SetFont('Sarabun', '', $commentFontSize);
                                    $remainingComment = $comment;
                                    $commentLines = [];

                                    for ($lineIndex = 0; $lineIndex < 2 && $remainingComment !== ''; $lineIndex++) {
                                        $encodedRemaining = iconv('UTF-8', 'CP874//IGNORE', $remainingComment);
                                        if ($pdf->GetStringWidth($encodedRemaining) <= $commentMaxWidth) {
                                            $commentLines[] = $remainingComment;
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
                                        $commentLines[] = $fittedLine;
                                        $remainingComment = ltrim(mb_substr($remainingComment, $fittedLength));
                                    }

                                    $continuedApprovalComment = $remainingComment !== '' ? $remainingComment : null;

                                    $commentStartY = count($commentLines) > 1 ? 140 : 143.5;
                                    foreach ($commentLines as $lineIndex => $commentLine) {
                                        $thaiTextItems[] = [
                                            'text' => $commentLine,
                                            'x' => 130,
                                            'top' => $commentStartY - 5.5 + ($lineIndex * 3.5),
                                            'width' => 50.5,
                                            'font_size' => $commentFontSize,
                                        ];
                                    }
                                    $pdf->SetFont('Sarabun', '', 9);
                                }

                                $approverName = trim((string) ($actor['name'] ?? ''));
                                if ($approverName === '') {
                                    $approverName = $this->personName((int) $actor['pers_id']);
                                }
                                $encodedApproverName = iconv('UTF-8', 'CP874//IGNORE', $approverName);
                                $approverNameFontSize = 9;
                                while ($approverNameFontSize > 7 && $pdf->GetStringWidth($encodedApproverName) > (47 * $scaleX)) {
                                    $approverNameFontSize -= 0.5;
                                    $pdf->SetFont('Sarabun', '', $approverNameFontSize);
                                }
                                $thaiTextItems[] = [
                                    'text' => $approverName,
                                    'x' => 123,
                                    'top' => 155.7,
                                    'width' => 47,
                                    'font_size' => $approverNameFontSize,
                                    'align' => 'center',
                                ];
                                $thaiTextItems[] = [
                                    'text' => $this->thaiBuddhistDate(now()),
                                    'x' => 129.75,
                                    'top' => 165,
                                    'width' => 47,
                                    'font_size' => 9,
                                    'align' => 'center',
                                ];
                            }
                        }

                        if ($continuedApprovalComment !== null) {
                            $continuationX = 15 * $scaleX;
                            $continuationY = 188 * $scaleY;
                            $continuationWidth = 180 * $scaleX;
                            $continuationTitleText = '* ข้อคิดเห็นกรณีไม่อนุมัติ (ต่อ):';
                            $continuationTitle = iconv('UTF-8', 'CP874//IGNORE', $continuationTitleText);

                            $pdf->SetFont('Sarabun', '', 8);

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

                            $thaiTextItems[] = [
                                'text' => $continuationTitleText,
                                'x' => 15,
                                'top' => 184.5,
                                'width' => $titleWidth / $scaleX,
                                'font_size' => 8,
                                'color' => '#4b5563',
                            ];
                            $thaiTextItems[] = [
                                'text' => $firstContinuationLine,
                                'x' => 15 + ($titleWidth / $scaleX) + 2,
                                'top' => 184.5,
                                'width' => $firstLineMaxWidth / $scaleX,
                                'font_size' => 8,
                            ];

                            $remainingContinuation = ltrim(mb_substr($continuedApprovalComment, $firstLineLength));
                            if ($remainingContinuation !== '') {
                                $thaiTextItems[] = [
                                    'text' => $remainingContinuation,
                                    'x' => 15,
                                    'top' => 189,
                                    'width' => 180,
                                    'font_size' => 8,
                                    'wrap' => true,
                                    'line_height' => 4.5,
                                ];
                            }
                        }

                        $this->addUnicodeTextOverlay($pdf, $thaiTextItems, $size['width'], $size['height']);
                        $pdf->Output('F', $pdfAbsPath);
                    }
                }
                Storage::disk('local')->delete($signaturePath);
            }

            ApproverReview::create([
                'request_id' => $courseRequest->request_id,
                'approver_pers_id' => $actor['pers_id'],
                'decision' => $approvalDecision,
                'comment' => $data['decision'] === 'approve' ? null : $data['reason'],
                'decided_at' => now(),
            ]);
            $courseRequest->update(['status' => $status]);
            $this->addHistory($courseRequest, $status, $actor['pers_id'], 'APPROVER');
            $notificationType = match ($data['decision']) {
                'approve' => 'OFFICER_REVIEW_REQUIRED',
                'reject' => 'REQUEST_REJECTED',
            };
            if ($data['decision'] === 'approve') {
                $this->queueRoleNotifications($courseRequest, $notificationType, 'OFFICER', $actor);
            } else {
                $this->queueNotification($courseRequest, $notificationType, $this->requesterRecipient($courseRequest), $actor);
            }
        });

        $message = match ($data['decision']) {
            'approve' => 'อนุมัติคำร้องเรียบร้อยแล้ว',
            'reject' => 'บันทึกผลไม่อนุมัติ และส่งเหตุผลให้ผู้ยื่นคำร้องแล้ว',
        };

        return redirect()->route('approver.reviews')->with('success', $message);
    }

    public function courseId(Request $request, int $id)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'min:1'],
        ], [
            'course_id.integer' => 'กรุณากรอก Course ID เป็นตัวเลขเท่านั้น',
            'course_id.min' => 'Course ID ต้องเป็นเลขจำนวนเต็มตั้งแต่ 1 ขึ้นไป',
        ]);

        $wasRecorded = DB::connection('course133')->transaction(function () use ($request, $id, $data): bool {
            $courseRequest = CourseRequest::query()->lockForUpdate()->findOrFail($id);
            abort_unless($courseRequest->status === 'COURSE_ID_RECORDED', 409);
            $wasRecorded = $courseRequest->status === 'COURSE_ID_RECORDED';
            $actor = $this->actor($request);

            $courseRequest->update([
                'course_id' => (string) (int) $data['course_id'],
                'recorded_by_pers_id' => $actor['pers_id'],
                'status' => 'COURSE_ID_RECORDED',
            ]);
            $this->addHistory($courseRequest, 'COURSE_ID_RECORDED', $actor['pers_id'], 'OFFICER');
            $this->queueNotification($courseRequest, 'COURSE_ID_RECORDED', $this->requesterRecipient($courseRequest), $actor);

            return $wasRecorded;
        });

        return redirect()->route('officer.reviews')->with('success', $wasRecorded
            ? 'แก้ไข Course ID สำเร็จ<br>แจ้งเตือนผู้ยื่นคำร้องเรียบร้อยแล้ว'
            : 'บันทึก Course ID สำเร็จ<br>แจ้งเตือนผู้ยื่นคำร้องเรียบร้อยแล้ว');
    }

    public function document(Request $request, string $id = 'draft')
    {
        $record = $this->documentRecord($request, $id);

        if ($request->query('preview') === '1') {
            $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');

            return $this->generatedPdfResponse(
                $record,
                $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, $record['form_type_code'] ?? 'CC-ITED-06') : 'document.pdf',
                true,
            );
        }

        $downloadUrl = route('requests.document.download', ['id' => $id]);
        return view('portal.document', compact('record', 'downloadUrl'));
    }

    public function downloadDocument(Request $request, string $id = 'draft')
    {
        $record = $this->documentRecord($request, $id);
        $projectName = $record['project_name'] ?? 'โครงการขออนุมัติ';
        $safeName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $projectName);
        $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
        $requestCode = Str::afterLast($requestNumber, '-');
        $filename = $requestCode !== ''
            ? $this->requestDocumentFilename($requestNumber, $record['form_type_code'] ?? 'CC-ITED-06')
            : "{$safeName}.pdf";

        return $this->generatedPdfResponse($record, $filename, false);
    }

    public function websiteDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.website_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-01') === 'CC-ITED-01', 404);

        if ($request->query('preview') === '1') {
            $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');

            return $this->generatedPdfResponse(
                $record,
                $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-01') : 'CC-ITED-01.pdf',
                true,
            );
        }

        $downloadUrl = route('website-requests.document.download', ['id' => $id]);

        return view('portal.document', compact('record', 'downloadUrl'));
    }

    public function downloadWebsiteDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.website_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-01') === 'CC-ITED-01', 404);

        $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
        $websiteName = (string) ($record['website_name'] ?? 'คำขอใช้พื้นที่เว็บไซต์');
        $safeName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $websiteName);
        $filename = $requestNumber !== ''
            ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-01')
            : "{$safeName}.pdf";
        return $this->generatedPdfResponse($record, $filename, false);
    }

    public function websiteContactChangeDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.website_contact_change_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-02') === 'CC-ITED-02', 404);

        if ($request->query('preview') === '1') {
            $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
            return $this->generatedPdfResponse($record, $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-02') : 'CC-ITED-02.pdf', true);
        }

        $downloadUrl = route('website-contact-change-requests.document.download', ['id' => $id]);
        return view('portal.document', compact('record', 'downloadUrl'));
    }

    public function downloadWebsiteContactChangeDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.website_contact_change_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-02') === 'CC-ITED-02', 404);

        $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
        $websiteName = (string) ($record['website_name'] ?? 'คำขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์');
        $safeName = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $websiteName);
        $filename = $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-02') : "{$safeName}.pdf";
        return $this->generatedPdfResponse($record, $filename, false);
    }

    public function emeetingDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.emeeting_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-05') === 'CC-ITED-05', 404);

        if ($request->query('preview') === '1') {
            $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
            return $this->generatedPdfResponse($record, $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-05') : 'CC-ITED-05.pdf', true);
        }

        $downloadUrl = route('emeeting-requests.document.download', ['id' => $id]);
        return view('portal.document', compact('record', 'downloadUrl'));
    }

    public function downloadEmeetingDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.emeeting_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-05') === 'CC-ITED-05', 404);

        $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
        $filename = $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-05') : 'CC-ITED-05.pdf';
        return $this->generatedPdfResponse($record, $filename, false);
    }

    public function websiteStorageDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.website_storage_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-04') === 'CC-ITED-04', 404);

        if ($request->query('preview') === '1') {
            $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
            return $this->generatedPdfResponse($record, $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-04') : 'CC-ITED-04.pdf', true);
        }

        $downloadUrl = route('website-storage-requests.document.download', ['id' => $id]);
        return view('portal.document', compact('record', 'downloadUrl'));
    }

    public function downloadWebsiteStorageDocument(Request $request, string $id = 'draft')
    {
        $record = $id === 'draft'
            ? $request->session()->get('portal.website_storage_draft', [])
            : $this->documentRecord($request, $id);
        abort_unless(($record['form_type_code'] ?? 'CC-ITED-04') === 'CC-ITED-04', 404);

        $requestNumber = (string) ($record['request_no'] ?? $record['number'] ?? '');
        $filename = $requestNumber !== '' ? $this->requestDocumentFilename($requestNumber, 'CC-ITED-04') : 'CC-ITED-04.pdf';
        return $this->generatedPdfResponse($record, $filename, false);
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
        if ($kind === 'signed'
            && in_array($courseRequest->status, ['COMPLETED', 'REJECTED'], true)
            && in_array($courseRequest->form_type_code, ['CC-ITED-01', 'CC-ITED-02', 'CC-ITED-04', 'CC-ITED-05'], true)) {
            return $this->generatedPdfResponse(
                $this->toRecord($courseRequest),
                $this->requestDocumentFilename($courseRequest->request_no, $courseRequest->form_type_code),
                $request->boolean('preview'),
            );
        }
        $documents = $courseRequest->documents()->where('document_type_code', $type);
        if (in_array($kind, ['additional', 'roster'], true) && $request->filled('document')) {
            $documents->where('document_id', $request->integer('document'));
        }
        $document = $documents->latest('uploaded_at')->latest('document_id')->firstOrFail();

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        $downloadName = $kind === 'signed'
            ? $this->requestDocumentFilename($courseRequest->request_no, $courseRequest->form_type_code)
            : $document->original_filename;

        if ($kind === 'signed' && $request->boolean('preview')) {
            return $disk->response(
                $document->storage_key,
                $downloadName,
                [
                    'Content-Type' => 'application/pdf',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ],
                'inline'
            );
        }

        $response = $disk->download(
            $document->storage_key,
            $downloadName,
            $kind === 'signed'
                ? [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]
                : []
        );

        return $response;
    }

    private function findAuthorized(Request $request, int $id, string $role): CourseRequest
    {
        $courseRequest = CourseRequest::query()->with($this->recordRelations())->findOrFail($id);

        if ($role === 'user') {
            abort_unless((int) $courseRequest->requester_pers_id === (int) $this->actor($request)['pers_id'], 403);
        } elseif ($role === 'officer') {
            $latestRoster = $courseRequest->documents
                ->where('document_type_code', 'STUDENT_ROSTER')
                ->sortByDesc(fn ($document) => sprintf('%s-%020d', $document->uploaded_at?->format('Y-m-d H:i:s.u'), $document->document_id))
                ->first();
            $canReviewLateRoster = $courseRequest->status === 'PENDING_APPROVAL'
                && $latestRoster !== null
                && ($latestRoster->roster_acknowledged_at === null
                    || $courseRequest->notifications->contains(fn ($notification) =>
                        $notification->notification_type === 'STUDENT_ROSTER_REOPEN_REQUESTED'
                        && $notification->created_at?->greaterThanOrEqualTo($latestRoster->roster_acknowledged_at)
                    ));

            abort_unless(in_array($courseRequest->status, ['UNDER_OFFICER_REVIEW', 'COMPLETED', 'REJECTED'], true) || $canReviewLateRoster, 404);
        } else {
            abort_unless(in_array($courseRequest->status, ['PENDING_APPROVAL', 'COURSE_ID_RECORDED', 'REJECTED'], true), 404);
        }

        return $courseRequest;
    }

    private function documentRecord(Request $request, string $id): array
    {
        return $id === 'draft'
            ? $request->session()->get('portal.draft', [])
            : $this->toRecord($this->findAuthorized($request, (int) $id, $this->actor($request)['role']));
    }

    private function persistRequestData(CourseRequest $courseRequest, array $data, int $requesterId, string $status): void
    {
        $courseRequest->fill([
            'form_type_code' => 'CC-ITED-06',
            'requester_pers_id' => $requesterId,
            'target_dept_id' => (int) $data['target_dept_id'],
            'target_subdept_id' => isset($data['target_subdept_id']) ? (int) $data['target_subdept_id'] : null,
            'dean_pers_id' => (int) $data['dean_pers_id'],
            'dean_position_name' => $this->deanPositionName(
                (int) $data['target_dept_id'],
                (int) $data['dean_pers_id'],
            ),
            'request_title' => $data['project_name'],
            'status_code' => $status,
            'submitted_at' => now(),
        ])->save();

        $courseRequest->moodleCourseDetail()->updateOrCreate(['request_id' => $courseRequest->request_id], [
            'project_name' => $data['project_name'],
            'project_type_code' => $data['project_type'],
            'project_other' => $data['project_type'] === 'OTHER' ? $data['project_other'] : null,
            'course_name_th' => $data['course_th'],
            'course_name_en' => $data['course_en'],
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
        ]);

        $courseRequest->coordinatorContact()->updateOrCreate([
            'request_id' => $courseRequest->request_id,
            'contact_role_code' => 'COORDINATOR',
            'is_primary' => true,
        ], [
            'pers_id' => null,
            'full_name' => trim($data['coordinator_first'].' '.$data['coordinator_last']),
            'position_name' => $data['coordinator_position'],
            'phone' => $data['coordinator_phone'],
            'email' => $data['coordinator_email'],
        ]);

        $courseRequest->load('moodleCourseDetail.projectType', 'moodleCourseDetail.category', 'coordinatorContact');
    }

    private function persistWebsiteRequestData(CourseRequest $courseRequest, array $data, int $requesterId, string $status): void
    {
        $courseRequest->fill([
            'form_type_code' => 'CC-ITED-01',
            'requester_pers_id' => $requesterId,
            'target_dept_id' => (int) $data['target_dept_id'],
            'target_subdept_id' => isset($data['target_subdept_id']) ? (int) $data['target_subdept_id'] : null,
            'dean_pers_id' => (int) $data['dean_pers_id'],
            'dean_position_name' => $this->deanPositionName(
                (int) $data['target_dept_id'],
                (int) $data['dean_pers_id'],
            ),
            'request_title' => $data['website_name'],
            'status_code' => $status,
            'submitted_at' => now(),
        ])->save();

        $courseRequest->websiteDetail()->updateOrCreate(['request_id' => $courseRequest->request_id], [
            'website_name' => $data['website_name'],
            'domain_url' => filled($data['domain_url'] ?? null) ? trim($data['domain_url']) : null,
            'site_scope_type' => $data['site_scope_type'],
            'service_starts_on' => $data['service_starts_on'] ?? null,
            'service_ends_on' => $data['service_ends_on'] ?? null,
            'platform_code' => $data['platform_code'],
            'additional_detail' => filled($data['additional_detail'] ?? null) ? trim($data['additional_detail']) : null,
            'allocated_domain' => null,
        ]);

        $courseRequest->websiteContact()->updateOrCreate([
            'request_id' => $courseRequest->request_id,
            'contact_role_code' => 'WEBSITE_CONTACT',
            'is_primary' => true,
        ], [
            'pers_id' => null,
            'full_name' => trim($data['contact_first'].' '.$data['contact_last']),
            'position_name' => $data['contact_position'],
            'phone' => $data['contact_phone'],
            'email' => $data['contact_email'],
        ]);

        $courseRequest->load('websiteDetail.platform', 'websiteContact');
    }

    private function persistWebsiteContactChangeRequestData(CourseRequest $courseRequest, array $data, int $requesterId, string $status): void
    {
        $courseRequest->fill([
            'form_type_code' => 'CC-ITED-02',
            'requester_pers_id' => $requesterId,
            'target_dept_id' => (int) $data['target_dept_id'],
            'target_subdept_id' => isset($data['target_subdept_id']) ? (int) $data['target_subdept_id'] : null,
            'dean_pers_id' => (int) $data['dean_pers_id'],
            'dean_position_name' => $this->deanPositionName(
                (int) $data['target_dept_id'],
                (int) $data['dean_pers_id'],
            ),
            'request_title' => $data['website_name'],
            'status_code' => $status,
            'submitted_at' => now(),
        ])->save();

        $courseRequest->websiteContactChangeDetail()->updateOrCreate(['request_id' => $courseRequest->request_id], [
            'website_name' => $data['website_name'],
            'domain_url' => trim((string) $data['domain_url']),
            'additional_detail' => null,
        ]);
        $courseRequest->currentWebsiteContact()->updateOrCreate([
            'request_id' => $courseRequest->request_id,
            'contact_role_code' => 'CURRENT_WEBSITE_CONTACT',
            'is_primary' => true,
        ], [
            'pers_id' => null,
            'full_name' => trim($data['current_contact_first'].' '.$data['current_contact_last']),
            'position_name' => null,
            'phone' => null,
            'email' => null,
        ]);
        $courseRequest->newWebsiteContact()->updateOrCreate([
            'request_id' => $courseRequest->request_id,
            'contact_role_code' => 'NEW_WEBSITE_CONTACT',
            'is_primary' => true,
        ], [
            'pers_id' => null,
            'full_name' => trim($data['new_contact_first'].' '.$data['new_contact_last']),
            'position_name' => $data['new_contact_position'],
            'phone' => $data['new_contact_phone'],
            'email' => $data['new_contact_email'],
        ]);

        $courseRequest->load('websiteContactChangeDetail', 'currentWebsiteContact', 'newWebsiteContact');
    }

    private function persistEmeetingRequestData(CourseRequest $courseRequest, array $data, int $requesterId, string $status): void
    {
        $courseRequest->fill([
            'form_type_code' => 'CC-ITED-05',
            'requester_pers_id' => $requesterId,
            'target_dept_id' => (int) $data['target_dept_id'],
            'target_subdept_id' => isset($data['target_subdept_id']) ? (int) $data['target_subdept_id'] : null,
            'dean_pers_id' => (int) $data['dean_pers_id'],
            'dean_position_name' => $this->deanPositionName(
                (int) $data['target_dept_id'],
                (int) $data['dean_pers_id'],
            ),
            'request_title' => $data['action_type'] === 'CHANGE_CONTACT'
                ? 'เปลี่ยนแปลงสิทธิ์ผู้ดูแลกรอบการประชุม SWU e-Meeting'
                : 'ขอสิทธิ์ผู้ดูแลกรอบการประชุม SWU e-Meeting',
            'status_code' => $status,
            'submitted_at' => now(),
        ])->save();

        $courseRequest->emeetingDetail()->updateOrCreate(['request_id' => $courseRequest->request_id], [
            'action_type' => $data['action_type'],
        ]);
        if ($data['action_type'] === 'CHANGE_CONTACT') {
            $courseRequest->currentEmeetingContact()->updateOrCreate([
                'request_id' => $courseRequest->request_id,
                'contact_role_code' => 'CURRENT_EMEETING_CONTACT',
                'is_primary' => true,
            ], [
                'pers_id' => null,
                'full_name' => trim($data['current_contact_first'].' '.$data['current_contact_last']),
                'position_name' => null,
                'phone' => null,
                'email' => null,
            ]);
        }
        $courseRequest->emeetingContact()->updateOrCreate([
            'request_id' => $courseRequest->request_id,
            'contact_role_code' => 'EMEETING_CONTACT',
            'is_primary' => true,
        ], [
            'pers_id' => null,
            'full_name' => trim($data['contact_first'].' '.$data['contact_last']),
            'position_name' => $data['contact_position'],
            'phone' => $data['contact_phone'],
            'email' => $data['contact_email'],
        ]);

        $courseRequest->load('emeetingDetail', 'currentEmeetingContact', 'emeetingContact');
    }

    private function persistWebsiteStorageRequestData(CourseRequest $courseRequest, array $data, int $requesterId, string $status): void
    {
        $courseRequest->fill([
            'form_type_code' => 'CC-ITED-04',
            'requester_pers_id' => $requesterId,
            'target_dept_id' => (int) $data['target_dept_id'],
            'target_subdept_id' => isset($data['target_subdept_id']) ? (int) $data['target_subdept_id'] : null,
            'dean_pers_id' => (int) $data['dean_pers_id'],
            'dean_position_name' => $this->deanPositionName(
                (int) $data['target_dept_id'],
                (int) $data['dean_pers_id'],
            ),
            'request_title' => $data['website_name'],
            'status_code' => $status,
            'submitted_at' => now(),
        ])->save();

        $courseRequest->websiteStorageDetail()->updateOrCreate(['request_id' => $courseRequest->request_id], [
            'website_name' => $data['website_name'],
            'domain_url' => trim((string) $data['domain_url']),
            'requested_space_gb' => 1,
            'additional_detail' => null,
        ]);
        $courseRequest->websiteContact()->updateOrCreate([
            'request_id' => $courseRequest->request_id,
            'contact_role_code' => 'WEBSITE_CONTACT',
            'is_primary' => true,
        ], [
            'pers_id' => null,
            'full_name' => trim($data['contact_first'].' '.$data['contact_last']),
            'position_name' => $data['contact_position'],
            'phone' => $data['contact_phone'],
            'email' => $data['contact_email'],
        ]);

        $courseRequest->load('websiteStorageDetail', 'websiteContact');
    }

    private function toRecord(CourseRequest $courseRequest): array
    {
        if ($courseRequest->form_type_code === 'CC-ITED-01') {
            return $this->toWebsiteRecord($courseRequest);
        }
        if ($courseRequest->form_type_code === 'CC-ITED-02') {
            return $this->toWebsiteContactChangeRecord($courseRequest);
        }
        if ($courseRequest->form_type_code === 'CC-ITED-04') {
            return $this->toWebsiteStorageRecord($courseRequest);
        }
        if ($courseRequest->form_type_code === 'CC-ITED-05') {
            return $this->toEmeetingRecord($courseRequest);
        }

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
        /** @var ApproverReview|null $latestApproval */
        $latestApproval = null;
        $latestStatusHistory = $courseRequest->histories
            ->sortByDesc(fn ($history) => sprintf('%s-%020d', $history->changed_at?->format('Y-m-d H:i:s.u'), $history->history_id))
            ->first();
        $latestDeanReview = $courseRequest->deanReviews->sortByDesc('decided_at')->first();
        $courseIdNotification = $courseRequest->notifications
            ->where('notification_type', 'REQUEST_COMPLETED_REQUESTER')
            ->sortByDesc('notification_id')
            ->first();
        $studentRosterReopenRequested = $studentRoster?->roster_acknowledged_at !== null
            && $courseRequest->notifications->contains(fn ($notification) =>
                $notification->notification_type === 'STUDENT_ROSTER_REOPEN_REQUESTED'
                && $notification->created_at?->greaterThanOrEqualTo($studentRoster->roster_acknowledged_at)
            );
        $studentRosterIsOldFile = $studentRoster !== null
            && $studentRoster->roster_acknowledged_at === null
            && $courseRequest->notifications->contains(fn ($notification) =>
                $notification->notification_type === 'STUDENT_ROSTER_REOPENED'
                && $notification->created_at?->greaterThanOrEqualTo($studentRoster->uploaded_at)
            );
        $reason = match ($courseRequest->status) {
            'REJECTED' => match ($latestStatusHistory?->change_source) {
                'DEAN' => $latestDeanReview?->comment,
                'OFFICER' => $latestReview?->rejection_reason,
                default => $latestApproval?->comment,
            },
            default => null,
        };

        return [
            'id' => $courseRequest->request_id,
            'number' => $courseRequest->request_no,
            'requester' => $this->personName($courseRequest->requester_pers_id),
            'proposer' => trim($courseRequest->coordinator_first_name.' '.$courseRequest->coordinator_last_name),
            'requester_unit' => $this->personDepartmentName((int) $courseRequest->requester_pers_id),
            'target_dept_id' => $courseRequest->target_dept_id,
            'unit' => $this->departmentName($courseRequest->target_dept_id),
            'target_subdept_id' => $courseRequest->target_subdept_id,
            'subunit' => $courseRequest->target_subdept_id
                ? $this->departmentDirectory->subdepartmentName((int) $courseRequest->target_subdept_id)
                : null,
            'dean_pers_id' => $courseRequest->dean_pers_id,
            'dean_name' => $courseRequest->dean_pers_id ? $this->personName((int) $courseRequest->dean_pers_id) : null,
            'dean_position' => $this->requestDeanPosition($courseRequest),
            'dean_decision' => $latestDeanReview?->decision,
            'dean_decided_at' => $latestDeanReview?->decided_at?->toDateString(),
            'form_type_code' => 'CC-ITED-06',
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
            'student_roster_reopen_requested' => $studentRosterReopenRequested,
            'student_roster_needs_officer_attention' => $studentRoster !== null && $studentRoster->roster_acknowledged_at === null,
            'student_roster_is_old_file' => $studentRosterIsOldFile,
            'student_roster_is_update' => $studentRosterVersionCount > 1,
            'expected_students' => $courseRequest->expected_students,
            'status' => $courseRequest->status,
            'submitted_at' => $courseRequest->submitted_at?->toDateString() ?? $courseRequest->created_at?->toDateString(),
            'course_id' => $courseRequest->course_id,
            'completion_notification_id' => $courseIdNotification?->notification_id,
            'reason' => $reason,
            'decision_source' => $latestStatusHistory?->change_source,
            'officer_decision' => $latestReview?->decision,
            'officer_name' => $latestReview ? $this->personName((int) $latestReview->officer_pers_id) : null,
            'officer_reviewed_at' => $this->thaiBuddhistDate($latestReview?->reviewed_at),
            'approval_decision' => $latestApproval?->decision,
            'approver_name' => $latestApproval ? $this->personName((int) $latestApproval->approver_pers_id) : null,
            'approval_comment' => $latestApproval?->comment,
            'approval_decided_at' => $this->thaiBuddhistDate($latestApproval?->decided_at),
            'signed_path' => $signed?->storage_key,
            'document_name' => $this->requestDocumentFilename($courseRequest->request_no),
            'signed_name' => $signed ? $this->requestDocumentFilename($courseRequest->request_no) : null,
            'additional_files' => $additionalFiles,
            'removed_additional_document_ids' => [],
        ];
    }

    private function toWebsiteRecord(CourseRequest $courseRequest): array
    {
        $detail = $courseRequest->websiteDetail;
        $contact = $courseRequest->websiteContact;
        $contactParts = array_pad(preg_split('/\s+/u', trim((string) $contact?->full_name), 2) ?: [], 2, '');
        $signed = $courseRequest->documents->where('document_type', 'SIGNED_FORM')->sortByDesc('uploaded_at')->first();
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
        $latestDeanReview = $courseRequest->deanReviews->sortByDesc('decided_at')->first();
        $latestStatusHistory = $courseRequest->histories
            ->sortByDesc(fn ($history) => sprintf('%s-%020d', $history->changed_at?->format('Y-m-d H:i:s.u'), $history->history_id))
            ->first();
        $completionNotification = $courseRequest->notifications
            ->where('notification_type', 'REQUEST_COMPLETED_REQUESTER')
            ->sortByDesc('notification_id')
            ->first();
        $reason = match ($courseRequest->status) {
            'REJECTED' => $latestStatusHistory?->change_source === 'OFFICER'
                ? $latestReview?->rejection_reason
                : $latestDeanReview?->comment,
            default => null,
        };
        $scopeLabel = match ($detail?->site_scope_type) {
            'DEPARTMENT' => 'เว็บไซต์ส่วนงาน',
            'PROJECT' => 'เว็บไซต์โครงการ',
            'CONFERENCE' => 'เว็บไซต์ประชุมวิชาการ',
            default => null,
        };

        return [
            'id' => $courseRequest->request_id,
            'number' => $courseRequest->request_no,
            'request_no' => $courseRequest->request_no,
            'requester' => $this->personName((int) $courseRequest->requester_pers_id),
            'proposer' => trim((string) $contact?->full_name),
            'requester_unit' => $this->personDepartmentName((int) $courseRequest->requester_pers_id),
            'target_dept_id' => $courseRequest->target_dept_id,
            'unit' => $this->departmentName((int) $courseRequest->target_dept_id),
            'target_subdept_id' => $courseRequest->target_subdept_id,
            'subunit' => $courseRequest->target_subdept_id
                ? $this->departmentDirectory->subdepartmentName((int) $courseRequest->target_subdept_id)
                : null,
            'dean_pers_id' => $courseRequest->dean_pers_id,
            'dean_name' => $courseRequest->dean_pers_id ? $this->personName((int) $courseRequest->dean_pers_id) : null,
            'dean_position' => $this->requestDeanPosition($courseRequest),
            'dean_decision' => $latestDeanReview?->decision,
            'dean_decided_at' => $latestDeanReview?->decided_at?->toDateString(),
            'form_type_code' => 'CC-ITED-01',
            'project_name' => $detail?->website_name,
            'website_name' => $detail?->website_name,
            'domain_url' => $detail?->domain_url,
            'site_scope_type' => $detail?->site_scope_type,
            'site_scope_label' => $scopeLabel,
            'service_starts_on' => $detail?->service_starts_on?->format('Y-m-d'),
            'service_ends_on' => $detail?->service_ends_on?->format('Y-m-d'),
            'platform_code' => $detail?->platform_code,
            'platform_label' => $detail?->platform?->platform_name_th,
            'additional_detail' => $detail?->additional_detail,
            'allocated_domain' => $detail?->allocated_domain,
            'contact_first' => $contactParts[0],
            'contact_last' => $contactParts[1],
            'contact_position' => $contact?->position_name,
            'contact_phone' => $contact?->phone,
            'contact_email' => $contact?->email,
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_versions' => [],
            'student_roster_version_count' => 0,
            'student_roster_needs_officer_attention' => false,
            'student_roster_reopen_requested' => false,
            'student_roster_acknowledged' => false,
            'status' => $courseRequest->status,
            'submitted_at' => $courseRequest->submitted_at?->toDateString() ?? $courseRequest->created_at?->toDateString(),
            'completion_notification_id' => $completionNotification?->notification_id,
            'reason' => $reason,
            'decision_source' => $latestStatusHistory?->change_source,
            'officer_decision' => $latestReview?->decision,
            'officer_name' => $latestReview ? $this->personName((int) $latestReview->officer_pers_id) : null,
            'officer_reviewed_at' => $this->thaiBuddhistDate($latestReview?->reviewed_at),
            'approval_decision' => null,
            'approver_name' => null,
            'approval_comment' => null,
            'approval_decided_at' => null,
            'signed_path' => $signed?->storage_key,
            'document_name' => $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-01'),
            'signed_name' => $signed ? $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-01') : null,
            'additional_files' => $additionalFiles,
            'removed_additional_document_ids' => [],
        ];
    }

    private function toWebsiteStorageRecord(CourseRequest $courseRequest): array
    {
        $detail = $courseRequest->websiteStorageDetail;
        $contact = $courseRequest->websiteContact;
        $contactParts = array_pad(preg_split('/\s+/u', trim((string) $contact?->full_name), 2) ?: [], 2, '');
        $signed = $courseRequest->documents->where('document_type', 'SIGNED_FORM')->sortByDesc('uploaded_at')->first();
        $additionalFiles = $courseRequest->documents->where('document_type', 'ADDITIONAL_DOCUMENT')->sortBy('uploaded_at')->map(fn ($document) => [
            'document_id' => $document->document_id,
            'additional_path' => $document->storage_key,
            'additional_name' => $document->original_filename,
            'additional_mime' => $document->mime_type,
            'additional_size' => $document->file_size_bytes,
            'additional_pending' => false,
        ])->values()->all();
        $latestReview = $courseRequest->officerReviews->sortByDesc('reviewed_at')->first();
        $latestDeanReview = $courseRequest->deanReviews->sortByDesc('decided_at')->first();
        $latestStatusHistory = $courseRequest->histories->sortByDesc(fn ($history) => sprintf('%s-%020d', $history->changed_at?->format('Y-m-d H:i:s.u'), $history->history_id))->first();
        $completionNotification = $courseRequest->notifications->where('notification_type', 'REQUEST_COMPLETED_REQUESTER')->sortByDesc('notification_id')->first();
        $reason = $courseRequest->status === 'REJECTED'
            ? ($latestStatusHistory?->change_source === 'OFFICER' ? $latestReview?->rejection_reason : $latestDeanReview?->comment)
            : null;

        return [
            'id' => $courseRequest->request_id,
            'number' => $courseRequest->request_no,
            'request_no' => $courseRequest->request_no,
            'requester' => $this->personName((int) $courseRequest->requester_pers_id),
            'proposer' => trim((string) $contact?->full_name),
            'requester_unit' => $this->personDepartmentName((int) $courseRequest->requester_pers_id),
            'target_dept_id' => $courseRequest->target_dept_id,
            'unit' => $this->departmentName((int) $courseRequest->target_dept_id),
            'target_subdept_id' => $courseRequest->target_subdept_id,
            'subunit' => $courseRequest->target_subdept_id ? $this->departmentDirectory->subdepartmentName((int) $courseRequest->target_subdept_id) : null,
            'dean_pers_id' => $courseRequest->dean_pers_id,
            'dean_name' => $courseRequest->dean_pers_id ? $this->personName((int) $courseRequest->dean_pers_id) : null,
            'dean_position' => $this->requestDeanPosition($courseRequest),
            'dean_decision' => $latestDeanReview?->decision,
            'dean_decided_at' => $latestDeanReview?->decided_at?->toDateString(),
            'form_type_code' => 'CC-ITED-04',
            'project_name' => $detail?->website_name,
            'website_name' => $detail?->website_name,
            'domain_url' => $detail?->domain_url,
            'requested_space_gb' => $detail?->requested_space_gb,
            'granted_space_gb' => $detail?->granted_space_gb,
            'execution_detail' => $detail?->execution_detail,
            'contact_first' => $contactParts[0],
            'contact_last' => $contactParts[1],
            'contact_position' => $contact?->position_name,
            'contact_phone' => $contact?->phone,
            'contact_email' => $contact?->email,
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_versions' => [],
            'student_roster_version_count' => 0,
            'student_roster_needs_officer_attention' => false,
            'student_roster_reopen_requested' => false,
            'student_roster_acknowledged' => false,
            'status' => $courseRequest->status,
            'submitted_at' => $courseRequest->submitted_at?->toDateString() ?? $courseRequest->created_at?->toDateString(),
            'completion_notification_id' => $completionNotification?->notification_id,
            'reason' => $reason,
            'decision_source' => $latestStatusHistory?->change_source,
            'officer_decision' => $latestReview?->decision,
            'officer_name' => $latestReview ? $this->personName((int) $latestReview->officer_pers_id) : null,
            'officer_reviewed_at' => $this->thaiBuddhistDate($latestReview?->reviewed_at),
            'approval_decision' => null,
            'approver_name' => null,
            'approval_comment' => null,
            'approval_decided_at' => null,
            'signed_path' => $signed?->storage_key,
            'document_name' => $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-04'),
            'signed_name' => $signed ? $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-04') : null,
            'additional_files' => $additionalFiles,
            'removed_additional_document_ids' => [],
        ];
    }

    private function toEmeetingRecord(CourseRequest $courseRequest): array
    {
        $detail = $courseRequest->emeetingDetail;
        $currentContact = $courseRequest->currentEmeetingContact;
        $contact = $courseRequest->emeetingContact;
        $currentParts = array_pad(preg_split('/\s+/u', trim((string) $currentContact?->full_name), 2) ?: [], 2, '');
        $contactParts = array_pad(preg_split('/\s+/u', trim((string) $contact?->full_name), 2) ?: [], 2, '');
        $signed = $courseRequest->documents->where('document_type', 'SIGNED_FORM')->sortByDesc('uploaded_at')->first();
        $additionalFiles = $courseRequest->documents->where('document_type', 'ADDITIONAL_DOCUMENT')->sortBy('uploaded_at')->map(fn ($document) => [
            'document_id' => $document->document_id,
            'additional_path' => $document->storage_key,
            'additional_name' => $document->original_filename,
            'additional_mime' => $document->mime_type,
            'additional_size' => $document->file_size_bytes,
            'additional_pending' => false,
        ])->values()->all();
        $latestReview = $courseRequest->officerReviews->sortByDesc('reviewed_at')->first();
        $latestDeanReview = $courseRequest->deanReviews->sortByDesc('decided_at')->first();
        $latestStatusHistory = $courseRequest->histories->sortByDesc(fn ($history) => sprintf('%s-%020d', $history->changed_at?->format('Y-m-d H:i:s.u'), $history->history_id))->first();
        $completionNotification = $courseRequest->notifications->where('notification_type', 'REQUEST_COMPLETED_REQUESTER')->sortByDesc('notification_id')->first();
        $reason = $courseRequest->status === 'REJECTED'
            ? ($latestStatusHistory?->change_source === 'OFFICER' ? $latestReview?->rejection_reason : $latestDeanReview?->comment)
            : null;

        return [
            'id' => $courseRequest->request_id,
            'number' => $courseRequest->request_no,
            'request_no' => $courseRequest->request_no,
            'requester' => $this->personName((int) $courseRequest->requester_pers_id),
            'proposer' => trim((string) $contact?->full_name),
            'requester_unit' => $this->personDepartmentName((int) $courseRequest->requester_pers_id),
            'target_dept_id' => $courseRequest->target_dept_id,
            'unit' => $this->departmentName((int) $courseRequest->target_dept_id),
            'target_subdept_id' => $courseRequest->target_subdept_id,
            'subunit' => $courseRequest->target_subdept_id ? $this->departmentDirectory->subdepartmentName((int) $courseRequest->target_subdept_id) : null,
            'dean_pers_id' => $courseRequest->dean_pers_id,
            'dean_name' => $courseRequest->dean_pers_id ? $this->personName((int) $courseRequest->dean_pers_id) : null,
            'dean_position' => $this->requestDeanPosition($courseRequest),
            'dean_decision' => $latestDeanReview?->decision,
            'dean_decided_at' => $latestDeanReview?->decided_at?->toDateString(),
            'form_type_code' => 'CC-ITED-05',
            'project_name' => $detail?->action_type === 'CHANGE_CONTACT'
                ? 'เปลี่ยนแปลงสิทธิ์ผู้ดูแลกรอบการประชุม SWU e-Meeting'
                : 'ขอสิทธิ์ผู้ดูแลกรอบการประชุม SWU e-Meeting',
            'action_type' => $detail?->action_type,
            'execution_detail' => $detail?->execution_detail,
            'current_contact_first' => $currentParts[0],
            'current_contact_last' => $currentParts[1],
            'contact_first' => $contactParts[0],
            'contact_last' => $contactParts[1],
            'contact_position' => $contact?->position_name,
            'contact_phone' => $contact?->phone,
            'contact_email' => $contact?->email,
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_versions' => [],
            'student_roster_version_count' => 0,
            'student_roster_needs_officer_attention' => false,
            'student_roster_reopen_requested' => false,
            'student_roster_acknowledged' => false,
            'status' => $courseRequest->status,
            'submitted_at' => $courseRequest->submitted_at?->toDateString() ?? $courseRequest->created_at?->toDateString(),
            'completion_notification_id' => $completionNotification?->notification_id,
            'reason' => $reason,
            'decision_source' => $latestStatusHistory?->change_source,
            'officer_decision' => $latestReview?->decision,
            'officer_name' => $latestReview ? $this->personName((int) $latestReview->officer_pers_id) : null,
            'officer_reviewed_at' => $this->thaiBuddhistDate($latestReview?->reviewed_at),
            'approval_decision' => null,
            'approver_name' => null,
            'approval_comment' => null,
            'approval_decided_at' => null,
            'signed_path' => $signed?->storage_key,
            'document_name' => $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-05'),
            'signed_name' => $signed ? $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-05') : null,
            'additional_files' => $additionalFiles,
            'removed_additional_document_ids' => [],
        ];
    }

    private function toWebsiteContactChangeRecord(CourseRequest $courseRequest): array
    {
        $detail = $courseRequest->websiteContactChangeDetail;
        $currentContact = $courseRequest->currentWebsiteContact;
        $newContact = $courseRequest->newWebsiteContact;
        $currentParts = array_pad(preg_split('/\s+/u', trim((string) $currentContact?->full_name), 2) ?: [], 2, '');
        $newParts = array_pad(preg_split('/\s+/u', trim((string) $newContact?->full_name), 2) ?: [], 2, '');
        $signed = $courseRequest->documents->where('document_type', 'SIGNED_FORM')->sortByDesc('uploaded_at')->first();
        $additionalFiles = $courseRequest->documents->where('document_type', 'ADDITIONAL_DOCUMENT')->sortBy('uploaded_at')->map(fn ($document) => [
            'document_id' => $document->document_id,
            'additional_path' => $document->storage_key,
            'additional_name' => $document->original_filename,
            'additional_mime' => $document->mime_type,
            'additional_size' => $document->file_size_bytes,
            'additional_pending' => false,
        ])->values()->all();
        $latestReview = $courseRequest->officerReviews->sortByDesc('reviewed_at')->first();
        $latestDeanReview = $courseRequest->deanReviews->sortByDesc('decided_at')->first();
        $latestStatusHistory = $courseRequest->histories->sortByDesc(fn ($history) => sprintf('%s-%020d', $history->changed_at?->format('Y-m-d H:i:s.u'), $history->history_id))->first();
        $completionNotification = $courseRequest->notifications->where('notification_type', 'REQUEST_COMPLETED_REQUESTER')->sortByDesc('notification_id')->first();
        $reason = $courseRequest->status === 'REJECTED'
            ? ($latestStatusHistory?->change_source === 'OFFICER' ? $latestReview?->rejection_reason : $latestDeanReview?->comment)
            : null;

        return [
            'id' => $courseRequest->request_id,
            'number' => $courseRequest->request_no,
            'request_no' => $courseRequest->request_no,
            'requester' => $this->personName((int) $courseRequest->requester_pers_id),
            'proposer' => trim((string) $newContact?->full_name),
            'requester_unit' => $this->personDepartmentName((int) $courseRequest->requester_pers_id),
            'target_dept_id' => $courseRequest->target_dept_id,
            'unit' => $this->departmentName((int) $courseRequest->target_dept_id),
            'target_subdept_id' => $courseRequest->target_subdept_id,
            'subunit' => $courseRequest->target_subdept_id ? $this->departmentDirectory->subdepartmentName((int) $courseRequest->target_subdept_id) : null,
            'dean_pers_id' => $courseRequest->dean_pers_id,
            'dean_name' => $courseRequest->dean_pers_id ? $this->personName((int) $courseRequest->dean_pers_id) : null,
            'dean_position' => $this->requestDeanPosition($courseRequest),
            'dean_decision' => $latestDeanReview?->decision,
            'dean_decided_at' => $latestDeanReview?->decided_at?->toDateString(),
            'form_type_code' => 'CC-ITED-02',
            'project_name' => $detail?->website_name,
            'website_name' => $detail?->website_name,
            'domain_url' => $detail?->domain_url,
            'execution_detail' => $detail?->execution_detail,
            'current_contact_first' => $currentParts[0],
            'current_contact_last' => $currentParts[1],
            'new_contact_first' => $newParts[0],
            'new_contact_last' => $newParts[1],
            'new_contact_position' => $newContact?->position_name,
            'new_contact_phone' => $newContact?->phone,
            'new_contact_email' => $newContact?->email,
            'requires_student_roster' => false,
            'has_student_roster' => false,
            'student_roster_versions' => [],
            'student_roster_version_count' => 0,
            'student_roster_needs_officer_attention' => false,
            'student_roster_reopen_requested' => false,
            'student_roster_acknowledged' => false,
            'status' => $courseRequest->status,
            'submitted_at' => $courseRequest->submitted_at?->toDateString() ?? $courseRequest->created_at?->toDateString(),
            'completion_notification_id' => $completionNotification?->notification_id,
            'reason' => $reason,
            'decision_source' => $latestStatusHistory?->change_source,
            'officer_decision' => $latestReview?->decision,
            'officer_name' => $latestReview ? $this->personName((int) $latestReview->officer_pers_id) : null,
            'officer_reviewed_at' => $this->thaiBuddhistDate($latestReview?->reviewed_at),
            'approval_decision' => null,
            'approver_name' => null,
            'approval_comment' => null,
            'approval_decided_at' => null,
            'signed_path' => $signed?->storage_key,
            'document_name' => $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-02'),
            'signed_name' => $signed ? $this->requestDocumentFilename($courseRequest->request_no, 'CC-ITED-02') : null,
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
            'original_filename' => $type === 'SIGNED_FORM'
                ? $this->requestDocumentFilename($courseRequest->request_no, $courseRequest->form_type_code)
                : $data[$prefix . '_name'],
            'mime_type' => $data[$prefix . '_mime'],
            'file_size_bytes' => $data[$prefix . '_size'],
            'uploaded_by_pers_id' => $uploaderId,
            'version_no' => ((int) $courseRequest->documents()->where('document_type_code', $type)->max('version_no')) + 1,
            'uploaded_at' => now(),
        ]);
    }

    private function ensureSignedFormDocument(
        CourseRequest $courseRequest,
        int $uploaderId,
        bool $forceFresh = false,
    ): CourseDocument
    {
        if (! $forceFresh) {
            $existing = $courseRequest->documents()
                ->where('document_type_code', 'SIGNED_FORM')
                ->latest('uploaded_at')
                ->latest('document_id')
                ->first();

            if ($existing && Storage::disk('local')->exists($existing->storage_key)) {
                return $existing;
            }
        }

        $record = $this->toRecord($courseRequest);
        $contents = $this->documentPdfRenderer->render($record);
        $path = 'course-requests/' . now()->format('Y/m') . '/' . Str::uuid() . '.pdf';

        abort_unless(Storage::disk('local')->put($path, $contents), 500, 'ไม่สามารถสร้างเอกสารสำหรับลงนามได้');

        return CourseDocument::create([
            'request_id' => $courseRequest->request_id,
            'document_type' => 'SIGNED_FORM',
            'storage_key' => $path,
            'original_filename' => $this->requestDocumentFilename($courseRequest->request_no, $courseRequest->form_type_code),
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($contents),
            'uploaded_by_pers_id' => $uploaderId,
            'version_no' => ((int) $courseRequest->documents()->where('document_type_code', 'SIGNED_FORM')->max('version_no')) + 1,
            'uploaded_at' => now(),
        ]);
    }

    private function activeDeanPersonIds(int $targetDepartmentId): array
    {
        if ($targetDepartmentId <= 0) {
            return [];
        }

        $personIds = FacultyDean::query()
            ->where('target_dept_id', $targetDepartmentId)
            ->where('is_active', 'Y')
            ->orderBy('faculty_dean_id')
            ->pluck('dean_pers_id')
            ->map(fn ($personId) => (int) $personId)
            ->all();

        if ($personIds === []) {
            $fallbackPersonId = (int) config('course-workflow.dean.fallback_person_id', 0);
            if ($fallbackPersonId > 0) {
                return [$fallbackPersonId];
            }
        }

        return array_values(array_filter($personIds, function (int $personId): bool {
            $person = $this->personnelDirectory->findByPersonId($personId);

            return $person !== null && $this->personnelDirectory->email($person) !== null;
        }));
    }

    private function deanOptionsForTargetDepartment(int $targetDepartmentId): array
    {
        return collect($this->activeDeanPersonIds($targetDepartmentId))
            ->mapWithKeys(fn (int $personId) => [$personId => $this->personName($personId)])
            ->all();
    }

    private function activeWebsiteDeanPersonIds(int $targetDepartmentId): array
    {
        if ($this->usesTestNotificationRecipient()) {
            $departmentDeanPersonIds = $this->activeDeanPersonIds($targetDepartmentId);
            $personId = (int) config('course-workflow.notifications.test_recipient_person_id', 0);
            $expectedEmail = strtolower(trim((string) config('course-workflow.notifications.test_recipient_email')));
            $person = $personId > 0 ? $this->personnelDirectory->findByPersonId($personId) : null;
            $actualEmail = $person !== null
                ? strtolower(trim((string) $this->personnelDirectory->email($person)))
                : '';

            $departmentDeanPersonIds = array_values(array_filter(
                $departmentDeanPersonIds,
                fn (int $departmentDeanPersonId): bool => $departmentDeanPersonId !== $personId,
            ));

            if ($expectedEmail !== '' && hash_equals($expectedEmail, $actualEmail)) {
                $departmentDeanPersonIds[] = $personId;
            }

            return array_values(array_unique($departmentDeanPersonIds));
        }

        return $this->activeDeanPersonIds($targetDepartmentId);
    }

    private function websiteDeanOptionsForTargetDepartment(int $targetDepartmentId): array
    {
        $personIds = $this->activeWebsiteDeanPersonIds($targetDepartmentId);

        if ($this->usesTestNotificationRecipient()) {
            $email = strtolower(trim((string) config('course-workflow.notifications.test_recipient_email')));
            $testRecipientPersonId = (int) config('course-workflow.notifications.test_recipient_person_id', 0);

            return collect($personIds)
                ->mapWithKeys(fn (int $personId) => [
                    $personId => $personId === $testRecipientPersonId
                        ? trim($this->personName($personId).' ('.$email.')')
                        : $this->personName($personId),
                ])
                ->all();
        }

        return collect($personIds)
            ->mapWithKeys(fn (int $personId) => [$personId => $this->personName($personId)])
            ->all();
    }

    private function usesTestNotificationRecipient(): bool
    {
        return app()->environment(['local', 'uat']);
    }

    private function addHistory(CourseRequest $courseRequest, string $status, ?int $personId, string $source): void
    {
        $previousStatus = $courseRequest->histories()->latest('changed_at')->value('to_status_code');
        RequestStatusHistory::create([
            'request_id' => $courseRequest->request_id,
            'from_status_code' => $previousStatus,
            'to_status_code' => $status,
            'changed_by_pers_id' => $personId,
            'change_source' => $source,
            'changed_at' => now(),
        ]);
    }

    private function queueNotification(CourseRequest $courseRequest, string $type, array $recipient, array $actor): void
    {
        NotificationOutbox::create([
            'request_id' => $courseRequest->request_id,
            'notification_type' => $type,
            'recipient_pers_id' => $recipient['pers_id'],
            'recipient_email' => $recipient['email'],
            'reply_to_email' => $actor['email'],
            'reply_to_name' => $actor['name'],
            'delivery_status' => 'PENDING',
            'attempt_count' => 0,
            'created_at' => now(),
        ]);
    }

    private function queueRoleNotifications(CourseRequest $courseRequest, string $type, string $roleCode, array $actor): void
    {
        foreach ($this->roleRecipients($roleCode) as $recipient) {
            $this->queueNotification($courseRequest, $type, $recipient, $actor);
        }
    }

    private function requesterRecipient(CourseRequest $courseRequest): array
    {
        $personId = (int) $courseRequest->requester_pers_id;
        $person = $this->personnelDirectory->findByPersonId($personId);
        $email = $person ? $this->personnelDirectory->email($person) : null;

        if ($email !== null) {
            return ['pers_id' => $personId, 'email' => $email];
        }

        throw ValidationException::withMessages([
            'email' => 'ไม่พบอีเมล Google Workspace ของผู้ยื่นคำร้องใน SWU MIS API',
        ]);
    }

    private function personRecipient(int $personId, string $roleLabel): array
    {
        $person = $this->personnelDirectory->findByPersonId($personId);
        $email = $person ? $this->personnelDirectory->email($person) : null;

        if ($person !== null && $email !== null) {
            return ['pers_id' => $personId, 'email' => $email];
        }

        throw ValidationException::withMessages([
            'email' => "ไม่พบอีเมล Google Workspace ของ{$roleLabel}ใน SWU MIS API",
        ]);
    }

    private function personActor(int $personId, string $roleLabel): array
    {
        $recipient = $this->personRecipient($personId, $roleLabel);
        $person = $this->personnelDirectory->findByPersonId($personId);

        return $recipient + [
            'name' => $person ? $this->personnelDirectory->name($person) : $roleLabel,
        ];
    }

    private function roleRecipients(string $roleCode): array
    {
        $personIds = DB::connection('course133')
            ->table('service_portal.user_role_assignment as assignments')
            ->join('service_portal.app_role as roles', 'roles.role_id', '=', 'assignments.role_id')
            ->where('roles.role_code', $roleCode)
            ->where('assignments.is_active', 'Y')
            ->orderBy('assignments.role_assignment_id')
            ->pluck('assignments.pers_id');

        $recipients = $personIds->map(function ($personId) use ($roleCode) {
            $personId = (int) $personId;
            $person = $this->personnelDirectory->findByPersonId($personId);
            $email = $person ? $this->personnelDirectory->email($person) : null;

            if ($email === null) {
                throw ValidationException::withMessages([
                    'role' => "ไม่พบอีเมลของบุคลากรรหัส $personId สำหรับสิทธิ $roleCode",
                ]);
            }

            return ['pers_id' => $personId, 'email' => $email];
        })->values()->all();

        if ($recipients !== []) {
            return $recipients;
        }

        throw ValidationException::withMessages([
            'role' => "ยังไม่ได้กำหนดบุคลากรจริงสำหรับสิทธิ $roleCode",
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

    private function thaiBuddhistDate(?\DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $localizedDate = \Carbon\Carbon::instance($date)->locale('th');

        return $localizedDate->translatedFormat('j F').' '.($localizedDate->year + 543);
    }

    private function nextRequestNumber(): string
    {
        do {
            $number = 'SWU-' . date('Y') . '-' . (string) Str::upper(Str::random(8));
        } while (CourseRequest::query()->where('request_no', $number)->exists());

        return $number;
    }

    private function requestDocumentFilename(string $requestNumber, string $formTypeCode = 'CC-ITED-06'): string
    {
        return $formTypeCode . '_' . Str::afterLast($requestNumber, '-') . '.pdf';
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
            default => 'requests.index',
        };
    }

    private function personName(int $personId): string
    {
        $person = $this->personnelDirectory->findByPersonId($personId);
        if ($person !== null && ($name = $this->personnelDirectory->name($person)) !== '') {
            return $name;
        }

        return "บุคลากร #$personId";
    }

    private function personPosition(int $personId): string
    {
        $person = $this->personnelDirectory->findByPersonId($personId);

        return $person !== null ? trim((string) $person->position) : '';
    }

    private function deanPositionName(int $targetDepartmentId, int $personId): string
    {
        $position = FacultyDean::query()
            ->where('target_dept_id', $targetDepartmentId)
            ->where('dean_pers_id', $personId)
            ->where('is_active', 'Y')
            ->value('position_name');

        return filled($position)
            ? trim((string) $position)
            : $this->personPosition($personId);
    }

    private function requestDeanPosition(CourseRequest $courseRequest): ?string
    {
        if (filled($courseRequest->dean_position_name)) {
            return trim((string) $courseRequest->dean_position_name);
        }

        if (! $courseRequest->dean_pers_id) {
            return null;
        }

        return $this->deanPositionName(
            (int) $courseRequest->target_dept_id,
            (int) $courseRequest->dean_pers_id,
        );
    }

    private function departmentName(int $departmentId): string
    {
        return $this->departmentDirectory->name($departmentId);
    }

    private function personDepartmentName(int $personId): string
    {
        $person = $this->personnelDirectory->findByPersonId($personId);
        if ($person !== null) {
            if (! blank($person->division)) {
                return trim((string) $person->division);
            }

            $departmentId = (int) ($person->dept_cd ?: $person->in_dept_cd);
            if ($departmentId > 0) {
                return $this->departmentName($departmentId);
            }
        }

        return '—';
    }

    private function generatedPdfResponse(array $record, string $filename, bool $inline)
    {
        $fallbackFilename = Str::ascii($filename);
        if ($fallbackFilename === '' || $fallbackFilename === '.pdf') {
            $fallbackFilename = 'document.pdf';
        }

        $response = response($this->documentPdfRenderer->render($record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                $fallbackFilename,
            ),
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function addUnicodeTextOverlay(Fpdi $pdf, array $items, float $pageWidth, float $pageHeight): void
    {
        if ($items === []) {
            return;
        }

        $scaleX = $pageWidth / 210;
        $scaleY = $pageHeight / 297;
        $fontCachePath = storage_path('fonts/tcpdf');
        abort_unless(
            is_dir($fontCachePath) || @mkdir($fontCachePath, 0755, true) || is_dir($fontCachePath),
            500,
            'ไม่สามารถเตรียมพื้นที่เก็บฟอนต์ภาษาไทยสำหรับ PDF ได้',
        );
        $fontName = \TCPDF_FONTS::addTTFfont(
            resource_path('fonts/Sarabun-Regular.ttf'),
            'TrueTypeUnicode',
            '',
            96,
            $fontCachePath,
        );
        abort_unless(is_string($fontName) && $fontName !== '', 500, 'ไม่สามารถเตรียมฟอนต์ภาษาไทยสำหรับ PDF ได้');

        $overlayPdf = new \TCPDF('P', 'mm', [$pageWidth, $pageHeight], true, 'UTF-8', false);
        $overlayPdf->setPrintHeader(false);
        $overlayPdf->setPrintFooter(false);
        $overlayPdf->SetMargins(0, 0, 0);
        $overlayPdf->SetAutoPageBreak(false);
        $overlayPdf->setFontSubsetting(true);
        $overlayPdf->AddPage();
        $overlayPdf->AddFont($fontName, '', $fontCachePath.DIRECTORY_SEPARATOR.$fontName.'.php', true);
        $overlayPdf->setCellPaddings(0, 0, 0, 0);
        $overlayPdf->setCellMargins(0, 0, 0, 0);

        foreach ($items as $item) {
            $left = ((float) $item['x']) * $scaleX;
            $top = ((float) $item['top']) * $scaleY;
            $width = ((float) $item['width']) * $scaleX;
            $fontSize = (float) $item['font_size'];
            $align = strtolower((string) ($item['align'] ?? 'left'));
            $align = match (substr($align, 0, 1)) {
                'c' => 'center',
                'r' => 'right',
                default => 'left',
            };
            $color = $item['color'] ?? '#000000';
            $lineHeight = ((float) ($item['line_height'] ?? max(4.2, $fontSize * 0.48))) * $scaleY;
            $rgb = sscanf(ltrim((string) $color, '#'), '%02x%02x%02x');
            $overlayPdf->SetTextColor($rgb[0] ?? 0, $rgb[1] ?? 0, $rgb[2] ?? 0);
            $overlayPdf->SetFont($fontName, '', $fontSize);
            $overlayPdf->SetXY($left, $top);

            if ($item['wrap'] ?? false) {
                $overlayPdf->MultiCell(
                    $width,
                    $lineHeight,
                    (string) $item['text'],
                    0,
                    strtoupper(substr($align, 0, 1)),
                    false,
                    1,
                    null,
                    null,
                    true,
                    0,
                    false,
                    true,
                    0,
                    'T',
                    false,
                );
            } else {
                $overlayPdf->Cell(
                    $width,
                    $lineHeight,
                    (string) $item['text'],
                    0,
                    0,
                    strtoupper(substr($align, 0, 1)),
                );
            }
        }

        $overlay = $overlayPdf->Output('', 'S');
        $pdf->setSourceFile(StreamReader::createByString($overlay));
        $overlayTemplate = $pdf->importPage(1);
        $pdf->useTemplate($overlayTemplate, 0, 0, $pageWidth, $pageHeight);
    }

    private function recordRelations(): array
    {
        return ['moodleCourseDetail.projectType', 'moodleCourseDetail.category', 'coordinatorContact', 'websiteDetail.platform', 'websiteContact', 'websiteContactChangeDetail', 'websiteStorageDetail', 'emeetingDetail', 'currentWebsiteContact', 'newWebsiteContact', 'currentEmeetingContact', 'emeetingContact', 'instructors', 'documents', 'deanReviews', 'officerReviews', 'notifications', 'histories'];
    }
}
