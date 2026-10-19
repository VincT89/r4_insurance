@props(['title'])
<fieldset {{ $attributes->merge(['class'=>'form-section']) }}><legend>{{ $title }}</legend>{{ $slot }}</fieldset>
