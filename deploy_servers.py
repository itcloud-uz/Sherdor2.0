import paramiko
import sys
import codecs

sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())

servers = [
    {
        'hostname': '100.70.240.43',
        'username': 'itcloud',
        'password': 'clone1997',
        'path': '/home/itcloud/Sherdor2.0',
        'update_cmd': "echo 'clone1997' | sudo -S bash update.sh"
    },
    {
        'hostname': '100.69.189.125',
        'username': 'haus',
        'password': 'clone2026',
        'path': '/var/www/sellerarp',
        'update_cmd': "echo 'clone2026' | sudo -S bash update.sh"
    }
]

for s in servers:
    print(f"\n--- Deploying to {s['hostname']} ({s['username']}) ---")
    try:
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        ssh.connect(s['hostname'], 22, s['username'], s['password'], timeout=10)
        
        commands = [
            f"cd {s['path']} && git stash",
            f"cd {s['path']} && {s['update_cmd']}"
        ]
        
        for cmd in commands:
            print(f"Executing: {cmd}")
            stdin, stdout, stderr = ssh.exec_command(cmd)
            # Wait for command to finish
            exit_status = stdout.channel.recv_exit_status()
            out = stdout.read().decode('utf-8', errors='ignore')
            err = stderr.read().decode('utf-8', errors='ignore')
            print("STDOUT:", out.encode('ascii', 'replace').decode('ascii'))
            print("STDERR:", err.encode('ascii', 'replace').decode('ascii'))
            
        ssh.close()
    except Exception as e:
        print(f"Error connecting to {s['hostname']}: {e}")
