const fs = require('fs');
const s = fs.readFileSync('assets/js/admin.js','utf8');
const lines = s.split(/\r?\n/);
let inSingle=false, inDouble=false, inBack=false, prev='';
let paren=0;
for (let i=0;i<lines.length;i++){
  const line = lines[i];
  for (let j=0;j<line.length;j++){
    const ch = line[j];
    if (ch==='\\' && prev!=='\\') { prev=ch; continue; }
    if (!inSingle && !inDouble && !inBack) {
      if (ch==='(') paren++;
      else if (ch===')') paren--;
    }
    if (ch==="'" && !inDouble && !inBack && prev!=='\\') inSingle=!inSingle;
    if (ch==='"' && !inSingle && !inBack && prev!=='\\') inDouble=!inDouble;
    if (ch==='`' && !inSingle && !inDouble && prev!=='\\') inBack=!inBack;
    prev=ch;
  }
  if (paren<0) {
    console.log('Negative paren at line', i+1, 'count', paren);
    console.log(lines.slice(Math.max(0,i-3),i+2).map((l,idx)=>`${i-3+idx+1}: ${l}`).join('\n'));
    process.exit(0);
  }
}
console.log('Done. final paren count', paren);
