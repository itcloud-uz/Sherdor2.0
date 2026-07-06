import paramiko

def update_server(hostname, username, password, path, bot_service):
    try:
        print(f"Connecting to {hostname}...")
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        ssh.connect(hostname, port=22, username=username, password=password, timeout=10)
        
        # Git pull
        print(f"Git pull on {hostname} in {path}...")
        stdin, stdout, stderr = ssh.exec_command(f'cd {path} && git pull origin main')
        print(stdout.read().decode('utf-8'))
        print(stderr.read().decode('utf-8'))
        
        # Restart Bot service
        if bot_service:
            print(f"Restarting bot service: {bot_service}")
            # we run it with sudo if possible, or just systemctl --user
            stdin, stdout, stderr = ssh.exec_command(f'echo {password} | sudo -S systemctl restart {bot_service}')
            print(stdout.read().decode('utf-8'))
            print(stderr.read().decode('utf-8'))
            
        ssh.close()
    except Exception as e:
        print(f"Error on {hostname}: {e}")

# Sherdor server
update_server('100.70.240.43', 'itcloud', 'clone1997', '/home/itcloud/Sherdor2.0', 'sher-dor-bot.service')
# Hayot House server
update_server('100.69.189.125', 'haus', 'clone2026', '/var/www/sellerarp', 'sellerarp-bot.service') # Assuming the service might be sellerarp-bot or something similar.
