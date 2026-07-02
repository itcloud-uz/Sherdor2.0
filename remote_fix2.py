import paramiko
import sys

hostname = '100.69.189.125'
port = 22
username = 'haus'
password = 'clone2026'

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(hostname, port, username, password, timeout=10)
    
    commands = [
        "node -v",
        "npm -v",
        "cd /var/www/sellerarp && git pull",
        "cd /var/www/sellerarp && echo 'clone2026' | sudo -S npm install",
        "cd /var/www/sellerarp && echo 'clone2026' | sudo -S npm run build",
        "cd /var/www/sellerarp && php artisan migrate --force",
    ]
    
    for cmd in commands:
        print(f"Executing: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        print("STDOUT:", stdout.read().decode('utf-8'))
        print("STDERR:", stderr.read().decode('utf-8'))
        
    ssh.close()
except Exception as e:
    print(f"Error: {e}")
