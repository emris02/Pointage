const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets', 'js', 'admin.js');
const s = fs.readFileSync(file, 'utf8');

let paren = 0, brace = 0, brack = 0;
let inSingle = false, inDouble = false, inBack = false, escaped = false;
let firstNegative = null;
const lines = s.split(/\r?\n/);
for (let i = 0; i < lines.length; i++) {
  const L = lines[i];
  for (let j = 0; j < L.length; j++) {
    const ch = L[j];
    if (escaped) { escaped = false; continue; }
    if (ch === '\\') { escaped = true; continue; }
    if (!inSingle && !inDouble && !inBack) {
      if (ch === '(') paren++;
      else if (ch === ')') paren--;
      else if (ch === '{') brace++;
      else if (ch === '}') brace--;
      else if (ch === '[') brack++;
      else if (ch === ']') brack--;
    }
    if (ch === "'" && !inDouble && !inBack) inSingle = !inSingle;
    else if (ch === '"' && !inSingle && !inBack) inDouble = !inDouble;
    else if (ch === '`' && !inSingle && !inDouble) inBack = !inBack;

    if (paren < 0 && !firstNegative) {
      firstNegative = { line: i + 1, col: j + 1, ctx: lines.slice(Math.max(0, i - 3), i + 2) };
    }
    if (brace < 0) { console.log('Negative brace at line', i + 1); process.exit(0); }
    if (brack < 0) { console.log('Negative bracket at line', i + 1); process.exit(0); }
  }
}

if (firstNegative) {
  console.log('First negative paren at line', firstNegative.line, 'col', firstNegative.col);
  console.log(firstNegative.ctx.map((x,idx)=>((Math.max(0, firstNegative.line-4)+idx)+1)+': '+x).join('\n'));
}
console.log('Final counts -> paren', paren, 'brace', brace, 'brack', brack, 'inSingle', inSingle, 'inDouble', inDouble, 'inBack', inBack);
