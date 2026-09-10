$ErrorActionPreference = "Stop"

$src = "i:\Nizam-School-System"
$dst = "i:\Nizam-Release\htdocs"

Write-Host "Creating package directory..."
if (Test-Path "i:\Nizam-Release") {
    Remove-Item -Recurse -Force "i:\Nizam-Release"
}
New-Item -ItemType Directory -Path $dst | Out-Null

Write-Host "Copying required directories and files..."
$includes = @(
    "app", "config", "database", "docs", "lang", "public", 
    "storage", "vendor", "vendor-assets", "views",
    ".htaccess", "README.md", "composer.json", "composer.lock"
)

foreach ($item in $includes) {
    Copy-Item -Path "$src\$item" -Destination "$dst\$item" -Recurse
}

Write-Host "Cleaning development artifacts..."
# Remove actual config (leave config.example.php)
if (Test-Path "$dst\config\config.php") {
    Remove-Item "$dst\config\config.php"
}

# Remove test backups
if (Test-Path "$dst\database\backups") {
    Get-ChildItem "$dst\database\backups\*.sql" | Remove-Item
}

# Remove logs
if (Test-Path "$dst\storage\logs") {
    Get-ChildItem "$dst\storage\logs\*.log" | Remove-Item
}

# Remove any development/test photos or logo -- a school's installation must
# start with zero uploaded files, same principle as the test-backups cleanup
# above. The .htaccess (execution lockout, O-18) and the folder itself are
# recreated immediately below regardless.
if (Test-Path "$dst\storage\uploads") {
    Remove-Item -Recurse -Force "$dst\storage\uploads"
}

# Uploads live under storage/uploads (decision #11 -- outside public/ entirely,
# already unreachable by direct URL under the root deny-all .htaccess), copied
# wholesale as part of "storage" above, execution-lockout .htaccess included.
# Ensure the folder and its .htaccess exist even on a fresh install where no
# photo/logo has been uploaded yet, so the O-18 lockout is never missing.
if (-not (Test-Path "$dst\storage\uploads")) {
    New-Item -ItemType Directory -Path "$dst\storage\uploads" | Out-Null
}
if (-not (Test-Path "$dst\storage\uploads\.htaccess")) {
    Set-Content -Path "$dst\storage\uploads\.htaccess" -Value "php_flag engine off`nOptions -ExecCGI"
}

Write-Host "Verifying PHP Syntax (Lint)..."
$phpFiles = Get-ChildItem -Path $dst -Filter "*.php" -Recurse | Where-Object { $_.FullName -notmatch "\\vendor\\" }
$lintErrors = 0
foreach ($file in $phpFiles) {
    $result = & C:\xampp\php\php.exe -l $file.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Lint Error in $($file.Name): $result" -ForegroundColor Red
        $lintErrors++
    }
}
Write-Host "Lint checked $($phpFiles.Count) files. Errors: $lintErrors"

Write-Host "Verifying offline requirement (Generic External URL Scan)..."
$externalUrlsFound = 0
$searchFiles = Get-ChildItem -Path $dst -Include *.php, *.html, *.css, *.js -Recurse | Where-Object { $_.FullName -notmatch "\\vendor\\" }
$genericRegex = "https?://[a-zA-Z0-9.-]+"

foreach ($file in $searchFiles) {
    # Read file line by line to accurately report where the hit is
    $lines = Get-Content $file.FullName
    $lineNum = 1
    foreach ($line in $lines) {
        if ($line -match $genericRegex) {
            $matchVal = $matches[0]
            # Ignore standard XML namespaces or documentation examples that don't represent runtime external network requests
            if ($matchVal -ne "http://www.w3.org" -and $matchVal -ne "http://" -and $matchVal -ne "https://") {
                Write-Host "External URL Detected in $($file.FullName):$lineNum -> $matchVal" -ForegroundColor Yellow
                $externalUrlsFound++
            }
        }
        $lineNum++
    }
}
Write-Host "Generic offline check complete. Total external URLs found: $externalUrlsFound"

Write-Host "Package structure:"
Get-ChildItem $dst | Select-Object Name

Write-Host "Packaging Verification Complete."
