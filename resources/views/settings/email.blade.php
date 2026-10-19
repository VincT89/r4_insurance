<x-app-layout title="Impostazioni email">
    <x-page-header><x-slot:title>Impostazioni email</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('agency-communications.index') }}">Comunicazioni</a></x-slot:actions></x-page-header>
    @include('profile.partials.settings-navigation')
    <x-panel padded>
        <p class="u-mb-md">Casella usata per i riepiloghi alle agenzie. {{ $ready ? 'Invio abilitato.' : 'Invio non ancora abilitato.' }}</p>
        <form method="POST" action="{{ route('email-settings.update') }}" class="form-stack" autocomplete="off">@csrf @method('PATCH')
            <fieldset class="form-section"><legend>Mittente</legend><div class="form-row">
                <x-form-group name="from_name" label="Nome mittente"><input id="field-from_name" class="form-in" name="from_name" value="{{ old('from_name',$settings['from_name']) }}" maxlength="150" required></x-form-group>
                <x-form-group name="from_address" label="Indirizzo email mittente"><input id="field-from_address" class="form-in" name="from_address" type="email" value="{{ old('from_address',$settings['from_address']) }}" maxlength="254" required></x-form-group>
            </div></fieldset>
            <fieldset class="form-section"><legend>Connessione alla casella</legend><div class="form-row">
                <x-form-group name="host" label="Server SMTP"><input id="field-host" class="form-in" name="host" value="{{ old('host',$settings['host']) }}" maxlength="253" required autocapitalize="none" spellcheck="false"></x-form-group>
                <x-form-group name="port" label="Porta"><input id="field-port" class="form-in" name="port" type="number" min="1" max="65535" value="{{ old('port',$settings['port']) }}" required></x-form-group>
                <x-form-group name="encryption" label="Protezione della connessione"><select id="field-encryption" class="form-sel" name="encryption"><option value="starttls" @selected(old('encryption',$settings['encryption'])==='starttls')>STARTTLS (normalmente porta 587)</option><option value="tls" @selected(old('encryption',$settings['encryption'])==='tls')>TLS (normalmente porta 465)</option></select></x-form-group>
                <x-form-group name="username" label="Nome utente SMTP"><input id="field-username" class="form-in" name="username" value="{{ old('username',$settings['username']) }}" maxlength="255" required autocapitalize="none" spellcheck="false"></x-form-group>
                <x-form-group name="smtp_password" label="Password della casella"><input id="field-smtp_password" class="form-in" name="smtp_password" type="password" maxlength="1000" autocomplete="new-password" @required(!$settings['password_saved'])><p class="form-help">{{ $settings['password_saved'] ? 'Password salvata. Lascia vuoto per conservarla.' : 'Inserisci la password applicativa se richiesta dal servizio di posta.' }}</p></x-form-group>
            </div></fieldset>
            <input type="hidden" name="enabled" value="0"><label class="email-review-confirm"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$settings['enabled']))> Abilita l’invio dopo il controllo delle polizze</label>
            <p class="form-help">La password viene conservata cifrata. Salvare i parametri non invia email e non verifica l’accesso al server.</p>
            <div class="insurance-form-actions"><button class="btn btn-p" type="submit">Salva configurazione email</button></div>
        </form>
    </x-panel>
</x-app-layout>
