const fs = require('fs');
const vm = require('vm');
const code = fs.readFileSync('assets/js/admin.js','utf8');
try {
  new vm.Script(code, { filename: 'assets/js/admin.js' });
  console.log('VM_PARSE_OK');
} catch (e) {
  console.error('VM_PARSE_ERROR');
  console.error(e.stack);
  process.exit(1);
}
