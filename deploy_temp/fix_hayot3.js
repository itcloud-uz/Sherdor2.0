const { NodeSSH } = require('node-ssh');
const ssh = new NodeSSH();
ssh.connect({host: '100.69.189.125', username: 'haus', password: 'clone2026'}).then(async () => {

    // Create migration file for telephony_audio on server
    const migrationContent = `<?php
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('telephony_audio')) {
            Schema::create('telephony_audio', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('file_path')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('telephony_audio');
    }
};`;

    // Write migration file on server
    const r1 = await ssh.execCommand(
        `cat > database/migrations/2026_01_01_000001_create_telephony_audio_table.php << 'MIGEOF'\n${migrationContent}\nMIGEOF`,
        { cwd: '/var/www/sellerarp' }
    );
    console.log('WRITE MIGRATION:', r1.stdout || r1.stderr);

    // Run migration
    const r2 = await ssh.execCommand('php artisan migrate --force 2>&1', { cwd: '/var/www/sellerarp' });
    console.log('MIGRATE:', r2.stdout || r2.stderr);

    // Restart php-fpm
    const r3 = await ssh.execCommand('sudo systemctl restart php8.3-fpm 2>&1 || sudo service php8.3-fpm restart 2>&1 || true', { cwd: '/var/www/sellerarp' });
    console.log('RESTART PHP-FPM:', r3.stdout || r3.stderr);

    // Reload nginx
    const r4 = await ssh.execCommand('sudo nginx -s reload 2>&1 || true', { cwd: '/var/www/sellerarp' });
    console.log('NGINX:', r4.stdout || r4.stderr);

    // Check HTTP status
    const r5 = await ssh.execCommand('curl -s -o /dev/null -w "%{http_code}" http://localhost 2>&1', { cwd: '/' });
    console.log('HTTP STATUS:', r5.stdout);

    ssh.dispose();
    console.log('Done!');
}).catch(e => console.error(e));
