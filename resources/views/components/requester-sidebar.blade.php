@php
$actor = request()->attributes->get('portal.actor', session('portal.actor', config('course-workflow.actors.user')));
$name = $actor['name'] ?? 'ผู้ใช้งาน';
$roleLabel = $actor['label'] ?? 'บุคลากร';
$buasriId = $actor['buasri_id'] ?? '';
$formTypes = [
    ['code' => 'CC-ITED-01', 'name' => 'แบบฟอร์มขอใช้พื้นที่สำหรับจัดทำเว็บไซต์', 'enabled' => true, 'route' => 'website-requests.create'],
    ['code' => 'CC-ITED-02', 'name' => 'แบบฟอร์มขอเปลี่ยนแปลงผู้ดูแลเว็บไซต์', 'enabled' => true, 'route' => 'website-contact-change-requests.create'],
    ['code' => 'CC-ITED-04', 'name' => 'แบบฟอร์มขอเพิ่มพื้นที่บนเว็บไซต์', 'enabled' => true, 'route' => 'website-storage-requests.create'],
    ['code' => 'CC-ITED-05', 'name' => 'แบบฟอร์มขอสิทธิ์ผู้ดูแลกรอบการประชุมของส่วนงานบนระบบ e-Meeting', 'enabled' => true, 'route' => 'emeeting-requests.create'],
    ['code' => 'CC-ITED-06', 'name' => 'แบบฟอร์มขอสร้างรายวิชาในระบบ SWU Moodle Academy', 'enabled' => true, 'route' => 'requests.create'],
];
$courseFormActive = request()->routeIs('requests.create', 'requests.form', 'requests.document');
$websiteFormActive = request()->routeIs('website-requests.create', 'website-requests.form', 'website-requests.document');
$websiteContactChangeFormActive = request()->routeIs('website-contact-change-requests.create', 'website-contact-change-requests.form', 'website-contact-change-requests.document');
$emeetingFormActive = request()->routeIs('emeeting-requests.create', 'emeeting-requests.form', 'emeeting-requests.document');
$websiteStorageFormActive = request()->routeIs('website-storage-requests.create', 'website-storage-requests.form', 'website-storage-requests.document');
@endphp
<aside id="requester-sidebar" class="requester-sidebar" aria-label="เมนูแบบฟอร์มขอรับบริการ">
    <div class="requester-sidebar__brand">
        <a href="{{ route('requests.index') }}" aria-label="กลับหน้าสถานะเอกสาร">
            <img src="{{ asset('img/logo.png') }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ" class="requester-sidebar__logo">
            <span>แบบฟอร์มขอรับบริการ</span>
        </a>
        <button type="button" class="requester-sidebar__close" data-requester-sidebar-close aria-label="ปิดเมนู">×</button>
    </div>

    <nav class="requester-sidebar__nav">
        <a href="{{ route('requests.index') }}" @class(['requester-sidebar__item', 'is-active' => request()->routeIs('requests.index', 'requests.show')]) @if(request()->routeIs('requests.index', 'requests.show')) aria-current="page" @endif>
            <x-portal-icon name="clipboard-clock" />
            <span class="requester-sidebar__item-copy"><strong>สถานะเอกสาร</strong></span>
        </a>

        <div class="requester-sidebar__forms" aria-label="ประเภทแบบฟอร์ม">
            @foreach($formTypes as $formType)
                @if($formType['enabled'])
                    @php($isFormActive = match ($formType['code']) { 'CC-ITED-01' => $websiteFormActive, 'CC-ITED-02' => $websiteContactChangeFormActive, 'CC-ITED-04' => $websiteStorageFormActive, 'CC-ITED-05' => $emeetingFormActive, default => $courseFormActive })
                    <a href="{{ route($formType['route']) }}" @class(['requester-sidebar__item', 'requester-sidebar__item--form', 'is-active' => $isFormActive]) @if($isFormActive) aria-current="page" @endif>
                        <x-portal-icon name="file-text" />
                        <span class="requester-sidebar__item-copy"><small>{{ $formType['code'] }}</small><strong>{{ $formType['name'] }}</strong></span>
                    </a>
                @else
                    <span class="requester-sidebar__item requester-sidebar__item--form is-disabled" aria-disabled="true" title="แบบฟอร์มนี้ยังไม่เปิดใช้งาน">
                        <x-portal-icon name="file-text" />
                        <span class="requester-sidebar__item-copy"><small>{{ $formType['code'] }}</small><strong>{{ $formType['name'] }}</strong></span>
                    </span>
                @endif
            @endforeach
        </div>
    </nav>

    <div class="requester-sidebar__account">
        <div class="requester-sidebar__identity">
            <span class="portal-avatar"><x-portal-icon name="user" /></span>
            <span class="requester-sidebar__identity-copy">
                <strong>{{ $name }} ({{ $roleLabel }})</strong>
                <small>{{ $buasriId }}</small>
            </span>
        </div>
        <button type="button" class="requester-sidebar__logout" data-open-dialog="logout-dialog">ออกจากระบบ</button>
    </div>
</aside>
