const { NodeSSH } = require('node-ssh');
const ssh = new NodeSSH();
ssh.connect({host: '100.69.189.125', username: 'haus', password: 'clone2026'}).then(async () => {
    // Check laravel log for recent errors
    const r1 = await ssh.execCommand("tail -80 storage/logs/laravel.log | grep -i 'exception\\|error\\|fatal\\|SQLSTATE' | head -20", { cwd: '/var/www/sellerarp' });
    console.log('ERRORS:', r1.stdout || r1.stderr);

    // Check if migration is needed
    const r2 = await ssh.execCommand('php artisan migrate:status 2>&1 | tail -20', { cwd: '/var/www/sellerarp' });
    console.log('MIGRATE STATUS:', r2.stdout || r2.stderr);

    // Check php error
    const r3 = await ssh.execCommand('php artisan config:clear && php artisan cache:clear && php artisan view:clear 2>&1', { cwd: '/var/www/sellerarp' });
    console.log('CLEAR:', r3.stdout || r3.stderr);

    ssh.dispose();
}).catch(e => console.error(e));
