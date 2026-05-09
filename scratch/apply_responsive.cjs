const fs = require('fs');
const path = require('path');

function processFile(filePath) {
    let content = fs.readFileSync(filePath, 'utf8');
    let original = content;

    // 1. Fix grid columns
    content = content.replace(/class="([^"]*)grid-cols-4([^"]*)"/g, (match, p1, p2) => {
        if (p1.includes('sm:') || p2.includes('sm:')) return match; // already responsive
        return `class="${p1}grid-cols-1 sm:grid-cols-2 lg:grid-cols-4${p2}"`;
    });

    content = content.replace(/class="([^"]*)grid-cols-3([^"]*)"/g, (match, p1, p2) => {
        if (p1.includes('sm:') || p2.includes('sm:')) return match;
        return `class="${p1}grid-cols-1 sm:grid-cols-2 lg:grid-cols-3${p2}"`;
    });
    
    content = content.replace(/class="([^"]*)grid-cols-2([^"]*)"/g, (match, p1, p2) => {
        if (p1.includes('sm:') || p2.includes('sm:')) return match;
        return `class="${p1}grid-cols-1 sm:grid-cols-2${p2}"`;
    });

    // 2. Wrap tables with overflow-x-auto if not already wrapped
    // This is tricky with regex, but we can try to find <table and if the previous tag isn't overflow-x-auto, we just add a class block-level wrapper or we can add block w-full overflow-x-auto to the table itself.
    // Actually, adding block overflow-x-auto to table directly often works well in Tailwind!
    // Let's add it to the table class
    content = content.replace(/<table class="([^"]*)"/g, (match, p1) => {
        if (!p1.includes('block') && !p1.includes('overflow-x-auto')) {
            return `<table class="block w-full overflow-x-auto whitespace-nowrap ${p1}"`;
        }
        return match;
    });

    // 3. Global padding fix: look for 'p-8' inside div and change to 'p-4 md:p-8'
    content = content.replace(/class="([^"]*)\bp-8\b([^"]*)"/g, (match, p1, p2) => {
        if (p1.includes('md:p-8') || p2.includes('md:p-8')) return match;
        return `class="${p1}p-4 md:p-8${p2}"`;
    });
    
    // 4. Change min-w-[500px] or fixed widths that break mobile to w-full max-w-[...]
    content = content.replace(/class="([^"]*)\bw-\[([0-9]+)px\]\b([^"]*)"/g, (match, p1, p2, p3) => {
        if (p1.includes('max-w') || p3.includes('max-w')) return match;
        return `class="${p1}w-full max-w-[${p2}px]${p3}"`;
    });

    if (content !== original) {
        fs.writeFileSync(filePath, content);
        console.log(`Updated ${filePath}`);
    }
}

function scanDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            scanDir(fullPath);
        } else if (fullPath.endsWith('.vue')) {
            processFile(fullPath);
        }
    }
}

scanDir('d:/Sherdor 2.0/resources/js/Pages');
scanDir('d:/Sherdor 2.0/resources/js/Layouts');
scanDir('d:/Sherdor 2.0/resources/js/Components');
