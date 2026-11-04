@props(['title' => 'SWU Moodle Academy', 'headerContext' => null, 'highlightHeaderContext' => false, 'tabTitle' => null, 'role' => 'user', 'login' => false, 'description' => null, 'showHeaderLogo' => false])
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(request()->attributes->get('portal.tab_role_enabled', false))
    <meta name="portal-tab-role" content="{{ request()->attributes->get('portal.actor', session('portal.actor'))['role'] ?? 'user' }}">
    @endif
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <title>{{ $tabTitle ?? $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="portal-page {{ $login ? 'login-page' : '' }} {{ ! $login && $role === 'user' ? 'portal-page--requester' : '' }}">
    @php
        $globalErrors = $errors->has('form') ? $errors->get('form') : [];
        $requesterShell = ! $login && $role === 'user';
    @endphp

    @if($requesterShell)
    <div class="requester-shell" data-requester-shell>
        <x-requester-sidebar />
        <button type="button" class="requester-sidebar-overlay" data-requester-sidebar-close aria-label="ปิดเมนู" tabindex="-1"></button>
        <div class="requester-workspace">
            <x-requester-page-header :title="$title" :context-title="$headerContext" :highlight-context="$highlightHeaderContext" :description="$description" />
            @if(count($globalErrors) > 0)
            <div class="error-summary" role="alert" tabindex="-1">
                <strong>กรุณาตรวจสอบข้อมูล</strong>
                <ul>@foreach($globalErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
            @endif
            {{ $slot }}
        </div>
    </div>
    @else
        @unless($login)
        <x-requester-page-header :title="$title" :context-title="$headerContext" :description="$description" :role="$role" :show-menu="false" :show-account="true" :show-logo="$showHeaderLogo" />
        @endunless
        @if(count($globalErrors) > 0 && !$login)
        <div class="error-summary" role="alert" tabindex="-1">
            <strong>กรุณาตรวจสอบข้อมูล</strong>
            <ul>@foreach($globalErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
        @endif
        {{ $slot }}
    @endif
    <x-portal-footer />
    @unless($login)
    <x-portal-modal id="logout-dialog" title="ยืนยันการออกจากระบบ">
        <p class="confirmation-message">คุณต้องการออกจากระบบใช่หรือไม่?</p>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <div class="modal-actions">
                <x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button>
                <x-portal-button type="submit">ยืนยันออกจากระบบ</x-portal-button>
            </div>
        </form>
    </x-portal-modal>
    @endunless
    @if(session('success'))
    <x-portal-modal id="success-dialog" title="ดำเนินการสำเร็จ" :auto-open="true" class="portal-modal--success">
        <div class="success-message"><span class="success-icon"><x-portal-icon name="check" /></span>
            <p>{!! session('success') !!}</p>
        </div>
        <div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>เสร็จสิ้น</x-portal-button></div>
    </x-portal-modal>
    @endif
</body>

</html>
