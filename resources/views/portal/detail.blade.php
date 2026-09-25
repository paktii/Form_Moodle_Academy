@php
$isUser = $role === 'user';
$home = $isUser ? 'requests.index' : ($role === 'officer' ? 'officer.reviews' : 'approver.reviews');
$title = $isUser ? 'รายละเอียดเอกสาร' : ($role === 'officer' ? 'ตรวจสอบรายละเอียดคำขอและเอกสาร' : 'ตรวจสอบรายละเอียดคำขออนุมัติสร้างรายวิชา');
$canReview = $role === 'officer' && $record['status'] === 'UNDER_OFFICER_REVIEW';
$canApprove = $role === 'approver' && $record['status'] === 'PENDING_APPROVAL';
@endphp
<x-portal-layout :title="$title" :role="$role">
    <main class="portal-main detail-page detail-page--{{ $role }}">
        <div class="detail-actions requester-detail__navigation"><x-portal-button :href="route($home)" variant="secondary">ย้อนกลับ</x-portal-button>
            @if($isUser && in_array($record['status'], ['RETURNED_FOR_REVISION', 'PENDING_SIGNED_DOCUMENT']))<x-portal-button data-open-dialog="edit-request-dialog">แก้ไขคำร้อง</x-portal-button>@else<span class="muted">เลขที่คำร้อง: {{ $record['number'] }}</span>@endif
        </div>
        <div class="detail-heading">
            <div>
                <h2>@if($role === 'approver')ตรวจสอบรายละเอียด<br class="approver-title__mobile-break">คำขออนุมัติสร้างรายวิชา@else{{ $title }}@endif</h2>
            </div><x-status-badge :status="$record['status']" />
        </div>
        @if($record['reason'] ?? null)<section class="reason-banner reason-banner--message">
            <div>
                <h3>{{ $record['status'] === 'RETURNED_FOR_REVISION' ? 'เหตุผลที่ส่งกลับแก้ไข' : 'เหตุผลที่ไม่อนุมัติ' }}</h3>
                <p>{{ $record['reason'] }}</p>
            </div>
        </section>@endif
        <section class="portal-card detail-card"><x-request-data :record="$record" /></section>
        <section class="portal-card attachment-card">
            <h3>เอกสารแนบเพื่อตรวจสอบ</h3>
            <div class="document-row"><x-portal-icon name="file-code" /><span class="document-row__name">{{ $record['signed_name'] ?? 'โครงการขออนุมัติ AI course.pdf' }}</span>
                @if($role === 'officer')
                <x-portal-button :href="isset($record['signed_path']) ? route('requests.attachment', [$record['id'], 'signed']) : route('requests.document', $record['id'])" variant="secondary" class="document-row__download" :target="isset($record['signed_path']) ? null : '_blank'" :aria-label="(isset($record['signed_path']) ? 'ดาวน์โหลดไฟล์ ' : 'ดูเอกสาร ').($record['signed_name'] ?? 'โครงการขออนุมัติ AI course.pdf')"><x-portal-icon name="download" /><span class="document-row__download-label">{{ isset($record['signed_path']) ? 'ดาวน์โหลด' : 'ดูเอกสาร' }}</span></x-portal-button>
                @elseif(isset($record['signed_path']))<a href="{{ route('requests.attachment', [$record['id'], 'signed']) }}">ดาวน์โหลด</a>@else<a href="{{ route('requests.document', $record['id']) }}" target="_blank">ดูเอกสาร</a>@endif
            </div>
            @foreach($record['additional_files'] ?? [] as $file)<div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $file['additional_name'] }}</span>@if($role === 'officer')<x-portal-button :href="route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์ '.$file['additional_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>@else<a href="{{ route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']]) }}">ดาวน์โหลด</a>@endif</div>@endforeach
            @if($record['has_student_roster'])
            <div class="document-row roster-document-row">
                <x-portal-icon name="file-text" />
                <span class="document-row__name">{{ $record['student_roster_name'] }}</span>
                @if($record['student_roster_needs_officer_attention'])
                <span class="roster-status roster-status--pending">{{ $record['student_roster_is_update'] ? 'อัปเดตใหม่' : 'ไฟล์ใหม่' }}</span>
                @elseif($record['student_roster_acknowledged'])
                <span class="roster-status roster-status--complete">รับทราบแล้ว</span>
                @endif
                @if($role === 'officer')
                <x-portal-button :href="route('requests.attachment', [$record['id'], 'roster'])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์รายชื่อผู้เรียน '.$record['student_roster_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>
                @else
                <a href="{{ route('requests.attachment', [$record['id'], 'roster']) }}">ดาวน์โหลด</a>
                @endif
            </div>
            @if($role === 'officer')
            <div class="roster-review-actions">
                @if($record['student_roster_needs_officer_attention'])<x-portal-button data-open-dialog="acknowledge-roster-dialog">รับทราบรายชื่อแล้ว</x-portal-button>
                @elseif($record['student_roster_acknowledged'])<x-portal-button variant="secondary" data-open-dialog="reopen-roster-dialog">เปิดให้แก้ไขรายชื่อ</x-portal-button>@endif
            </div>
            @endif
            @if($record['student_roster_version_count'] > 1)
            <details class="roster-version-history">
                <summary>ประวัติรายชื่อผู้เรียน ({{ $record['student_roster_version_count'] }} เวอร์ชัน)</summary>
                @foreach($record['student_roster_versions'] as $version)
                <div class="roster-version-row"><span>เวอร์ชัน {{ $version['version'] }} — {{ $version['name'] }}<small>{{ $version['uploaded_at'] }}</small></span><a href="{{ route('requests.attachment', ['id' => $record['id'], 'kind' => 'roster', 'document' => $version['document_id']]) }}">ดาวน์โหลด</a></div>
                @endforeach
            </details>
            @endif
            @elseif($record['requires_student_roster'])<p class="roster-empty">ยังไม่แนบรายชื่อผู้เรียน</p>@endif
        </section>
        @if($record['course_id'] ?? null)<section class="portal-card course-result">
            <h3>ผลการสร้างรายวิชา</h3>
            <p>Course ID: <a class="course-link" href="{{ rtrim(config('course-workflow.moodle_url'), '/').'/course/view.php?name='.rawurlencode($record['course_id']) }}" target="_blank" rel="noopener noreferrer" aria-label="เปิด Course ID {{ $record['course_id'] }} ใน SWU Moodle Academy (แท็บใหม่)">{{ $record['course_id'] }}</a></p>
        </section>@endif
        @if($canReview || $canApprove)
        <section class="portal-card review-card">
            <h3>{{ $canReview ? 'ผลการตรวจสอบ' : 'ผลการพิจารณา' }}</h3>
            @if($canReview)
            <fieldset class="radio-fieldset">
                <legend>สถานะผลการตรวจสอบ</legend>
                <div class="radio-options"><label><input type="radio" name="review_choice" value="pass">เอกสารถูกต้องครบถ้วน</label><label><input type="radio" name="review_choice" value="return">เอกสารไม่ถูกต้อง / ส่งกลับแก้ไข</label></div>
            </fieldset>
            <x-portal-button data-review-submit disabled>กรุณาเลือกผลการตรวจสอบ</x-portal-button>
            @else
            <p class="muted">สถานะผลการตรวจสอบ</p>
            <p class="review-passed"><x-portal-icon name="clipboard-check" />ข้อมูลถูกต้องครบถ้วน โดย: เจ้าหน้าที่ตรวจสอบ</p>
            <x-portal-button data-open-dialog="approve-dialog">อนุมัติการสร้างรายวิชา</x-portal-button>
            <x-portal-button variant="danger-outline" data-open-dialog="reject-dialog">ไม่อนุมัติการสร้างรายวิชา</x-portal-button>
            @endif
        </section>
        @endif

    </main>
    @if($role === 'officer' && $record['has_student_roster'] && $record['student_roster_needs_officer_attention'])
    <x-portal-modal id="acknowledge-roster-dialog" title="ยืนยันการรับทราบรายชื่อ">
        <p class="confirmation-message">คุณตรวจสอบรายชื่อผู้เรียนเวอร์ชันล่าสุดแล้วใช่หรือไม่?<br>เมื่อยืนยัน ระบบจะล็อกไม่ให้ผู้ยื่นคำร้องแก้ไขไฟล์</p>
        <form action="{{ route('officer.student-roster.acknowledge', $record['id']) }}" method="post">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">ยืนยันรับทราบ</x-portal-button></div></form>
    </x-portal-modal>
    @elseif($role === 'officer' && $record['has_student_roster'] && $record['student_roster_acknowledged'])
    <x-portal-modal id="reopen-roster-dialog" title="เปิดให้แก้ไขรายชื่อ">
        <p class="confirmation-message">ต้องการเปิดให้ผู้ยื่นคำร้องอัปโหลดรายชื่อเวอร์ชันใหม่ใช่หรือไม่?<br>ระบบจะแจ้งผู้ยื่นคำร้องให้ทราบ</p>
        <form action="{{ route('officer.student-roster.reopen', $record['id']) }}" method="post">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">ยืนยันเปิดให้แก้ไข</x-portal-button></div></form>
    </x-portal-modal>
    @endif
    @if($isUser && in_array($record['status'], ['RETURNED_FOR_REVISION', 'PENDING_SIGNED_DOCUMENT']))
    <x-portal-modal id="edit-request-dialog" title="ยืนยันการแก้ไขคำร้อง">
        <p class="confirmation-message">คุณต้องการเปิดคำร้องนี้เพื่อแก้ไขข้อมูลใช่หรือไม่?</p>
        <form action="{{ route('requests.edit', $record['id']) }}" method="post">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">ยืนยันแก้ไขคำร้อง</x-portal-button></div>
        </form>
    </x-portal-modal>
    @endif
    @if($canReview)
    <x-portal-modal id="review-dialog" title="ยืนยันการส่งต่อเอกสาร">
        <form action="{{ route('officer.review', $record['id']) }}" method="post" enctype="multipart/form-data">@csrf<input type="hidden" name="decision" value="pass">
            <div data-signature-entry>
                <p class="confirmation-message confirmation-message--left">วาดลายเซ็นด้านล่าง หรืออัปโหลดลายเซ็น <span class="signature-file-hint__mobile-line">(ไฟล์ภาพพื้นหลังใส .png)</span></p>
                <div class="signature-pad" data-signature-pad>
                    <div class="signature-pad__heading">
                        <span>วาดลายเซ็น</span>
                        <button type="button" class="signature-pad__clear" data-signature-clear disabled>ล้างลายเซ็น</button>
                    </div>
                    <canvas class="signature-pad__canvas" width="1200" height="300" data-signature-canvas aria-label="พื้นที่วาดลายเซ็น ใช้เมาส์หรือปลายนิ้วในการวาด"></canvas>
                    <p class="signature-pad__hint" data-signature-status aria-live="polite">ใช้เมาส์หรือปลายนิ้ววาดภายในกรอบ</p>
                </div>
                <div class="signature-divider"><span>หรืออัปโหลดไฟล์</span></div>
                <x-portal-upload name="signature_file" id="officer-signature" accept=".png,.jpeg,.jpg" :compact="true" compact-label="อัปโหลดลายเซ็น" :required="true" />
                <div class="modal-actions"><x-portal-button variant="secondary" data-signature-cancel>ยกเลิก</x-portal-button><x-portal-button data-signature-send>ยืนยันส่งต่อ</x-portal-button></div>
            </div>
            <div data-signature-confirm hidden>
                <p class="confirmation-message" data-signature-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button variant="plain" data-signature-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button data-signature-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    <x-portal-modal id="return-dialog" title="ยืนยันส่งกลับแก้ไข">
        <form action="{{ route('officer.review', $record['id']) }}" method="post" id="return-form">@csrf<input type="hidden" name="decision" value="return">
            <div data-return-entry>
                <label class="form-label" for="return-reason">ระบุเหตุผลเพื่อส่งให้ผู้ยื่นคำร้อง<textarea id="return-reason" name="reason" class="form-field form-field--textarea" required maxlength="3000">{{ old('reason') }}</textarea></label>
                <div class="modal-actions"><x-portal-button type="button" variant="secondary" data-return-cancel>ยกเลิก</x-portal-button><x-portal-button type="button" variant="danger" data-return-send>ยืนยันส่งกลับแก้ไข</x-portal-button></div>
            </div>
            <div data-return-confirm hidden>
                <p class="confirmation-message" data-return-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button type="button" variant="plain" data-return-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button type="button" data-return-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    @endif
    @if($canApprove)
    <x-portal-modal id="approve-dialog" title="ยืนยันการอนุมัติ">
        <form action="{{ route('approver.approve', $record['id']) }}" method="post" enctype="multipart/form-data" data-signature-context="approve">@csrf<input type="hidden" name="decision" value="approve">
            <div data-signature-entry>
                <p class="confirmation-message confirmation-message--left">วาดลายเซ็นด้านล่าง หรืออัปโหลดลายเซ็น <span class="signature-file-hint__mobile-line">(ไฟล์ภาพพื้นหลังใส .png)</span></p>
                <div class="signature-pad" data-signature-pad>
                    <div class="signature-pad__heading">
                        <span>วาดลายเซ็น</span>
                        <button type="button" class="signature-pad__clear" data-signature-clear disabled>ล้างลายเซ็น</button>
                    </div>
                    <canvas class="signature-pad__canvas" width="1200" height="300" data-signature-canvas aria-label="พื้นที่วาดลายเซ็น ใช้เมาส์หรือปลายนิ้วในการวาด"></canvas>
                    <p class="signature-pad__hint" data-signature-status aria-live="polite">ใช้เมาส์หรือปลายนิ้ววาดภายในกรอบ</p>
                </div>
                <div class="signature-divider"><span>หรืออัปโหลดไฟล์</span></div>
                <x-portal-upload name="signature_file" id="approver-signature" accept=".png,.jpeg,.jpg" :compact="true" compact-label="อัปโหลดลายเซ็น" :required="true" />
                <div class="modal-actions"><x-portal-button variant="secondary" data-signature-cancel>ยกเลิก</x-portal-button><x-portal-button data-signature-send>ยืนยันอนุมัติ</x-portal-button></div>
            </div>
            <div data-signature-confirm hidden>
                <p class="confirmation-message" data-signature-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button variant="plain" data-signature-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button data-signature-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    <x-portal-modal id="reject-dialog" title="ยืนยันไม่อนุมัติการสร้างรายวิชา">
        <form action="{{ route('approver.approve', $record['id']) }}" method="post" enctype="multipart/form-data" data-signature-context="reject">@csrf<input type="hidden" name="decision" value="reject">
            <div data-signature-entry>
                <label class="form-label" for="reject-reason">เหตุผลที่ไม่อนุมัติ (ส่งให้ผู้ยื่นคำร้อง)<textarea id="reject-reason" name="reason" class="form-field form-field--textarea" required maxlength="3000">{{ old('reason') }}</textarea></label>
                <p class="confirmation-message confirmation-message--left">วาดลายเซ็นด้านล่าง หรืออัปโหลดลายเซ็น <span class="signature-file-hint__mobile-line">(ไฟล์ภาพพื้นหลังใส .png)</span></p>
                <div class="signature-pad" data-signature-pad>
                    <div class="signature-pad__heading">
                        <span>วาดลายเซ็น</span>
                        <button type="button" class="signature-pad__clear" data-signature-clear disabled>ล้างลายเซ็น</button>
                    </div>
                    <canvas class="signature-pad__canvas" width="1200" height="300" data-signature-canvas aria-label="พื้นที่วาดลายเซ็น ใช้เมาส์หรือปลายนิ้วในการวาด"></canvas>
                    <p class="signature-pad__hint" data-signature-status aria-live="polite">ใช้เมาส์หรือปลายนิ้ววาดภายในกรอบ</p>
                </div>
                <div class="signature-divider"><span>หรืออัปโหลดไฟล์</span></div>
                <x-portal-upload name="signature_file" id="approver-reject-signature" accept=".png,.jpeg,.jpg" :compact="true" compact-label="อัปโหลดลายเซ็น" :required="true" />
                <div class="modal-actions"><x-portal-button variant="secondary" data-signature-cancel>ยกเลิก</x-portal-button><x-portal-button variant="danger" data-signature-send>ยืนยันไม่อนุมัติ</x-portal-button></div>
            </div>
            <div data-signature-confirm hidden>
                <p class="confirmation-message" data-signature-confirm-message></p>
                <div class="modal-actions">
                    <x-portal-button variant="plain" data-signature-confirm-back>ย้อนกลับ</x-portal-button>
                    <x-portal-button variant="danger" data-signature-confirm-action></x-portal-button>
                </div>
            </div>
        </form>
    </x-portal-modal>
    @endif
</x-portal-layout>
