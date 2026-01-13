const fs = require('fs');
const path = 'assets/js/admin.js';
const code = fs.readFileSync(path,'utf8');
const lines = code.split(/\r?\n/);
for (let i=1;i<=lines.length;i++){
  const snippet = lines.slice(0,i).join('\n');
  try {
    new Function(snippet);
  } catch (e) {
    console.error('ERROR at line', i, '\n', e.message);
    console.error('Context:\n', lines.slice(Math.max(0,i-5), i+2).join('\n'));
    process.exit(1);
  }
}
console.log('No error found when parsing by incremental lines.');
