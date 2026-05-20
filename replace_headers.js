const fs = require('fs');
const path = require('path');

function walk(dir) {
    let results = [];
    const list = fs.readdirSync(dir);
    list.forEach(function(file) {
        file = path.join(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) { 
            results = results.concat(walk(file));
        } else if (file.endsWith('.vue')) {
            results.push(file);
        }
    });
    return results;
}

const files = walk(path.join(__dirname, 'resources/js/Pages/Admin'));

files.forEach(f => {
    let content = fs.readFileSync(f, 'utf8');
    // Remove the h1 block with tracking-widest uppercase drop-shadow etc
    // Usually it's wrapped in `<div class="mb-8">...</div>` or `<div class="mb-6">...</div>`
    // We'll replace matching lines
    let newContent = content.replace(/<div class="mb-[0-9]+">\s*<h1 class="[^"]*font-display[^"]*">[\s\S]*?<\/h1>\s*<\/div>/g, '');
    
    if (newContent !== content) {
        fs.writeFileSync(f, newContent, 'utf8');
        console.log('Removed header from: ' + f);
    }
});
