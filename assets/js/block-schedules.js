(function () {
    'use strict';
    const blockType = document.getElementById('block_type');
    const range = document.getElementById('time_range_wrap');
    const start = document.getElementById('start_time');
    const end = document.getElementById('end_time');
    const purposeWrap = document.getElementById('purpose_wrap');
    const purpose = document.getElementById('target_purpose');
    if (!blockType || !range || !start || !end) return;

    function updateEndTimes() {
        const minimum = start.value || '07:00';
        for (const option of end.options) {
            option.disabled = !!option.value && option.value <= minimum;
        }
        if (end.value && end.value <= minimum) end.value = '';
        end.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function toggleBlockType() {
        const isRange = blockType.value === 'time_range';
        const isPurpose = blockType.value === 'specific_purpose';
        range.style.display = isRange ? '' : 'none';
        for (const field of [start, end]) {
            field.required = isRange;
            field.disabled = !isRange;
            if (!isRange) {
                field.value = '';
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        if (purposeWrap) purposeWrap.style.display = isPurpose ? '' : 'none';
        if (purpose) {
            purpose.required = isPurpose;
            purpose.disabled = !isPurpose;
            if (!isPurpose) purpose.value = '';
        }
        if (isRange) updateEndTimes();
    }

    blockType.addEventListener('change', toggleBlockType);
    start.addEventListener('change', updateEndTimes);
    toggleBlockType();
})();
