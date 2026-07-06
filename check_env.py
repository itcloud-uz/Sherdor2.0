import paramiko
import sys
import re

def check_env(hostname, username, password, path):
    try:
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        ssh.connect(hostname, port=22, username=username, password=password, timeout=10)
        
        stdin, stdout, stderr = ssh.exec_command("cat " + path + "/.env")
        output = stdout.read().decode("utf-8")
        print(f"--- {hostname} : {path} ---")
        for line in output.split("\n"):
            if re.search(r"APP_URL|TELEGRAM|BOT|DB_DATABASE", line):
                print(line.strip())
        ssh.close()
    except Exception as e:
        print(f"Error on {hostname}: {e}")

check_env("100.69.189.125", "haus", "clone2026", "/var/www/sellerarp")
check_env("100.70.240.43", "itcloud", "clone1997", "/home/itcloud/Sherdor2.0")
