@echo off
REM === Complaint Portal Backup Script ===

REM Set backup directory and date
set BACKUP_DIR=backups
set DATE=%DATE:~10,4%-%DATE:~4,2%-%DATE:~7,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%
set DEST=%BACKUP_DIR%\backup_%DATE%
set ZIP_FILE=backup_%DATE%.zip

REM Create backup directory
mkdir "%DEST%"

REM === MySQL DB Backup ===
REM Edit these variables as needed (or parse from includes/config.php)
set DB_USER=root
set DB_PASS=root
set DB_NAME=complaint_portal
set DB_HOST=localhost

REM Dump the database
mysqldump -u%DB_USER% -p%DB_PASS% -h%DB_HOST% %DB_NAME% > "%DEST%\db_backup.sql"

REM === Backup important folders ===
xcopy includes "%DEST%\includes" /E /I /Y
xcopy assets "%DEST%\assets" /E /I /Y
xcopy logs "%DEST%\logs" /E /I /Y

REM === Backup config and schema files ===
if exist schema.sql copy schema.sql "%DEST%\schema.sql"
if exist trigger.sql copy trigger.sql "%DEST%\trigger.sql"

REM === Compress backup ===
cd %BACKUP_DIR%
powershell -Command "Compress-Archive -Path '%DEST%\*' -DestinationPath '%ZIP_FILE%' -Force" >nul
cd ..

REM Done
@echo Backup completed! Files saved to %DEST%
pause