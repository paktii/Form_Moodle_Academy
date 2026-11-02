@php
$isUser = $role === 'user';
$home = $isUser ? 'requests.index' : ($role === 'officer' ? 'officer.reviews' : 'approver.reviews');
$title = $isUser ? 'รายละเอียดเอกสาร' : ($role === 'officer' ? 'ตรวจสอบรายละเอียดคำร้องและเอกสาร' : 'ตรวจสอบรายละเอียดคำขออนุมัติสร้างรายวิชา');
$headerTitle = $role === 'officer' ? 'คำร้องแบบฟอร์มขอรับบริการ' : $title;
$headerContext = $role === 'officer' ? 'ตรวจสอบรายละเอียดคำร้องและเอกสาร' : null;
$tabTitle = $role === 'officer' ? 'Officer Review' : ($role === 'approver' ? 'Approval Review' : 'Request Details');
$canReview = $role === 'officer' && $record['status'] === 'UNDER_OFFICER_REVIEW';
$canEditResult = $role === 'officer' && $record['status'] === 'COMPLETED';
$canApprove = $role === 'approver' && $record['status'] === 'PENDING_APPROVAL';
$formTypeCode = $record['form_type_code'] ?? 'CC-ITED-06';
$isWebsiteRequest = $formTypeCode === 'CC-ITED-01';
$isWebsiteContactChangeRequest = $formTypeCode === 'CC-ITED-02';
$isWebsiteStorageRequest = $formTypeCode === 'CC-ITED-04';
$isEmeetingRequest = $formTypeCode === 'CC-ITED-05';
$documentDownloadRoute = match ($formTypeCode) {
    'CC-ITED-01' => 'website-requests.document.download',
    'CC-ITED-02' => 'website-contact-change-requests.document.download',
    'CC-ITED-04' => 'website-storage-requests.document.download',
    'CC-ITED-05' => 'emeeting-requests.document.download',
    default => 'requests.document.download',
};
@endphp
<x-portal-layout :title="$headerTitle" :header-context="$headerContext" :tab-title="$tabTitle" :role="$role" :show-header-logo="$role === 'officer'">
    <main class="portal-main detail-page detail-page--{{ $role }}">
        <div class="detail-actions requester-detail__navigation"><x-portal-button :href="route($home)" variant="secondary">ย้อนกลับ</x-portal-button>
            <span class="muted">เลขที่คำร้อง: {{ $record['number'] }}</span>
        </div>
        <div class="detail-heading">
            <div>
                <h2>@if($role === 'approver')ตรวจสอบรายละเอียด<br class="approver-title__mobile-break">คำขออนุมัติสร้างรายวิชา@else {{ $title }}@endif</h2>
            </div><x-status-badge :status="$record['status']" />
        </div>
        @if($record['reason'] ?? null)<section class="reason-banner reason-banner--message">
            <div>
                @if($role === 'officer')
                <h3 class="reason-banner__inline">เหตุผลที่ไม่อนุมัติ: {{ $record['reason'] }}</h3>
                @else
                <h3>เหตุผลที่ไม่อนุมัติ</h3>
                <p>{{ $record['reason'] }}</p>
                @endif
            </div>
        </section>@endif
        <section class="portal-card detail-card"><x-request-data :record="$record" :show-requester-unit="false" :split-target-unit="true" /></section>
        <x-portal-detail-card title="เอกสารแนบเพื่อตรวจสอบ">
            <div class="document-row"><x-portal-icon name="file-code" /><span class="document-row__name">{{ $record['document_name'] }}</span>
                @if($role === 'officer')
                <x-portal-button :href="isset($record['signed_path']) ? route('requests.attachment', [$record['id'], 'signed']) : route($documentDownloadRoute, $record['id'])" variant="secondary" class="document-row__download detail-card__action" :aria-label="'ดาวน์โหลดไฟล์ '.$record['document_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>
                @elseif($role === 'approver')<x-portal-button :href="isset($record['signed_path']) ? route('requests.attachment', [$record['id'], 'signed']) : route('requests.document.download', $record['id'])" variant="secondary" class="document-row__download detail-card__action" :aria-label="'ดาวน์โหลดไฟล์ '.$record['document_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>@elseif(isset($record['signed_path']))<a href="{{ route('requests.attachment', [$record['id'], 'signed']) }}">ดาวน์โหลด</a>@else <a href="{{ route('requests.document', $record['id']) }}" target="_blank">ดูเอกสาร</a>@endif
            </div>
            @foreach($record['additional_files'] ?? [] as $file)<div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $file['additional_name'] }}</span>@if($role === 'officer')<x-portal-button :href="route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์ '.$file['additional_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>@elseif($role === 'approver')<a class="approver-document-download" href="{{ route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']]) }}" aria-label="ดาวน์โหลดไฟล์ {{ $file['additional_name'] }}"><x-portal-icon name="download" /><span>ดาวน์โหลด</span></a>@else <a href="{{ route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']]) }}">ดาวน์โหลด</a>@endif</div>@endforeach
            @if(! in_array($role, ['officer', 'approver'], true) && $record['has_student_roster'])
            <div class="document-row roster-document-row">
                <x-portal-icon name="file-text" />
                <span class="document-row__name">{{ $record['student_roster_name'] }}</span>
                @if($record['student_roster_reopen_requested'])
                <span class="roster-status roster-status--pending">ขอแก้ไข</span>
                @elseif($record['student_roster_needs_officer_attention'])
                <span class="roster-status roster-status--pending">{{ $record['student_roster_is_old_file'] ? 'ไฟล์เก่า' : ($record['student_roster_is_update'] ? 'อัปเดตใหม่' : 'ไฟล์ใหม่') }}</span>
                @elseif($record['student_roster_acknowledged'])
                <span class="roster-status roster-status--complete">รับทราบแล้ว</span>
                @endif
                @if($role === 'officer')
                <x-portal-button :href="route('requests.attachment', [$record['id'], 'roster'])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์รายชื่อผู้เรียน '.$record['student_roster_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>
                @elseif($role === 'approver')
                <a class="approver-document-download" href="{{ route('requests.attachment', [$record['id'], 'roster']) }}" aria-label="ดาวน์โหลดไฟล์รายชื่อผู้เรียน {{ $record['student_roster_name'] }}"><x-portal-icon name="download" /><span>ดาวน์โหลด</span></a>
                @else
                <a href="{{ route('requests.attachment', [$record['id'], 'roster']) }}">ดาวน์โหลด</a>
                @endif
            </div>
            @if($role === 'officer')
            <div class="roster-review-actions">
                @if($record['student_roster_needs_officer_attention'])<x-portal-button data-open-dialog="acknowledge-roster-dialog">รับทราบรายชื่อแล้ว</x-portal-button>
                @elseif($record['student_roster_acknowledged'])<x-portal-button :variant="$record['student_roster_reopen_requested'] ? 'primary' : 'secondary'" data-open-dialog="reopen-roster-dialog">เปิดให้แก้ไขรายชื่อ</x-portal-button>@endif
            </div>
            @endif
            @if($record['student_roster_version_count'] > 1)
            <details class="roster-version-history">
                <summary>ประวัติรายชื่อผู้เรียน ({{ $record['student_roster_version_count'] }} เวอร์ชัน)</summary>
                @foreach($record['student_roster_versions'] as $version)
                <div class="roster-version-row"><span>เวอร์ชัน {{ $version['version'] }} — {{ $version['name'] }}<small>{{ $version['uploaded_at'] }}</small></span><a href="{{ route('requests.attachment', ['id' => $record['id'], 'kind' => 'roster', 'document' => $version['document_id']]) }}">ดาวน์โหลด</a></div>
                @endforeach
            </details>
            @endif
            @endif
            @if(! in_array($role, ['officer', 'approver'], true) && ! $record['has_student_roster'] && $record['requires_student_roster'])
            <p class="roster-empty">ยังไม่แนบรายชื่อผู้เรียน</p>
            @endif
        </x-portal-detail-card>
        @if(in_array($role, ['officer', 'approver'], true) && $record['requires_student_roster'])
        <section class="portal-card student-roster{{ $role === 'officer' && ! $record['has_student_roster'] ? ' student-roster--empty' : '' }}" id="student-roster">
            <div class="student-roster__heading">
                <div>
                    <h3>รายชื่อผู้เรียน</h3>
                    @if($role === 'approver' || $record['student_roster_reopen_requested'] || $record['student_roster_needs_officer_attention'] || $record['student_roster_acknowledged'])
                    <p>@if($role === 'approver'){{ $record['has_student_roster'] ? 'รายชื่อผู้เรียนที่แนบมากับคำร้อง' : 'ยังไม่แนบรายชื่อผู้เรียน' }}@elseif($record['student_roster_reopen_requested'])ผู้ยื่นคำร้องขอแก้ไขรายชื่อ กรุณาตรวจสอบคำขอ@elseif($record['student_roster_needs_officer_attention'])แนบรายชื่อแล้ว กรุณาตรวจสอบและรับทราบ@elseif($record['student_roster_acknowledged'])รับทราบรายชื่อแล้ว สามารถเปิดให้ผู้ยื่นแก้ไขได้เมื่อจำเป็น@endif</p>
                    @endif
                </div>
                <span class="roster-status {{ $record['student_roster_reopen_requested'] || $record['student_roster_needs_officer_attention'] ? 'roster-status--pending' : ($record['student_roster_acknowledged'] ? 'roster-status--complete' : 'roster-status--missing') }}">@if($record['student_roster_reopen_requested'])ขอแก้ไข@elseif($record['student_roster_needs_officer_attention']){{ $record['student_roster_is_old_file'] ? 'ไฟล์เก่า' : ($record['student_roster_is_update'] ? 'อัปเดตใหม่' : 'ไฟล์ใหม่') }}@elseif($record['student_roster_acknowledged'])รับทราบแล้ว@else ยังไม่แนบ@endif</span>
            </div>
            @if($record['has_student_roster'])
            <div class="document-row">
                <x-portal-icon name="file-text" />
                <span class="document-row__name">{{ $record['student_roster_name'] }}<small class="roster-file-meta">เวอร์ชัน {{ $record['student_roster_version_count'] }} • อัปโหลด {{ $record['student_roster_uploaded_at'] }}</small></span>
                <x-portal-button :href="route('requests.attachment', [$record['id'], 'roster'])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์รายชื่อผู้เรียน '.$record['student_roster_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>
            </div>
            @if($role === 'officer')
            <div class="roster-review-actions">
                @if($record['student_roster_needs_officer_attention'])<x-portal-button data-open-dialog="acknowledge-roster-dialog">รับทราบรายชื่อแล้ว</x-portal-button>
                @elseif($record['student_roster_acknowledged'])<x-portal-button :variant="$record['student_roster_reopen_requested'] ? 'primary' : 'secondary'" data-open-dialog="reopen-roster-dialog">เปิดให้แก้ไขรายชื่อ</x-portal-button>@endif
            </div>
            @endif
            @if($record['student_roster_version_count'] > 1)
            <details class="roster-version-history">
                <summary>ประวัติรายชื่อผู้เรียน ({{ $record['student_roster_version_count'] }} เวอร์ชัน)</summary>
                @foreach($record['student_roster_versions'] as $version)
                <div class="roster-version-row"><span>เวอร์ชัน {{ $version['version'] }} — {{ $version['name'] }}<small>{{ $version['uploaded_at'] }}</small></span><a href="{{ route('requests.attachment', ['id' => $record['id'], 'kind' => 'roster', 'document' => $version['document_id']]) }}">ดาวน์โหลด</a></div>
                @endforeach
            </details>
            @endif
            @endif
        </section>
        @endif
        @if($record['course_id'] ?? null)<x-portal-detail-card title="ผลการสร้างรายวิชา" class="course-result">
            <div class="document-row"><x-portal-icon name="globe-code" /><span class="document-row__name">Course ID: <a class="course-link" href="{{ rtrim(config('course-workflow.moodle_url'), '/').'/course/view.php?id='.rawurlencode($record['course_id']) }}" target="_blank" rel="noopener noreferrer" aria-label="เปิด Course ID {{ $record['course_id'] }} ใน SWU Moodle Academy (แท็บใหม่)">{{ $record['course_id'] }}</a></span>@if($canEditResult)<x-portal-button variant="secondary" class="detail-card__action course-result__edit" data-open-dialog="edit-result-dialog"><x-portal-icon name="pen-line" /><span class="course-result__edit-label">แก้ไขผล</span></x-portal-button>@endif</div>
        </x-portal-detail-card>@endif
        @if($isWebsiteRequest && ($record['allocated_domain'] ?? null))<x-portal-detail-card title="ผลการดำเนินการ" class="course-result">
            <div class="document-row"><x-portal-icon name="globe-code" /><span class="document-row__name">ข้อมูลการจัดสรรพื้นที่เว็บไซต์: <span class="allocated-domain-text">{{ $record['allocated_domain'] }}</span></span>@if($canEditResult)<x-portal-button variant="secondary" class="detail-card__action course-result__edit" data-open-dialog="edit-result-dialog"><x-portal-icon name="pen-line" /><span class="course-result__edit-label">แก้ไขผล</span></x-portal-button>@endif</div>
        </x-portal-detail-card>@endif
        @if($isWebsiteContactChangeRequest && ($record['execution_detail'] ?? null))<x-portal-detail-card title="ผลการดำเนินการ" class="course-result">
            <div class="document-row execution-detail-row"><x-portal-icon name="globe-code" /><span class="document-row__name">ข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์: <span class="execution-detail-text">{{ $record['execution_detail'] }}</span></span>@if($canEditResult)<x-portal-button variant="secondary" class="detail-card__action course-result__edit" data-open-dialog="edit-result-dialog"><x-portal-icon name="pen-line" /><span class="course-result__edit-label">แก้ไขผล</span></x-portal-button>@endif</div>
        </x-portal-detail-card>@endif
        @if($isWebsiteStorageRequest && (($record['execution_detail'] ?? null) || ($record['granted_space_gb'] ?? null)))<x-portal-detail-card title="ผลการดำเนินการ" class="course-result">
            <div class="document-row execution-detail-row"><x-portal-icon name="globe-code" /><span class="document-row__name">พื้นที่เว็บไซต์ที่เพิ่มให้: 1 GB<br>ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์: <span class="execution-detail-text">{{ $record['execution_detail'] ?? '—' }}</span></span>@if($canEditResult)<x-portal-button variant="secondary" class="detail-card__action course-result__edit" data-open-dialog="edit-result-dialog"><x-portal-icon name="pen-line" /><span class="course-result__edit-label">แก้ไขผล</span></x-portal-button>@endif</div>
        </x-portal-detail-card>@endif
        @if($isEmeetingRequest && ($record['execution_detail'] ?? null))<x-portal-detail-card title="ผลการดำเนินการ" class="course-result">
            <div class="document-row execution-detail-row"><x-portal-icon name="globe-code" /><span class="document-row__name">ข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม: <span class="execution-detail-text">{{ $record['execution_detail'] }}</span></span>@if($canEditResult)<x-portal-button variant="secondary" class="detail-card__action course-result__edit" data-open-dialog="edit-result-dialog"><x-portal-icon name="pen-line" /><span class="course-result__edit-label">แก้ไขผล</span></x-portal-button>@endif</div>
        </x-portal-detail-card>@endif
        @if($canReview || $canApprove)
        <section class="portal-card review-card">
            <h3>{{ $canReview ? 'ผลการตรวจสอบ' : 'ผลการพิจารณา' }}</h3>
            @if($canReview)
            <fieldset class="radio-fieldset">
                <legend>สถานะผลการตรวจสอบ</legend>
                <div class="radio-options"><label><input type="radio" name="review_choice" value="pass">อนุมัติ</label><label><input type="radio" name="review_choice" value="reject">ไม่อนุมัติ</label></div>
            </fieldset>
            <x-portal-button data-review-submit disabled>กรุณาเลือกผลการตรวจสอบ</x-portal-button>
            @else
            <p class="muted">สถานะผลการตรวจสอบ</p>
            <p class="review-passed"><x-portal-icon name="clipboard-check" />ข้อมูลถูกต้องครบถ้วน โดย: {{ $record['officer_name'] ?? '—' }}</p>
            <x-portal-button data-open-dialog="approve-dialog">อนุมัติการสร้างรายวิชา</x-portal-button>
            <x-portal-button variant="danger-outline" data-open-dialog="reject-dialog">ไม่อนุมัติการสร้างรายวิชา</x-portal-button>
            @endif
        </section>
        @endif

    </main>
    @if($canEditResult)
    <x-portal-modal id="edit-result-dialog" title="แก้ไขผลการดำเนินการ" :auto-open="$errors->hasAny(['course_id', 'allocated_domain', 'execution_detail', 'granted_space_gb', 'notify_requester'])">
        <form action="{{ route('officer.result.update', $record['id']) }}" method="post" data-edit-result-form>@csrf
            <div data-edit-result-entry>
            @if($isWebsiteRequest)
            <label class="form-label" for="edit-result-allocated-domain">ข้อมูลการจัดสรรพื้นที่เว็บไซต์<span class="required-indicator" aria-hidden="true">*</span>
                <textarea id="edit-result-allocated-domain" name="allocated_domain" class="form-field form-field--textarea @error('allocated_domain') form-field--error @enderror" rows="4" maxlength="5000" required>{{ old('allocated_domain', $record['allocated_domain'] ?? '') }}</textarea>
                @error('allocated_domain')<span class="field-error" role="alert">{{ $message }}</span>@enderror
            </label>
            @elseif($isWebsiteContactChangeRequest)
            <label class="form-label" for="edit-result-execution-detail">ข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์<span class="required-indicator" aria-hidden="true">*</span>
                <textarea id="edit-result-execution-detail" name="execution_detail" class="form-field form-field--textarea @error('execution_detail') form-field--error @enderror" rows="4" maxlength="5000" required>{{ old('execution_detail', $record['execution_detail'] ?? '') }}</textarea>
                @error('execution_detail')<span class="field-error" role="alert">{{ $message }}</span>@enderror
            </label>
            @elseif($isWebsiteStorageRequest)
            <label class="form-label" for="edit-result-execution-detail">ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์<span class="required-indicator" aria-hidden="true">*</span>
                <textarea id="edit-result-execution-detail" name="execution_detail" class="form-field form-field--textarea @error('execution_detail') form-field--error @enderror" rows="4" maxlength="5000" required>{{ old('execution_detail', $record['execution_detail'] ?? '') }}</textarea>
                @error('execution_detail')<span class="field-error" role="alert">{{ $message }}</span>@enderror
            </label>
            <x-portal-field name="granted_space_gb" id="edit-result-granted-space" label="พื้นที่เว็บไซต์ที่เพิ่มให้ (GB)" type="number" value="1" class="form-field--display-only" disabled title="พื้นที่ถูกกำหนดไว้ที่ 1 GB ไม่สามารถแก้ไขได้" />
            @elseif($isEmeetingRequest)
            <label class="form-label" for="edit-result-execution-detail">ข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม<span class="required-indicator" aria-hidden="true">*</span>
                <textarea id="edit-result-execution-detail" name="execution_detail" class="form-field form-field--textarea @error('execution_detail') form-field--error @enderror" rows="4" maxlength="5000" required>{{ old('execution_detail', $record['execution_detail'] ?? '') }}</textarea>
                @error('execution_detail')<span class="field-error" role="alert">{{ $message }}</span>@enderror
            </label>
            @else
            <x-portal-field name="course_id" id="edit-result-course-id" label="Course ID" type="number" :value="old('course_id', $record['course_id'] ?? '')" :required="true" min="1" step="1" inputmode="numeric" />
            @endif
            <label class="result-notification-option">
                <input type="checkbox" name="notify_requester" value="1" @checked(old('notify_requester'))>
                <span><strong>ส่งอีเมลแจ้งเตือนผู้ยื่นคำร้อง</strong><small>แจ้งว่าผลการดำเนินการถูกแก้ไขและให้กลับมาตรวจสอบรายละเอียดอีกครั้ง</small></span>
            </label>
            <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-edit-result-cancel>ยกเลิก</x-portal-button><x-portal-button type="button" data-edit-result-save>บันทึกการแก้ไข</x-portal-button></div>
            </div>
            <div data-edit-result-confirm hidden>
                <p class="confirmation-message" data-edit-result-confirm-message></p>
                <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-edit-result-confirm-back>กลับไปแก้ไข</x-portal-button><x-portal-button type="button" data-edit-result-confirm-action></x-portal-button></div>
            </div>
        </form>
    </x-portal-modal>
    @endif
    @if($role === 'officer' && $record['has_student_roster'] && $record['student_roster_needs_officer_attention'])
    <x-portal-modal id="acknowledge-roster-dialog" title="ยืนยันการรับทราบรายชื่อ">
        <p class="confirmation-message">คุณตรวจสอบรายชื่อผู้เรียน<br class="roster-confirmation__mobile-break">เวอร์ชันล่าสุดแล้วใช่หรือไม่?<br>เมื่อยืนยัน ระบบจะล็อกไม่ให้ผู้ยื่นคำร้องแก้ไขไฟล์</p>
        <form action="{{ route('officer.student-roster.acknowledge', $record['id']) }}" method="post">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">ยืนยันรับทราบ</x-portal-button></div></form>
    </x-portal-modal>
    @elseif($role === 'officer' && $record['has_student_roster'] && $record['student_roster_acknowledged'])
    <x-portal-modal id="reopen-roster-dialog" title="ตรวจสอบคำขอแก้ไขรายชื่อ">
        <p class="confirmation-message">ต้องการอนุญาตให้ผู้ยื่นคำร้องอัปโหลดรายชื่อเวอร์ชันใหม่ใช่หรือไม่?<br>ระบบจะแจ้งผู้ยื่นคำร้องให้ทราบ</p>
        <form action="{{ route('officer.student-roster.reopen', $record['id']) }}" method="post">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">อนุญาตให้อัปโหลดใหม่</x-portal-button></div></form>
    </x-portal-modal>
    @endif
    @if($canReview)
    <x-portal-modal id="review-dialog" title="ยืนยันอนุมัติคำร้อง">
        <form action="{{ route('officer.review', $record['id']) }}" method="post" data-officer-approval-form>@csrf<input type="hidden" name="decision" value="pass">
            <div data-officer-approval-entry>
                @if($isWebsiteRequest)
                <label class="form-label" for="officer-allocated-domain">ข้อมูลการจัดสรรพื้นที่เว็บไซต์<span class="required-indicator" aria-hidden="true">*</span>
                    <textarea id="officer-allocated-domain" name="allocated_domain" class="form-field form-field--textarea @error('allocated_domain') form-field--error @enderror" rows="4" placeholder="ระบุโดเมนและข้อมูลอื่น ๆ ที่เกี่ยวข้อง" required>{{ old('allocated_domain') }}</textarea>
                    @error('allocated_domain')<span class="field-error" role="alert" style="position: absolute; bottom: 0; left: 0; color: var(--portal-red); font-size: 13px; line-height: 1;">{{ $message }}</span>@enderror
                </label>
                @elseif($isWebsiteContactChangeRequest)
                <label class="form-label" for="officer-execution-detail">ข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์<span class="required-indicator" aria-hidden="true">*</span>
                    <textarea id="officer-execution-detail" name="execution_detail" class="form-field form-field--textarea @error('execution_detail') form-field--error @enderror" rows="4" maxlength="5000" placeholder="ระบุรายละเอียดการดำเนินการที่เสร็จสิ้นแล้ว" required data-officer-execution-detail>{{ old('execution_detail') }}</textarea>
                    @error('execution_detail')<span class="field-error" role="alert" style="position: absolute; bottom: 0; left: 0; color: var(--portal-red); font-size: 13px; line-height: 1;">{{ $message }}</span>@enderror
                </label>
                @elseif($isWebsiteStorageRequest)
                <label class="form-label" for="officer-execution-detail">ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์<span class="required-indicator" aria-hidden="true">*</span>
                    <textarea id="officer-execution-detail" name="execution_detail" class="form-field form-field--textarea @error('execution_detail') form-field--error @enderror" rows="4" maxlength="5000" placeholder="ระบุรายละเอียดการดำเนินการที่เสร็จสิ้นแล้ว" required data-officer-execution-detail>{{ old('execution_detail') }}</textarea>
                    @error('execution_detail')<span class="field-error" role="alert" style="position: absolute; bottom: 0; left: 0; color: var(--portal-red); font-size: 13px; line-height: 1;">{{ $message }}</span>@enderror
                </label>
                <x-portal-field name="granted_space_gb" id="officer-granted-space" label="พื้นที่เว็บไซต์ที่เพิ่มให้ (GB)" type="number" value="1" class="form-field--display-only" disabled title="พื้นที่ถูกกำหนดไว้ที่ 1 GB ไม่สามารถแก้ไขได้" />
                @elseif($isEmeetingRequest)
                <label class="form-label" for="officer-execution-detail">ข้อมูลการดำเนินการกำหนดหรือเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม<span class="required-indicator" aria-hidden="true">*</span>
                    <textarea id="officer-execution-detail" name="execution_detail" class="form-field form-field--textarea @error('execution_detail') form-field--error @enderror" rows="4" maxlength="5000" placeholder="ระบุรายละเอียดการดำเนินการที่เสร็จสิ้นแล้ว" required data-officer-execution-detail>{{ old('execution_detail') }}</textarea>
                    @error('execution_detail')<span class="field-error" role="alert" style="position: absolute; bottom: 0; left: 0; color: var(--portal-red); font-size: 13px; line-height: 1;">{{ $message }}</span>@enderror
                </label>
                @else
                <x-portal-field name="course_id" id="officer-course-id" label="Course ID" type="number" :value="old('course_id')" placeholder="เช่น 6674" :required="true" min="1" step="1" inputmode="numeric" />
                @endif
                <div class="modal-actions"><x-portal-button variant="secondary" data-officer-approval-cancel>ยกเลิก</x-portal-button><x-portal-button data-officer-approval-send>{{ $isWebsiteRequest ? 'อนุมัติและบันทึกโดเมน' : (($isWebsiteContactChangeRequest || $isWebsiteStorageRequest || $isEmeetingRequest) ? 'อนุมัติและบันทึกข้อมูลการดำเนินการ' : 'อนุมัติและบันทึก Course ID') }}</x-portal-button></div>
            </div>
            <div data-officer-approval-confirm hidden>
                <p class="confirmation-message" data-officer-approval-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button variant="plain" data-officer-approval-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button data-officer-approval-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    <x-portal-modal id="officer-reject-dialog" title="ระบุเหตุผลที่ไม่อนุมัติ">
        <form action="{{ route('officer.review', $record['id']) }}" method="post" id="officer-reject-form">@csrf<input type="hidden" name="decision" value="reject">
            <div data-officer-reject-entry>
                <label class="form-label" for="officer-reject-reason">เหตุผลที่ไม่อนุมัติ (ส่งให้ผู้ยื่นคำร้อง)<textarea id="officer-reject-reason" name="reason" class="form-field form-field--textarea" required maxlength="3000">{{ old('reason') }}</textarea></label>
                <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-officer-reject-cancel>ยกเลิก</x-portal-button><x-portal-button type="button" variant="danger" data-officer-reject-send>ไม่อนุมัติ</x-portal-button></div>
            </div>
            <div data-officer-reject-confirm hidden>
                <p class="confirmation-message" data-officer-reject-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button type="button" variant="plain" data-officer-reject-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button type="button" data-officer-reject-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    @endif
    @if($canApprove)
    <x-portal-modal id="approve-dialog" title="ยืนยันการอนุมัติ">
        <form action="{{ route('approver.approve', $record['id']) }}" method="post" enctype="multipart/form-data" data-signature-context="approve">@csrf<input type="hidden" name="decision" value="approve">
            <div data-signature-entry>
                <p class="confirmation-message confirmation-message--left">วาดลายเซ็นด้านล่าง หรืออัปโหลดลายเซ็น <span class="signature-file-hint__mobile-line">(ไฟล์ภาพพื้นหลังใส .png)</span></p>
                <div class="signature-pad" data-signature-pad>
                    <div class="signature-pad__heading">
                        <span>วาดลายเซ็น</span>
                        <button type="button" class="signature-pad__clear" data-signature-clear disabled>ล้างลายเซ็น</button>
                    </div>
                    <canvas class="signature-pad__canvas" width="1200" height="300" data-signature-canvas aria-label="พื้นที่วาดลายเซ็น ใช้เมาส์หรือปลายนิ้วในการวาด"></canvas>
                    <p class="signature-pad__hint" data-signature-status aria-live="polite">ใช้เมาส์หรือปลายนิ้ววาดภายในกรอบ</p>
                </div>
                <div class="signature-divider"><span>หรืออัปโหลดไฟล์</span></div>
                <x-portal-upload name="signature_file" id="approver-signature" accept=".png,.jpeg,.jpg" :compact="true" compact-label="อัปโหลดลายเซ็น" :required="true" />
                <div class="modal-actions"><x-portal-button variant="secondary" data-signature-cancel>ยกเลิก</x-portal-button><x-portal-button data-signature-send>ยืนยันอนุมัติ</x-portal-button></div>
            </div>
            <div data-signature-confirm hidden>
                <p class="confirmation-message" data-signature-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button variant="plain" data-signature-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button data-signature-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    <x-portal-modal id="reject-dialog" title="ยืนยันไม่อนุมัติการสร้างรายวิชา">
        <form action="{{ route('approver.approve', $record['id']) }}" method="post" enctype="multipart/form-data" data-signature-context="reject">@csrf<input type="hidden" name="decision" value="reject">
            <div data-signature-entry>
                <label class="form-label" for="reject-reason">เหตุผลที่ไม่อนุมัติ (ส่งให้ผู้ยื่นคำร้อง)<textarea id="reject-reason" name="reason" class="form-field form-field--textarea" required maxlength="3000">{{ old('reason') }}</textarea></label>
                <p class="confirmation-message confirmation-message--left">วาดลายเซ็นด้านล่าง หรืออัปโหลดลายเซ็น <span class="signature-file-hint__mobile-line">(ไฟล์ภาพพื้นหลังใส .png)</span></p>
                <div class="signature-pad" data-signature-pad>
                    <div class="signature-pad__heading">
                        <span>วาดลายเซ็น</span>
                        <button type="button" class="signature-pad__clear" data-signature-clear disabled>ล้างลายเซ็น</button>
                    </div>
                    <canvas class="signature-pad__canvas" width="1200" height="300" data-signature-canvas aria-label="พื้นที่วาดลายเซ็น ใช้เมาส์หรือปลายนิ้วในการวาด"></canvas>
                    <p class="signature-pad__hint" data-signature-status aria-live="polite">ใช้เมาส์หรือปลายนิ้ววาดภายในกรอบ</p>
                </div>
                <div class="signature-divider"><span>หรืออัปโหลดไฟล์</span></div>
                <x-portal-upload name="signature_file" id="approver-reject-signature" accept=".png,.jpeg,.jpg" :compact="true" compact-label="อัปโหลดลายเซ็น" :required="true" />
                <div class="modal-actions"><x-portal-button variant="secondary" data-signature-cancel>ยกเลิก</x-portal-button><x-portal-button variant="danger" data-signature-send>ยืนยันไม่อนุมัติ</x-portal-button></div>
            </div>
            <div data-signature-confirm hidden>
                <p class="confirmation-message" data-signature-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button variant="plain" data-signature-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button variant="danger" data-signature-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    @endif
</x-portal-layout>
