@props(['step', 'steps', 'data' => []])
<aside class="stepper">
    <h2 class="stepper__title">ขั้นตอนการกรอกข้อมูล</h2>
    <ol class="stepper__list">
        @foreach($steps as $number => $label)
        <li class="stepper__item {{ $number < $step ? 'is-complete' : ($number === $step ? 'is-current' : '') }}" @if($number===$step) aria-current="step" @endif>
            <span class="stepper__number">@if($number
                < $step)<x-portal-icon name="check" />@else{{ $number }}@endif
            </span>
            <span class="stepper__text">{{ $label }}</span>
        </li>
        @endforeach
    </ol>
    <section class="stepper-upload">
        <h3><x-portal-icon name="paperclip" />เอกสารเพิ่มเติม</h3>
        <x-portal-multi-upload name="additional_documents" id="additional-documents" form="course-form" :files="$data['additional_files'] ?? []" :max-files="5" />
    </section>
</aside>
