const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/home-reminders.js'), 'utf8');
const storage = new Map();
function visit(storageDisabled = false) {
    let shows = 0;
    let dismiss;
    vm.runInNewContext(source, {
        document: { readyState: 'complete', getElementById: id => id === 'importantRemindersModal' ? {} : { addEventListener: (_, handler) => { dismiss = handler; } } },
        localStorage: {
            getItem(key) { if (storageDisabled) throw Error('disabled'); return storage.get(key); },
            setItem(key, value) { if (storageDisabled) throw Error('disabled'); storage.set(key, value); }
        },
        bootstrap: { Modal: class { show() { shows++; } } }
    });
    return { shows, dismiss };
}
assert.equal(visit().shows, 1, 'First visit shows reminders');
assert.equal(visit().shows, 1, 'Ordinary dismissal does not suppress later visits');
visit().dismiss();
assert.equal(visit().shows, 0, 'Do not show again persists across visits');
assert.equal(visit(true).shows, 1, 'Disabled storage still permits viewing reminders');
assert.doesNotThrow(() => visit(true).dismiss(), 'Disabled storage does not break dismissal');
console.log('Passed 5 homepage reminder checks.');
