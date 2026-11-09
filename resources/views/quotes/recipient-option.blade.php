@php
    $reason = $reasons[$key];
    $disabled = ($emailMode && $reason) || ($entryMode && ($company->status !== 'active' || $target->status !== 'active'));
@endphp
<label class="quote-recipient-option" data-recipient-option="{{ $target->name }} {{ $target->email }}">
    <input type="checkbox" name="recipient_keys[]" value="{{ $key }}" @disabled($disabled)
        @checked(!$disabled && in_array($key, $selectedRecipients, true))
        aria-label="{{ $direct ? 'Invio diretto a '.$company->name : 'Tramite '.$target->name.' per '.$company->name }}" aria-describedby="contact-{{ $key }}">
    <span><span>{{ $direct ? ($compactMode ? $company->name : 'Invio diretto alla compagnia') : $target->name }}</span><small id="contact-{{ $key }}">{{ $reason ?: $target->email }}</small></span>
</label>
