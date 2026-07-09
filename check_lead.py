import paramiko
try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.70.240.43', port=22, username='itcloud', password='clone1997', timeout=10)
    stdin, stdout, stderr = ssh.exec_command("cd ~/Sherdor2.0 && php artisan tinker --execute=\"echo json_encode(App\\Models\\Lead::where('phone', 'like', '%998917084444%')->first());\"")
    print('STDOUT:', stdout.read().decode('utf-8'))
    print('STDERR:', stderr.read().decode('utf-8'))
except Exception as e:
    print('ERROR:', str(e))
