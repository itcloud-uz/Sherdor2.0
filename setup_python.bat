@echo off
echo === Setting up Python Virtual Environment (Windows) ===
where python >nul 2>nul
if %errorlevel% neq 0 (
    echo ❌ Error: python is not installed or not in PATH!
    exit /b 1
)

if not exist venv (
    echo Creating virtual environment...
    python -m venv venv
    if %errorlevel% neq 0 (
        echo ❌ Error: Failed to create virtual environment!
        exit /b 1
    )
)

echo Installing dependencies...
call venv\Scripts\activate.bat
python -m pip install --upgrade pip
pip install -r requirements.txt

if %errorlevel% equ 0 (
    echo ✅ Dependencies successfully installed!
    echo.
    echo === IMPORTANT NEXT STEP ===
    echo Please update the PYTHON_PATH in your .env file to use the virtual environment:
    echo.
    echo PYTHON_PATH=venv\Scripts\python.exe
    echo.
) else (
    echo ❌ Error: Failed to install python dependencies!
    exit /b 1
)
