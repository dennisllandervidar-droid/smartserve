const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/appointment-live.js'), 'utf8');

function page(replies) {
    const requests = [], timers = [], events = {}, documentEvents = {};
    let reloads = 0;
    const window = {
        location: {href: 'http://localhost/smartserve/student/booking-details?id=TEST-CASHIER&status=0', reload() { reloads++; }},
        addEventListener(type, fn) { events[type] = fn; }
    };
    const document = {
        hidden: false,
        querySelector() { return {dataset: {ssAppointmentVersion: 'initial'}}; },
        addEventListener(type, fn) { documentEvents[type] = fn; }
    };
    vm.runInNewContext(source, {
        window, document, URL,
        setInterval(fn, delay) { timers.push({fn, delay}); },
        async fetch(url, options) {
            requests.push({url, options});
            const reply = replies.shift();
            if (reply instanceof Error) throw reply;
            if (typeof reply === 'function') return reply();
            return {ok: true, json: async () => reply};
        }
    });
    return {window, document, events, documentEvents, requests, timers, reloads: () => reloads};
}

(async () => {
    const unchanged = page([{version: 'initial'}]);
    assert.equal(unchanged.timers[0].delay, 5000, 'Visible appointments are checked every five seconds');
    await unchanged.timers[0].fn();
    assert.equal(unchanged.reloads(), 0, 'Unchanged appointments do not reload');
    assert.equal(unchanged.requests[0].url.searchParams.get('id'), 'TEST-CASHIER', 'Poll retains the ticket identity');
    assert.equal(unchanged.requests[0].url.searchParams.get('status'), '0', 'Poll retains the selected history filter');
    assert.equal(unchanged.requests[0].url.searchParams.get('appointment_status'), '1');
    assert.equal(unchanged.requests[0].options.cache, 'no-store', 'Status cannot come from stale browser cache');
    assert.equal(unchanged.requests[0].options.credentials, 'same-origin', 'Status uses the logged-in student session');

    const completed = page([{version: 'completed'}]);
    await completed.events.focus();
    await completed.timers[0].fn();
    assert.equal(completed.reloads(), 1, 'New completed status refreshes the ticket once');
    assert.equal(completed.requests.length, 1, 'No duplicate request while reload is pending');

    const hidden = page([{version: 'cancelled'}]);
    hidden.document.hidden = true;
    await hidden.timers[0].fn();
    assert.equal(hidden.requests.length, 0, 'Background tabs do not poll');
    hidden.document.hidden = false;
    await hidden.documentEvents.visibilitychange();
    assert.equal(hidden.reloads(), 1, 'Returning to a tab immediately receives cancellation updates');

    const offline = page([new Error('Offline'), {}, {version: 'initial'}, {version: 'completed'}]);
    await offline.timers[0].fn();
    await offline.timers[0].fn();
    assert.equal(offline.reloads(), 0, 'Connection errors or malformed replies leave the current page visible');
    await offline.events.pageshow();
    assert.equal(offline.reloads(), 0, 'Unchanged restored page is not reloaded');
    await offline.timers[0].fn();
    assert.equal(offline.reloads(), 1, 'Polling recovers after connection failure');

    let resolveRequest;
    const overlapping = page([() => new Promise(resolve => {resolveRequest = resolve;})]);
    const first = overlapping.timers[0].fn();
    await overlapping.events.focus();
    assert.equal(overlapping.requests.length, 1, 'Overlapping focus and timer events share one request');
    resolveRequest({ok: true, json: async () => ({version: 'initial'})});
    await first;

    const expired = page([() => ({ok: true, redirected: true, json: async () => {throw Error('Login HTML must not be parsed');}})]);
    await expired.timers[0].fn();
    assert.equal(expired.reloads(), 0, 'Expired sessions do not cause a reload loop');
    console.log('Passed 16 appointment live-refresh checks.');
})().catch(error => {console.error(error); process.exitCode = 1;});
