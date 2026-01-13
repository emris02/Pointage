const fs = require('fs');
const code = fs.readFileSync('assets/js/admin.js','utf8');
const lines = code.split(/\r?\n/);
let low = 0, high = lines.length, good = 0;
while (low <= high) {
  const mid = Math.floor((low + high) / 2);
  const snippet = lines.slice(0, mid).join('\n');
  try {
    new Function(snippet);
    good = mid; low = mid + 1;
  } catch (e) {
    high = mid - 1;
  }
}
console.log('Max parseable prefix lines:', good);
console.log('Problem context around line', good+1,':');
const start = Math.max(0, good-5);
const end = Math.min(lines.length, good+5);
console.log(lines.slice(start, end).map((l,i)=>`${start+i+1}: ${l}`).join('\n'));
