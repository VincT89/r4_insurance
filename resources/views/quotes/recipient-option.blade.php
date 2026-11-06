@php
    $reason = $reasons[$key];
    $disabled = $emailMode && $reason;
@endphp
<label class="quote-recipient-option" data-recipient-option="{{ $target->name }} {{ $target->email }}">
    <input type="checkbox" name="recipient_keys[]" value="{{ $key }}" @disabled($disabled)
        @checked(!$disabled && in_array($key, $selectedRecipients, true))
        aria-label="{{ $direct ? 'Invio diretto a '.$company->name : 'Tramite '.$target->name.' per '.$company->name }}" aria-describedby="contact-{{ $key }}">
    <span>{{ $direct ? 'Invio diretto alla compagnia' : $target->name }}<small id="contact-{{ $key }}">{{ $reason ?: $target->email }}</small></span>
</label>
