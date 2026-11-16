<x-portal-layout title="คำร้องแบบฟอร์มขอรับบริการ" header-context="จัดการผู้รับผิดชอบระบบ" tab-title="System Administration" role="admin" :show-header-logo="true">
    <main class="portal-main admin-page">
        <div class="admin-page__heading">
            <div>
                <h2>จัดการผู้รับผิดชอบระบบ</h2>
                <p>กำหนดผู้รับรองของส่วนงานและผู้รับผิดชอบแบบฟอร์ม</p>
            </div>
        </div>

        @if($errors->any())
        <div class="error-summary" role="alert" tabindex="-1">
            <strong>กรุณาตรวจสอบข้อมูล</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif

        <section class="portal-card admin-section" aria-labelledby="dean-management-heading">
            <div class="admin-section__heading">
                <div>
                    <h3 id="dean-management-heading">ผู้รับรองของส่วนงาน</h3>
                    <p>การบันทึกจะปิดผู้รับรองเดิมของส่วนงานและเปิดใช้งานผู้รับรองคนใหม่</p>
                </div>
            </div>

            <form method="post" action="{{ route('admin.deans.update') }}" class="admin-editor admin-editor--dean" data-admin-assignment-confirm data-admin-assignment-ajax data-admin-assignment-kind="dean" data-confirm-mode="dean">
                @csrf
                @method('put')
                <label class="form-label">ส่วนงาน<span class="required-indicator">*</span>
                    <select name="target_dept_id" class="form-field" required>
                        <option value="">เลือกส่วนงาน</option>
                        @foreach($departments as $departmentId => $departmentName)
                        <option value="{{ $departmentId }}" @selected((string) old('target_dept_id') === (string) $departmentId)>{{ $departmentName }}</option>
                        @endforeach
                    </select>
                    @error('target_dept_id')<span class="admin-field-error">{{ $message }}</span>@enderror
                </label>
                <x-admin-person-search name="dean_buasri_id" label="ผู้รับรอง" placeholder="พิมพ์ชื่อ–นามสกุล หรือ Buasri ID" depends-on="target_dept_id" />
                <label class="form-label">ตำแหน่งผู้รับรอง<span class="required-indicator">*</span>
                    <input name="position_name" class="form-field" value="{{ old('position_name') }}" placeholder="เช่น คณบดีคณะ..." required>
                    @error('position_name')<span class="admin-field-error">{{ $message }}</span>@enderror
                </label>
                <x-portal-button type="submit">บันทึกผู้รับรอง</x-portal-button>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ส่วนงาน</th><th>ผู้รับรองปัจจุบัน</th><th>Buasri ID</th><th>ตำแหน่ง</th></tr></thead>
                    <tbody data-admin-dean-rows>
                        @forelse($deanAssignments as $assignment)
                        @php
                            $person = $people->get((int) $assignment->dean_pers_id, []);
                        @endphp
                        <tr data-admin-dean-row data-admin-department-id="{{ $assignment->target_dept_id }}">
                            <td data-label="ส่วนงาน">{{ $departments->get((int) $assignment->target_dept_id, 'หน่วยงาน #'.$assignment->target_dept_id) }}</td>
                            <td data-label="ผู้รับรองปัจจุบัน">{{ $person['name'] ?? 'บุคลากร #'.$assignment->dean_pers_id }}</td>
                            <td data-label="Buasri ID">{{ $person['buasri_id'] ?? '-' }}</td>
                            <td data-label="ตำแหน่ง">{{ $assignment->position_name ?: '-' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="empty-state">ยังไม่มีผู้รับรองที่เปิดใช้งาน</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <nav class="admin-mobile-pagination" aria-label="แบ่งหน้ารายชื่อผู้รับรองของส่วนงาน" data-admin-dean-pagination hidden>
                <x-portal-button type="button" variant="secondary" aria-label="หน้าก่อนหน้า" title="หน้าก่อนหน้า" data-admin-dean-previous><x-portal-icon name="chevron-left" /></x-portal-button>
                <span aria-live="polite" data-admin-dean-page-status></span>
                <x-portal-button type="button" variant="secondary" aria-label="หน้าถัดไป" title="หน้าถัดไป" data-admin-dean-next><x-portal-icon name="chevron-right" /></x-portal-button>
            </nav>
        </section>

        <section class="portal-card admin-section admin-section--forms" aria-labelledby="form-management-heading">
            <div class="admin-section__heading">
                <div>
                    <h3 id="form-management-heading">ผู้รับผิดชอบแบบฟอร์ม</h3>
                    <p>กำหนดหัวหน้าหนึ่งคนและเจ้าหน้าที่ได้หลายคนต่อแบบฟอร์ม</p>
                </div>
            </div>

            <div class="admin-form-grid">
                @foreach($formTypes as $formType)
                @php
                    $heads = $headAssignments->get($formType->form_type_code, collect());
                    $officers = $officerAssignments->get($formType->form_type_code, collect());
                    $formDisplayName = match ($formType->form_type_code) {
                        'CC-ITED-01' => 'ขอใช้พื้นที่สำหรับจัดทำเว็บไซต์',
                        'CC-ITED-02' => 'ขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์',
                        'CC-ITED-04' => 'ขอเพิ่มพื้นที่บนเว็บไซต์',
                        'CC-ITED-05' => 'ขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงานบนระบบ e-Meeting',
                        'CC-ITED-06' => 'ขอสร้างรายวิชาในระบบ SWU Moodle Academy',
                        default => $formType->short_name_th ?: $formType->form_name_th,
                    };
                @endphp
                <details class="admin-form-card">
                    <summary class="admin-form-card__header">
                        <span class="admin-form-card__title">
                            <span>{{ $formType->form_type_code }}</span>
                            <strong>{{ $formDisplayName }}</strong>
                        </span>
                    </summary>

                    <div class="admin-form-card__body">
                    <div class="admin-responsibility" data-admin-responsibility="head">
                        <h5>หัวหน้าผู้รับผิดชอบ</h5>
                        <div class="admin-assignment-list">
                            @forelse($heads as $assignment)
                            @php
                                $person = $people->get((int) $assignment->head_pers_id, []);
                            @endphp
                            <div class="admin-assignment">
                                <span><strong>{{ $person['name'] ?? 'บุคลากร #'.$assignment->head_pers_id }}</strong><small>{{ $person['buasri_id'] ?? '' }}{{ filled($person['email'] ?? '') ? ' · '.$person['email'] : '' }}</small></span>
                                <form method="post" action="{{ route('admin.form-heads.remove', $assignment->head_assignment_id) }}" data-admin-assignment-confirm data-admin-assignment-ajax data-admin-assignment-kind="head" data-confirm-mode="remove" data-confirm-person="{{ $person['name'] ?? 'บุคลากร #'.$assignment->head_pers_id }}" data-confirm-role="หัวหน้าผู้รับผิดชอบ" data-confirm-form="{{ $formType->form_type_code }}">
                                    @csrf
                                    @method('delete')
                                    <x-portal-button type="submit" variant="danger-outline" aria-label="ยกเลิกสิทธิ์หัวหน้าผู้รับผิดชอบของ {{ $person['name'] ?? 'บุคลากร #'.$assignment->head_pers_id }}" title="ยกเลิกสิทธิ์"><x-portal-icon name="x" /></x-portal-button>
                                </form>
                            </div>
                            @empty
                            <p class="admin-assignment-list__empty">ยังไม่ได้กำหนดหัวหน้าผู้รับผิดชอบ</p>
                            @endforelse
                        </div>
                        <p class="admin-head-assignment-notice" role="status" data-admin-head-notice @if($heads->isEmpty()) hidden @endif>
                            กรุณายกเลิกสิทธิ์หัวหน้าผู้รับผิดชอบคนเดิมก่อน จึงจะสามารถเพิ่มหัวหน้าคนใหม่ได้
                        </p>
                        <form method="post" action="{{ route('admin.form-heads.update', $formType->form_type_code) }}" class="admin-inline-form" data-admin-assignment-confirm data-admin-assignment-ajax data-admin-assignment-kind="head" data-confirm-mode="add" data-confirm-role="หัวหน้าผู้รับผิดชอบ" data-confirm-form="{{ $formType->form_type_code }}" @if($heads->isNotEmpty()) hidden @endif>
                            @csrf
                            @method('put')
                            <x-admin-person-search name="head_buasri_id" label="หัวหน้าผู้รับผิดชอบ" :id="'head-'.$formType->form_type_code" :hide-label="true" />
                            <x-portal-button type="submit">บันทึกหัวหน้า</x-portal-button>
                        </form>
                    </div>

                    <div class="admin-responsibility" data-admin-responsibility="officer">
                        <h5>เจ้าหน้าที่ผู้รับผิดชอบ</h5>
                        <div class="admin-assignment-list">
                            @forelse($officers as $assignment)
                            @php
                                $person = $people->get((int) $assignment->officer_pers_id, []);
                            @endphp
                            <div class="admin-assignment">
                                <span><strong>{{ $person['name'] ?? 'บุคลากร #'.$assignment->officer_pers_id }}</strong><small>{{ $person['buasri_id'] ?? '' }}{{ filled($person['email'] ?? '') ? ' · '.$person['email'] : '' }}</small></span>
                                <form method="post" action="{{ route('admin.form-officers.remove', $assignment->officer_assignment_id) }}" data-admin-assignment-confirm data-admin-assignment-ajax data-admin-assignment-kind="officer" data-confirm-mode="remove" data-confirm-person="{{ $person['name'] ?? 'บุคลากร #'.$assignment->officer_pers_id }}" data-confirm-role="เจ้าหน้าที่ผู้รับผิดชอบ" data-confirm-form="{{ $formType->form_type_code }}">
                                    @csrf
                                    @method('delete')
                                    <x-portal-button type="submit" variant="danger-outline" aria-label="ยกเลิกสิทธิ์เจ้าหน้าที่ผู้รับผิดชอบของ {{ $person['name'] ?? 'บุคลากร #'.$assignment->officer_pers_id }}" title="ยกเลิกสิทธิ์"><x-portal-icon name="x" /></x-portal-button>
                                </form>
                            </div>
                            @empty
                            <p class="admin-assignment-list__empty">ยังไม่ได้กำหนดเจ้าหน้าที่ผู้รับผิดชอบ</p>
                            @endforelse
                        </div>
                        <form method="post" action="{{ route('admin.form-officers.add', $formType->form_type_code) }}" class="admin-inline-form" data-admin-assignment-confirm data-admin-assignment-ajax data-admin-assignment-kind="officer" data-confirm-mode="add" data-confirm-role="เจ้าหน้าที่ผู้รับผิดชอบ" data-confirm-form="{{ $formType->form_type_code }}">
                            @csrf
                            <x-admin-person-search name="officer_buasri_id" label="เจ้าหน้าที่ผู้รับผิดชอบ" :id="'officer-'.$formType->form_type_code" :hide-label="true" />
                            <x-portal-button type="submit">เพิ่มเจ้าหน้าที่</x-portal-button>
                        </form>
                    </div>
                    </div>
                </details>
                @endforeach
            </div>
        </section>
    </main>

    <x-portal-modal id="admin-assignment-confirm-dialog" title="ยืนยันการจัดการสิทธิ์">
        <p class="confirmation-message" data-admin-assignment-confirm-message></p>
        <div class="modal-actions">
            <x-portal-button type="button" variant="secondary" data-close-dialog>ย้อนกลับ</x-portal-button>
            <x-portal-button type="button" data-admin-assignment-confirm-action>ยืนยัน</x-portal-button>
        </div>
    </x-portal-modal>
    <x-portal-modal id="admin-assignment-result-dialog" title="ดำเนินการสำเร็จ" class="portal-modal--success">
        <div class="success-message" data-admin-assignment-result-content>
            <span class="success-icon" data-admin-assignment-result-icon><x-portal-icon name="check" /></span>
            <p data-admin-assignment-result-message></p>
        </div>
        <div class="modal-actions">
            <x-portal-button type="button" variant="secondary" data-close-dialog>เสร็จสิ้น</x-portal-button>
        </div>
    </x-portal-modal>
</x-portal-layout>
