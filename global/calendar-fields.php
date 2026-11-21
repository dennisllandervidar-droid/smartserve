<div class="ss-calendar" data-schedule-calendar data-target="<?php echo htmlspecialchars($calendarTarget, ENT_QUOTES); ?>" data-mode="<?php echo htmlspecialchars($calendarMode, ENT_QUOTES); ?>">
    <div class="ss-calendar-switch" role="group" aria-label="Date entry method">
        <button type="button" class="ss-calendar-toggle active" data-date-mode="calendar"><i class="ti-calendar"></i> Calendar</button>
        <button type="button" class="ss-calendar-toggle" data-date-mode="manual"><i class="ti-pencil"></i> Enter date</button>
    </div>
    <div class="ss-calendar-view"><div class="ss-calendar-heading"><button type="button" data-month-shift="-1" aria-label="Previous month">‹</button><strong class="ss-calendar-month"></strong><button type="button" data-month-shift="1" aria-label="Next month">›</button></div>
    <div class="ss-calendar-days" aria-label="Select a date"></div><small class="ss-calendar-note">Select a date or use Enter date.</small></div>
</div>
