import paramiko
import sys
import codecs

sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.69.189.125', 22, 'haus', 'clone2026', timeout=10)
    stdin, stdout, stderr = ssh.exec_command('tail -n 1000 /var/www/sellerarp/storage/logs/laravel.log | grep -A 2 -B 2 "ERROR"')
    print(stdout.read().decode('utf-8', errors='ignore'))
    ssh.close()
except Exception as e:
    print(e)
