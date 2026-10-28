[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $PhpPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Split-Path -Parent $PSScriptRoot)).Path
$php = (Resolve-Path -LiteralPath $PhpPath).Path
$artisan = (Resolve-Path -LiteralPath (Join-Path $projectRoot 'artisan')).Path
if ([IO.Path]::GetFileName($php) -ne 'php.exe') {
    throw 'Indica il percorso di php.exe usato dal gestionale.'
}

# Il nome identifica questo checkout; altre installazioni restano indipendenti.
$sha = [Security.Cryptography.SHA256]::Create()
try {
    $hash = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($projectRoot.ToLowerInvariant()))).Replace('-', '').Substring(0, 12)
} finally {
    $sha.Dispose()
}
$taskName = 'R4Insurance-Notifiche-' + $hash
$legacyArguments = '"' + $artisan + '" insurance:deadline-reminders --no-interaction'
$powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$command = "& '" + $php.Replace("'", "''") + "' '" + $artisan.Replace("'", "''") + "' insurance:deadline-reminders --no-interaction; exit " + '$LASTEXITCODE'
$arguments = '-NoProfile -NonInteractive -WindowStyle Hidden -Command "' + $command + '"'
$account = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
$sameAction = $existing -and $existing.Actions.Count -eq 1 -and $existing.Actions[0].Execute -eq $powershell -and $existing.Actions[0].Arguments -eq $arguments
$legacyAction = $existing -and $existing.Actions.Count -eq 1 -and $existing.Actions[0].Execute -eq $php -and $existing.Actions[0].Arguments -eq $legacyArguments
if ($existing -and !$sameAction -and !$legacyAction) {
    throw 'Esiste un''attivita con questo nome e un comando diverso. Nessuna modifica eseguita.'
}

# Non vengono salvate password e non vengono richiesti privilegi amministrativi.
$action = New-ScheduledTaskAction -Execute $powershell -Argument $arguments -WorkingDirectory $projectRoot
$principal = New-ScheduledTaskPrincipal -UserId $account -LogonType Interactive -RunLevel Limited
$triggers = @(
    (New-ScheduledTaskTrigger -AtLogOn -User $account),
    (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5))
)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 15) -RestartCount 2 -RestartInterval (New-TimeSpan -Minutes 1) -Hidden
$description = 'Promemoria interni R4 Insurance ogni cinque minuti e all''accesso Windows. Non invia email. Richiede PC acceso, utente connesso e database disponibile.'
Register-ScheduledTask -TaskName $taskName -Action $action -Principal $principal -Trigger $triggers -Settings $settings -Description $description -Force | Out-Null
Get-ScheduledTaskInfo -TaskName $taskName | Select-Object TaskName, LastRunTime, NextRunTime, LastTaskResult
