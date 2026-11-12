$ErrorActionPreference = 'Stop'
$previewRoot = (Resolve-Path -LiteralPath $PSScriptRoot).Path.Replace('\','/')
$previewRun = 'run-' + [guid]::NewGuid().ToString()
$previewPath = $previewRoot + '/' + $previewRun
foreach ($suffix in @('','/storage/app/private','/storage/app/attachments','/storage/app/public','/storage/framework/cache/data','/storage/framework/sessions','/storage/framework/views','/storage/logs','/bootstrap')) { New-Item -ItemType Directory -Path ($previewPath + $suffix) -Force | Out-Null }
$env:INBOUND_EMAIL_QA_PATH = $previewPath
$env:APP_ENV = 'local'
$env:APP_DEBUG = 'false'
$env:APP_URL = 'http://127.0.0.1:8012'
$previewBytes = New-Object byte[] 32
$previewRandom = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$previewRandom.GetBytes($previewBytes)
$previewRandom.Dispose()
$env:APP_KEY = 'base64:' + [Convert]::ToBase64String($previewBytes)
# I percorsi delle cache sono relativi al progetto per compatibilità con Windows.
$previewCache = 'work/inbound-email-preview/' + $previewRun + '/bootstrap/'
$env:APP_CONFIG_CACHE = $previewCache + 'config.php'
$env:APP_ROUTES_CACHE = $previewCache + 'routes.php'
$env:APP_EVENTS_CACHE = $previewCache + 'events.php'
$env:APP_SERVICES_CACHE = $previewCache + 'services.php'
$env:APP_PACKAGES_CACHE = $previewCache + 'packages.php'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $previewPath + '/test.sqlite'
$env:DB_URL = ''
$env:MAIL_MAILER = 'array'
$env:CACHE_STORE = 'file'
$env:SESSION_DRIVER = 'file'
$env:SESSION_COOKIE = 'r4_inbound_email_preview'
$env:SESSION_SECURE_COOKIE = 'false'
$env:SESSION_DOMAIN = 'null'
$env:VIEW_COMPILED_PATH = $previewPath + '/storage/framework/views'
$env:QUEUE_CONNECTION = 'sync'
$env:LOG_CHANNEL = 'single'
$env:LOG_LEVEL = 'error'
$env:INBOUND_EMAIL_ENABLED = 'false'
$env:INBOUND_EMAIL_TOKEN_SHA256 = ''
$env:INBOUND_EMAIL_SOURCE = 'preview-mailbox'
$env:BCRYPT_ROUNDS = '4'
php work/inbound-email-preview/prepare.php
if ($LASTEXITCODE -ne 0 -or !(Test-Path -LiteralPath ($previewPath + '/ready'))) { throw 'Preparazione della copia di prova non completata.' }
php -S 127.0.0.1:8012 -t public work/inbound-email-preview/router.php
