import paramiko
import os

files_to_upload = [
    'app/Http/Controllers/Admin/ContractTemplateController.php',
    'app/Http/Controllers/Admin/CrmController.php',
    'app/Http/Controllers/Admin/FinanceController.php',
    'app/Http/Controllers/Admin/LedgerExportController.php',
    'app/Http/Controllers/Admin/PartnerHubController.php',
    'app/Models/ContractTemplate.php',
    'app/Models/Transaction.php',
    'app/Models/TransactionImport.php',
    'app/Python/docx_processor.py',
    'app/Http/Middleware/HandleInertiaRequests.php',
    'database/migrations/2026_07_16_160600_add_description_to_transactions_table.php',
    'database/migrations/2026_07_16_205600_create_transaction_imports_table.php',
    'database/migrations/2026_07_20_193000_alter_leads_source_column.php',
    'resources/js/Pages/Admin/CRM/Index.vue',
    'resources/js/Pages/Admin/ContractTemplates/Index.vue',
    'resources/js/Pages/Admin/Facility/Index.vue',
    'resources/js/Pages/Admin/Finance/Index.vue',
    'resources/js/Pages/Admin/Partners/Index.vue',
    'resources/views/exports/client_ledger.blade.php',
    'routes/web.php'
]

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect('100.70.240.43', port=22, username='itcloud', password='clone1997', timeout=10)
    print("Connected to Sherdor server.")
    
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
    print("All files uploaded successfully.")
    
    print("Now running optimization and rebuild commands on the server...")
    commands = [
        "cd ~/Sherdor2.0 && php artisan optimize:clear",
        "cd ~/Sherdor2.0 && php artisan migrate --force",
        "cd ~/Sherdor2.0 && npm run build",
        "cd ~/Sherdor2.0 && echo 'clone1997' | sudo -S php artisan optimize",
        "cd ~/Sherdor2.0 && echo 'clone1997' | sudo -S chown -R www-data:www-data storage bootstrap/cache"
    ]
    
    for cmd in commands:
        print(f"Running command: {cmd}")
        stdin, stdout, stderr = ssh.exec_command(cmd)
        exit_status = stdout.channel.recv_exit_status()
        out = stdout.read().decode('utf-8', errors='replace')
        err = stderr.read().decode('utf-8', errors='replace')
        out_clean = out.encode('ascii', 'replace').decode('ascii')
        err_clean = err.encode('ascii', 'replace').decode('ascii')
        print(f"Exit Status: {exit_status}")
        print("STDOUT:", out_clean[:1000] + '...' if len(out_clean)>1000 else out_clean)
        if err:
            print("STDERR:", err_clean[:1000] + '...' if len(err_clean)>1000 else err_clean)
            
except Exception as e:
    print('ERROR:', str(e))
