@echo off
REM Athenaeum — start the local development server (Windows).
REM Requires PHP 8.1+ on PATH. No web server, no Composer needed.
REM
REM   bin\serve.cmd            -> http://127.0.0.1:8080
REM   bin\serve.cmd 9000       -> http://127.0.0.1:9000

setlocal
set PORT=%1
if "%PORT%"=="" set PORT=8080

set ROOT=%~dp0..
pushd "%ROOT%"

php ^
  -d extension=pdo_sqlite ^
  -d extension=pdo_mysql ^
  -d extension=sqlite3 ^
  -d extension=zip ^
  -d extension=gd ^
  -d extension=fileinfo ^
  -d extension=intl ^
  -d upload_max_filesize=32M ^
  -d post_max_size=40M ^
  -d max_execution_time=120 ^
  -S 127.0.0.1:%PORT% -t public public/router.php

popd
endlocal
