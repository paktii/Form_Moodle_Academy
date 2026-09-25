@props(['index' => 0, 'instructor' => []])
<div class="instructor-row" data-instructor>
    <div class="instructor-row__heading">
        <h4 data-instructor-title>อาจารย์ผู้สอนคนที่ {{ $index + 1 }}</h4>
        <button type="button" class="icon-button instructor-remove" data-remove-instructor aria-label="ลบอาจารย์ผู้สอนคนนี้" @if($index === 0) hidden @endif><x-portal-icon name="trash" /></button>
    </div>
    <div class="form-grid form-grid--three">
        <x-portal-field :name="'instructors['.$index.'][first]'" label="ชื่ออาจารย์ผู้สอน:" :value="$instructor['first'] ?? ''" placeholder="ระบุชื่ออาจารย์ผู้สอน" :required="true" />
        <x-portal-field :name="'instructors['.$index.'][last]'" label="นามสกุลอาจารย์ผู้สอน:" :value="$instructor['last'] ?? ''" placeholder="ระบุนามสกุลอาจารย์ผู้สอน" :required="true" />
        <x-portal-field :name="'instructors['.$index.'][email]'" label="Email:" type="email" :value="$instructor['email'] ?? ''" placeholder="ระบุ Email อาจารย์ผู้สอน" :required="true" />
    </div>
</div>
