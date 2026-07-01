#!/bin/bash
# Python virtual environment setup script for Sher-Dor 2.0 (Linux Server)

echo "=== Setting up Python Virtual Environment ==="

# Check if python3 is installed
if ! command -v python3 &> /dev/null
then
    echo "❌ Error: python3 is not installed on this server!"
    exit 1
fi

# Create venv directory if not exists
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
    if [ $? -ne 0 ]; then
        echo "❌ Error: Failed to create virtual environment! Try installing python3-venv: sudo apt-get install python3-venv"
        exit 1
    fi
fi

# Activate virtual environment and install requirements
echo "Installing dependencies..."
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

if [ $? -eq 0 ]; then
    echo "✅ Dependencies successfully installed!"
    echo ""
    echo "=== IMPORTANT NEXT STEP ==="
    echo "Please update the PYTHON_PATH in your .env file to use the virtual environment:"
    echo ""
    echo "PYTHON_PATH=venv/bin/python"
    echo ""
else
    echo "❌ Error: Failed to install python dependencies!"
    exit 1
fi
