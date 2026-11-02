<x-portal-layout title="รายละเอียดเอกสาร" tab-title="Request Details" description="ตรวจสอบรายละเอียดและติดตามขั้นตอนการดำเนินการของคำร้องขอรับบริการ">
    <main class="portal-main requester-detail">
        <div class="detail-actions requester-detail__navigation"><x-portal-button :href="route('requests.index')" variant="secondary">ย้อนกลับ</x-portal-button><span class="muted">เลขที่คำร้อง: {{ $record['number'] }}</span></div>
        @if($record['reason'] ?? null)
        @php
            $reasonTitle = match (true) {
                ($record['decision_source'] ?? null) === 'DEAN'
                    || (($record['decision_source'] ?? null) === null && ($record['dean_decision'] ?? null) === 'REJECTED')
                    => 'ไม่ผ่านการอนุมัติโดยคณบดี',
                default => 'ไม่ผ่านการอนุมัติโดยผู้มีอำนาจพิจารณา',
            };
        @endphp
        <section class="reason-banner">
            <div>
                <h2><strong class="reason-banner__title">{{ $reasonTitle }}:</strong> {{ $record['reason'] }}</h2>
            </div>
        </section>@endif
        <x-request-progress :status="$record['status']" :decision-source="$record['decision_source'] ?? null" />
        <article class="portal-card requester-detail__card">
            <div class="requester-detail__heading" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid #e5e7eb;">
                <h2 style="font-size: 28px; font-weight: 700; color: #000; margin: 0; line-height: 1.4;">{{ $record['project_name'] }}</h2>
                <div style="flex-shrink: 0; margin-top: 4px;"><x-status-badge :status="$record['status']" /></div>
            </div>
            <x-request-data :record="$record" variant="summary" :show-requester-unit="false" :split-target-unit="true" />
            <section class="requester-attachments">
                <div class="requester-attachments__heading">
                    <h3><x-portal-icon name="paperclip" />เอกสารแนบประกอบคำร้อง</h3><span class="muted">ทั้งหมด {{ 1 + count($record['additional_files'] ?? []) + ($record['has_student_roster'] ? 1 : 0) }} ไฟล์</span>
                </div>
                <div class="document-row"><x-portal-icon name="file-code" /><span class="document-row__name">{{ $record['document_name'] }}</span>
                    <x-portal-button :href="isset($record['signed_path']) ? route('requests.attachment', [$record['id'], 'signed']) : route(match ($record['form_type_code'] ?? 'CC-ITED-06') { 'CC-ITED-01' => 'website-requests.document.download', 'CC-ITED-02' => 'website-contact-change-requests.document.download', 'CC-ITED-04' => 'website-storage-requests.document.download', 'CC-ITED-05' => 'emeeting-requests.document.download', default => 'requests.document.download' }, $record['id'])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์ '.$record['document_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button>
                </div>
                @foreach($record['additional_files'] ?? [] as $file)<div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $file['additional_name'] }}</span><x-portal-button :href="route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์ '.$file['additional_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button></div>@endforeach
            </section>
            @if($record['requires_student_roster'])
            <section class="student-roster" id="student-roster">
                <div class="student-roster__heading">
                    <div><h3><x-portal-icon name="user-plus" />รายชื่อผู้เรียน</h3><p>@if($record['student_roster_reopen_requested'])ส่งคำขอแก้ไขรายชื่อแล้ว กรุณารอเจ้าหน้าที่เปิดสิทธิ์@elseif($record['student_roster_acknowledged'])เจ้าหน้าที่รับทราบรายชื่อแล้ว หากต้องการแก้ไขสามารถส่งคำขอไปยังเจ้าหน้าที่ได้@elseif($record['has_student_roster'])แนบรายชื่อแล้วและรอเจ้าหน้าที่รับทราบ สามารถอัปโหลดไฟล์ใหม่ได้ในระหว่างนี้@elseยังไม่แนบรายชื่อผู้เรียน กรุณาอัปโหลดเมื่อมีรายชื่อพร้อมแล้ว@endif</p></div>
                    <span class="roster-status {{ $record['student_roster_reopen_requested'] ? 'roster-status--pending' : ($record['student_roster_acknowledged'] ? 'roster-status--complete' : ($record['has_student_roster'] ? 'roster-status--pending' : 'roster-status--missing')) }}">{{ $record['student_roster_reopen_requested'] ? 'รอเปิดสิทธิ์' : ($record['student_roster_acknowledged'] ? 'รับทราบแล้ว' : ($record['has_student_roster'] ? 'รอรับทราบ' : 'ยังไม่แนบ')) }}</span>
                </div>
                @if($record['has_student_roster'])
                <div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $record['student_roster_name'] }}<small class="roster-file-meta">เวอร์ชัน {{ $record['student_roster_version_count'] }} • อัปโหลด {{ $record['student_roster_uploaded_at'] }}</small></span><x-portal-button :href="route('requests.attachment', [$record['id'], 'roster'])" variant="secondary" class="document-row__download" :aria-label="'ดาวน์โหลดไฟล์รายชื่อผู้เรียน '.$record['student_roster_name']"><x-portal-icon name="download" /><span class="document-row__download-label">ดาวน์โหลด</span></x-portal-button></div>
                @endif
                @unless($record['student_roster_acknowledged'])
                <form class="student-roster__form" id="student-roster-form-{{ $record['id'] }}" method="post" action="{{ route('requests.student-roster.upload', $record['id']) }}" enctype="multipart/form-data">
                    @csrf
                    <x-portal-upload name="student_roster" :id="'student-roster-file-'.$record['id']" accept=".xls,.xlsx,.csv" :compact="true" :compact-label="$record['has_student_roster'] ? 'เลือกไฟล์รายชื่อใหม่' : 'อัปโหลดรายชื่อผู้เรียน'" :required="true" />
                    <x-portal-button type="submit" data-roster-confirm="{{ $record['id'] }}" :data-confirm-dialog="'student-roster-confirm-'.$record['id']">{{ $record['has_student_roster'] ? 'บันทึกไฟล์ใหม่' : 'บันทึกรายชื่อ' }}</x-portal-button>
                </form>
                @elseif(($record['form_type_code'] ?? 'CC-ITED-06') === 'CC-ITED-02')
                <h3><x-portal-icon name="globe-code" />ผลการเปลี่ยนแปลงผู้ดูแลเว็บไซต์</h3>
                <p>@if($record['status'] === 'COMPLETED')ดำเนินการเปลี่ยนแปลงผู้ดูแลเว็บไซต์เรียบร้อยแล้ว@elseif($record['status'] === 'REJECTED')<span class="muted">ไม่ได้รับอนุมัติ</span>@else<span class="muted">รอเจ้าหน้าที่ดำเนินการ</span>@endif</p>
                @else
                <div class="student-roster__form">
                    <x-portal-button type="button" variant="secondary" data-open-dialog="student-roster-reopen-request" :disabled="$record['student_roster_reopen_requested']">{{ $record['student_roster_reopen_requested'] ? 'ส่งคำขอแก้ไขแล้ว' : 'ขอแก้ไขรายชื่อ' }}</x-portal-button>
                </div>
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
            @if($record['student_roster_acknowledged'] && ! $record['student_roster_reopen_requested'])
            <x-portal-modal id="student-roster-reopen-request" title="ยืนยันการขอแก้ไขรายชื่อ">
                <p class="upload-confirmation__message">ระบบจะส่งคำขอไปยังเจ้าหน้าที่เพื่อเปิดสิทธิ์แก้ไขรายชื่อ<br>คุณต้องการดำเนินการต่อใช่หรือไม่?</p>
                <form method="post" action="{{ route('requests.student-roster.reopen-request', $record['id']) }}">
                    @csrf
                    <div class="modal-actions upload-confirmation__actions">
                        <x-portal-button type="button" variant="plain" data-close-dialog>ยกเลิก</x-portal-button>
                        <x-portal-button type="submit">ยืนยันส่งคำขอ</x-portal-button>
                    </div>
                </form>
            </x-portal-modal>
            @endif
            @endif
            <section class="course-result">
                @if(($record['form_type_code'] ?? 'CC-ITED-06') === 'CC-ITED-01')
                <h3><x-portal-icon name="globe-code" />ผลการดำเนินการ</h3>
                <dl class="course-result__list"><div class="course-result__row"><dt>ข้อมูลการจัดสรรพื้นที่เว็บไซต์</dt><dd class="allocated-domain-text">@if($record['allocated_domain'] ?? null){{ $record['allocated_domain'] }}@elseif($record['status'] === 'REJECTED')<span class="muted">—</span>@else<span class="muted">รอเจ้าหน้าที่ดำเนินการ</span>@endif</dd></div></dl>
                @elseif(($record['form_type_code'] ?? 'CC-ITED-06') === 'CC-ITED-04')
                <h3><x-portal-icon name="globe-code" />ผลการดำเนินการ</h3>
                <dl class="course-result__list">
                    <div class="course-result__row"><dt>พื้นที่เว็บไซต์ที่เพิ่มให้</dt><dd>@if($record['granted_space_gb'] ?? null)1 GB@elseif($record['status'] === 'REJECTED')<span class="muted">—</span>@else<span class="muted">รอเจ้าหน้าที่ดำเนินการ</span>@endif</dd></div>
                    <div class="course-result__row"><dt>ข้อมูลการดำเนินการเพิ่มพื้นที่เว็บไซต์</dt><dd class="execution-detail-text">@if($record['execution_detail'] ?? null){{ $record['execution_detail'] }}@elseif($record['status'] === 'REJECTED')<span class="muted">—</span>@else<span class="muted">รอเจ้าหน้าที่ดำเนินการ</span>@endif</dd></div>
                </dl>
                @elseif(($record['form_type_code'] ?? 'CC-ITED-06') === 'CC-ITED-02')
                <h3><x-portal-icon name="globe-code" />ผลการดำเนินการ</h3>
                <dl class="course-result__list"><div class="course-result__row"><dt>ข้อมูลการดำเนินการเปลี่ยนผู้ดูแลเว็บไซต์</dt><dd class="execution-detail-text">@if($record['execution_detail'] ?? null){{ $record['execution_detail'] }}@elseif($record['status'] === 'REJECTED')<span class="muted">—</span>@else<span class="muted">รอเจ้าหน้าที่ดำเนินการ</span>@endif</dd></div></dl>
                @elseif(($record['form_type_code'] ?? 'CC-ITED-06') === 'CC-ITED-05')
                <h3><x-portal-icon name="globe-code" />ผลการดำเนินการ</h3>
                <dl class="course-result__list"><div class="course-result__row"><dt>ข้อมูลการเปลี่ยนสิทธิ์ผู้ดูแลกรอบการประชุม</dt><dd class="execution-detail-text">@if($record['execution_detail'] ?? null){{ $record['execution_detail'] }}@elseif($record['status'] === 'REJECTED')<span class="muted">—</span>@else<span class="muted">รอเจ้าหน้าที่ดำเนินการ</span>@endif</dd></div></dl>
                @else
                <h3><x-portal-icon name="globe-code" />ผลการสร้างรายวิชา</h3>
                <dl class="course-result__list"><div class="course-result__row"><dt>Course ID</dt><dd>@if($record['course_id'] ?? null)<a class="course-link" href="{{ rtrim(config('course-workflow.moodle_url'), '/').'/course/view.php?id='.rawurlencode($record['course_id']) }}" target="_blank" rel="noopener noreferrer" aria-label="เปิด Course ID {{ $record['course_id'] }} ใน SWU Moodle Academy (แท็บใหม่)">{{ $record['course_id'] }}</a>@elseif($record['status'] === 'REJECTED')<span class="muted">—</span>@else<span class="muted">รอเจ้าหน้าที่ดำเนินการ</span>@endif</dd></div></dl>
                @endif
            </section>
        </article>
    </main>
</x-portal-layout>
