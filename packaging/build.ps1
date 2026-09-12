param(
    [switch]$SkipRuntimeCopy
)
$ErrorActionPreference = "Stop"

# Nizam -- Windows release build. Assembles packaging\stage (app files +
# portable runtime) and compiles packaging\installer.iss into
# dist\Nizam-Setup-<version>.exe via Inno Setup. Source of the app files is
# the project root itself (already-tested, already-complete); source of the
# runtime binaries is this machine's own XAMPP install, since this app has
# only ever been built/tested against that exact PHP/Apache/MariaDB build --
# reusing it rather than downloading a different distribution avoids
# introducing any version drift package.ps1's own clean-room testing hasn't
# already covered.

$root = Split-Path -Parent $PSScriptRoot
$packaging = Join-Path $root "packaging"
$stage = Join-Path $packaging "stage"
$xampp = "C:\xampp"
$iscc = "$env:USERPROFILE\tools\InnoSetup\ISCC.exe"

Write-Host "=== Hadaba Al-Ahram School - Windows Build ===" -ForegroundColor Cyan

Write-Host "Cleaning previous app stage..."
if (Test-Path "$stage\app") { Remove-Item -Recurse -Force "$stage\app" }
if ((-not $SkipRuntimeCopy) -and (Test-Path "$stage\runtime")) { Remove-Item -Recurse -Force "$stage\runtime" }
New-Item -ItemType Directory -Path "$stage\app" -Force | Out-Null
New-Item -ItemType Directory -Path "$stage\runtime" -Force | Out-Null

# --- 1. Application files (reuses package.ps1's own proven exclusion list) ---
Write-Host "Staging application files..."
# NB: the internal engineering docs/ (blueprint, developer guide) is
# deliberately NOT included -- a school has no use for the architecture
# blueprint or developer-facing notes, and it's a chunk of Google-Fonts-
# referencing HTML the offline check below would otherwise (correctly)
# flag. The school-facing guides live under packaging\docs instead (see
# installer.iss's own [Files] entries), installed to {app}\docs.
$appIncludes = @("app", "database", "lang", "public", "storage", "vendor", "vendor-assets", "views", "composer.json", "composer.lock")
foreach ($item in $appIncludes) {
    Copy-Item -Path "$root\$item" -Destination "$stage\app\$item" -Recurse
}
New-Item -ItemType Directory -Path "$stage\app\config" -Force | Out-Null
Copy-Item -Path "$root\config\config.example.php" -Destination "$stage\app\config\config.example.php" -Force

# Same development-artifact cleanup package.ps1 already does, applied here too.
if (Test-Path "$stage\app\database\backups") {
    Get-ChildItem "$stage\app\database\backups\*.sql" -ErrorAction SilentlyContinue | Remove-Item
}
if (Test-Path "$stage\app\storage\logs") {
    Get-ChildItem "$stage\app\storage\logs\*.log" -ErrorAction SilentlyContinue | Remove-Item
}
if (Test-Path "$stage\app\storage\uploads") {
    Remove-Item -Recurse -Force "$stage\app\storage\uploads"
}
New-Item -ItemType Directory -Path "$stage\app\storage\uploads" -Force | Out-Null
Set-Content -Path "$stage\app\storage\uploads\.htaccess" -Value "php_flag engine off`nOptions -ExecCGI"
# config/config.php itself must never ship -- the packaged product writes its
# own into ProgramData via the Setup Wizard, exactly like every other install.
Remove-Item -Path "$stage\app\config\config.php" -ErrorAction SilentlyContinue

# --- 2. Portable runtime: Apache + PHP + MariaDB, the exact binaries this
#        app has actually been developed and tested against. ---
if (-not $SkipRuntimeCopy) {
    Write-Host "Staging Apache runtime..."
    Copy-Item -Path "$xampp\apache" -Destination "$stage\runtime\apache" -Recurse
    # Strip the parts of a general-purpose XAMPP Apache Nizam never uses.
    Remove-Item -Recurse -Force "$stage\runtime\apache\htdocs" -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force "$stage\runtime\apache\manual" -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force "$stage\runtime\apache\conf\extra" -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Path "$stage\runtime\apache\logs" -Force | Out-Null

    Write-Host "Staging PHP runtime..."
    Copy-Item -Path "$xampp\php" -Destination "$stage\runtime\php" -Recurse
    Remove-Item -Recurse -Force "$stage\runtime\php\PEAR" -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force "$stage\runtime\php\doc" -ErrorAction SilentlyContinue

    Write-Host "Staging MariaDB runtime..."
    Copy-Item -Path "$xampp\mysql\bin" -Destination "$stage\runtime\mysql\bin" -Recurse
    Copy-Item -Path "$xampp\mysql\share" -Destination "$stage\runtime\mysql\share" -Recurse
    # The clean system-tables template used to seed a FRESH install's data
    # directory -- never this machine's own live "data" folder, which holds
    # real (test) school data and must never ship.
    Copy-Item -Path "$xampp\mysql\backup" -Destination "$stage\runtime\mysql-seed" -Recurse
} else {
    Write-Host "Skipping runtime copy (using existing stage\runtime)." -ForegroundColor Yellow
}

# --- 3. Offline guarantee re-check on the actual staged app tree ---
Write-Host "Verifying PHP syntax across staged app..."
$phpFiles = Get-ChildItem -Path "$stage\app" -Filter "*.php" -Recurse | Where-Object { $_.FullName -notmatch "\\vendor\\" }
$lintErrors = 0
foreach ($file in $phpFiles) {
    & "$xampp\php\php.exe" -l $file.FullName *> $null
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Lint error: $($file.FullName)" -ForegroundColor Red
        $lintErrors++
    }
}
Write-Host "Linted $($phpFiles.Count) files, $lintErrors errors."
if ($lintErrors -gt 0) { throw "PHP lint failed -- aborting build." }

Write-Host "Checking for stray external URLs in the staged app..."
$externalHits = 0
$searchFiles = Get-ChildItem -Path "$stage\app" -Include *.php, *.html, *.css, *.js -Recurse | Where-Object { $_.FullName -notmatch "\\vendor\\" }
foreach ($file in $searchFiles) {
    $matches = Select-String -Path $file.FullName -Pattern "https?://[a-zA-Z0-9.-]+" -AllMatches
    foreach ($m in $matches) {
        foreach ($g in $m.Matches) {
            if ($g.Value -notin @("http://www.w3.org", "http://", "https://")) {
                Write-Host "External URL: $($file.FullName): $($g.Value)" -ForegroundColor Yellow
                $externalHits++
            }
        }
    }
}
Write-Host "External URL scan complete: $externalHits found in the shipped app tree."
if ($externalHits -gt 0) { throw "Offline guarantee violated -- aborting build." }

# --- 4. Compile the installer ---
if (-not (Test-Path $iscc)) {
    throw "Inno Setup compiler not found at $iscc"
}
New-Item -ItemType Directory -Path "$root\dist" -Force | Out-Null
Write-Host "Compiling installer..." -ForegroundColor Cyan
& $iscc "$packaging\installer.iss"
if ($LASTEXITCODE -ne 0) { throw "Inno Setup compilation failed." }

Write-Host "=== Build complete ===" -ForegroundColor Green
Get-ChildItem "$root\dist\*.exe" | ForEach-Object { Write-Host "  $($_.FullName)  ($([math]::Round($_.Length/1MB,1)) MB)" }
