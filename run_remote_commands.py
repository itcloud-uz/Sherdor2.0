import paramiko
import sys
import codecs

sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())

hostname = '100.69.189.125'
port = 22
username = 'haus'
password = 'clone2026'

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(hostname, port, username, password, timeout=10)
    
    commands = [
        "cd /var/www/sellerarp && git stash",
        "cd /var/www/sellerarp && git fetch origin",
        "cd /var/www/sellerarp && git reset --hard origin/main",
        "cd /var/www/sellerarp && php artisan migrate --force",
        "cd /var/www/sellerarp && php artisan optimize:clear",
        "cd /var/www/sellerarp && npm run build",
        "cd /var/www/sellerarp && echo 'clone2026' | sudo -S -u www-data php artisan optimize",
        "cd /var/www/sellerarp && bash restart_bot.sh"
    ]
    
    for cmd in commands:
        print(f"Executing: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        out = stdout.read().decode('utf-8', errors='ignore')
        err = stderr.read().decode('utf-8', errors='ignore')
        print("STDOUT:", out.encode('ascii', 'replace').decode('ascii'))
        print("STDERR:", err.encode('ascii', 'replace').decode('ascii'))
        
    ssh.close()
except Exception as e:
    print(f"Error: {e}")
