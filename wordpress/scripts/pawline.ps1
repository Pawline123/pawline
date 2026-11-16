<#
.SYNOPSIS
  Pawline – lokale Testumgebung unter Windows steuern (Docker Desktop erforderlich).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 start
  powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 setup
  powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 test
#>
param(
    [Parameter(Position = 0)]
    [ValidateSet('hilfe', 'pruefen', 'start', 'setup', 'demo', 'demo-entfernen', 'test', 'stop', 'sicherung', 'wiederherstellen', 'logs', 'paket', 'zuruecksetzen')]
    [string]$Befehl = 'hilfe',
    [Parameter(Position = 1)]
    [string]$Datei
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

function Write-Step($Text) { Write-Host "`n==> $Text" -ForegroundColor Green }
function Fail($Text) { Write-Host "FEHLER: $Text" -ForegroundColor Red; exit 1 }

function New-Secret([int]$Length = 24) {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'.ToCharArray()
    $bytes = New-Object byte[] $Length
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    -join ($bytes | ForEach-Object { $chars[$_ % $chars.Length] })
}

function Test-Docker {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        Fail 'Docker ist nicht installiert. Docker Desktop installieren: https://www.docker.com/products/docker-desktop/ (siehe docs/EINRICHTUNG-WINDOWS.md)'
    }
    docker info *> $null
    if ($LASTEXITCODE -ne 0) { Fail 'Docker Desktop läuft nicht. Bitte starten und warten, bis "Engine running" angezeigt wird.' }
    docker compose version *> $null
    if ($LASTEXITCODE -ne 0) { Fail 'Docker Compose fehlt (ist in aktuellem Docker Desktop enthalten).' }
}

function Initialize-Env {
    if (Test-Path .env) { return }
    Write-Step '.env mit Zufallspasswörtern anlegen'
    $content = Get-Content .env.example -Raw
    $content = $content -replace '(?m)^DB_PASSWORD=.*$', "DB_PASSWORD=$(New-Secret)"
    $content = $content -replace '(?m)^DB_ROOT_PASSWORD=.*$', "DB_ROOT_PASSWORD=$(New-Secret)"
    $content = $content -replace '(?m)^WP_ADMIN_PASSWORD=.*$', "WP_ADMIN_PASSWORD=$(New-Secret 20)"
    [System.IO.File]::WriteAllText((Join-Path $Root '.env'), $content, (New-Object System.Text.UTF8Encoding $false))
    Write-Host 'Admin-Passwort steht in der Datei .env (nur lokal, nicht weitergeben).'
}

function Get-EnvValue($Name) {
    $line = Get-Content .env | Where-Object { $_ -match "^$Name=" } | Select-Object -First 1
    if ($line) { return $line.Substring($Name.Length + 1) }
}

function Invoke-WpCli([string[]]$Arguments) {
    docker compose --profile tools run --rm wpcli @Arguments
    if ($LASTEXITCODE -ne 0) { Fail "Befehl fehlgeschlagen: $($Arguments -join ' ')" }
}

function Wait-WordPress {
    $port = Get-EnvValue 'WP_PORT'; if (-not $port) { $port = 8080 }
    Write-Host "Warte auf http://localhost:$port ..."
    for ($i = 0; $i -lt 60; $i++) {
        try { Invoke-WebRequest "http://localhost:$port/wp-login.php" -UseBasicParsing -TimeoutSec 3 *> $null; return } catch { Start-Sleep -Seconds 2 }
    }
    Write-Host 'WordPress antwortet noch nicht – weiter mit dem Setup.' -ForegroundColor Yellow
}

