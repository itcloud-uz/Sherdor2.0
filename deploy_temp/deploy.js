const { NodeSSH } = require('node-ssh');
const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

const ssh = new NodeSSH();

async function deploy() {
    try {
        console.log('Connecting to remote server...');
        await ssh.connect({
            host: '100.70.240.43',
            username: 'itcloud',
            password: 'clone1997'
        });
        console.log('Connected!');

        const diffOutput = execSync('git diff --name-only origin/main HEAD -- . :!deploy_temp/node_modules', { cwd: path.resolve(__dirname, '..') }).toString();
        const files = diffOutput.split('\n')
            .map(f => f.trim())
            .filter(f => {
                if (!f) return false;
                const isProjectFile = f.startsWith('app/') || 
                                      f.startsWith('resources/') || 
                                      f.startsWith('routes/') || 
                                      f.startsWith('database/') || 
                                      f.startsWith('config/') ||
                                      f.startsWith('bootstrap/') ||
                                      f.startsWith('public/');
                return isProjectFile && fs.existsSync(path.resolve(__dirname, '../' + f));
            });

        console.log(`Uploading ${files.length} project files...`);
        for (const file of files) {
            const localPath = path.resolve(__dirname, '../' + file);
            const remotePath = '/home/itcloud/Sherdor2.0/' + file.replace(/\\/g, '/');
            
            const remoteDir = path.dirname(remotePath).replace(/\\/g, '/');
            await ssh.execCommand(`mkdir -p "${remoteDir}"`);
            
            await ssh.putFile(localPath, remotePath);
            console.log(`Uploaded ${file}`);
        }

        console.log('Clearing optimization cache...');
        await ssh.execCommand('php artisan optimize:clear', { cwd: '/home/itcloud/Sherdor2.0' });

        console.log('Running migrations...');
        const migrateResult = await ssh.execCommand('php artisan migrate --force', { cwd: '/home/itcloud/Sherdor2.0' });
        console.log('Migration output:', migrateResult.stdout || migrateResult.stderr);

        console.log('Running npm run build...');
        const buildResult = await ssh.execCommand('npm run build', { cwd: '/home/itcloud/Sherdor2.0' });
        console.log('Build STDOUT:', buildResult.stdout);
        console.log('Build STDERR:', buildResult.stderr);

        console.log('Running final optimization and permission checks...');
        await ssh.execCommand("echo 'clone1997' | sudo -S php artisan optimize", { cwd: '/home/itcloud/Sherdor2.0' });
        await ssh.execCommand("echo 'clone1997' | sudo -S chown -R www-data:www-data storage bootstrap/cache", { cwd: '/home/itcloud/Sherdor2.0' });

        console.log('Deployment complete!');
        ssh.dispose();
    } catch (err) {
        console.error('Error during deployment: ', err);
        ssh.dispose();
    }
}

deploy();
