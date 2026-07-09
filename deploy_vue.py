import paramiko
import time

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.70.240.43', port=22, username='itcloud', password='clone1997', timeout=10)
    print("Connected to Sherdor. Pulling code and building...")
    
    commands = [
        "cd ~/Sherdor2.0 && git pull origin main",
        "cd ~/Sherdor2.0 && npm run build"
    ]
    
    for cmd in commands:
        print(f"Running: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        
        while not stdout.channel.exit_status_ready():
            time.sleep(1)
            
        print('STDOUT:', stdout.read().decode('utf-8'))
        print('STDERR:', stderr.read().decode('utf-8'))
        
except Exception as e:
    print('ERROR:', str(e))