switch ($Befehl) {
    'hilfe' {
        Write-Host @'
Pawline – Befehle (Aufruf: powershell -ExecutionPolicy Bypass -File .\scripts\pawline.ps1 <befehl>)

  pruefen          Werkzeuge prüfen (Docker, Compose, Claude Code, Git)
  start            Container starten (WordPress, Datenbank, Mailpit)
  setup            WordPress, WooCommerce, Germanized, ATUM, Stripe, Theme und Plugin einrichten
  demo             Gekennzeichnete [DEMO]-Produkte anlegen
  demo-entfernen   Alle [DEMO]-Daten löschen
  test             Logiktests + Integrationsprüfung in WordPress
  sicherung        Datenbank + Uploads + Belege nach .\backups sichern
  wiederherstellen <datei.sql>  Datenbank aus Sicherung einspielen
  logs             Protokolle anzeigen
  paket            Theme und Plugin als ZIP für den Upload auf das Webhosting erstellen
  stop             Container anhalten (Daten bleiben erhalten)
  zuruecksetzen    ALLES löschen (Datenbank, Uploads, Belege) – nur Testumgebung
'@
    }
    'pruefen' {
        foreach ($tool in 'docker', 'git', 'claude') {
            $cmd = Get-Command $tool -ErrorAction SilentlyContinue
            if ($cmd) { Write-Host "ok     $tool ($($cmd.Source))" } else { Write-Host "fehlt  $tool" -ForegroundColor Yellow }
        }
        Test-Docker
        Write-Host 'ok     Docker läuft, Compose verfügbar'
    }
    'start' {
        Test-Docker; Initialize-Env
        Write-Step 'Container starten'
        docker compose up -d
        if ($LASTEXITCODE -ne 0) { Fail 'docker compose up fehlgeschlagen.' }
        Wait-WordPress
        $port = Get-EnvValue 'WP_PORT'; if (-not $port) { $port = 8080 }
        Write-Host "Shop: http://localhost:$port   Mails: http://localhost:$(Get-EnvValue 'MAILPIT_PORT')"
        Write-Host 'Beim ersten Mal danach:  .\scripts\pawline.ps1 setup'
    }
    'setup' {
        Test-Docker; Initialize-Env
        docker compose up -d
        Wait-WordPress
        Write-Step 'Rechte der Belegablage setzen'
        docker compose --profile tools run --rm --user root wpcli sh -c 'mkdir -p /var/pawline-private && chown -R 33:33 /var/pawline-private && chmod 750 /var/pawline-private'
        Write-Step 'WordPress einrichten'
        Invoke-WpCli @('sh', '/project/scripts/setup-wp.sh')
        Write-Host "`nAnmeldung: Benutzer $(Get-EnvValue 'WP_ADMIN_USER'), Passwort siehe .env (WP_ADMIN_PASSWORD)"
    }
    'demo' { Test-Docker; Invoke-WpCli @('sh', '/project/scripts/demo-data.sh') }
    'demo-entfernen' { Test-Docker; Invoke-WpCli @('sh', '/project/scripts/demo-remove.sh') }
    'test' {
        Test-Docker
        Write-Step 'Logiktests (ohne WordPress)'
        Invoke-WpCli @('php', '/project/tests/run.php')
        Write-Step 'Integrationsprüfung in WordPress'
        Invoke-WpCli @('wp', 'eval-file', '/project/tests/wp-smoke.php')
        if (Get-Command python -ErrorAction SilentlyContinue) {
            Write-Step 'Statische Block-Prüfung'
            python tests/check_blocks.py
        } else { Write-Host 'Python nicht installiert – statische Block-Prüfung übersprungen.' -ForegroundColor Yellow }
        Write-Host "`nManuelle Prüfungen: docs/TESTPLAN.md"
    }
    'sicherung' {
        Test-Docker
        $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
        New-Item -ItemType Directory -Force -Path backups | Out-Null
        Write-Step "Sicherung $stamp"
        Invoke-WpCli @('wp', 'db', 'export', "/project/backups/db-$stamp.sql")
        Invoke-WpCli @('tar', '-czf', "/project/backups/uploads-$stamp.tar.gz", '-C', '/var/www/html/wp-content', 'uploads')
        Invoke-WpCli @('tar', '-czf', "/project/backups/belege-$stamp.tar.gz", '-C', '/var', 'pawline-private')
        Write-Host "Gesichert nach .\backups (*-$stamp). Wiederherstellung erproben: .\scripts\pawline.ps1 wiederherstellen backups\db-$stamp.sql"
    }
    'wiederherstellen' {
        Test-Docker
        if (-not $Datei -or -not (Test-Path $Datei)) { Fail 'Pfad zur .sql-Datei angeben, z. B. backups\db-20261116-120000.sql' }
        $name = Split-Path $Datei -Leaf
        Write-Host "Datenbank wird durch $name ERSETZT. Fortfahren? (j/n)" -ForegroundColor Yellow
        if ((Read-Host) -ne 'j') { exit 0 }
        Invoke-WpCli @('wp', 'db', 'import', "/project/backups/$name")
        Write-Host 'Datenbank wiederhergestellt. Uploads/Belege bei Bedarf aus den .tar.gz-Dateien zurückspielen (siehe README).'
    }
    'logs' { docker compose logs --tail=200 -f wordpress }
    'paket' {
        Write-Step 'ZIP-Pakete für das Webhosting'
        New-Item -ItemType Directory -Force -Path dist | Out-Null
        Compress-Archive -Path wp-content\themes\pawline -DestinationPath dist\pawline-theme.zip -Force
        Compress-Archive -Path wp-content\plugins\pawline-dropship -DestinationPath dist\pawline-dropship.zip -Force
        Write-Host 'Erstellt: dist\pawline-theme.zip, dist\pawline-dropship.zip (Upload über Design > Themes bzw. Plugins > Installieren)'
    }
    'stop' { docker compose stop }
    'zuruecksetzen' {
        Write-Host 'Löscht ALLE lokalen Testdaten (Datenbank, Uploads, Belege). Eingeben: LOESCHEN' -ForegroundColor Yellow
        if ((Read-Host) -ne 'LOESCHEN') { exit 0 }
        docker compose --profile tools down -v
    }
}
