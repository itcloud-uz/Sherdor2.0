const { NodeSSH } = require('node-ssh');

async function runArtisanCommand(host, username, password, dir, name) {
    const ssh = new NodeSSH();
    try {
        await ssh.connect({ host, username, password });
        console.log(`--- CONNECTED TO ${name} (${host}) ---`);
        console.log(`Running scan:orphans on ${name}...`);
        const result = await ssh.execCommand('php artisan scan:orphans', { cwd: dir });
        console.log(`STDOUT:`);
        console.log(result.stdout);
        if (result.stderr) {
            console.log(`STDERR:`);
            console.log(result.stderr);
        }
        ssh.dispose();
    } catch (e) {
        console.error(`Error connecting/executing on ${name}:`, e.message);
        ssh.dispose();
    }
}

async function run() {
    // 1. Sherdor Server
    await runArtisanCommand('100.70.240.43', 'itcloud', 'clone1997', '/home/itcloud/Sherdor2.0', 'Sherdor');
    
    // 2. Hayot House Server
    await runArtisanCommand('100.69.189.125', 'haus', 'clone2026', '/var/www/sellerarp', 'Hayot House');
}

run();
