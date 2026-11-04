@php
$isUser = $role === 'user';
$title = $isUser ? 'สถานะเอกสาร' : ($role === 'officer' ? 'คำร้องแบบฟอร์มขอรับบริการ' : 'ขั้นตอนการลงนามอนุมัติ');
$tabTitle = $isUser ? 'My Requests' : ($role === 'officer' ? 'Officer Queue' : 'Approval Queue');
$description = match ($role) {
    'officer' => null,
    'approver' => 'ผู้บริหารพิจารณาอนุมัติสร้างรายวิชาในระบบ SWU Moodle Academy',
    default => 'ติดตามขั้นตอนและประวัติการส่งเอกสารของคำร้องขอรับบริการทั้งหมดของคุณ',
};
$detailRoute = $isUser ? 'requests.show' : ($role === 'officer' ? 'officer.show' : 'approver.show');
$completedRequests = $isUser ? $records->filter(fn ($record) => $record['status'] === 'COMPLETED' && filled($record['completion_notification_id'] ?? null)) : collect();
$pendingOfficerReviews = $role === 'officer' ? $records->where('status', 'UNDER_OFFICER_REVIEW')->count() : 0;
$pendingApprovals = $role === 'approver' ? $records->where('status', 'PENDING_APPROVAL')->count() : 0;
$missingRosters = $records->filter(fn ($record) => ($record['requires_student_roster'] ?? false) && ! ($record['has_student_roster'] ?? false))->count();
$rosterUpdates = $records->filter(fn ($record) => $record['student_roster_needs_officer_attention'] ?? false)->count();
$officerNotificationMessages = collect([
    $pendingOfficerReviews ? "{$pendingOfficerReviews} คำร้องสถานะรออนุมัติ" : null,
    $rosterUpdates ? "{$rosterUpdates} คำร้องที่มีรายชื่อผู้เรียนใหม่หรือมีการอัปเดต" : null,
])->filter()->implode(' และ ');
$filterStatuses = match ($role) {
'officer' => [
'UNDER_OFFICER_REVIEW' => 'รออนุมัติ',
'COURSE_ID_RECORDED' => 'เสร็จสมบูรณ์',
'COMPLETED' => 'เสร็จสมบูรณ์',
'REJECTED' => 'ไม่อนุมัติ',
],
'approver' => [
'PENDING_APPROVAL' => 'รออนุมัติ',
'REJECTED' => 'ไม่อนุมัติ',
'COURSE_ID_RECORDED' => 'เสร็จสมบูรณ์',
],
default => $statuses,
};
@endphp
<x-portal-layout :title="$title" :tab-title="$tabTitle" :role="$role" :description="$description" :show-header-logo="$role === 'officer'">
    <main @class(['portal-main', 'list-page', 'list-page--user' => $isUser, 'list-page--officer' => $role === 'officer']) data-request-list>
        @if($isUser && ($missingRosters || $completedRequests->isNotEmpty()))
        <div class="notification-banner" aria-label="รายการแจ้งเตือน" @if(!$missingRosters) hidden data-completed-only-banner @endif>
            <span>แจ้งเตือน: @if($missingRosters) {{ $missingRosters }} คำร้องที่ยังไม่แนบรายชื่อผู้เรียน@endif @if($completedRequests->isNotEmpty())<span hidden data-completed-banner-message>@if($missingRosters) และ @endifมี <strong data-completed-banner-count></strong> คำร้องเสร็จสมบูรณ์แล้ว</span>@endif</span>
            <div class="notification-banner__actions">
                @if($missingRosters)<x-portal-button variant="secondary" data-filter-roster>แนบรายชื่อ</x-portal-button>@endif
                @if($completedRequests->isNotEmpty())<x-portal-button variant="secondary" data-filter-newly-completed hidden>ดูคำร้อง</x-portal-button>@endif
            </div>
        </div>
        @endif
        @if($role === 'approver' && $pendingApprovals)
        <div class="notification-banner" aria-label="รายการแจ้งเตือน">
            <span>แจ้งเตือน: คุณมี {{ $pendingApprovals }} คำร้องสถานะรออนุมัติ</span>
            <div class="notification-banner__actions">
                <x-portal-button variant="secondary" data-filter-approver-pending>พิจารณาคำร้อง</x-portal-button>
            </div>
        </div>
        @endif
        @if($role === 'officer')
        <div class="page-heading">
            <div>
                <h2 class="page-heading__title">เอกสารรอการตรวจสอบและลงนาม</h2>
                <p class="page-heading__description">ตรวจสอบความถูกต้องของเอกสารและคำร้อง</p>
            </div>
        </div>
        @endif
        @if($role === 'officer' && ($pendingOfficerReviews || $rosterUpdates))
        <div class="notification-banner" aria-label="รายการแจ้งเตือน">
            <span>แจ้งเตือน: คุณมี {{ $officerNotificationMessages }} กรุณาตรวจสอบและดำเนินการ</span>
            <div class="notification-banner__actions notification-banner__actions--officer">
                @if($pendingOfficerReviews)<x-portal-button variant="secondary" data-filter-officer-pending>ตรวจสอบคำร้อง</x-portal-button>@endif
                @if($rosterUpdates)<x-portal-button variant="secondary" data-filter-roster-updates>ตรวจสอบรายชื่อ</x-portal-button>@endif
            </div>
        </div>
        @endif
        <section class="filter-card" aria-label="ค้นหาและกรองคำร้อง">
            <label class="search-control"><x-portal-icon name="search" /><input type="search" data-search placeholder="ค้นหาเลขที่คำร้อง, ชื่อโครงการ หรือ ส่วนงาน" aria-label="ค้นหาคำร้อง"></label>
            <div class="type-filter-menu" data-type-filter-menu>
                <select class="filter-control type-filter-menu__native" data-type-filter aria-label="กรองประเภทคำร้อง">
                    <option value="">ประเภทคำร้องทั้งหมด</option>
                    @foreach(['CC-ITED-01', 'CC-ITED-02', 'CC-ITED-04', 'CC-ITED-05', 'CC-ITED-06'] as $formTypeCode)
                    <option value="{{ $formTypeCode }}">{{ $formTypeCode }}</option>
                    @endforeach
                </select>
                <button type="button" class="filter-control type-filter-menu__trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="request-type-filter-options" data-type-filter-trigger>
                    <span data-type-filter-label>ประเภทคำร้องทั้งหมด</span>
                </button>
                <div id="request-type-filter-options" class="type-filter-menu__options" role="listbox" hidden data-type-filter-options>
                    <button type="button" role="option" aria-selected="true" data-type-filter-option data-value="">ประเภทคำร้องทั้งหมด</button>
                    @foreach(['CC-ITED-01', 'CC-ITED-02', 'CC-ITED-04', 'CC-ITED-05', 'CC-ITED-06'] as $formTypeCode)
                    <button type="button" role="option" aria-selected="false" data-type-filter-option data-value="{{ $formTypeCode }}">{{ $formTypeCode }}</button>
                    @endforeach
                </div>
            </div>
            <div class="status-filter-menu" data-status-filter-menu>
                <select class="filter-control status-filter-menu__native" data-status-filter aria-label="กรองสถานะ">
                    @if($role === 'officer')
                        <option value="UNDER_OFFICER_REVIEW" data-default-queue-status selected>งานที่ต้องดำเนินการ</option>
                    @elseif($role === 'approver')
                        <option value="PENDING_APPROVAL" data-default-queue-status selected>งานที่ต้องดำเนินการ</option>
                    @endif
                    <option value="">สถานะทั้งหมด</option>@foreach(array_unique($filterStatuses) as $label)<option value="{{ implode(',', array_keys($filterStatuses, $label)) }}">{{ $label }}</option>@endforeach
                </select>
                <button type="button" class="filter-control status-filter-menu__trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="request-status-filter-options" data-status-filter-trigger>
                    <span data-status-filter-label>{{ $isUser ? 'สถานะทั้งหมด' : 'งานที่ต้องดำเนินการ' }}</span>
                </button>
                <div id="request-status-filter-options" class="status-filter-menu__options" role="listbox" hidden data-status-filter-options>
                    @if($role === 'officer')
                    <button type="button" role="option" aria-selected="true" data-status-filter-option data-value="UNDER_OFFICER_REVIEW">งานที่ต้องดำเนินการ</button>
                    @elseif($role === 'approver')
                    <button type="button" role="option" aria-selected="true" data-status-filter-option data-value="PENDING_APPROVAL">งานที่ต้องดำเนินการ</button>
                    @endif
                    <button type="button" role="option" aria-selected="{{ $isUser ? 'true' : 'false' }}" data-status-filter-option data-value="">สถานะทั้งหมด</button>
                    @foreach(array_unique($filterStatuses) as $label)<button type="button" role="option" aria-selected="false" data-status-filter-option data-value="{{ implode(',', array_keys($filterStatuses, $label)) }}">{{ $label }}</button>@endforeach
                </div>
            </div>
            <div class="date-filter-menu" data-date-filter-menu>
                <select class="filter-control date-filter-menu__native" data-date-filter aria-label="กรองวันที่ยื่น">
                    <option value="" @selected(! $isUser)>วันที่ยื่น: ทั้งหมด</option>
                    <option value="month" @selected($isUser)>เดือนนี้</option>
                    <option value="year">ปีนี้</option>
                </select>
                <button type="button" class="filter-control date-filter-menu__trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="request-date-filter-options" data-date-filter-trigger>
                    <span data-date-filter-label>{{ $isUser ? 'เดือนนี้' : 'วันที่ยื่น: ทั้งหมด' }}</span>
                </button>
                <div id="request-date-filter-options" class="date-filter-menu__options" role="listbox" hidden data-date-filter-options>
                    <button type="button" role="option" aria-selected="{{ $isUser ? 'false' : 'true' }}" data-date-filter-option data-value="">วันที่ยื่น: ทั้งหมด</button>
                    <button type="button" role="option" aria-selected="{{ $isUser ? 'true' : 'false' }}" data-date-filter-option data-value="month">เดือนนี้</button>
                    <button type="button" role="option" aria-selected="false" data-date-filter-option data-value="year">ปีนี้</button>
                </div>
            </div>
            <button type="button" class="clear-filter-btn" data-clear-filter aria-label="ล้างตัวกรอง">
                <x-portal-icon name="filter-x" />
            </button>
        </section>
        <div class="table-container" tabindex="0" role="region" aria-label="ตารางคำร้อง">
            <table @class(['request-table', 'request-table--officer' => $role === 'officer'])>
                <thead>
                    <tr>@if($isUser)<th>วันที่ส่งคำร้อง</th>
                        <th>ชื่อโครงการ</th>
                        <th>ส่วนงาน/คณะ/สำนักเป้าหมาย</th>
                        <th>สถานะล่าสุด</th>
                        @else<th>เลขที่เอกสาร</th>
                        @if($role === 'officer')<th class="request-form-type">ประเภทฟอร์ม</th>@endif
                        <th>ชื่อโครงการ / รายวิชา</th>
                        <th>ผู้เสนอขอ</th>
                        <th>ส่วนงานเป้าหมาย</th>
                        <th>วันที่ยื่น</th>
                        <th>สถานะ</th>@endif<th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                    @php($targetUnit = collect([$record['unit'] ?? null, $record['subunit'] ?? null])->filter(fn ($value) => filled($value))->implode(', '))
                    @php($hasCourseIdAction = $role === 'officer' && $record['status'] === 'COURSE_ID_RECORDED')
                    @php($usesFullWidthMobileAction = in_array($role, ['officer', 'approver'], true))
                    <tr data-request-row data-status="{{ $record['status'] }}" data-form-type="{{ $record['form_type_code'] ?? 'CC-ITED-06' }}" data-date="{{ $record['submitted_at'] }}" data-completion-notification="{{ $isUser && $record['status'] === 'COMPLETED' ? ($record['completion_notification_id'] ?? '') : '' }}" data-missing-roster="{{ ($record['requires_student_roster'] ?? false) && ! ($record['has_student_roster'] ?? false) ? 'true' : 'false' }}" data-roster-update="{{ ($record['student_roster_needs_officer_attention'] ?? false) ? 'true' : 'false' }}" data-search-content="{{ $record['number'] }} {{ $record['project_name'] }} {{ $record['form_type_code'] ?? '' }} {{ $targetUnit }} {{ $record['proposer'] ?? '' }}">
                        @if($isUser)
                        <td class="date-cell request-card__date">{{ \Carbon\Carbon::parse($record['submitted_at'])->locale('th')->translatedFormat('d M').' '.(\Carbon\Carbon::parse($record['submitted_at'])->year + 543) }}</td>
                        <td class="course-cell request-card__title"><span title="{{ $record['project_name'] }} ({{ $record['form_type_code'] ?? 'CC-ITED-06' }})">{{ $record['project_name'] }} ({{ $record['form_type_code'] ?? 'CC-ITED-06' }})</span><small>เลขที่คำร้อง: {{ $record['number'] }}</small></td>
                        <td class="request-card__unit"><span class="request-card__unit-text" title="{{ $targetUnit }}">{{ $targetUnit }}</span></td>
                        <td class="request-card__status"><x-status-badge :status="$record['status']" /></td>
                        @else
                        <td class="request-table__number request-card__number">{{ $record['number'] }}</td>
                        @if($role === 'officer')<td class="request-form-type request-card__form-type">{{ $record['form_type_code'] ?? 'CC-ITED-06' }}</td>@endif
                        <td class="course-cell request-card__title"><span title="{{ $record['project_name'] }}">@if($role === 'officer' && ($record['form_type_code'] ?? '') === 'CC-ITED-05' && str_contains($record['project_name'], 'SWU e-Meeting')){{ trim(\Illuminate\Support\Str::beforeLast($record['project_name'], 'SWU e-Meeting')) }}<br>SWU e-Meeting @else{{ $record['project_name'] }}@endif</span>@if($role === 'officer')<small class="request-card__mobile-form-type">{{ $record['form_type_code'] ?? 'CC-ITED-06' }}</small>@endif</td>
                        <td @class([
                            'requester-cell',
                            'request-card__requester',
                            'request-card__requester--paired' => $usesFullWidthMobileAction,
                        ])>{{ $record['proposer'] }}</td>
                        <td class="request-card__unit"><span class="request-card__unit-text" title="{{ $targetUnit }}">{{ $targetUnit }}</span></td>
                        <td @class([
                            'date-cell',
                            'request-card__date',
                            'request-card__date--paired' => $usesFullWidthMobileAction,
                        ])>{{ \Carbon\Carbon::parse($record['submitted_at'])->locale('th')->translatedFormat('d M').' '.(\Carbon\Carbon::parse($record['submitted_at'])->year + 543) }}</td>
                        <td class="request-card__status"><x-status-badge :status="$record['status']" /></td>
                        @endif
                        <td @class([
                            'table-actions',
                            'request-card__actions',
                            'request-card__actions--paired' => $usesFullWidthMobileAction,
                        ])>
                            <span data-default-actions @class([
                                'request-actions-group' => $role === 'officer',
                                'request-actions-group--paired' => $usesFullWidthMobileAction,
                            ])>
                                @if($role === 'officer' && ($record['student_roster_reopen_requested'] ?? false))
                                <x-portal-button :href="route($detailRoute, $record['id'])">ตรวจสอบ</x-portal-button>
                                @elseif($role === 'officer' && ($record['student_roster_needs_officer_attention'] ?? false))
                                <x-portal-button :href="route($detailRoute, $record['id'])">ตรวจรายชื่อ</x-portal-button>
                                @elseif($hasCourseIdAction)
                                <x-portal-button
                                    variant="secondary"
                                    class="request-preview-button"
                                    :data-open-dialog="'native-pdf-preview-'.$record['id']"
                                    aria-label="ดูตัวอย่าง PDF ที่อนุมัติแล้ว"
                                    title="Preview PDF"><x-portal-icon name="eye" /></x-portal-button>
                                <x-portal-button
                                    :data-open-dialog="'course-'.$record['id']"
                                    data-course-id-action>{{ $record['status'] === 'COURSE_ID_RECORDED' ? 'แก้ไขผลการดำเนินการ' : 'บันทึกผลการดำเนินการ' }}</x-portal-button>
                                @else
                                <x-portal-button :href="route($detailRoute, $record['id'])" :variant="$isUser ? 'secondary' : 'primary'">{{ $role === 'officer' && $record['status'] === 'UNDER_OFFICER_REVIEW' ? 'ตรวจสอบ' : ($role === 'approver' && $record['status'] === 'PENDING_APPROVAL' ? 'พิจารณา' : 'รายละเอียด') }}</x-portal-button>
                                @endif
                            </span>
                            @if($isUser && ($record['requires_student_roster'] ?? false) && ! ($record['has_student_roster'] ?? false))
                            <span data-roster-action hidden><x-portal-button :href="route('requests.show', $record['id']).'#student-roster'" variant="secondary">แนบรายชื่อ</x-portal-button></span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    <tr data-empty-row @if($records->isNotEmpty()) hidden @endif><td colspan="{{ $role === 'officer' ? 8 : ($isUser ? 6 : 7) }}" class="empty-state">ไม่พบคำร้องที่ตรงกับการค้นหา</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <nav class="request-pagination" data-pagination aria-label="เปลี่ยนหน้ารายการคำร้อง" hidden>
            <div class="request-pagination__pages">
                <button type="button" class="request-pagination__button" data-page-prev aria-label="หน้าก่อนหน้า"><span class="request-pagination__chevron request-pagination__chevron--previous" aria-hidden="true"></span></button>
                <div class="request-pagination__numbers" data-page-numbers></div>
                <button type="button" class="request-pagination__button" data-page-next aria-label="หน้าถัดไป"><span class="request-pagination__chevron request-pagination__chevron--next" aria-hidden="true"></span></button>
            </div>
            <label class="request-pagination__goto">ไปที่
                <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off" data-page-input aria-label="หมายเลขหน้าที่ต้องการไป">
                <button type="button" class="request-pagination__go" data-page-go>ไป</button>
            </label>
        </nav>
        <p class="sr-only" data-filter-count aria-live="polite"></p>
    </main>
    @foreach($records as $record)
    @if($role === 'officer' && $record['status'] === 'COURSE_ID_RECORDED')
    <x-portal-modal :id="'native-pdf-preview-'.$record['id']" title="ตัวอย่าง PDF ที่อนุมัติแล้ว" class="native-pdf-preview-dialog">
        <iframe
            data-native-pdf-preview
            data-pdf-url="{{ $record['signed_path']
                ? route('requests.attachment', [$record['id'], 'signed', 'preview' => 1])
                : route('requests.document', ['id' => $record['id'], 'preview' => 1]) }}"
            title="ตัวอย่าง PDF ที่อนุมัติแล้ว"></iframe>
    </x-portal-modal>
    <x-portal-modal :id="'course-'.$record['id']" :title="$record['status'] === 'COURSE_ID_RECORDED' ? 'แก้ไขผลการดำเนินการ' : 'บันทึกผลการดำเนินการ'" class="course-modal">
        <form id="course-id-form-{{ $record['id'] }}" method="post" action="{{ route('officer.course-id', $record['id']) }}">@csrf
            <div id="course-id-step1-{{ $record['id'] }}">
                <dl class="request-summary">
                    <div>
                        <dt>เลขที่เอกสาร</dt>
                        <dd>{{ $record['number'] }}</dd>
                    </div>
                    <div>
                        <dt>ผู้เสนอขอ</dt>
                        <dd>{{ $record['proposer'] }}</dd>
                    </div>
                    <div>
                        <dt>วันที่ยื่น</dt>
                        <dd>{{ $record['submitted_at'] }}</dd>
                    </div>
                </dl>
                <x-portal-field name="course_id" :id="'course-id-'.$record['id']" label="Course ID (ตัวเลขหลัง id=)" type="number" :value="$record['course_id'] ?? ''" placeholder="เช่น 2" :required="true" min="1" step="1" inputmode="numeric" />
                <label class="form-label" for="course-execution-detail-{{ $record['id'] }}">รายละเอียดการดำเนินการ (ถ้ามี)
                    <textarea id="course-execution-detail-{{ $record['id'] }}" name="execution_detail" class="form-field form-field--textarea" rows="4" maxlength="5000" placeholder="ระบุรายละเอียดที่ต้องการแจ้งให้ผู้ยื่นคำร้องทราบ">{{ $record['execution_detail'] ?? '' }}</textarea>
                </label>
                <div class="modal-actions modal-actions--stack"><x-portal-button type="button" data-course-id-next="{{ $record['id'] }}">{{ $record['status'] === 'COURSE_ID_RECORDED' ? 'บันทึกการแก้ไขและแจ้งเตือนผู้ขอคำร้อง' : 'บันทึกและแจ้งเตือนผู้ขอคำร้อง' }}</x-portal-button><x-portal-button variant="plain" data-course-id-cancel="{{ $record['id'] }}">ยกเลิก</x-portal-button></div>
            </div>
            <div id="course-id-step2-{{ $record['id'] }}" hidden>
                <p class="confirmation-message">ยืนยัน{{ $record['status'] === 'COURSE_ID_RECORDED' ? 'แก้ไข' : 'บันทึก' }}ผลการดำเนินการสำหรับ Course ID <strong data-course-id-preview></strong><br>และแจ้งเตือนผู้ขอคำร้องใช่หรือไม่?</p>
                <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-course-id-back="{{ $record['id'] }}">ย้อนกลับ</x-portal-button><x-portal-button type="submit">ยืนยัน{{ $record['status'] === 'COURSE_ID_RECORDED' ? 'การแก้ไข' : 'บันทึก' }}</x-portal-button></div>
            </div>
            <div id="course-id-step3-{{ $record['id'] }}" hidden>
                <p class="confirmation-message">คุณต้องการยกเลิกใช่หรือไม่?<br>Course ID และรายละเอียดการดำเนินการที่กรอกไว้จะถูกล้าง</p>
                <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-course-id-cancel-back="{{ $record['id'] }}">ย้อนกลับ</x-portal-button><x-portal-button type="button" data-course-id-cancel-confirm="{{ $record['id'] }}">ยืนยันยกเลิก</x-portal-button></div>
            </div>
        </form>
    </x-portal-modal>
    @endif
    @endforeach
</x-portal-layout>
