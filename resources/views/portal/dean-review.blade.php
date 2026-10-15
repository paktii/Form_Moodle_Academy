<x-portal-layout title="พิจารณารับรองคำร้อง" tab-title="Dean Review" :login="true">
    <main class="portal-main dean-review-page">
        <section class="portal-card dean-review-card">
            <div class="dean-review-card__brand">
                <img src="{{ asset('img/logo.png') }}" alt="มหาวิทยาลัยศรีนครินทรวิโรฒ">
                <span>SWU Moodle Academy</span>
            </div>
            @if($alreadyProcessed)
                <div class="dean-review-result" role="status">
                    <h1>คำร้องได้รับการดำเนินการแล้ว</h1>
                    <p>ไม่สามารถเปลี่ยนผลผ่านลิงก์นี้ได้อีก</p>
                </div>
            @else
                <h1>รายละเอียดคำร้องเพื่อพิจารณารับรอง</h1>
                <p class="muted">กรุณาตรวจสอบข้อมูลและเลือกผลการรับรอง ระบบจะบันทึกผลและแจ้งผู้เกี่ยวข้องโดยอัตโนมัติ</p>
                <div class="dean-review-card__request-number">เลขที่คำร้อง: <strong>{{ $courseRequest->request_no }}</strong></div>

                <div class="dean-review-card__details">
                    <x-request-data :record="$record" :show-requester-unit="false" :split-target-unit="true" />
                </div>

                @if(!empty($record['additional_files']))
                <section class="dean-review-attachments" aria-labelledby="dean-review-attachments-heading">
                    <h2 id="dean-review-attachments-heading">เอกสารแนบเพื่อตรวจสอบ</h2>
                    @foreach($record['additional_files'] ?? [] as $file)
                        <div class="document-row">
                            <x-portal-icon name="file-text" />
                            <span class="document-row__name">{{ $file['additional_name'] }}</span>
                            <x-portal-button :href="$additionalDocumentUrls[$file['document_id']]" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์ '.$file['additional_name']">
                                <x-portal-icon name="download" />
                                <span class="document-row__download-label">ดาวน์โหลด</span>
                            </x-portal-button>
                        </div>
                    @endforeach
                </section>
                @endif

                <div class="modal-actions dean-review-actions">
                    <x-portal-button type="button" data-open-dialog="dean-approve-dialog">รับรองคำร้อง</x-portal-button>
                    <x-portal-button type="button" data-open-dialog="dean-reject-dialog" variant="danger-outline">ไม่รับรอง</x-portal-button>
                </div>
            @endif
        </section>
    </main>
    @unless($alreadyProcessed)
        <x-portal-modal id="dean-approve-dialog" title="ยืนยันการรับรองคำร้อง">
            <p class="confirmation-message">คุณต้องการรับรองคำร้องนี้ใช่หรือไม่?<br>ระบบจะส่งต่อคำร้องให้เจ้าหน้าที่ตรวจสอบ</p>
            <form method="post" action="{{ $decisionUrl }}">
                @csrf
                <input type="hidden" name="decision" value="approve">
                <div class="modal-actions">
                    <x-portal-button type="button" variant="secondary" data-close-dialog>ยกเลิก</x-portal-button>
                    <x-portal-button type="submit">ยืนยันรับรองคำร้อง</x-portal-button>
                </div>
            </form>
        </x-portal-modal>
        <x-portal-modal id="dean-reject-dialog" title="ยืนยันไม่รับรองคำร้อง" :auto-open="$errors->has('reason')">
            <form method="post" action="{{ $decisionUrl }}">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <div data-dean-reject-entry>
                    <label class="form-label" for="dean-reject-reason">
                        เหตุผลที่ไม่รับรอง (ส่งให้ผู้ยื่นคำร้อง)<span class="required-indicator" aria-hidden="true">*</span>
                        <textarea id="dean-reject-reason" name="reason" class="form-field form-field--textarea @error('reason') form-field--error @enderror" required maxlength="3000">{{ old('reason') }}</textarea>
                        @error('reason')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    </label>
                    <div class="modal-actions">
                        <x-portal-button type="button" variant="secondary" data-dean-reject-cancel>ยกเลิก</x-portal-button>
                        <x-portal-button type="button" variant="danger" data-dean-reject-send>ยืนยันไม่รับรองคำร้อง</x-portal-button>
                    </div>
                </div>
                <div data-dean-reject-confirm hidden>
                    <p class="confirmation-message" data-dean-reject-confirm-message></p>
                    <div class="modal-actions">
                        <x-portal-button type="button" variant="plain" data-dean-reject-confirm-back>ย้อนกลับ</x-portal-button>
                        <x-portal-button type="button" data-dean-reject-confirm-action></x-portal-button>
                    </div>
                </div>
            </form>
        </x-portal-modal>
    @endunless
</x-portal-layout>
