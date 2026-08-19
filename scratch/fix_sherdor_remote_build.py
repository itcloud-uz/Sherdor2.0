import paramiko
import sys
import os

HOST = '100.70.240.43'
USER = 'itcloud'
PASS = 'clone1997'
PATH = '/home/itcloud/Sherdor2.0'
TOKEN = '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'

def safe_print(text):
    print(text.encode('ascii', errors='replace').decode('ascii'))

def run_remote():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    print(f"Connecting to {HOST}...")
    ssh.connect(HOST, username=USER, password=PASS, timeout=15)
    print("Connected successfully!")

    def exec_cmd(cmd):
        print(f"\n--- Executing: {cmd} ---")
        stdin, stdout, stderr = ssh.exec_command(f"cd {PATH} && {cmd}")
        out = stdout.read().decode('utf-8', errors='ignore')
        err = stderr.read().decode('utf-8', errors='ignore')
        safe_print("STDOUT:\n" + out)
        if err:
            safe_print("STDERR:\n" + err)
        return out, err

    # 1. Update git remote URL with token
    exec_cmd(f"git remote set-url origin https://{TOKEN}@github.com/itcloud-uz/Sherdor2.0.git")

    # 2. Git pull
    exec_cmd("git pull origin main")

    # 3. Build JS bundle
    exec_cmd("NODE_OPTIONS='--max-old-space-size=4096' npm run build")

    # 4. Clear and optimize Laravel caches
    exec_cmd("php artisan optimize:clear")
    exec_cmd(f"echo '{PASS}' | sudo -S php artisan optimize")
    exec_cmd(f"echo '{PASS}' | sudo -S chown -R www-data:www-data storage bootstrap/cache public/build")

    ssh.close()
    print("\nDONE!")

if __name__ == '__main__':
    run_remote()
