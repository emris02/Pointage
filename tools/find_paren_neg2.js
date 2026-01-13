const fs = require('fs');
const s = fs.readFileSync('assets/js/admin.js','utf8');
let paren=0, inSingle=false, inDouble=false, inBack=false, prev='';
for (let i=0;i<s.length;i++){
  const ch=s[i];
  if (ch==='\\' && prev!=='\\') { prev=ch; continue; }
  if (!inSingle && !inDouble && !inBack) {
    if (ch==='(') paren++;
    else if (ch===')') paren--;
  }
  if (ch==="'" && !inDouble && !inBack && prev!=='\\') inSingle=!inSingle;
  if (ch==='"' && !inSingle && !inBack && prev!=='\\') inDouble=!inDouble;
  if (ch==='`' && !inSingle && !inDouble && prev!=='\\') inBack=!inBack;
  if (paren<0){
    const start = Math.max(0, i-80);
    const end = Math.min(s.length, i+80);
    const context = s.slice(start,end);
    console.log('Negative paren at index', i, 'line', s.slice(0,i).split(/\r?\n/).length);
    console.log('context:\n', context);
    process.exit(0);
  }
  prev=ch;
}
console.log('final paren count',paren);
