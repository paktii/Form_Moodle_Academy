@props(['title' => 'SWU Moodle Academy', 'role' => 'user', 'login' => false])
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="portal-page {{ $login ? 'login-page' : '' }}">
    @unless($login)
    <x-portal-header :role="$role" />
    @endunless
    @php
        $globalErrors = $errors->has('form') ? $errors->get('form') : [];
    @endphp
    @if(count($globalErrors) > 0 && !$login)
    <div class="error-summary" role="alert" tabindex="-1">
        <strong>กรุณาตรวจสอบข้อมูล</strong>
        <ul>@foreach($globalErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
    @endif
    {{ $slot }}
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
