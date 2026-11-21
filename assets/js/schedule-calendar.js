(function () {
    const format = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
    function init(root) {
        const input = document.getElementById(root.dataset.target);
        if (!input) return;
        const booking = root.dataset.mode === 'booking';
        root.classList.toggle('ss-weekdays-only', booking);
        const form = input.form;
        const grid = root.querySelector('.ss-calendar-days');
        const note = root.querySelector('.ss-calendar-note');
        const now = new Date(input.min + 'T00:00:00');
        let month = new Date(now.getFullYear(), now.getMonth(), 1), availability = {}, loading = false, revision = 0;
        input.classList.add('ss-calendar-date'); input.readOnly = true;
        const offices = () => booking ? Array.from(form.querySelectorAll('.office-checkbox:checked')).map(i=>i.value) : [root.dataset.office];
        function render() {
            grid.replaceChildren();
            root.querySelector('.ss-calendar-month').textContent = month.toLocaleDateString('en-PH', {month:'long', year:'numeric'});
            for (const day of (booking ? ['Mon','Tue','Wed','Thu','Fri'] : ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'])) { const span=document.createElement('span'); span.className='ss-calendar-weekday'; span.textContent=day; grid.append(span); }
            const offset = booking ? (month.getDay() >= 1 && month.getDay() <= 5 ? month.getDay()-1 : 0) : month.getDay();
            for (let i=0;i<offset;i++) grid.append(document.createElement('span'));
            const count = new Date(month.getFullYear(),month.getMonth()+1,0).getDate();
            for (let day=1;day<=count;day++) {
                const date=format(new Date(month.getFullYear(),month.getMonth(),day));
                if (booking && !SmartServe.rules.weekdayDate(date)) continue;
                const button=document.createElement('button'); button.type='button'; button.textContent=String(day); button.dataset.date=date;
                const unavailable=availability[date]?.available === false;
                button.disabled = date<input.min || (input.max && date>input.max) || loading || unavailable || (booking && !offices().length);
                button.classList.toggle('selected', input.value===date);
                button.setAttribute('aria-label',date + (unavailable?' — unavailable':''));
                button.setAttribute('aria-pressed',String(input.value===date));
                if (availability[date]?.note) button.title=availability[date].note;
                button.addEventListener('click',()=>{input.value=date; input.dispatchEvent(new Event('change',{bubbles:true})); if (booking) showTimes(); else showBlockTimes();});
                grid.append(button);
            }
        }
        async function refresh() {
            const selected=offices(); const current=++revision;
            availability={}; loading=!booking || selected.length>0; render();
            if (booking && !selected.length) { note.textContent='Select an office first to see available dates.'; return; }
            note.textContent='Checking available dates…';
            const data=booking ? new URLSearchParams({fetch_calendar:'1',date:input.min}) : new URLSearchParams({fetch_block_calendar:'1',calendar_start:format(month),calendar_end:format(new Date(month.getFullYear(),month.getMonth()+1,0))});
            if(booking)selected.forEach(id=>data.append('office_ids[]',id));
            try {
                const response=await fetch(window.location.href,{method:'POST',body:data}); const result=await response.json();
                if (current!==revision) return;
                if (result.status==='error') throw new Error(result.msg);
                availability=result; loading=false; note.textContent=booking?'Bookings are available Monday to Friday, up to seven days ahead. Gray dates have no available time.':'Gray dates are already blocked. Select a date to inspect booked and blocked times before saving a block.'; render();
            } catch (error) { if(current!==revision)return; loading=true; note.textContent='Unable to load availability. Select an office again to retry.'; render(); }
        }
        let dialog;
        function showTimes() {
            const times=document.getElementById('time-container'); if(!times)return;
            const anchor=document.createComment('time-slot-position'); times.before(anchor);
            dialog=document.createElement('dialog'); dialog.className='ss-time-dialog';
            const title=document.createElement('h5'); title.textContent='Available times · '+input.value;
            const close=document.createElement('button'); close.type='button'; close.className='btn green radius-xl mt-3'; close.textContent='Done';
            dialog.append(title,times,close); form.append(dialog); close.addEventListener('click',()=>dialog.close());
            dialog.addEventListener('close',()=>{anchor.replaceWith(times); dialog.remove(); dialog=null;});
            dialog.addEventListener('click',event=>{if(event.target.closest('.time-slot.available')) setTimeout(()=>dialog?.close(),0);});
            dialog.showModal();
        }
        function showBlockTimes() {
            const useRange=form.querySelector('[name="block_type"]')?.value==='time_range';
            const range=useRange?document.getElementById('time_range_wrap'):null;
            const slots=availability[input.value]?.slots || {};
            const overview=document.createElement('div'); overview.className='time-grid';
            Object.entries(slots).forEach(([time,slot])=>{const item=document.createElement('div');item.className='ss-block-time '+(slot.available>0?'':'unavailable');item.textContent=time+' · '+slot.label;overview.append(item);});
            if(!range) {
                const modal=document.createElement('dialog');modal.className='ss-time-dialog';
                const title=document.createElement('h5');title.textContent='Schedule · '+input.value;
                const done=document.createElement('button');done.type='button';done.className='btn green radius-xl mt-3';done.textContent='Use this date';
                modal.append(title,overview,done);form.append(modal);done.addEventListener('click',()=>modal.close());modal.addEventListener('close',()=>modal.remove());modal.showModal();return;
            }
            const anchor=document.createComment('block-time-position'); range.before(anchor);
            const modal=document.createElement('dialog'); modal.className='ss-time-dialog';
            const title=document.createElement('h5'); title.textContent='Block times · '+input.value;
            const done=document.createElement('button'); done.type='button'; done.className='btn green radius-xl'; done.textContent='Use this time range';
            modal.append(title,overview,range,done); form.append(modal);
            done.addEventListener('click',()=>{ if(!SmartServe.rules.blockTimeRange(form.elements.start_time.value,form.elements.end_time.value)) { note.textContent='Select a start time and a later end time.'; modal.querySelector('h5').textContent='Select a start time and a later end time'; return; } modal.close(); });
            modal.addEventListener('close',()=>{anchor.replaceWith(range); modal.remove();}); modal.showModal();
        }
        root.querySelectorAll('[data-date-mode]').forEach(button=>button.addEventListener('click',()=>{
            const manual=button.dataset.dateMode==='manual'; input.readOnly=!manual;
            root.querySelector('.ss-calendar-view').hidden=manual;
            root.querySelectorAll('[data-date-mode]').forEach(other=>other.classList.toggle('active',other===button));
            if(manual)input.focus();
        }));
        root.querySelectorAll('[data-month-shift]').forEach(button=>button.addEventListener('click',()=>{month=new Date(month.getFullYear(),month.getMonth()+Number(button.dataset.monthShift),1);if(booking)render();else refresh();}));
        input.addEventListener('change',render);
        form.querySelectorAll('.office-checkbox').forEach(checkbox=>checkbox.addEventListener('change',refresh));
        refresh();
    }
    function start(){document.querySelectorAll('[data-schedule-calendar]').forEach(init);}
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
}());
