const fs = require('fs');
const path = 'assets/js/admin.js';
try {
  const code = fs.readFileSync(path, 'utf8');
  new Function(code);
  console.log('PARSE_OK');
} catch (e) {
  console.error('PARSE_ERROR:');
  console.error(e && e.stack ? e.stack : e);
  process.exit(1);
}
