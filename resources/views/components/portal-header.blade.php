@props(['role' => 'user'])
@php
    $actor = session('portal.actor', config("course-workflow.actors.$role"));
    $name = $actor['name'] ?? 'ผู้ใช้งาน';
    $roleLabel = $actor['label'] ?? '';
    $buasriId = $actor['buasri_id'] ?? '';
    $home = match ($role) { 'officer' => 'officer.reviews', 'approver' => 'approver.reviews', default => 'requests.index' };
@endphp
<header class="portal-header">
    <div class="portal-header__inner">
        <a href="{{ route($home) }}" aria-label="กลับหน้ารายการคำร้อง"><img src="{{ asset('img/logo.png') }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ" class="portal-logo"></a>
        <h1 class="portal-title">แบบฟอร์มขอสร้างรายวิชาในระบบ SWU Moodle Academy</h1>
        <details class="portal-account">
            <summary class="portal-user">
                <span><span class="portal-user__name">{{ $name }} ({{ $roleLabel }})</span><span class="portal-user__id">{{ $buasriId }}</span></span>
                <span class="portal-avatar"><x-portal-icon name="user" /></span>
            </summary>
            <div class="account-menu"><button type="button" data-open-dialog="logout-dialog">ออกจากระบบ</button></div>
        </details>
    </div>
</header>
