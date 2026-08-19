import paramiko
import sys
import codecs

sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())

s = {
    'hostname': '100.70.240.43',
    'username': 'itcloud',
    'password': 'clone1997',
    'path': '/home/itcloud/Sherdor2.0'
}

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(s['hostname'], 22, s['username'], s['password'], timeout=10)
    
    cmd = f"grep -B 2 -A 5 'ERROR' {s['path']}/storage/logs/laravel.log | tail -n 20"
    print(f"Executing: {cmd}")
    stdin, stdout, stderr = ssh.exec_command(cmd)
    
    out = stdout.read().decode('utf-8', errors='ignore')
    err = stderr.read().decode('utf-8', errors='ignore')
    print("STDOUT:")
    print(out)
        
    ssh.close()
except Exception as e:
    print(f"Error: {e}")
