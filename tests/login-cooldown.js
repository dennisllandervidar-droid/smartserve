const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/login-cooldown.js'), 'utf8');
let checks = 0;
function check(ok, label) { checks++; assert.ok(ok, label); }
function page(initial, replies = []) {
    let now = 0, requests = 0;
    const elements = {
        'login-form': {dataset:{loginState:JSON.stringify(initial)}, addEventListener(type, fn) { this[type] = fn; }},
        'login-button': {}, 'login-button-label': {}, 'login-attempt-status': {}, 'login-attempt-message': {}
    };
    const intervals = [];
    const window = {location:{href:'http://localhost/smartserve/login'},addEventListener(type, fn){this[type]=fn;}};
    const context = {
        document:{getElementById:id=>elements[id],addEventListener(){},hidden:false},
        window, URL, performance:{now:()=>now},
        setInterval:fn=>intervals.push(fn),
        fetch:async()=>{requests++;const reply=replies.shift();if(reply instanceof Error)throw reply;return {ok:true,json:async()=>reply};}
    };
    vm.runInNewContext(source, context);
    return {elements, window, tick(milliseconds) {now=milliseconds;intervals[0]();}, requests:()=>requests};
}
const regular = page({attempts:1,remaining:4,locked:false,unlock_at:0,server_now:1000});
check(regular.elements['login-attempt-message'].textContent === '4 attempts remaining', 'First failure shows four attempts');
check(!regular.elements['login-button'].disabled && !regular.elements['login-attempt-status'].hidden, 'Remaining attempts keep login enabled');
const last = page({attempts:4,remaining:1,locked:false,unlock_at:0,server_now:1000});
check(last.elements['login-attempt-message'].textContent === '1 attempt remaining', 'Singular remaining attempt');
const locked = page({attempts:5,remaining:0,locked:true,unlock_at:1300,server_now:1000});
check(locked.elements['login-button'].disabled, 'Fifth failure disables login');
check(locked.elements['login-button-label'].textContent === 'Try again in 05:00', 'Five-minute timer starts');
locked.tick(1000);
check(locked.elements['login-button-label'].textContent === 'Try again in 04:59', 'Timer counts down');
locked.tick(90000);
check(locked.elements['login-button-label'].textContent === 'Try again in 03:30', 'Delayed rendering uses elapsed time');
const refreshed = page({attempts:5,remaining:0,locked:true,unlock_at:1300,server_now:1090});
check(refreshed.elements['login-button-label'].textContent === 'Try again in 03:30', 'Reload uses persisted server deadline');
let prevented = false;
refreshed.elements['login-form'].submit({preventDefault(){prevented=true;}});
check(prevented, 'Client blocks submission during cooldown');
(async()=>{
    const expiry = page({attempts:5,remaining:0,locked:true,unlock_at:1001,server_now:1000}, [new Error('Offline'),{attempts:0,remaining:5,locked:false,unlock_at:0,server_now:1003}]);
    expiry.tick(1000); await new Promise(resolve=>setImmediate(resolve));
    check(expiry.elements['login-button'].disabled && expiry.elements['login-button-label'].textContent === 'Checking cooldown…', 'Timer cannot enable login without server confirmation');
    expiry.tick(3000); await new Promise(resolve=>setImmediate(resolve));
    check(!expiry.elements['login-button'].disabled && expiry.elements['login-button-label'].textContent === 'Login Account', 'Server-confirmed expiry re-enables login');
    check(expiry.elements['login-attempt-status'].hidden, 'Expired counter is cleared');
    const otherTab = page({attempts:1,remaining:4,locked:false,unlock_at:0,server_now:1000},[{attempts:5,remaining:0,locked:true,unlock_at:1300,server_now:1000}]);
    otherTab.window.focus(); await new Promise(resolve=>setImmediate(resolve));
    check(otherTab.elements['login-button'].disabled, 'Returning to another tab refreshes server cooldown');
    console.log(`Passed ${checks} login countdown checks.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
