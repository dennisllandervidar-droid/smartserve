const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const sandbox = { window: {}, document: { readyState: 'loading', addEventListener() {} }, TextEncoder };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/forms.js'), 'utf8'), sandbox);
const rules = sandbox.window.SmartServe.rules;
let checks = 0;
function check(actual, expected, label) { checks++; assert.equal(!!actual, expected, label); }
for (const name of ['Juan', 'María-José', 'Dela Cruz', 'Peña', 'Jose\u0301']) check(rules.name(name), true, name);
for (const name of ['Juan2', 'Juan!', "O'Neil", '   ', '--', 'Juan\nCruz', '<script>']) check(rules.name(name), false, name);
check(rules.name('', false), true, 'Optional middle name');
check(rules.name('ñ'.repeat(51)), false, 'Name limit');
for (const phone of ['09123456789', '+639123456789']) check(rules.phone(phone), true, phone);
for (const phone of ['0912345678', '091234567890', '+63912345678', '+6391234567890', '08123456789', '639123456789', '+6309123456789', '09 123456789', '09123-456789', '09abcdefghi', '０９１２３４５６７８９']) check(rules.phone(phone), false, phone);
for (const email of ['sample@edu.ph', 'a+b@example.com', 'sample@example.co.uk', 'juan@sub.example.ph']) check(rules.email(email), true, email);
for (const email of ['sample@edu', 'sample@', 'sample@edu.', 'sample@.edu.ph', 'sample@example.c', 'sample@-edu.ph', 'sample@edu..ph', 'sample @edu.ph', '.sample@edu.ph', 'sample..x@edu.ph']) check(rules.email(email), false, email);
check(rules.password('Secure#123'), true, 'Strong password');
check(rules.password('Ab#1ñññ'), false, 'Seven Unicode characters do not meet the minimum');
check(rules.password('Ab#1ññññ'), true, 'Eight Unicode characters meet the minimum');
check(rules.password('a'.repeat(68) + 'Ab#1'), true, '72-character ASCII password fits secure storage');
check(rules.password('Secure#1\0'), false, 'Null characters cannot reach password hashing');
for (const password of ['12345678', 'password', 'Secure123', 'Weak#1', 'Secure 123!', 'a'.repeat(70) + '#12', 'ñ'.repeat(35) + 'Ab#1']) check(rules.password(password), false, password);
const blockHours = Array.from({ length: 10 }, (_, i) => String(i + 7).padStart(2, '0') + ':00');
for (const day of ['2026-11-09','2026-11-10','2026-11-11','2026-11-12','2026-11-13']) check(rules.weekdayDate(day), true, 'Weekday allowed ' + day);
for (const day of ['2026-11-14','2026-11-15','2026-02-31','invalid','', ['2026-11-11']]) check(rules.weekdayDate(day), false, 'Weekend/invalid date rejected');
for (const start of blockHours) {
    check(rules.blockHour(start), true, 'Allowed hour ' + start);
    for (const end of blockHours) check(rules.blockTimeRange(start, end), start < end, start + ' to ' + end);
}
for (const invalid of ['06:00', '17:00', '07:30', '16:01', '07:00:00', '7:00', '', '10:00 AM', ['07:00']]) {
    check(rules.blockHour(invalid), false, 'Invalid block hour');
    check(rules.blockTimeRange(invalid, '16:00'), false, 'Invalid range start');
    check(rules.blockTimeRange('07:00', invalid), false, 'Invalid range end');
}
// HTML patterns compile under the current Unicode Sets flag used by browsers.
for (const pattern of ['[\\p{L}\\p{M} \\-]+', '(?:09[0-9]{9}|\\+639[0-9]{9})', '[^@\\s]+@[A-Za-z0-9](?:[A-Za-z0-9\\-]*[A-Za-z0-9])?(?:\\.[A-Za-z0-9](?:[A-Za-z0-9\\-]*[A-Za-z0-9])?)*\\.[A-Za-z]{2,63}']) {
    assert.doesNotThrow(() => new RegExp('^(?:' + pattern + ')$', 'v'));
    checks++;
}
console.log(`Passed ${checks} browser-rule checks.`);
