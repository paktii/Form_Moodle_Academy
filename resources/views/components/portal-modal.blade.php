@props(['id', 'title', 'autoOpen' => false])
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if($autoOpen) data-auto-open @endif {{ $attributes->class(['portal-modal']) }}>
    <div class="modal-heading">
        <h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="modal-close" data-close-dialog aria-label="ปิดหน้าต่าง">×</button>
    </div>
    <div class="modal-body">{{ $slot }}</div>
</dialog>