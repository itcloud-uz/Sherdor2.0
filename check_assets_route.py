import paramiko
import json

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.70.240.43', port=22, username='itcloud', password='clone1997', timeout=10)
    
    cmd = "curl -s -H 'X-Inertia: true' -H 'X-Inertia-Version: 1' -b 'sherdor_session=1' http://127.0.0.1:8000/admin/fixed-assets"
    stdin, stdout, stderr = ssh.exec_command(cmd)
    
    output = stdout.read().decode('utf-8')
    try:
        data = json.loads(output)
        print("Component:", data.get('component'))
        print("URL:", data.get('url'))
    except:
        print("Raw output:", output[:500])

except Exception as e:
    print('ERROR:', str(e))
