<x-portal-layout title="เข้าสู่ระบบ SWU Academy" tab-title="Sign In" :login="true">
    <main class="login-card">
        <section class="login-visual">
            <img src="{{ asset('img/SWU_Logo_TH_Color.png') }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ" class="login-visual__logo">
        </section>
        <section class="login-content">
            <h1>เข้าสู่ระบบ</h1>

            <form action="{{ route('login.enter') }}" method="post" class="login-form">
                @csrf
                <label class="form-label" for="buasri-id">Buasri ID<input id="buasri-id" name="buasri_id" value="{{ old('buasri_id') }}" class="form-field" placeholder="Buasri ID" autocomplete="username" required></label>
                <label class="form-label" for="password">Password<input id="password" name="password" class="form-field" type="password" placeholder="Password" autocomplete="current-password" required></label>
                @if(config('course-workflow.dev_role_switcher'))
                <fieldset class="login-role-switcher">
                    <legend>บทบาทสำหรับทดสอบ</legend>
                    <div class="login-role-switcher__options">
                        <label><input type="radio" name="login_role" value="" @checked(old('login_role', '') === '')><span>อัตโนมัติ</span></label>
                        <label><input type="radio" name="login_role" value="officer" @checked(old('login_role') === 'officer')><span>เจ้าหน้าที่</span></label>
                        <label><input type="radio" name="login_role" value="approver" @checked(old('login_role') === 'approver')><span>ผู้อนุมัติ</span></label>
                    </div>
                    <small>ตัวเลือกนี้แสดงเฉพาะในสภาพแวดล้อมสำหรับพัฒนา</small>
                    @error('login_role')<p class="login-role-switcher__error">{{ $message }}</p>@enderror
                </fieldset>
                @endif
                <div style="min-height: 21px; margin-top: -8px;">
                    @error('buasri_id')<p style="color:#dc2626;font-size:14px;margin:0;">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="login-submit">เข้าสู่ระบบ</button>
            </form>
        </section>
    </main>
</x-portal-layout>
