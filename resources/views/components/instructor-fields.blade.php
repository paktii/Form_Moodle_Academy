@props(['index' => 0, 'instructor' => []])
<div class="instructor-row" data-instructor>
    <div class="form-grid form-grid--three">
        <x-portal-field :name="'instructors['.$index.'][first]'" :label="'ชื่ออาจารย์ผู้สอนหลัก คนที่ '.($index + 1).' :'" :value="$instructor['first'] ?? ''" placeholder="ระบุชื่ออาจารย์ผู้สอนหลัก" :required="true" />
        <x-portal-field :name="'instructors['.$index.'][last]'" label="นามสกุลอาจารย์ผู้สอนหลัก:" :value="$instructor['last'] ?? ''" placeholder="ระบุนามสกุลอาจารย์ผู้สอนหลัก" :required="true" />
        <x-portal-field :name="'instructors['.$index.'][email]'" label="Email:" type="email" :value="$instructor['email'] ?? ''" placeholder="ระบุ Email อาจารย์ผู้สอนหลัก" :required="true" />
    </div>
    <button type="button" class="icon-button instructor-remove" data-remove-instructor aria-label="ลบอาจารย์ผู้สอนคนนี้" @if($index === 0) hidden @endif><x-portal-icon name="trash" /></button>
</div>
