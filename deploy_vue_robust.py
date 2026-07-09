import paramiko
import sys

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.70.240.43', port=22, username='itcloud', password='clone1997', timeout=10)
    print("Connected to Sherdor.")
    
    commands = [
        "cd ~/Sherdor2.0 && git fetch origin main && git reset --hard origin/main",
        "cd ~/Sherdor2.0 && composer install --no-interaction --prefer-dist --optimize-autoloader",
        "cd ~/Sherdor2.0 && php artisan optimize:clear",
        "cd ~/Sherdor2.0 && npm install",
        "cd ~/Sherdor2.0 && npm run build"
    ]
    
    for cmd in commands:
        print(f"Running: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        exit_status = stdout.channel.recv_exit_status()
        out = stdout.read().decode('utf-8', errors='replace')
        err = stderr.read().decode('utf-8', errors='replace')
        print(f"Exit Status: {exit_status}")
        print("STDOUT:", out[:500] + '...' if len(out)>500 else out)
        if err:
            print("STDERR:", err[:500] + '...' if len(err)>500 else err)
            
except Exception as e:
    print('ERROR:', str(e))
