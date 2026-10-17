// Compare synthetic fixtures from PHP Validator; never supply real passwords.
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/password-policy.js'), 'utf8').replace(/\r\n/g, '\n');
const start = source.indexOf('function passwordRequirementState(');
const end = source.indexOf('\ndocument.querySelectorAll', start);
if (start < 0 || end < start) throw new Error('Password feedback function unavailable');
const feedback = vm.runInNewContext('(' + source.slice(start, end) + ')', {TextEncoder});
const fixtures = JSON.parse(fs.readFileSync(0, 'utf8'));
let passed = 0;
for (const fixture of fixtures) {
  const state = feedback(fixture.value, fixture.minimum, 72, fixture.counts);
  if (Object.values(state).every(Boolean) !== fixture.accepted) throw new Error('Client/server password policy mismatch');
  passed++;
}
console.log(passed + ' synthetic password policy parity checks PASS');
