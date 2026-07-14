const { NodeSSH } = require('node-ssh');
const ssh = new NodeSSH();
ssh.connect({host: '100.69.189.125', username: 'haus', password: 'clone2026'}).then(async () => {

    // 1. Run pending migrations
    console.log('Running migrations...');
    const r1 = await ssh.execCommand('php artisan migrate --force 2>&1', { cwd: '/var/www/sellerarp' });
    console.log('MIGRATE:', r1.stdout || r1.stderr);

    // 2. Check if telephony_audio migration exists, create if needed
    const r2 = await ssh.execCommand("php artisan tinker --execute=\"if (!Schema::hasTable('telephony_audio')) { Schema::create('telephony_audio', function(\$t){ \$t->id(); \$t->string('name')->nullable(); \$t->string('file_path')->nullable(); \$t->text('description')->nullable(); \$t->timestamps(); }); echo 'Table created'; } else { echo 'Table exists'; }\" 2>&1", { cwd: '/var/www/sellerarp' });
    console.log('TABLE:', r2.stdout || r2.stderr);

    // 3. Clear all caches
    const r3 = await ssh.execCommand('php artisan config:clear; php artisan view:clear; php artisan route:clear 2>&1', { cwd: '/var/www/sellerarp' });
    console.log('CLEAR:', r3.stdout || r3.stderr);

    // 4. Restart PHP-FPM
    const r4 = await ssh.execCommand('sudo systemctl restart php8.2-fpm 2>&1 || sudo service php8.2-fpm restart 2>&1 || true', { cwd: '/var/www/sellerarp' });
    console.log('RESTART:', r4.stdout || r4.stderr);

    ssh.dispose();
    console.log('Done!');
}).catch(e => console.error(e));
