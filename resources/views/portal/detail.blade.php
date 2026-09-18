@php
    $isUser = $role === 'user';
    $home = $isUser ? 'requests.index' : ($role === 'officer' ? 'officer.reviews' : 'approver.reviews');
    $title = $isUser ? 'รายละเอียดเอกสาร' : ($role === 'officer' ? 'ตรวจสอบรายละเอียดคำขอและเอกสาร' : 'ตรวจสอบรายละเอียดคำขออนุมัติสร้างรายวิชา');
    $canReview = $role === 'officer' && $record['status'] === 'UNDER_OFFICER_REVIEW';
    $canApprove = $role === 'approver' && $record['status'] === 'PENDING_APPROVAL';
@endphp
<x-portal-layout :title="$title" :role="$role">
    <main class="portal-main detail-page">
        <div class="detail-heading"><div><h2>{{ $title }}</h2><p>เลขที่เอกสาร: {{ $record['number'] }}</p></div><x-status-badge :status="$record['status']" /></div>
        @if($record['reason'] ?? null)<section class="reason-banner"><h3>{{ $record['status'] === 'RETURNED_FOR_REVISION' ? 'เหตุผลที่ส่งกลับแก้ไข' : 'เหตุผลที่ไม่อนุมัติ' }}</h3><p>{{ $record['reason'] }}</p></section>@endif
        <section class="portal-card detail-card"><x-request-data :record="$record" /></section>
        <section class="portal-card attachment-card"><h3>เอกสารแนบเพื่อตรวจสอบ</h3>
            <div class="document-row"><x-portal-icon name="file-code" /><span class="document-row__name">{{ $record['signed_name'] ?? 'โครงการขออนุมัติ AI course.pdf' }}</span>
                @if(isset($record['signed_path']))<a href="{{ route('requests.attachment', [$record['id'], 'signed']) }}">ดาวน์โหลด</a>@else<a href="{{ route('requests.document', $record['id']) }}" target="_blank">ดูเอกสาร</a>@endif
            </div>
            @foreach($record['additional_files'] ?? [] as $file)<div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $file['additional_name'] }}</span><a href="{{ route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']]) }}">ดาวน์โหลด</a></div>@endforeach
        </section>
        @if($record['course_id'] ?? null)<section class="portal-card course-result"><h3>ผลการสร้างรายวิชา</h3><p>Course ID: <strong>{{ $record['course_id'] }}</strong></p></section>@endif
        @if($canReview || $canApprove)
            <section class="portal-card review-card"><h3>{{ $canReview ? 'ผลการตรวจสอบ' : 'ผลการพิจารณา' }}</h3>
                @if($canReview)
                    <fieldset class="radio-fieldset"><legend>สถานะผลการตรวจสอบ</legend><div class="radio-options"><label><input type="radio" name="review_choice" value="pass" checked>เอกสารถูกต้องครบถ้วน</label><label><input type="radio" name="review_choice" value="return">เอกสารไม่ถูกต้อง / ส่งกลับแก้ไข</label></div></fieldset>
                    <x-portal-button data-review-submit>ส่งต่อผู้มีอำนาจพิจารณา</x-portal-button>
                @else
                    <p class="muted">สถานะผลการตรวจสอบ</p><p class="review-passed"><x-portal-icon name="check" />ข้อมูลถูกต้องครบถ้วน โดย: เจ้าหน้าที่ตรวจสอบ</p>
                    <x-portal-button data-open-dialog="approve-dialog">อนุมัติการสร้างรายวิชา</x-portal-button>
                    <x-portal-button variant="danger-outline" data-open-dialog="reject-dialog">ไม่อนุมัติการสร้างรายวิชา</x-portal-button>
                @endif
            </section>
        @endif
        <div class="detail-actions"><x-portal-button :href="route($home)" variant="secondary">ย้อนกลับ</x-portal-button>
            @if($isUser && in_array($record['status'], ['RETURNED_FOR_REVISION', 'DRAFT', 'PENDING_SIGNED_DOCUMENT']))<form action="{{ route('requests.edit', $record['id']) }}" method="post">@csrf<x-portal-button type="submit">แก้ไขคำร้อง</x-portal-button></form>@else<span class="muted">เลขที่คำร้อง: {{ $record['number'] }}</span>@endif
        </div>
    </main>
    @if($canReview)
        <x-portal-modal id="review-dialog" title="ยืนยันการส่งต่อเอกสาร"><p>ยืนยันส่งคำร้องให้ผู้มีอำนาจพิจารณาอนุมัติ?</p><form action="{{ route('officer.review', $record['id']) }}" method="post">@csrf<input type="hidden" name="decision" value="pass"><div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">ยืนยัน</x-portal-button></div></form></x-portal-modal>
        <x-portal-modal id="return-dialog" title="ข้อความเพิ่มเติม"><form action="{{ route('officer.review', $record['id']) }}" method="post">@csrf<input type="hidden" name="decision" value="return"><label class="form-label" for="return-reason">ระบุเหตุผลเพื่อส่งให้ผู้ยื่นคำร้อง<textarea id="return-reason" name="reason" class="form-field form-field--textarea" required maxlength="3000">{{ old('reason') }}</textarea></label><div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" variant="danger">ยืนยันส่งกลับแก้ไข</x-portal-button></div></form></x-portal-modal>
    @endif
    @if($canApprove)
        <x-portal-modal id="approve-dialog" title="ยืนยันการอนุมัติ"><p>คุณต้องการอนุมัติการสร้างรายวิชานี้หรือไม่?</p><form action="{{ route('approver.approve', $record['id']) }}" method="post">@csrf<input type="hidden" name="decision" value="approve"><div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit">ยืนยันอนุมัติ</x-portal-button></div></form></x-portal-modal>
        <x-portal-modal id="reject-dialog" title="ไม่อนุมัติการสร้างรายวิชา"><form action="{{ route('approver.approve', $record['id']) }}" method="post">@csrf<input type="hidden" name="decision" value="reject"><label class="form-label" for="reject-reason">เหตุผลที่ไม่อนุมัติ (ส่งให้ผู้ยื่นคำร้อง)<textarea id="reject-reason" name="reason" class="form-field form-field--textarea" required maxlength="3000">{{ old('reason') }}</textarea></label><div class="modal-actions"><x-portal-button variant="secondary" data-close-dialog>ยกเลิก</x-portal-button><x-portal-button type="submit" variant="danger">ยืนยันไม่อนุมัติ</x-portal-button></div></form></x-portal-modal>
    @endif
</x-portal-layout>
