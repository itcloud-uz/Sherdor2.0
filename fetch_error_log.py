import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('100.69.189.125', 22, 'haus', 'clone2026', timeout=10)

stdin, stdout, stderr = ssh.exec_command('grep "production.ERROR" /var/www/sellerarp/storage/logs/laravel.log | tail -n 10')
print(stdout.read().decode('utf-8'))
