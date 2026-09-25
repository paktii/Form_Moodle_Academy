<x-portal-layout title="รายละเอียดเอกสาร">
    <main class="portal-main requester-detail">
        <div class="detail-actions requester-detail__navigation"><x-portal-button :href="route('requests.index')" variant="secondary">ย้อนกลับ</x-portal-button><span class="muted">เลขที่คำร้อง: {{ $record['number'] }}</span></div>
        @if($record['reason'] ?? null)<section class="reason-banner">
            <div>
                <h2>{{ $record['status'] === 'RETURNED_FOR_REVISION' ? 'ส่งกลับแก้ไข' : 'ไม่ผ่านการอนุมัติ' }}: {{ $record['reason'] }}</h2>
            </div>
            @if($record['status'] === 'RETURNED_FOR_REVISION')<x-portal-button data-open-dialog="edit-request-dialog" variant="danger-outline">แก้ไขคำร้อง</x-portal-button>@endif
        </section>@endif
        <x-request-progress :status="$record['status']" :returned-by="$record['returned_by'] ?? null" />
        <article class="portal-card requester-detail__card">
            <div class="requester-detail__heading" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 28px; font-weight: 700; color: #000; margin: 0; line-height: 1.4;">{{ $record['project_name'] }}</h2>
                <div style="flex-shrink: 0; margin-top: 4px;"><x-status-badge :status="$record['status']" /></div>
            </div>
            <x-request-data :record="$record" variant="summary" />
            <section class="requester-attachments">
                <div class="requester-attachments__heading">
                    <h3><x-portal-icon name="paperclip" />เอกสารแนบประกอบคำร้อง</h3><span class="muted">ทั้งหมด {{ 1 + count($record['additional_files'] ?? []) + ($record['has_student_roster'] ? 1 : 0) }} ไฟล์</span>
                </div>
                <div class="document-row"><x-portal-icon name="file-code" /><span class="document-row__name">{{ $record['signed_name'] ?? $record['project_name'].'.pdf' }}</span>
                    <x-portal-button :href="isset($record['signed_path']) ? route('requests.attachment', [$record['id'], 'signed']) : route('requests.document.download', $record['id'])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์ '.($record['signed_name'] ?? $record['project_name'].'.pdf')"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>
                </div>
                @foreach($record['additional_files'] ?? [] as $file)<div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $file['additional_name'] }}</span><x-portal-button :href="route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์ '.$file['additional_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button></div>@endforeach
            </section>
            @if($record['status'] === 'PENDING_SIGNED_DOCUMENT')
            <section class="pending-signed-document" id="signed-document-upload">
                <div class="pending-signed-document__heading">
                    <div><h3><x-portal-icon name="file-check" />เอกสารที่ลงนามแล้ว</h3><p>เลือกไฟล์ PDF ที่ลงนามแล้วเพื่อส่งให้เจ้าหน้าที่ตรวจสอบ</p></div>
                    <span class="roster-status roster-status--missing">ยังไม่อัปโหลด</span>
                </div>
                <form class="pending-signed-document__form" id="upload-form-{{ $record['id'] }}" method="post" action="{{ route('requests.upload', $record['id']) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="redirect_to" value="detail">
                    <div id="upload-step1-{{ $record['id'] }}">
                        <x-portal-upload name="signed_document" :id="'signed-'.$record['id']" :compact="true" compact-label="อัปโหลดเอกสารที่ลงนามแล้ว" :required="true" />
                        <x-portal-button type="button" data-upload-next="{{ $record['id'] }}" :data-confirm-dialog="'signed-upload-confirm-'.$record['id']">อัปโหลดเอกสาร</x-portal-button>
                    </div>
                </form>
            </section>
            <x-portal-modal :id="'signed-upload-confirm-'.$record['id']" title="ยืนยันการส่งเอกสาร">
                <p class="upload-confirmation__message">ระบบจะส่งคำร้องให้เจ้าหน้าที่ตรวจสอบ<br>คุณต้องการยืนยันการส่งใช่หรือไม่?</p>
                <div class="modal-actions upload-confirmation__actions">
                    <x-portal-button type="button" variant="plain" data-close-dialog>ย้อนกลับ</x-portal-button>
                    <x-portal-button type="submit" :form="'upload-form-'.$record['id']">ยืนยันการส่ง</x-portal-button>
                </div>
            </x-portal-modal>
            @endif
            @if($record['requires_student_roster'])
            <section class="student-roster" id="student-roster">
                <div class="student-roster__heading">
                    <div><h3><x-portal-icon name="user-plus" />รายชื่อผู้เรียน</h3><p>@if($record['student_roster_acknowledged'])เจ้าหน้าที่รับทราบรายชื่อแล้ว หากต้องการแก้ไขกรุณาติดต่อเจ้าหน้าที่เพื่อเปิดสิทธิ์@elseif($record['has_student_roster'])แนบรายชื่อแล้วและรอเจ้าหน้าที่รับทราบ สามารถอัปโหลดไฟล์ใหม่ได้ในระหว่างนี้@elseยังไม่แนบรายชื่อผู้เรียน กรุณาอัปโหลดเมื่อมีรายชื่อพร้อมแล้ว@endif</p></div>
                    <span class="roster-status {{ $record['student_roster_acknowledged'] ? 'roster-status--complete' : ($record['has_student_roster'] ? 'roster-status--pending' : 'roster-status--missing') }}">{{ $record['student_roster_acknowledged'] ? 'รับทราบแล้ว' : ($record['has_student_roster'] ? 'รอรับทราบ' : 'ยังไม่แนบ') }}</span>
                </div>
                @if($record['has_student_roster'])
                <div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $record['student_roster_name'] }}<small class="roster-file-meta">เวอร์ชัน {{ $record['student_roster_version_count'] }} • อัปโหลด {{ $record['student_roster_uploaded_at'] }}</small></span><x-portal-button :href="route('requests.attachment', [$record['id'], 'roster'])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์รายชื่อผู้เรียน '.$record['student_roster_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button></div>
                @endif
                @unless($record['student_roster_acknowledged'])
                <form class="student-roster__form" id="student-roster-form-{{ $record['id'] }}" method="post" action="{{ route('requests.student-roster.upload', $record['id']) }}" enctype="multipart/form-data">
                    @csrf
                    <x-portal-upload name="student_roster" :id="'student-roster-file-'.$record['id']" accept=".xls,.xlsx,.csv" :compact="true" :compact-label="$record['has_student_roster'] ? 'เลือกไฟล์รายชื่อใหม่' : 'อัปโหลดรายชื่อผู้เรียน'" :required="true" />
                    <x-portal-button type="button" data-roster-confirm="{{ $record['id'] }}" :data-confirm-dialog="'student-roster-confirm-'.$record['id']">{{ $record['has_student_roster'] ? 'บันทึกไฟล์ใหม่' : 'บันทึกรายชื่อ' }}</x-portal-button>
                </form>
                @endunless
            </section>
            @unless($record['student_roster_acknowledged'])
            <x-portal-modal :id="'student-roster-confirm-'.$record['id']" title="ยืนยันการบันทึกรายชื่อ">
                <p class="upload-confirmation__message">ระบบจะ{{ $record['has_student_roster'] ? 'แทนที่ไฟล์รายชื่อผู้เรียนเดิม' : 'บันทึกไฟล์รายชื่อผู้เรียน' }}<br>คุณต้องการยืนยันการบันทึกใช่หรือไม่?</p>
                <div class="modal-actions upload-confirmation__actions">
                    <x-portal-button type="button" variant="plain" data-close-dialog>ย้อนกลับ</x-portal-button>
                    <x-portal-button type="submit" :form="'student-roster-form-'.$record['id']">ยืนยันการบันทึก</x-portal-button>
                </div>
            </x-portal-modal>
            @endunless
            @endif
            @if($record['course_id'] ?? null)<section class="course-result">
                <h3>ผลการสร้างรายวิชา</h3>
                <p>Course ID: <a class="course-link" href="{{ rtrim(config('course-workflow.moodle_url'), '/').'/course/view.php?name='.rawurlencode($record['course_id']) }}" target="_blank" rel="noopener noreferrer" aria-label="เปิด Course ID {{ $record['course_id'] }} ใน SWU Moodle Academy (แท็บใหม่)">{{ $record['course_id'] }}</a></p>
            </section>@endif
        </article>
    </main>
    @if($record['status'] === 'RETURNED_FOR_REVISION')
    <x-portal-modal id="edit-request-dialog" title="ยืนยันการแก้ไขคำร้อง">
        <p class="confirmation-message">คุณต้องการเปิดคำร้องนี้เพื่อแก้ไขข้อมูลใช่หรือไม่?</p>
        <form action="{{ route('requests.edit', $record['id']) }}" method="post">@csrf<div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">ยืนยันแก้ไขคำร้อง</x-portal-button></div>
        </form>
    </x-portal-modal>
    @endif
</x-portal-layout>
