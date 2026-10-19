@props(['label' => '', 'name' => '', 'required' => false, 'for' => null])
@php
    $fieldId = $for ?: ($name ? 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']', '.'], '-', $name)) : null);
@endphp
<div class="form-g" data-form-group @if($fieldId) data-field-id="{{ $fieldId }}" @endif>
    <label class="form-lbl" data-form-label @if($fieldId) id="{{ $fieldId }}-label" for="{{ $fieldId }}" @endif>
        {{ $label }}
        @if($required)
            <span aria-hidden="true"> *</span><span class="sr-only"> (obbligatorio)</span>
        @endif
    </label>
    {{ $slot }}
    @error($name)
        <div class="invalid-feedback" @if($fieldId) id="{{ $fieldId }}-error" @endif>{{ $message }}</div>
    @enderror
</div>
