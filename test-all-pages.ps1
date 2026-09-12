# Comprehensive Test Script for Nizam School System
# Tests all fixed pages for translation keys and UI enhancements

$baseUrl = "http://localhost:8095"
$testResults = @()

Write-Host "╔════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║  NIZAM SCHOOL SYSTEM - COMPREHENSIVE UI TEST      ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Function to test a page
function Test-Page {
    param(
        [string]$Name,
        [string]$Url,
        [array]$ExpectedTexts,
        [array]$ProhibitedTexts
    )
    
    Write-Host "Testing: $Name" -ForegroundColor Yellow
    Write-Host "URL: $Url" -ForegroundColor Gray
    
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 10
        $content = $response.Content
        
        $passed = 0
        $failed = 0
        
        # Check for expected texts
        foreach ($text in $ExpectedTexts) {
            if ($content -match [regex]::Escape($text)) {
                Write-Host "  ✅ Found: $text" -ForegroundColor Green
                $passed++
            } else {
                Write-Host "  ❌ Missing: $text" -ForegroundColor Red
                $failed++
            }
        }
        
        # Check for prohibited texts (raw translation keys)
        foreach ($text in $ProhibitedTexts) {
            if ($content -match $text) {
                Write-Host "  ❌ ERROR: Found prohibited text: $text" -ForegroundColor Red
                $failed++
            } else {
                Write-Host "  ✅ No raw key: $text" -ForegroundColor Green
                $passed++
            }
        }
        
        $total = $passed + $failed
        $score = if ($total -gt 0) { [math]::Round(($passed / $total) * 100, 1) } else { 0 }
        
        Write-Host "  Score: $passed/$total ($score percent)" -ForegroundColor $(if($score -eq 100){"Green"}elseif($score -ge 70){"Yellow"}else{"Red"})
        Write-Host ""
        
        return @{
            Name = $Name
            Passed = $passed
            Failed = $failed
            Score = $score
        }
    }
    catch {
        Write-Host "  ❌ ERROR: Could not load page - $($_.Exception.Message)" -ForegroundColor Red
        Write-Host ""
        return @{
            Name = $Name
            Passed = 0
            Failed = 1
            Score = 0
        }
    }
}

# TEST 1: Login Page
$testResults += Test-Page -Name "Login Page" -Url "$baseUrl/login" -ExpectedTexts @(
    "مرحباً بك في نِظام",
    "نظام إدارة مدرسي متكامل",
    "إدارة الطلاب",
    "عمليات المعلمين",
    "التخطيط الأكاديمي",
    "التقارير والتحليلات",
    "موثوق به من قبل المدارس في المنطقة",
    "اتصال آمن ومشفر"
) -ProhibitedTexts @(
    "auth\.login\.welcome_title",
    "auth\.login\.feature1_title",
    "auth\.login\.trusted_by_schools"
)

# Since we need authentication for other pages, let's test what we can access
# For now, we'll test the public login page thoroughly

Write-Host "╔════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║              TEST SUMMARY                          ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

$totalPassed = ($testResults | Measure-Object -Property Passed -Sum).Sum
$totalFailed = ($testResults | Measure-Object -Property Failed -Sum).Sum
$overallScore = if (($totalPassed + $totalFailed) -gt 0) { 
    [math]::Round(($totalPassed / ($totalPassed + $totalFailed)) * 100, 1) 
} else { 0 }

foreach ($result in $testResults) {
    $status = if ($result.Score -eq 100) { "✅" } elseif ($result.Score -ge 70) { "⚠️" } else { "❌" }
    Write-Host "$status $($result.Name): $($result.Passed)/$($result.Passed + $result.Failed) ($($result.Score) percent)"
}

Write-Host ""
Write-Host "Overall Score: $totalPassed/$($totalPassed + $totalFailed) ($overallScore percent)" -ForegroundColor $(
    if($overallScore -eq 100){"Green"}elseif($overallScore -ge 70){"Yellow"}else{"Red"}
)
Write-Host ""

# Test modal z-index fix by checking CSS
Write-Host "╔════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║         ADDITIONAL CHECKS                          ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

Write-Host "Checking CSS for modal z-index fixes..." -ForegroundColor Yellow
$cssPath = "public\assets\css\app.css"
if (Test-Path $cssPath) {
    $cssContent = Get-Content $cssPath -Raw
    if ($cssContent -match "z-index:\s*1060") {
        Write-Host "  ✅ Modal z-index fix found (1060)" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Modal z-index fix NOT found" -ForegroundColor Red
    }
    
    if ($cssContent -match "z-index:\s*1055") {
        Write-Host "  ✅ Modal backdrop z-index fix found (1055)" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Modal backdrop z-index fix NOT found" -ForegroundColor Red
    }
} else {
    Write-Host "  ❌ CSS file not found" -ForegroundColor Red
}

Write-Host ""
Write-Host "Checking JavaScript for auto-dismiss functionality..." -ForegroundColor Yellow
$jsPath = "public\assets\js\app.js"
if (Test-Path $jsPath) {
    $jsContent = Get-Content $jsPath -Raw
    if ($jsContent -match "data-flash-autodismiss") {
        Write-Host "  ✅ Auto-dismiss functionality found" -ForegroundColor Green
    } else {
        Write-Host "  ❌ Auto-dismiss functionality NOT found" -ForegroundColor Red
    }
    
    if ($jsContent -match "5000") {
        Write-Host "  ✅ 5-second timeout configured" -ForegroundColor Green
    } else {
        Write-Host "  ❌ 5-second timeout NOT found" -ForegroundColor Red
    }
} else {
    Write-Host "  ❌ JavaScript file not found" -ForegroundColor Red
}

Write-Host ""
Write-Host "╔════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║              TESTING COMPLETE!                     ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════╝" -ForegroundColor Green
