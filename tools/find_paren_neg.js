const fs = require('fs');
const s = fs.readFileSync('assets/js/admin.js','utf8');
let p=0, inS=false, inD=false, inB=false, esc=false;
for (let i=0;i<s.length;i++){
  const ch = s[i];
  if(esc){ esc=false; continue; }
  if(ch==='\\'){ esc=true; continue; }
  if(ch==="'" && !inD && !inB){ inS=!inS; continue; }
  if(ch==='"' && !inS && !inB){ inD=!inD; continue; }
  if(ch==='`' && !inS && !inD){ inB=!inB; continue; }
  if(inS||inD||inB) continue;
  if(ch==='(') p++; else if(ch===')') p--;
  if(p<0){
    // print context around i
    const start = Math.max(0, i-80);
    const end = Math.min(s.length, i+80);
    const context = s.slice(start,end);
    console.log('Negative paren at index', i, 'context:\n', context);
    // also print line number
    const prefix = s.slice(0,i);
    const line = prefix.split(/\r?\n/).length;
    console.log('line:', line);
    process.exit(0);
  }
}
console.log('No negative found, final p=',p);
