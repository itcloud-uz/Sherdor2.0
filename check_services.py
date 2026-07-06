import paramiko

def check_services(hostname, username, password):
    try:
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        ssh.connect(hostname, port=22, username=username, password=password, timeout=10)
        
        stdin, stdout, stderr = ssh.exec_command('systemctl list-units --type=service | grep -E "bot|sellerarp|sherdor"')
        print(f'--- {hostname} ---')
        print(stdout.read().decode('utf-8'))
        ssh.close()
    except Exception as e:
        print(f'Error on {hostname}: {e}')

check_services('100.69.189.125', 'haus', 'clone2026')
check_services('100.70.240.43', 'itcloud', 'clone1997')
