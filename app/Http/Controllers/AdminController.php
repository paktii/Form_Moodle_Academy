<?php

namespace App\Http\Controllers;

use App\Models\ServicePortal\FacultyDean;
use App\Models\ServicePortal\FormHeadAssignment;
use App\Models\ServicePortal\FormOfficerAssignment;
use App\Models\ServicePortal\FormType;
use App\Services\DepartmentDirectory;
use App\Services\PersonnelDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function __construct(
        private readonly DepartmentDirectory $departmentDirectory,
        private readonly PersonnelDirectory $personnelDirectory,
    ) {
    }

    public function index()
    {
        $departments = $this->departmentDirectory->options();
        $deanAssignments = FacultyDean::query()
            ->where('is_active', 'Y')
            ->orderBy('target_dept_id')
            ->orderBy('faculty_dean_id')
            ->get();
        $formTypes = FormType::query()
            ->where('is_active', 'Y')
            ->orderBy('sort_order')
            ->orderBy('form_type_code')
            ->get(['form_type_code', 'form_name_th', 'short_name_th']);
        $officerAssignments = FormOfficerAssignment::query()
            ->where('is_active', 'Y')
            ->orderBy('form_type_code')
            ->orderBy('officer_assignment_id')
            ->get()
            ->groupBy('form_type_code');
        $headAssignments = FormHeadAssignment::query()
            ->where('is_active', 'Y')
            ->orderBy('form_type_code')
            ->orderBy('head_assignment_id')
            ->get()
            ->groupBy('form_type_code');

        $personIds = $deanAssignments->pluck('dean_pers_id')
            ->merge($officerAssignments->flatten(1)->pluck('officer_pers_id'))
            ->merge($headAssignments->flatten(1)->pluck('head_pers_id'))
            ->map(fn ($personId) => (int) $personId)
            ->unique()
            ->values();
        $people = $personIds->mapWithKeys(fn (int $personId) => [
            $personId => $this->personSummary($personId),
        ]);

        return view('portal.admin', compact(
            'departments',
            'deanAssignments',
            'formTypes',
            'officerAssignments',
            'headAssignments',
            'people',
        ));
    }

    public function searchPersonnel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'target_dept_id' => ['nullable', 'integer'],
        ]);

        $people = $this->personnelDirectory->searchActive($data['q']);

        $results = collect($people)
            ->map(fn (object $person) => [
                'value' => (string) $person->buasri_id,
                'name' => $this->personnelDirectory->name($person),
                'buasri_id' => (string) $person->buasri_id,
                'department' => trim((string) $person->division),
                'person_id' => (int) $person->person_id,
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    public function updateDean(Request $request)
    {
        $departments = $this->departmentDirectory->options();
        $departmentIds = $departments->keys()->map(fn ($id) => (int) $id)->all();
        $data = $request->validate([
            'target_dept_id' => ['required', 'integer', Rule::in($departmentIds)],
            'dean_buasri_id' => ['required', 'string', 'max:200'],
            'position_name' => ['required', 'string', 'max:300'],
        ]);
        $person = $this->activePerson($data['dean_buasri_id'], 'dean_buasri_id');

        DB::connection('course133')->transaction(function () use ($data, $person): void {
            FacultyDean::query()
                ->where('target_dept_id', (int) $data['target_dept_id'])
                ->where('is_active', 'Y')
                ->update(['is_active' => 'N', 'updated_at' => now()]);

            FacultyDean::query()->updateOrCreate([
                'target_dept_id' => (int) $data['target_dept_id'],
                'dean_pers_id' => (int) $person->person_id,
            ], [
                'position_name' => trim($data['position_name']),
                'is_active' => 'Y',
                'updated_at' => now(),
            ]);
        });

        if ($request->expectsJson()) {
            $assignment = FacultyDean::query()
                ->where('target_dept_id', (int) $data['target_dept_id'])
                ->where('dean_pers_id', (int) $person->person_id)
                ->where('is_active', 'Y')
                ->firstOrFail();

            return response()->json([
                'message' => 'อัปเดตผู้รับรองของส่วนงานเรียบร้อยแล้ว',
                'row_html' => view('portal.partials.admin-dean-row', [
                    'assignment' => $assignment,
                    'departmentName' => $departments->get((int) $data['target_dept_id'], 'หน่วยงาน #'.$data['target_dept_id']),
                    'person' => $this->personSummary((int) $person->person_id),
                ])->render(),
            ]);
        }

        return back()->with('success', 'อัปเดตผู้รับรองของส่วนงานเรียบร้อยแล้ว');
    }

    public function updateHead(Request $request, string $formType)
    {
        $formType = $this->activeFormTypeCode($formType);
        $data = $request->validate([
            'head_buasri_id' => ['required', 'string', 'max:200'],
        ]);
        $person = $this->activePerson($data['head_buasri_id'], 'head_buasri_id');

        DB::connection('course133')->transaction(function () use ($formType, $person): void {
            $hasActiveHead = FormHeadAssignment::query()
                ->where('form_type_code', $formType)
                ->where('is_active', 'Y')
                ->lockForUpdate()
                ->exists();

            if ($hasActiveHead) {
                throw ValidationException::withMessages([
                    'head_buasri_id' => 'กรุณายกเลิกสิทธิ์หัวหน้าผู้รับผิดชอบคนเดิมก่อน จึงจะสามารถเพิ่มหัวหน้าคนใหม่ได้',
                ]);
            }

            FormHeadAssignment::query()->updateOrCreate([
                'form_type_code' => $formType,
                'head_pers_id' => (int) $person->person_id,
            ], [
                'is_active' => 'Y',
                'updated_at' => now(),
            ]);
        });

        $assignment = FormHeadAssignment::query()
            ->where('form_type_code', $formType)
            ->where('head_pers_id', (int) $person->person_id)
            ->where('is_active', 'Y')
            ->firstOrFail();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'อัปเดตหัวหน้าผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว',
                'row_html' => view('portal.partials.admin-assignment', [
                    'kind' => 'head',
                    'assignment' => $assignment,
                    'person' => $this->personSummary((int) $person->person_id),
                    'formType' => $formType,
                ])->render(),
            ]);
        }

        return back()->with('success', 'อัปเดตหัวหน้าผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว');
    }

    public function removeHead(Request $request, int $assignment)
    {
        $headAssignment = FormHeadAssignment::query()->findOrFail($assignment);
        $headAssignment->update([
            'is_active' => 'N',
            'updated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'ยกเลิกหัวหน้าผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว',
            ]);
        }

        return back()->with('success', 'ยกเลิกหัวหน้าผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว');
    }

    public function addOfficer(Request $request, string $formType)
    {
        $formType = $this->activeFormTypeCode($formType);
        $data = $request->validate([
            'officer_buasri_id' => ['required', 'string', 'max:200'],
        ]);
        $person = $this->activePerson($data['officer_buasri_id'], 'officer_buasri_id');

        $assignment = FormOfficerAssignment::query()->updateOrCreate([
            'form_type_code' => $formType,
            'officer_pers_id' => (int) $person->person_id,
        ], [
            'is_active' => 'Y',
            'updated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'เพิ่มเจ้าหน้าที่ผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว',
                'row_html' => view('portal.partials.admin-assignment', [
                    'kind' => 'officer',
                    'assignment' => $assignment,
                    'person' => $this->personSummary((int) $person->person_id),
                    'formType' => $formType,
                ])->render(),
            ]);
        }

        return back()->with('success', 'เพิ่มเจ้าหน้าที่ผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว');
    }

    public function removeOfficer(Request $request, int $assignment)
    {
        $officerAssignment = FormOfficerAssignment::query()->findOrFail($assignment);
        $officerAssignment->update([
            'is_active' => 'N',
            'updated_at' => now(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'ยกเลิกเจ้าหน้าที่ผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว',
            ]);
        }

        return back()->with('success', 'ยกเลิกเจ้าหน้าที่ผู้รับผิดชอบแบบฟอร์มเรียบร้อยแล้ว');
    }

    private function activePerson(string $buasriId, string $field): object
    {
        $query = trim($buasriId);
        $person = preg_match('/[^\x20-\x7E]/u', $query) || str_contains($query, ' ')
            ? $this->personnelDirectory->findActiveByName($query)
            : $this->personnelDirectory->findActiveByBuasriId(strtolower(strtok($query, '@')));

        if ($person === null) {
            throw ValidationException::withMessages([
                $field => 'ไม่พบบุคลากรที่ใช้งาน หรือพบชื่อซ้ำ กรุณาระบุชื่อ–นามสกุลให้ครบหรือใช้ Buasri ID',
            ]);
        }

        return $person;
    }

    private function activeFormTypeCode(string $formType): string
    {
        abort_unless(FormType::query()
            ->whereKey($formType)
            ->where('is_active', 'Y')
            ->exists(), 404);

        return $formType;
    }

    private function personSummary(int $personId): array
    {
        $person = $this->personnelDirectory->findByPersonId($personId);

        return [
            'name' => $person ? $this->personnelDirectory->name($person) : 'บุคลากร #'.$personId,
            'buasri_id' => $person?->buasri_id ?? '',
            'email' => $person ? ($this->personnelDirectory->email($person) ?? '') : '',
        ];
    }
}
