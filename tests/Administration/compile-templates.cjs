const fs = require('node:fs');
const path = require('node:path');
const { compile } = require(process.argv[2]);

const root = path.resolve(__dirname, '../../src/Resources/app/administration/src');
function check(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const file = path.join(directory, entry.name);
        if (entry.isDirectory()) check(file);
        else if (file.endsWith('.html.twig')) {
            const template = fs.readFileSync(file, 'utf8').replace(/{%[\s\S]*?%}/g, '');
            compile(template, { mode: 'module' });
            console.log('Compiled ' + path.relative(root, file));
        }
    }
}
check(root);
