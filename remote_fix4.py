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
        "echo 'clone2026' | sudo -S chown -R haus:www-data /var/www/sellerarp",
        "echo 'clone2026' | sudo -S chmod -R 775 /var/www/sellerarp/storage",
        "echo 'clone2026' | sudo -S chmod -R 775 /var/www/sellerarp/bootstrap/cache",
        "echo 'clone2026' | sudo -S chmod -R 775 /var/www/sellerarp/public/build",
        "echo 'clone2026' | sudo -S chmod +x /var/www/sellerarp/update.sh"
    ]
    
    for cmd in commands:
        print(f"Executing: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        print("STDOUT:", stdout.read().decode('utf-8', errors='ignore'))
        print("STDERR:", stderr.read().decode('utf-8', errors='ignore'))
        
    ssh.close()
except Exception as e:
    print(f"Error: {e}")
