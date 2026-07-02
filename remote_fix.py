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
        "echo 'clone2026' | sudo -S mkdir -p /var/www/sellerarp/storage/logs /var/www/sellerarp/bootstrap/cache",
        "echo 'clone2026' | sudo -S chmod -R 777 /var/www/sellerarp/storage /var/www/sellerarp/bootstrap/cache",
        "echo 'clone2026' | sudo -S chown -R $USER:www-data /var/www/sellerarp",
        "cd /var/www/sellerarp && composer install --optimize-autoloader --no-dev",
        "cd /var/www/sellerarp && php artisan optimize:clear",
        "cd /var/www/sellerarp && echo 'clone2026' | sudo -S chmod -R 777 /var/www/sellerarp/storage /var/www/sellerarp/bootstrap/cache",
    ]
    
    for cmd in commands:
        print(f"Executing: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        print("STDOUT:", stdout.read().decode('utf-8'))
        print("STDERR:", stderr.read().decode('utf-8'))
        
    ssh.close()
except Exception as e:
    print(f"Error: {e}")
