# Simple Test Script for Nizam School System
$baseUrl = "http://localhost:8095"

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  NIZAM UI TESTING" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Test Login Page
Write-Host "TEST 1: Login Page" -ForegroundColor Yellow
Write-Host "-------------------"
try {
    $response = Invoke-WebRequest -Uri "$baseUrl/login" -UseBasicParsing -TimeoutSec 10
    $content = $response.Content
    
    # Check for Arabic welcome text
    if ($content -match "مرحباً بك في نِظام") {
        Write-Host "[PASS] Arabic welcome title found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Arabic welcome title NOT found" -ForegroundColor Red
    }
    
    # Check for system subtitle
    if ($content -match "نظام إدارة مدرسي متكامل") {
        Write-Host "[PASS] System subtitle found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] System subtitle NOT found" -ForegroundColor Red
    }
    
    # Check for feature titles
    $features = @("إدارة الطلاب", "عمليات المعلمين", "التخطيط الأكاديمي", "التقارير والتحليلات")
    $foundFeatures = 0
    foreach ($feature in $features) {
        if ($content -match [regex]::Escape($feature)) {
            $foundFeatures++
        }
    }
    Write-Host "[INFO] Found $foundFeatures/4 feature titles" -ForegroundColor Cyan
    
    # Check for raw translation keys
    if ($content -match "auth\.login\.") {
        Write-Host "[FAIL] Raw translation keys detected!" -ForegroundColor Red
    } else {
        Write-Host "[PASS] No raw translation keys" -ForegroundColor Green
    }
    
    # Check for trusted by schools text
    if ($content -match "موثوق به من قبل المدارس") {
        Write-Host "[PASS] Trust indicator found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Trust indicator NOT found" -ForegroundColor Red
    }
    
    # Check for secure connection text
    if ($content -match "اتصال آمن ومشفر") {
        Write-Host "[PASS] Secure connection badge found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Secure connection badge NOT found" -ForegroundColor Red
    }
    
} catch {
    Write-Host "[ERROR] Could not load login page: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "TEST 2: Translation Files" -ForegroundColor Yellow
Write-Host "-------------------------"

# Check Arabic translation file
$arPath = "lang\ar.php"
if (Test-Path $arPath) {
    $arContent = Get-Content $arPath -Raw
    if ($arContent -match "auth.login.welcome_title") {
        Write-Host "[PASS] Arabic translations file has new keys" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Arabic translations missing new keys" -ForegroundColor Red
    }
    
    if ($arContent -match "teachers.empty_title") {
        Write-Host "[PASS] Teachers empty state keys found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Teachers empty state keys missing" -ForegroundColor Red
    }
    
    if ($arContent -match "students.empty_title") {
        Write-Host "[PASS] Students empty state keys found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Students empty state keys missing" -ForegroundColor Red
    }
    
    if ($arContent -match "classes.empty_title") {
        Write-Host "[PASS] Classes empty state keys found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Classes empty state keys missing" -ForegroundColor Red
    }
    
    if ($arContent -match "assignments.empty_title") {
        Write-Host "[PASS] Assignments empty state keys found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Assignments empty state keys missing" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] Arabic translation file not found" -ForegroundColor Red
}

Write-Host ""
Write-Host "TEST 3: View Files" -ForegroundColor Yellow
Write-Host "------------------"

# Check grades view (modal fix)
$gradesPath = "views\grades\index.php"
if (Test-Path $gradesPath) {
    $gradesContent = Get-Content $gradesPath -Raw
    # Check if modals are outside the table
    if ($gradesContent -match "</table>.*<!-- Edit Modals") {
        Write-Host "[PASS] Grades modals moved outside table" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Grades modals still inside table" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] Grades view file not found" -ForegroundColor Red
}

# Check teachers view (enhancements)
$teachersPath = "views\teachers\index.php"
if (Test-Path $teachersPath) {
    $teachersContent = Get-Content $teachersPath -Raw
    if ($teachersContent -match "n-empty-state") {
        Write-Host "[PASS] Teachers has empty state component" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Teachers missing empty state component" -ForegroundColor Red
    }
    
    if ($teachersContent -match "n-stat-card") {
        Write-Host "[PASS] Teachers has stat cards" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Teachers missing stat cards" -ForegroundColor Red
    }
    
    if ($teachersContent -match "teachers.description") {
        Write-Host "[PASS] Teachers has description" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Teachers missing description" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] Teachers view file not found" -ForegroundColor Red
}

# Check students view (enhancements)
$studentsPath = "views\students\index.php"
if (Test-Path $studentsPath) {
    $studentsContent = Get-Content $studentsPath -Raw
    if ($studentsContent -match "n-empty-state") {
        Write-Host "[PASS] Students has empty state component" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Students missing empty state component" -ForegroundColor Red
    }
    
    if ($studentsContent -match "n-stat-card") {
        Write-Host "[PASS] Students has stat cards" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Students missing stat cards" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] Students view file not found" -ForegroundColor Red
}

Write-Host ""
Write-Host "TEST 4: CSS & JavaScript" -ForegroundColor Yellow
Write-Host "------------------------"

# Check CSS file
$cssPath = "public\assets\css\app.css"
if (Test-Path $cssPath) {
    $cssContent = Get-Content $cssPath -Raw
    if ($cssContent -match "z-index:\s*1060") {
        Write-Host "[PASS] Modal z-index 1060 found in CSS" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Modal z-index 1060 NOT found" -ForegroundColor Red
    }
    
    if ($cssContent -match "z-index:\s*1055") {
        Write-Host "[PASS] Backdrop z-index 1055 found in CSS" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Backdrop z-index 1055 NOT found" -ForegroundColor Red
    }
    
    if ($cssContent -match "n-empty-state") {
        Write-Host "[PASS] Empty state CSS found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Empty state CSS NOT found" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] CSS file not found" -ForegroundColor Red
}

# Check JavaScript file
$jsPath = "public\assets\js\app.js"
if (Test-Path $jsPath) {
    $jsContent = Get-Content $jsPath -Raw
    if ($jsContent -match "data-flash-autodismiss") {
        Write-Host "[PASS] Flash auto-dismiss functionality found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Flash auto-dismiss NOT found" -ForegroundColor Red
    }
    
    if ($jsContent -match "5000") {
        Write-Host "[PASS] 5-second timeout configured" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] 5-second timeout NOT configured" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] JavaScript file not found" -ForegroundColor Red
}

Write-Host ""
Write-Host "TEST 5: Cloud Storage Configuration" -ForegroundColor Yellow
Write-Host "-----------------------------------"

$configPath = "config\config.php"
if (Test-Path $configPath) {
    $configContent = Get-Content $configPath -Raw
    if ($configContent -match "pgsql") {
        Write-Host "[PASS] PostgreSQL driver configured" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] PostgreSQL driver NOT configured" -ForegroundColor Red
    }
    
    if ($configContent -match "supabase") {
        Write-Host "[PASS] Supabase configuration found" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] Supabase configuration NOT found" -ForegroundColor Red
    }
} else {
    Write-Host "[ERROR] Config file not found" -ForegroundColor Red
}

Write-Host ""
Write-Host "=====================================" -ForegroundColor Green
Write-Host "  TESTING COMPLETE" -ForegroundColor Green
Write-Host "=====================================" -ForegroundColor Green
