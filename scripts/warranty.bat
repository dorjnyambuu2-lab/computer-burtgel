@echo off
rem Баталгаа удахгүй дуусах төхөөрөмжийн сануулга (Windows Task Scheduler өдөр бүр ажиллуулна).
rem Үр дүн storage\logs\warranty.log-д бичигдэнэ.
cd /d "%~dp0.."
C:\xampp\php\php.exe artisan warranty:digest >> storage\logs\warranty.log 2>&1
