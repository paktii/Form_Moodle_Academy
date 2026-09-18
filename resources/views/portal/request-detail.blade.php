<x-portal-layout title="รายละเอียดเอกสาร">
    <main class="portal-main requester-detail">
        <div class="detail-actions requester-detail__navigation"><x-portal-button :href="route('requests.index')" variant="secondary">ย้อนกลับ</x-portal-button><span class="muted">เลขที่คำร้อง: {{ $record['number'] }}</span></div>
        @if($record['reason'] ?? null)<section class="reason-banner">
            <h2>{{ $record['status'] === 'RETURNED_FOR_REVISION' ? 'ส่งกลับแก้ไข' : 'ไม่ผ่านการอนุมัติ' }}: {{ $record['reason'] }}</h2>
        </section>@endif
        <x-request-progress :status="$record['status']" />
        <article class="portal-card requester-detail__card">
            <div class="requester-detail__heading">
                <h2>{{ $record['project_name'] }}</h2><x-status-badge :status="$record['status']" />
            </div>
            <x-request-data :record="$record" variant="summary" />
            <section class="requester-attachments">
                <div class="requester-attachments__heading">
                    <h3><x-portal-icon name="paperclip" />เอกสารแนบประกอบคำร้อง</h3><span class="muted">ทั้งหมด {{ 1 + count($record['additional_files'] ?? []) }} ไฟล์</span>
                </div>
                <div class="document-row"><x-portal-icon name="file-code" /><span class="document-row__name">{{ $record['signed_name'] ?? 'โครงการขออนุมัติ.pdf' }}</span>
                    <x-portal-button :href="isset($record['signed_path']) ? route('requests.attachment', [$record['id'], 'signed']) : route('requests.document.download', $record['id'])" variant="secondary"><x-portal-icon name="download" />ดาวน์โหลด</x-portal-button>
                </div>
                @foreach($record['additional_files'] ?? [] as $file)<div class="document-row"><x-portal-icon name="file-text" /><span class="document-row__name">{{ $file['additional_name'] }}</span><x-portal-button :href="route('requests.attachment', ['id' => $record['id'], 'kind' => 'additional', 'document' => $file['document_id']])" variant="secondary"><x-portal-icon name="download" />ดาวน์โหลด</x-portal-button></div>@endforeach
            </section>
            @if($record['course_id'] ?? null)<section class="course-result">
                <h3>ผลการสร้างรายวิชา</h3>
                <p>Course ID: <strong>{{ $record['course_id'] }}</strong></p>
            </section>@endif
            @if(in_array($record['status'], ['RETURNED_FOR_REVISION', 'DRAFT', 'PENDING_SIGNED_DOCUMENT']))<form class="requester-edit" action="{{ route('requests.edit', $record['id']) }}" method="post">@csrf<x-portal-button type="submit">แก้ไขคำร้อง</x-portal-button></form>@endif
        </article>
    </main>
</x-portal-layout>
