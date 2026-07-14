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

        const diffOutput = execSync('git diff --name-only HEAD~12 HEAD -- . :!deploy_temp/node_modules', { cwd: path.resolve(__dirname, '..') }).toString();
        const files = diffOutput.split('\n').map(f => f.trim()).filter(f => f && fs.existsSync(path.resolve(__dirname, '../' + f)));

        console.log('Uploading files...');
        for (const file of files) {
            const localPath = path.resolve(__dirname, '../' + file);
            const remotePath = '/home/itcloud/Sherdor2.0/' + file.replace(/\\/g, '/');
            
            // create remote directory if it doesn't exist
            const remoteDir = path.dirname(remotePath).replace(/\\/g, '/');
            await ssh.execCommand(`mkdir -p "${remoteDir}"`);
            
            await ssh.putFile(localPath, remotePath);
            console.log(`Uploaded ${file}`);
        }

        console.log('Running npm install...');
        await ssh.execCommand('npm install', { cwd: '/home/itcloud/Sherdor2.0' });

        console.log('Running npm run build...');
        const result = await ssh.execCommand('npm run build', { cwd: '/home/itcloud/Sherdor2.0' });
        console.log('STDOUT: ' + result.stdout);
        console.log('STDERR: ' + result.stderr);

        console.log('Deployment complete!');
        ssh.dispose();
    } catch (err) {
        console.error('Error during deployment: ', err);
        ssh.dispose();
    }
}

deploy();
