@props(['title', 'contextTitle' => null, 'highlightContext' => false, 'description' => null, 'role' => 'user', 'showMenu' => true, 'showAccount' => false, 'showLogo' => false])
@php
$actor = request()->attributes->get('portal.actor', session('portal.actor', config("course-workflow.actors.$role")));
$name = $actor['name'] ?? 'ผู้ใช้งาน';
$roleLabel = $actor['label'] ?? '';
$buasriId = $actor['buasri_id'] ?? '';
@endphp
<header @class([
    'requester-page-header',
    'requester-page-header--with-logo' => $showLogo,
    'requester-page-header--with-context' => filled($contextTitle),
    'requester-page-header--active-context' => $highlightContext,
])>
    @if($showMenu)
    <button type="button" class="requester-page-header__menu" data-requester-sidebar-toggle aria-controls="requester-sidebar" aria-expanded="false" aria-label="เปิดเมนูแบบฟอร์ม">
        <span></span><span></span><span></span>
    </button>
    @endif
    @if($showLogo)
    <img src="{{ asset('img/logo.png') }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ" class="requester-page-header__logo">
    @endif
    <div class="requester-page-header__copy">
        <h1>
            <span>{{ $title }}</span>
            @if($contextTitle)
            <span class="requester-page-header__separator" aria-hidden="true">/</span>
            <span class="requester-page-header__context">{{ $contextTitle }}</span>
            @endif
        </h1>
        @if($description)<p>{{ $description }}</p>@endif
    </div>
    @if($showAccount)
        @if($role === 'officer')
        <div class="portal-header__officer-account">
            <div class="portal-user portal-user--static" title="{{ $name }} ({{ $roleLabel }})">
                <span><span class="portal-user__name">{{ $name }} ({{ $roleLabel }})</span><span class="portal-user__id">{{ $buasriId }}</span></span>
                <span class="portal-avatar"><x-portal-icon name="user" /></span>
            </div>
            <button type="button" class="portal-header__logout-btn" data-open-dialog="logout-dialog" aria-label="ออกจากระบบ" title="ออกจากระบบ">
                <x-portal-icon name="log-out" />
            </button>
        </div>
        @else
        <details class="portal-account">
            <summary class="portal-user">
                <span><span class="portal-user__name">{{ $name }} ({{ $roleLabel }})</span><span class="portal-user__id">{{ $buasriId }}</span></span>
                <span class="portal-avatar"><x-portal-icon name="user" /></span>
            </summary>
            <div class="account-menu"><button type="button" data-open-dialog="logout-dialog">ออกจากระบบ</button></div>
        </details>
        @endif
    @endif
</header>
