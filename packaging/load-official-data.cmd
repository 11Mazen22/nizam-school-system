@echo off
setlocal
title Hadaba Al-Ahram School - Official School Data
chcp 65001 >nul

rem Apache receives this path from its own configuration. A standalone CLI
rem process does not, so set it explicitly to guarantee both use the same
rem school database and mutable-data location.
set "NIZAM_DATA_PATH=%ProgramData%\HadabaSchool"
cd /d "%~dp0app"

echo.
echo Loading the official 2026/2027 school data.
echo This is safe to run again: it never duplicates a class, student,
echo lesson, assessment, teacher or subject.
echo.
"%~dp0runtime\php\php.exe" "bin\seed-official-data.php"
set "RESULT=%ERRORLEVEL%"

echo.
if not "%RESULT%"=="0" echo The official data was not loaded. Review the message above.
if "%RESULT%"=="0" echo The official data is loaded and verified.
echo You may close this window when finished.
pause >nul
exit /b %RESULT%
