@echo off
setlocal
title Hadaba Al-Ahram School - Administrator Recovery
chcp 65001 >nul

rem Apache receives this path from its own configuration. A standalone CLI
rem process does not, so set it explicitly to guarantee both use the same
rem school database and mutable-data location.
set "NIZAM_DATA_PATH=%ProgramData%\HadabaSchool"
cd /d "%~dp0app"

echo.
"%~dp0runtime\php\php.exe" "bin\recover-admin.php" --interactive
set "RESULT=%ERRORLEVEL%"

echo.
if not "%RESULT%"=="0" echo Recovery did not complete. Review the message above.
echo You may close this window when finished.
pause >nul
exit /b %RESULT%
