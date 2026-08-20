import paramiko
import sys

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.70.240.43', username='itcloud', password='clone1997', timeout=10)
    stdin, stdout, stderr = ssh.exec_command('cd /home/itcloud/Sherdor2.0 && git show 4ac2669 -- resources/js/Pages/Admin/HR/QurilishIshHaqi.vue | head -n 30')
    
    out = stdout.read().decode('utf-8')
    err = stderr.read().decode('utf-8')
    
    print('OUTPUT:', out)
    
    ssh.close()
except Exception as e:
    print('Error:', e)
