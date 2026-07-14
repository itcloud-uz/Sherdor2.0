const { NodeSSH } = require('node-ssh');
const ssh = new NodeSSH();
ssh.connect({host: '100.69.189.125', username: 'haus', password: 'clone2026'}).then(async () => {
    // Create telephony_audio table via artisan raw DB
    const r1 = await ssh.execCommand(
        "php -r \"require 'vendor/autoload.php'; \\$app = require 'bootstrap/app.php'; \\$app->make('Illuminate\\\\Contracts\\\\Http\\\\Kernel'); \\$db = app('db'); if(!\\$db->getSchemaBuilder()->hasTable('telephony_audio')){ \\$db->statement('CREATE TABLE telephony_audio (id BIGSERIAL PRIMARY KEY, name VARCHAR(255), file_path VARCHAR(255), description TEXT, created_at TIMESTAMP, updated_at TIMESTAMP)'); echo 'Created'; } else { echo 'Already exists'; }\"",
        { cwd: '/var/www/sellerarp' }
    );
    console.log('TABLE RESULT:', r1.stdout || r1.stderr);

    // Find php-fpm service
    const r2 = await ssh.execCommand('ls /etc/init.d/ | grep php || ls /lib/systemd/system/ | grep php', { cwd: '/' });
    console.log('PHP-FPM:', r2.stdout || r2.stderr);

    // Reload nginx
    const r3 = await ssh.execCommand('sudo nginx -s reload 2>&1 || true', { cwd: '/var/www/sellerarp' });
    console.log('NGINX:', r3.stdout || r3.stderr);

    // Check site status
    const r4 = await ssh.execCommand('curl -s -o /dev/null -w "%{http_code}" http://localhost', { cwd: '/' });
    console.log('HTTP STATUS:', r4.stdout);

    ssh.dispose();
}).catch(e => console.error(e));
