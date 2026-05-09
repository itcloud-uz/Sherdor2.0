const fs = require('fs');
const path = require('path');

function scanFile(filePath) {
    const content = fs.readFileSync(filePath, 'utf-8');
    
    // Very simple heuristic to find raw text:
    // Look for text between > and < that contains word characters
    // Filter out standard vue syntax and numbers
    const templateMatch = content.match(/<template>([\s\S]*?)<\/template>/);
    if (!templateMatch) return;

    let template = templateMatch[1];
    
    // Remove comments
    template = template.replace(/<!--[\s\S]*?-->/g, '');
    
    // Find content between tags
    const matches = template.matchAll(/>([^<{}]+)</g);
    
    const strings = new Set();
    for (const match of matches) {
        const text = match[1].trim();
        if (text && /[a-zA-Z\u0400-\u04FF]/.test(text) && !text.includes('{{') && !text.includes('}}')) {
            strings.add(text);
        }
    }
    
    if (strings.size > 0) {
        console.log(`\n--- ${filePath} ---`);
        for (const str of strings) {
            console.log(`"${str}"`);
        }
    }
}

function scanDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            scanDir(fullPath);
        } else if (fullPath.endsWith('.vue')) {
            scanFile(fullPath);
        }
    }
}

scanDir('d:/Sherdor 2.0/resources/js/Pages/Admin/Finance');
