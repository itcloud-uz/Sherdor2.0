const { NodeSSH } = require('node-ssh');
const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const ssh = new NodeSSH();

async function deploy() {
    try {
        console.log('Connecting to Hayot House server...');
        await ssh.connect({
            host: '100.69.189.125',
            username: 'haus',
            password: 'clone2026'
        });
        console.log('Connected!');

        const diffOutput = execSync('git diff --name-only HEAD~15 HEAD -- . :!deploy_temp/node_modules', { cwd: path.resolve(__dirname, '..') }).toString();
        const files = diffOutput.split('\n').map(f => f.trim()).filter(f => f && fs.existsSync(path.resolve(__dirname, '../' + f)));

        console.log('Uploading files...');
        for (const file of files) {
            const localPath = path.resolve(__dirname, '../' + file);
            const remotePath = '/var/www/sellerarp/' + file.replace(/\\/g, '/');
            
            // create remote directory if it doesn't exist
            const remoteDir = path.dirname(remotePath).replace(/\\/g, '/');
            await ssh.execCommand(`mkdir -p "${remoteDir}"`);
            
            await ssh.putFile(localPath, remotePath);
            console.log(`Uploaded ${file}`);
        }

        console.log('Running npm install...');
        await ssh.execCommand('npm install', { cwd: '/var/www/sellerarp' });

        console.log('Running npm run build...');
        const result = await ssh.execCommand('npm run build', { cwd: '/var/www/sellerarp' });
        console.log('STDOUT: ' + result.stdout);
        console.log('STDERR: ' + result.stderr);

        console.log('Running Laravel migrations and optimization...');
        const clearOpt = await ssh.execCommand('php artisan optimize:clear', { cwd: '/var/www/sellerarp' });
        console.log('Clear Opt STDOUT: ' + clearOpt.stdout);
        
        const migrateResult = await ssh.execCommand('php artisan migrate --force', { cwd: '/var/www/sellerarp' });
        console.log('Migrate STDOUT: ' + migrateResult.stdout);
        if (migrateResult.stderr) {
            console.log('Migrate STDERR: ' + migrateResult.stderr);
        }

        const optResult = await ssh.execCommand('echo "clone2026" | sudo -S php artisan optimize', { cwd: '/var/www/sellerarp' });
        console.log('Optimize STDOUT: ' + optResult.stdout);

        await ssh.execCommand('echo "clone2026" | sudo -S chown -R www-data:www-data storage bootstrap/cache', { cwd: '/var/www/sellerarp' });

        console.log('Deployment complete!');
        ssh.dispose();
    } catch (err) {
        console.error('Error during deployment: ', err);
        ssh.dispose();
    }
}

deploy();
