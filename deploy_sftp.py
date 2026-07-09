import paramiko
import os

files_to_upload = [
    'app/Http/Controllers/Admin/CrmController.php',
    'app/Http/Controllers/Admin/PartnerHubController.php',
    'app/Http/Controllers/Api/LeadController.php',
    'app/Models/Client.php',
    'app/Models/Lead.php',
    'app/Models/Transaction.php',
    'resources/js/Layouts/AdminLayout.vue',
    'resources/js/Pages/Admin/CRM/Index.vue',
    'resources/js/Pages/Admin/Finance/Index.vue',
    'resources/js/Pages/Admin/Partners/Assets.vue',
    'resources/js/Pages/Admin/Partners/Index.vue',
    'resources/js/Pages/Auth/Login.vue',
    'routes/web.php'
]

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.70.240.43', port=22, username='itcloud', password='clone1997', timeout=10)
    print("Connected to Sherdor.")
    
    sftp = ssh.open_sftp()
    
    for file_path in files_to_upload:
        local_path = os.path.join(r"d:\Sherdor 2.0", file_path)
        remote_path = "/home/itcloud/Sherdor2.0/" + file_path.replace("\\", "/")
        print(f"Uploading {file_path}...")
        try:
            sftp.put(local_path, remote_path)
        except Exception as e:
            print(f"Failed to upload {file_path}: {e}")
            
    sftp.close()
    
    print("Files uploaded. Now running npm run build...")
    commands = [
        "cd ~/Sherdor2.0 && php artisan optimize:clear",
        "cd ~/Sherdor2.0 && npm run build"
    ]
    
    for cmd in commands:
        print(f"Running: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        exit_status = stdout.channel.recv_exit_status()
        out = stdout.read().decode('utf-8', errors='replace')
        err = stderr.read().decode('utf-8', errors='replace')
        print(f"Exit Status: {exit_status}")
        print("STDOUT:", out[:500] + '...' if len(out)>500 else out)
        if err:
            print("STDERR:", err[:500] + '...' if len(err)>500 else err)
            
except Exception as e:
    print('ERROR:', str(e))
