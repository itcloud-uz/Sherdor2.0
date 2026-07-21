const { NodeSSH } = require('node-ssh');
const path = require('path');

async function checkServer(host, username, password, dir, name) {
    const ssh = new NodeSSH();
    try {
        await ssh.connect({ host, username, password });
        console.log(`\n========================================`);
        console.log(`=== REMOTE CHECK: ${name} (${host}) ===`);
        console.log(`========================================`);

        const localPhp = path.resolve(__dirname, 'check_kassa.php');
        const remotePhp = dir + '/check_kassa_temp.php';
        await ssh.putFile(localPhp, remotePhp);

        const res = await ssh.execCommand('php check_kassa_temp.php', { cwd: dir });
        console.log(res.stdout || res.stderr);

        await ssh.execCommand('rm -f check_kassa_temp.php', { cwd: dir });
        ssh.dispose();
    } catch (e) {
        console.error(`Error on ${name}:`, e.message);
        ssh.dispose();
    }
}

async function run() {
    await checkServer('100.70.240.43', 'itcloud', 'clone1997', '/home/itcloud/Sherdor2.0', 'Sherdor');
    await checkServer('100.69.189.125', 'haus', 'clone2026', '/var/www/sellerarp', 'Hayot House');
}

run();
