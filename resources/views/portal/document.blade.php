<x-portal-layout title="เอกสารขออนุมัติสร้างรายวิชา">
    <nav class="print-toolbar">
        <x-portal-button :href="$downloadUrl"><x-portal-icon name="download" />ดาวน์โหลด PDF</x-portal-button>
        <x-portal-button :href="url()->previous()" variant="secondary">ย้อนกลับ</x-portal-button>
    </nav>

    @include('portal.partials.document-content', ['record' => $record])
</x-portal-layout>
