@echo off
rem Өгөгдлийн сангийн өдөр тутмын нөөц (Windows Task Scheduler ажиллуулна).
rem Үр дүн storage\logs\backup.log-д бичигдэнэ.
cd /d "%~dp0.."
C:\xampp\php\php.exe artisan db:backup >> storage\logs\backup.log 2>&1
