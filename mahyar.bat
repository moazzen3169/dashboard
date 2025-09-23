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
echo در حال انتظار برای اجرای کامل WampServer...

REM انتظار تا زمانی که WampServer کاملاً اجرا شود
:CHECK_WAMP
timeout /t 3 /nobreak >nul
tasklist /FI "IMAGENAME eq wampmanager.exe" | find /I "wampmanager.exe" >nul
if "%ERRORLEVEL%"=="1" (
    echo هنوز در حال اجرای WampServer...
    goto CHECK_WAMP
)

echo WampServer با موفقیت اجرا شد.

REM انتظار اضافی برای اطمینان از کامل شدن اجرای سرویس‌ها
echo در حال انتظار برای راه‌اندازی سرویس‌ها...
timeout /t 15 /nobreak >nul
)

REM باز کردن لینک در Google Chrome
echo در حال باز کردن مرورگر...
start chrome "http://localhost/dashboard/factor-products.php"

echo عملیات با موفقیت انجام شد.

endlocal