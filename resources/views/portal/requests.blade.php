@php
    $isUser = $role === 'user';
    $title = $isUser ? 'สถานะเอกสาร' : ($role === 'officer' ? 'เอกสารรอการตรวจสอบและลงนาม' : 'ขั้นตอนการลงนามอนุมัติ (Pending Executive Approval)');
    $detailRoute = $isUser ? 'requests.show' : ($role === 'officer' ? 'officer.show' : 'approver.show');
    $pending = $records->where('status', 'PENDING_SIGNED_DOCUMENT')->count();
@endphp
<x-portal-layout :title="$title" :role="$role">
    <main class="portal-main list-page {{ $isUser ? 'list-page--user' : '' }}" data-request-list>
        <div class="page-heading">
            <div><h2 class="page-heading__title">@if($role === 'officer')<span class="page-heading__icon"><x-portal-icon name="check" /></span>@endif{{ $title }}</h2>@if($isUser)<p class="page-heading__description">ติดตามขั้นตอนและประวัติการส่งเอกสารขอสร้างรายวิชาและคำร้องทั่วไปของคุณ</p>@elseif($role === 'approver')<p class="page-heading__description">ผู้บริหารพิจารณาอนุมัติสร้างรายวิชาในระบบ SWU Moodle Academy</p>@endif</div>
            @if($isUser)<x-portal-button :href="route('requests.create')" variant="danger"><x-portal-icon name="plus" />สร้างคำร้องใหม่</x-portal-button>@endif
        </div>
        @if($isUser && $pending)
            <div class="notification-banner"><span>แจ้งเตือน: คุณมี {{ $pending }} คำร้องที่ต้องอัปโหลดเอกสารที่ลงนามแล้ว</span><x-portal-button variant="secondary" data-filter-pending>ดูทั้งหมด</x-portal-button></div>
        @endif
        <section class="filter-card" aria-label="ค้นหาและกรองคำร้อง">
            <label class="search-control"><x-portal-icon name="search" /><input type="search" data-search placeholder="{{ $isUser ? 'ค้นหาชื่อเอกสาร หรือ เลขที่คำร้อง...' : 'ค้นหาชื่อโครงการ / รายวิชา' }}" aria-label="ค้นหาคำร้อง"></label>
            <select class="filter-control" data-status-filter aria-label="กรองสถานะ"><option value="">สถานะทั้งหมด</option>@foreach($statuses as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select>
            <select class="filter-control" data-date-filter aria-label="กรองวันที่ยื่น"><option value="">วันที่ยื่น: ทั้งหมด</option><option value="month">เดือนนี้</option><option value="year">ปีนี้</option></select>
        </section>
        <div class="table-container" tabindex="0" role="region" aria-label="ตารางคำร้อง">
            <table class="request-table">
                <thead><tr>@if($isUser)<th>วันที่ส่งคำร้อง</th><th>ชื่อโครงการ</th><th>ส่วนงาน/คณะ/สำนักเป้าหมาย</th><th>สถานะล่าสุด</th><th>Course ID</th>@else<th>เลขที่เอกสาร</th><th>ชื่อโครงการ / รายวิชา</th><th>ผู้เสนอขอ</th><th>วันที่ยื่น</th><th>สถานะ</th>@endif<th>การจัดการ</th></tr></thead>
                <tbody>
                    @foreach($records as $record)
                        <tr data-request-row data-status="{{ $record['status'] }}" data-date="{{ $record['submitted_at'] }}">
                            @if($isUser)
                                <td class="date-cell">{{ \Carbon\Carbon::parse($record['submitted_at'])->locale('th')->translatedFormat('d M').' '.(\Carbon\Carbon::parse($record['submitted_at'])->year + 543) }}</td>
                                <td class="course-cell"><span>{{ $record['project_name'] }}</span><small>เลขที่คำร้อง: {{ $record['number'] }}</small></td>
                                <td>{{ $record['unit'] }}</td><td><x-status-badge :status="$record['status']" /></td>
                                <td>@if($record['course_id'] ?? null)<a class="course-link" href="{{ route('requests.show', $record['id']) }}">{{ $record['course_id'] }}</a>@else<span class="muted">—</span>@endif</td>
                            @else
                                <td class="request-table__number">{{ $record['number'] }}</td><td class="course-cell">{{ $record['project_name'] }}</td><td>{{ $record['requester'] }}</td>
                                <td class="date-cell">{{ \Carbon\Carbon::parse($record['submitted_at'])->locale('th')->translatedFormat('d M').' '.(\Carbon\Carbon::parse($record['submitted_at'])->year + 543) }}</td><td><x-status-badge :status="$record['status']" /></td>
                            @endif
                            <td class="table-actions">
                                @if($isUser && $record['status'] === 'PENDING_SIGNED_DOCUMENT')
                                    <x-portal-button variant="secondary" :data-open-dialog="'upload-'.$record['id']">อัปโหลดไฟล์</x-portal-button>
                                @elseif($role === 'officer' && $record['status'] === 'PENDING_COURSE_ID')
                                    <x-portal-button :data-open-dialog="'course-'.$record['id']">บันทึก Course ID</x-portal-button>
                                @else
                                    <x-portal-button :href="route($detailRoute, $record['id'])" :variant="$isUser ? 'secondary' : 'primary'">{{ $role === 'officer' && $record['status'] === 'UNDER_OFFICER_REVIEW' ? 'ตรวจสอบ' : ($role === 'approver' && $record['status'] === 'PENDING_APPROVAL' ? 'พิจารณา' : 'รายละเอียด') }}</x-portal-button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr data-empty-row @if($records->isNotEmpty()) hidden @endif><td colspan="6" class="empty-state">ไม่พบคำร้องที่ตรงกับการค้นหา</td></tr>
                </tbody>
            </table>
        </div>
        <p class="sr-only" data-filter-count aria-live="polite"></p>
    </main>
    @foreach($records as $record)
        @if($isUser && $record['status'] === 'PENDING_SIGNED_DOCUMENT')
            <x-portal-modal :id="'upload-'.$record['id']" title="อัปโหลดเอกสารที่ลงนามแล้ว">
                <form method="post" action="{{ route('requests.upload', $record['id']) }}" enctype="multipart/form-data">@csrf
                    <x-portal-upload name="signed_document" :id="'signed-'.$record['id']" :required="true" />
                    <div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" variant="secondary">อัปโหลด</x-portal-button></div>
                </form>
            </x-portal-modal>
        @endif
        @if($role === 'officer' && $record['status'] === 'PENDING_COURSE_ID')
            <x-portal-modal :id="'course-'.$record['id']" title="บันทึก Course ID" class="course-modal">
                <form method="post" action="{{ route('officer.course-id', $record['id']) }}">@csrf
                    <dl class="request-summary"><div><dt>เลขที่เอกสาร</dt><dd>{{ $record['number'] }}</dd></div><div><dt>ผู้เสนอขอ</dt><dd>{{ $record['requester'] }}</dd></div><div><dt>วันที่ยื่น</dt><dd>{{ $record['submitted_at'] }}</dd></div></dl>
                    <x-portal-field name="course_id" :id="'course-id-'.$record['id']" label="Course ID" placeholder="เช่น DE164" :required="true" maxlength="100" pattern="[A-Za-z0-9_-]+" />
                    <div class="modal-actions modal-actions--stack"><x-portal-button type="submit">บันทึกและแจ้งเตือนผู้ขอคำร้อง</x-portal-button><x-portal-button variant="plain" data-close-dialog>ยกเลิก</x-portal-button></div>
                </form>
            </x-portal-modal>
        @endif
    @endforeach
</x-portal-layout>
