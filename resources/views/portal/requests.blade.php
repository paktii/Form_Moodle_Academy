@php
$isUser = $role === 'user';
$title = $isUser ? 'สถานะเอกสาร' : ($role === 'officer' ? 'เอกสารรอการตรวจสอบและลงนาม' : 'ขั้นตอนการลงนามอนุมัติ');
$detailRoute = $isUser ? 'requests.show' : ($role === 'officer' ? 'officer.show' : 'approver.show');
$pending = $records->where('status', 'PENDING_SIGNED_DOCUMENT')->count();
$missingRosters = $records->filter(fn ($record) => ($record['requires_student_roster'] ?? false) && ! ($record['has_student_roster'] ?? false))->count();
$rosterUpdates = $records->filter(fn ($record) => $record['student_roster_needs_officer_attention'] ?? false)->count();
$filterStatuses = match ($role) {
'officer' => [
'UNDER_OFFICER_REVIEW' => 'รออนุมัติ',
'PENDING_COURSE_ID' => 'อนุมัติแล้ว',
'COURSE_ID_RECORDED' => 'เสร็จสมบูรณ์',
'REJECTED' => 'ไม่อนุมัติ',
],
'approver' => [
'PENDING_APPROVAL' => 'รออนุมัติ',
'PENDING_COURSE_ID' => 'อนุมัติแล้ว',
'REJECTED' => 'ไม่อนุมัติ',
'COURSE_ID_RECORDED' => 'เสร็จสมบูรณ์',
],
default => $statuses,
};
@endphp
<x-portal-layout :title="$title" :role="$role">
    <main class="portal-main list-page {{ $isUser ? 'list-page--user' : '' }}" data-request-list>
        <div class="page-heading">
            <div>
                <h2 class="page-heading__title">{{ $title }}</h2>@if($isUser)<p class="page-heading__description">ติดตามขั้นตอนและประวัติการส่งเอกสารขอสร้างรายวิชาและคำร้องทั่วไปของคุณ</p>@elseif($role === 'approver')<p class="page-heading__description">ผู้บริหารพิจารณาอนุมัติสร้างรายวิชาในระบบ SWU Moodle Academy</p>@elseif($role === 'officer')<p class="page-heading__description">เจ้าหน้าที่ตรวจสอบความถูกต้องของเอกสารและคำร้องขอสร้างรายวิชาในระบบ SWU Moodle Academy</p>@endif
            </div>
            @if($isUser)<x-portal-button :href="route('requests.create')" variant="danger"><x-portal-icon name="plus" />สร้างคำร้องใหม่</x-portal-button>@endif
        </div>
        @if($isUser && ($pending || $missingRosters))
        <div class="notification-banner" aria-label="รายการแจ้งเตือน">
            <span>แจ้งเตือน: @if($pending)คุณมี {{ $pending }} คำร้องที่ต้องอัปโหลดเอกสารที่ลงนามแล้ว@endif @if($pending && $missingRosters) และ @endif @if($missingRosters) {{ $missingRosters }} คำร้องที่ยังไม่แนบรายชื่อผู้เรียน@endif</span>
            <div class="notification-banner__actions">
                @if($pending)<x-portal-button variant="secondary" data-filter-pending>ดูเอกสาร</x-portal-button>@endif
                @if($missingRosters)<x-portal-button variant="secondary" data-filter-roster>แนบรายชื่อ</x-portal-button>@endif
            </div>
        </div>
        @endif
        @if($role === 'officer' && $rosterUpdates)
        @php
            $rosterUpdatesPending = $records->filter(fn ($record) => ($record['student_roster_needs_officer_attention'] ?? false) && $record['status'] === 'PENDING_COURSE_ID')->count();
            $rosterUpdatesRecorded = $records->filter(fn ($record) => ($record['student_roster_needs_officer_attention'] ?? false) && $record['status'] === 'COURSE_ID_RECORDED')->count();
            $rosterDetail = '';
            if ($rosterUpdatesPending && $rosterUpdatesRecorded) {
                $rosterDetail = " ({$rosterUpdatesPending} รายการรอบันทึก Course ID / {$rosterUpdatesRecorded} รายการที่บันทึก Course ID แล้ว)";
            } elseif ($rosterUpdatesPending) {
                $rosterDetail = " ({$rosterUpdatesPending} รายการรอบันทึก Course ID)";
            } elseif ($rosterUpdatesRecorded) {
                $rosterDetail = " ({$rosterUpdatesRecorded} รายการที่บันทึก Course ID แล้ว)";
            }
        @endphp
        <div class="notification-banner" aria-label="แจ้งเตือนรายชื่อผู้เรียน">
            <span>แจ้งเตือน: คุณมี {{ $rosterUpdates }} คำร้องที่มีรายชื่อผู้เรียนใหม่หรือมีการอัปเดตที่ยังไม่รับทราบ{{ $rosterDetail }} กรุณาตรวจสอบและรับทราบรายชื่อ</span>
            <div class="notification-banner__actions"><x-portal-button variant="secondary" data-filter-roster-updates>ตรวจสอบรายชื่อ</x-portal-button></div>
        </div>
        @endif
        <section class="filter-card" aria-label="ค้นหาและกรองคำร้อง">
            <label class="search-control"><x-portal-icon name="search" /><input type="search" data-search placeholder="ค้นหาเลขที่คำร้อง, ชื่อโครงการ หรือ ส่วนงาน" aria-label="ค้นหาคำร้อง"></label>
            <select class="filter-control" data-status-filter aria-label="กรองสถานะ">
                <option value="">สถานะทั้งหมด</option>@foreach(array_unique($filterStatuses) as $label)<option value="{{ implode(',', array_keys($filterStatuses, $label)) }}">{{ $label }}</option>@endforeach
            </select>
            <select class="filter-control" data-date-filter aria-label="กรองวันที่ยื่น">
                <option value="">วันที่ยื่น: ทั้งหมด</option>
                <option value="month" selected>เดือนนี้</option>
                <option value="year">ปีนี้</option>
            </select>
            <button type="button" class="clear-filter-btn" data-clear-filter aria-label="ล้างตัวกรอง">
                <x-portal-icon name="filter-x" />
            </button>
        </section>
        <div class="table-container" tabindex="0" role="region" aria-label="ตารางคำร้อง">
            <table class="request-table">
                <thead>
                    <tr>@if($isUser)<th>วันที่ส่งคำร้อง</th>
                        <th>ชื่อโครงการ</th>
                        <th>ส่วนงาน/คณะ/สำนักเป้าหมาย</th>
                        <th>สถานะล่าสุด</th>
                        <th>Course ID</th>@else<th>เลขที่เอกสาร</th>
                        <th>ชื่อโครงการ / รายวิชา</th>
                        <th>ผู้เสนอขอ</th>
                        <th>ส่วนงานเป้าหมาย</th>
                        <th>วันที่ยื่น</th>
                        <th>สถานะ</th>@endif<th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                    <tr data-request-row data-status="{{ $record['status'] }}" data-date="{{ $record['submitted_at'] }}" data-missing-roster="{{ ($record['requires_student_roster'] ?? false) && ! ($record['has_student_roster'] ?? false) ? 'true' : 'false' }}" data-roster-update="{{ ($record['student_roster_needs_officer_attention'] ?? false) ? 'true' : 'false' }}" data-search-content="{{ $record['number'] }} {{ $record['project_name'] }} {{ $record['unit'] ?? '' }} {{ $record['requester'] ?? '' }}">
                        @if($isUser)
                        <td class="date-cell request-card__date">{{ \Carbon\Carbon::parse($record['submitted_at'])->locale('th')->translatedFormat('d M').' '.(\Carbon\Carbon::parse($record['submitted_at'])->year + 543) }}</td>
                        <td class="course-cell request-card__title"><span>{{ $record['project_name'] }}</span><small>เลขที่คำร้อง: {{ $record['number'] }}</small></td>
                        <td class="request-card__unit">{{ $record['unit'] }}</td>
                        <td class="request-card__status"><x-status-badge :status="$record['status']" /></td>
                        <td class="request-card__course-id">@if($record['course_id'] ?? null)<a class="course-link" href="{{ rtrim(config('course-workflow.moodle_url'), '/').'/course/view.php?name='.rawurlencode($record['course_id']) }}" target="_blank" rel="noopener noreferrer" aria-label="เปิด Course ID {{ $record['course_id'] }} ใน SWU Moodle Academy (แท็บใหม่)">{{ $record['course_id'] }}</a>@else<span class="muted">—</span>@endif</td>
                        @else
                        <td class="request-table__number request-card__number">{{ $record['number'] }}</td>
                        <td class="course-cell request-card__title"><span>{{ $record['project_name'] }}</span></td>
                        <td class="requester-cell request-card__requester">{{ $record['requester'] }}</td>
                        <td class="request-card__unit">{{ $record['unit'] }}</td>
                        <td class="date-cell request-card__date">{{ \Carbon\Carbon::parse($record['submitted_at'])->locale('th')->translatedFormat('d M').' '.(\Carbon\Carbon::parse($record['submitted_at'])->year + 543) }}</td>
                        <td class="request-card__status"><x-status-badge :status="$record['status']" /></td>
                        @endif
                        <td class="table-actions request-card__actions">
                            <span data-default-actions>
                                @if($isUser && $record['status'] === 'PENDING_SIGNED_DOCUMENT')
                                <x-portal-button
                                    :href="route('requests.document.download', $record['id'])"
                                    variant="secondary"
                                    data-request-pdf-download
                                    :hidden="$record['unsigned_pdf_downloaded']">ดาวน์โหลด PDF</x-portal-button>
                                <x-portal-button
                                    variant="secondary"
                                    :data-open-dialog="'upload-'.$record['id']"
                                    data-request-upload
                                    :hidden="! $record['unsigned_pdf_downloaded']">อัปโหลดไฟล์</x-portal-button>
                                @elseif($role === 'officer' && ($record['student_roster_needs_officer_attention'] ?? false))
                                <x-portal-button :href="route($detailRoute, $record['id'])">ตรวจรายชื่อ</x-portal-button>
                                @elseif($role === 'officer' && $record['status'] === 'PENDING_COURSE_ID')
                                <x-portal-button
                                    :href="route('requests.attachment', [$record['id'], 'signed'])"
                                    class="approved-pdf-button"
                                    aria-label="ดาวน์โหลด PDF ที่อนุมัติแล้ว"
                                    data-approved-pdf-download
                                    :hidden="$record['approved_pdf_downloaded']">ดาวน์โหลด PDF</x-portal-button>
                                <x-portal-button
                                    :data-open-dialog="'course-'.$record['id']"
                                    data-course-id-action
                                    :hidden="! $record['approved_pdf_downloaded']">บันทึก Course ID</x-portal-button>
                                @elseif($role === 'officer' && $record['status'] === 'COURSE_ID_RECORDED')
                                <x-portal-button :href="route('requests.attachment', [$record['id'], 'signed'])" class="approved-pdf-button" aria-label="ดาวน์โหลด PDF ที่อนุมัติแล้ว">ดาวน์โหลด PDF</x-portal-button>
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
                    <tr data-empty-row @if($records->isNotEmpty()) hidden @endif><td colspan="{{ $isUser ? 6 : 7 }}" class="empty-state">ไม่พบคำร้องที่ตรงกับการค้นหา</td>
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
    @if($isUser && $record['status'] === 'PENDING_SIGNED_DOCUMENT')
    <x-portal-modal :id="'upload-'.$record['id']" title="อัปโหลดเอกสารที่ลงนามแล้ว">
        <form id="upload-form-{{ $record['id'] }}" method="post" action="{{ route('requests.upload', $record['id']) }}" enctype="multipart/form-data">@csrf
            <div id="upload-step1-{{ $record['id'] }}">
                <x-portal-upload name="signed_document" :id="'signed-'.$record['id']" :required="true" />
                <div class="modal-actions">
                    <x-portal-button variant="secondary" data-upload-cancel="{{ $record['id'] }}">ยกเลิก</x-portal-button>
                    <x-portal-button type="button" variant="secondary" data-upload-next="{{ $record['id'] }}">อัปโหลด</x-portal-button>
                </div>
            </div>
            <div id="upload-step2-{{ $record['id'] }}" class="upload-confirmation" hidden>
                <p class="upload-confirmation__message">ระบบจะส่งคำร้องให้เจ้าหน้าที่ตรวจสอบ<br>คุณต้องการยืนยันการส่งใช่หรือไม่?</p>
                <div class="modal-actions upload-confirmation__actions">
                    <x-portal-button type="button" variant="plain" data-upload-back="{{ $record['id'] }}">ย้อนกลับ</x-portal-button>
                    <x-portal-button type="submit" variant="primary">ยืนยันการส่ง</x-portal-button>
                </div>
            </div>
            <div id="upload-cancel-step-{{ $record['id'] }}" class="upload-confirmation" hidden>
                <p class="upload-confirmation__message">คุณต้องการยกเลิกการอัปโหลดเอกสารใช่หรือไม่?<br>ไฟล์ที่เลือกไว้จะถูกล้าง</p>
                <div class="modal-actions upload-confirmation__actions">
                    <x-portal-button type="button" variant="plain" data-upload-cancel-back="{{ $record['id'] }}">ย้อนกลับ</x-portal-button>
                    <x-portal-button type="button" variant="primary" data-upload-cancel-confirm>ยืนยันการยกเลิก</x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    @endif
    @if($role === 'officer' && $record['status'] === 'PENDING_COURSE_ID')
    <x-portal-modal :id="'course-'.$record['id']" title="บันทึก Course ID" class="course-modal">
        <form id="course-id-form-{{ $record['id'] }}" method="post" action="{{ route('officer.course-id', $record['id']) }}">@csrf
            <div id="course-id-step1-{{ $record['id'] }}">
                <dl class="request-summary">
                    <div>
                        <dt>เลขที่เอกสาร</dt>
                        <dd>{{ $record['number'] }}</dd>
                    </div>
                    <div>
                        <dt>ผู้เสนอขอ</dt>
                        <dd>{{ $record['requester'] }}</dd>
                    </div>
                    <div>
                        <dt>วันที่ยื่น</dt>
                        <dd>{{ $record['submitted_at'] }}</dd>
                    </div>
                </dl>
                <x-portal-field name="course_id" :id="'course-id-'.$record['id']" label="Course ID" placeholder="เช่น DE164" :required="true" maxlength="100" pattern="[A-Za-z0-9_-]+" />
                <div class="modal-actions modal-actions--stack"><x-portal-button type="button" data-course-id-next="{{ $record['id'] }}">บันทึกและแจ้งเตือนผู้ขอคำร้อง</x-portal-button><x-portal-button variant="plain" data-course-id-cancel="{{ $record['id'] }}">ยกเลิก</x-portal-button></div>
            </div>
            <div id="course-id-step2-{{ $record['id'] }}" hidden>
                <p class="confirmation-message">ยืนยันบันทึก Course ID <strong data-course-id-preview></strong><br>และแจ้งเตือนผู้ขอคำร้องใช่หรือไม่?</p>
                <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-course-id-back="{{ $record['id'] }}">ย้อนกลับ</x-portal-button><x-portal-button type="submit">ยืนยันบันทึก</x-portal-button></div>
            </div>
            <div id="course-id-step3-{{ $record['id'] }}" hidden>
                <p class="confirmation-message">คุณต้องการยกเลิกใช่หรือไม่?<br>Course ID ที่กรอกไว้จะถูกล้าง</p>
                <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-course-id-cancel-back="{{ $record['id'] }}">ย้อนกลับ</x-portal-button><x-portal-button type="button" data-course-id-cancel-confirm="{{ $record['id'] }}">ยืนยันยกเลิก</x-portal-button></div>
            </div>
        </form>
    </x-portal-modal>
    @endif
    @endforeach
</x-portal-layout>
