@echo off
setlocal

REM مسیر نصب WampServer (در صورت نیاز تغییر بده)
set WAMP_PATH="C:\wamp64\wampmanager.exe"

REM بررسی روشن بودن WampServer
tasklist /FI "IMAGENAME eq wampmanager.exe" | find /I "wampmanager.exe" >nul

if "%ERRORLEVEL%"=="0" (
    echo WampServer در حال اجراست...
) else (
    echo WampServer روشن نیست. در حال اجرا...
    start "" %WAMP_PATH%
    echo صبر برای اجرای WampServer...
    timeout /t 10 /nobreak >nul
)

REM باز کردن لینک در Google Chrome
start chrome "http://localhost/pro-modir/dashboard.php"

endlocal
