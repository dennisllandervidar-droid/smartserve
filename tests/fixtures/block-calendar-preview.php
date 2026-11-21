<?php
require_once __DIR__.'/../../global/validation.php';
if($_SERVER['REQUEST_METHOD']==='POST') {header('Content-Type: application/json');$days=[];$start=$_POST['calendar_start']??'';$end=$_POST['calendar_end']??'';if(!SmartServeValidation::date($start)||!SmartServeValidation::date($end)){echo '{}';exit;}
for($day=$start;$day<=$end;$day=date('Y-m-d',strtotime($day.' +1 day'))){$slots=[];foreach(SmartServeValidation::SLOTS as $time)$slots[$time]=['available'=>$time==='09:00 AM'?0:5,'label'=>$time==='09:00 AM'?'Reserved':'5 slot(s) left'];$days[$day]=['available'=>true,'slots'=>$slots];}echo json_encode($days);exit;}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartServe office block calendar preview</title><link rel="stylesheet" href="../../dashboard/assets/css/assets.css"><link rel="stylesheet" href="../../dashboard/assets/css/style.css"><link rel="stylesheet" href="../../assets/css/forms.css"><link rel="stylesheet" href="../../assets/css/schedule-calendar.css"><script src="../../assets/js/forms.js"></script><script src="../../assets/js/schedule-calendar.js" defer></script><style>:root{--primary-green:#005141}body{background:#f5f8f6;padding:24px}.preview-wrap{max-width:750px;margin:auto;background:white;padding:32px;border-radius:18px}</style></head><body><main class="preview-wrap"><h3>Block a schedule</h3><p class="text-muted">Synthetic preview · no schedules are saved</p><form method="POST">
                        <div class="form-group">
                            <label>Block Type</label>
                            <select name="block_type" id="block_type" class="form-control" required>
                                <option value="full_day">Full Day</option>
                                <option value="time_range">Time Range</option>
                                <option value="specific_purpose">Specific Purpose</option>
                            </select>
                        </div>
                        <div class="form-group" id="purpose_wrap" style="display:none;">
                            <label>Specific Purpose</label>
                            <select name="target_purpose" id="target_purpose" class="form-control">
                                <option value="">Select purpose</option>
                                
                                    <option value="Medical Checkup">Medical Checkup</option>
                                
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <div class="ss-calendar" data-schedule-calendar data-target="block_date" data-mode="block">
    <div class="ss-calendar-switch" role="group" aria-label="Date entry method">
        <button type="button" class="ss-calendar-toggle active" data-date-mode="calendar"><i class="ti-calendar"></i> Calendar</button>
        <button type="button" class="ss-calendar-toggle" data-date-mode="manual"><i class="ti-pencil"></i> Enter date</button>
    </div>
    <div class="ss-calendar-view"><div class="ss-calendar-heading"><button type="button" data-month-shift="-1" aria-label="Previous month">‹</button><strong class="ss-calendar-month"></strong><button type="button" data-month-shift="1" aria-label="Next month">›</button></div>
    <div class="ss-calendar-days" aria-label="Select a date"></div><small class="ss-calendar-note">Select a date or use Enter date.</small></div>
</div>

                            <input type="date" name="block_date" id="block_date" class="form-control" min="2026-10-13" required>
                        </div>
                        <div class="row" id="time_range_wrap" style="display:none;">
                            <div class="col-6">
    <div class="form-group">
        <label for="start_time">Start Time</label>
        <select name="start_time" id="start_time" class="form-control" aria-describedby="time_range_help">
            <option value="">Select start time</option>
                            <option value="07:00">7 AM</option>
                            <option value="08:00">8 AM</option>
                            <option value="09:00">9 AM</option>
                            <option value="10:00">10 AM</option>
                            <option value="11:00">11 AM</option>
                            <option value="12:00">12 PM</option>
                            <option value="13:00">1 PM</option>
                            <option value="14:00">2 PM</option>
                            <option value="15:00">3 PM</option>
                            <option value="16:00" disabled>4 PM</option>
                    </select>
    </div>
</div>
<div class="col-6">
    <div class="form-group">
        <label for="end_time">End Time</label>
        <select name="end_time" id="end_time" class="form-control" aria-describedby="time_range_help">
            <option value="">Select end time</option>
                            <option value="07:00">7 AM</option>
                            <option value="08:00">8 AM</option>
                            <option value="09:00">9 AM</option>
                            <option value="10:00">10 AM</option>
                            <option value="11:00">11 AM</option>
                            <option value="12:00">12 PM</option>
                            <option value="13:00">1 PM</option>
                            <option value="14:00">2 PM</option>
                            <option value="15:00">3 PM</option>
                            <option value="16:00">4 PM</option>
                    </select>
    </div>
</div>
<div class="col-12">
    <small id="time_range_help" class="text-muted d-block mb-3">Choose hourly times from 7 AM to 4 PM. End time must be later than start time.</small>
</div>

                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <input type="text" name="reason" class="form-control" placeholder="Doctor unavailable" required>
                        </div>
                        <div class="form-group">
                            <label>Note (Shown to students)</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="Please select another date/time."></textarea>
                        </div>
                        <button type="submit" name="submit_block" class="btn green radius-xl" style="background: var(--primary-green);">Save Block & Notify</button>
                    </form></main><script src="../../assets/js/block-schedules.js"></script></body></html>