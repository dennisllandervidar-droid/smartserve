<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__.'/../../global/validation.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 header('Content-Type: application/json');
 if(isset($_POST['fetch_calendar'])) { $days=[]; for($i=0;$i<=7;$i++){$day=date('Y-m-d',strtotime("+$i days"));$weekday=SmartServeValidation::weekdayDate($day);$days[$day]=['available'=>$weekday && $i!==1,'note'=>!$weekday?'Weekdays only':($i===1?'Blocked date':'Available times')];}echo json_encode($days);exit; }
 if(isset($_POST['fetch_slots'])) { $slots=[];$weekday=SmartServeValidation::bookingDate($_POST['date'] ?? '');foreach(SmartServeValidation::SLOTS as $time)$slots[$time]=['available'=>!$weekday||in_array($time,['09:00 AM','10:00 AM'])?0:5,'label'=>!$weekday?'Weekdays only':($time==='09:00 AM'?'Blocked':($time==='10:00 AM'?'Reserved':'5 slot(s) left')),'note'=>'Synthetic test schedule'];echo json_encode($slots);exit; }
 if(isset($_POST['check_purpose_block'])){echo json_encode(['blocked'=>false]);exit;}
 if(isset($_POST['submit_appointment'])) {
  $errors=SmartServeValidation::request($_POST,'/student/booking.php');
  http_response_code($errors ? 422 : 200);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Booking preview result</title></head><body><h1>'.($errors ? 'Preview request needs correction' : 'Preview request accepted').'</h1><p>No booking is saved.</p>';
  foreach($errors as $error) echo '<p>'.htmlspecialchars($error,ENT_QUOTES).'</p>';
  foreach(['appointment_date'=>'Date','appointment_time'=>'Time','purpose_option'=>'Purpose'] as $field=>$label) {
   $value=$_POST[$field] ?? '';echo '<p>'.$label.': '.(is_string($value) ? htmlspecialchars($value,ENT_QUOTES) : '').'</p>';
  }
  echo '<a href="scheduling-preview.php">Return to booking preview</a></body></html>';exit;
 }
 echo json_encode(['status'=>'error','msg'=>'Preview only; no booking is saved.']);exit;
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartServe booking preview</title><link rel="stylesheet" href="../../dashboard/assets/css/assets.css"><link rel="stylesheet" href="../../dashboard/assets/css/style.css"><link rel="stylesheet" href="../../assets/css/forms.css"><link rel="stylesheet" href="../../assets/css/schedule-calendar.css"><script src="../../assets/js/forms.js"></script><script src="../../assets/js/schedule-calendar.js" defer></script><style>
        :root { --primary-green: #005141; --soft-green: #eef7f1; }
        
        .appointment-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 40px; border: 1px solid rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #2d3436; margin-bottom: 8px; display: block; }
        
        .custom-input { 
            border-radius: 12px; border: 2px solid #eee; padding: 12px 20px; 
            width: 100%; outline: none; transition: 0.3s; background: #fff;
            color: #495057; font-size: 1rem;
        }
        .custom-input:focus { border-color: var(--primary-green); box-shadow: 0 0 0 4px var(--soft-green); }

        .readonly-box { background: #f8f9fa; border: 2px solid #eee; border-radius: 12px; padding: 12px 20px; color: #6c757d; font-weight: 500; cursor: not-allowed; }

        /* --- Checkbox Cards for Offices --- */
        .office-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .office-card-label { cursor: pointer; margin: 0; }
        .office-card-label input[type="checkbox"] { display: none; }
        .office-card {
            border: 2px solid #eee; border-radius: 12px; padding: 15px; background: #fff;
            display: flex; align-items: center; gap: 10px; transition: 0.2s; font-weight: 500; color: #4a5568;
        }
        .office-card i { font-size: 1.2rem; color: #a0aec0; }
        .office-card-label input[type="checkbox"]:checked + .office-card {
            border-color: var(--primary-green); background: var(--soft-green); color: var(--primary-green);
        }
        .office-card-label input[type="checkbox"]:checked + .office-card i { color: var(--primary-green); }

        /* --- Time Slot Grid --- */
        .time-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; margin-top: 15px; }
        .time-slot { background: #f8f9fa; border: 2px solid #eee; border-radius: 12px; padding: 15px 10px; text-align: center; cursor: pointer; transition: 0.2s; }
        .time-slot.available:hover { border-color: var(--primary-green); background: var(--soft-green); }
        .time-slot.available.selected { background: var(--primary-green); border-color: var(--primary-green); color: white; }
        .time-slot.unavailable { background: #fdf2f2; border-color: #f8d7da; color: #dc3545; cursor: not-allowed; opacity: 0.7; }
        .time-text { font-weight: 700; display: block; }
        .slot-count { font-size: 0.8rem; font-weight: 500; }
        .time-slot.selected .slot-count { color: rgba(255,255,255,0.8); }

        /* --- Reminder Box (Light Red Theme) --- */
        .instructions-box {
            background: #fff2f2;
            border: 1px solid #f3caca;
            border-radius: 20px;
            padding: 30px;
            margin-top: 20px;
            color: #5c1f1f;
            box-shadow: 0 8px 22px rgba(157, 27, 27, 0.08);
        }

        .instructions-box h5 {
            color: #8b1d1d;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .route-path {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .route-step {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 10px 18px;
            border-radius: 50px;
            border: 1px solid #f2d4d4;
        }

        .route-step-num {
            background: #ef4444;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
        }

        .step-final .route-step-num { background: #c23c3c; }

        .route-step-text strong {
            display: block;
            font-size: 0.95rem;
            color: #5c1f1f;
            line-height: 1.2;
        }

        .route-step-text span {
            font-size: 0.72rem;
            color: rgba(92, 31, 31, 0.62);
            text-transform: uppercase;
        }

        .route-arrow { color: rgba(92, 31, 31, 0.35); }
        
        .time-warning {
            background: #ffe5e5;
            color: #8b1d1d;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 600;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            border: 1px solid #f2bcbc;
        }

        .purpose-helper {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 8px;
            display: block;
        }

        .reschedule-alert-card { background: #fff7e8; border: 1px solid #ffe2b8; border-radius: 14px; padding: 18px; margin-bottom: 15px; }
        .reschedule-alert-title { color: #9a6700; font-weight: 700; margin-bottom: 6px; }
        .choice-pill { background: #fff; border: 1px solid #f5d9ab; border-radius: 999px; padding: 3px 10px; font-size: 0.75rem; margin-right: 6px; display: inline-block; }

        @media (max-width: 768px) {
            .route-arrow { display: none; }
            .route-step { width: 100%; }
        }
    body{background:#f5f8f6;padding:24px}.preview-wrap{max-width:850px;margin:auto;background:white;padding:32px;border-radius:18px}</style></head><body><main class="preview-wrap"><h3>Book an appointment</h3><p class="text-muted">Synthetic preview · no accounts or bookings are saved</p><div class="appointment-card"><form method="POST" id="appointmentForm">
                            
                            <div class="form-group m-b25">
                                <label class="form-label">1. Select Offices / Departments</label>
                                <div class="office-grid">
                                    <label class="office-card-label"><input type="checkbox" name="office_ids[]" class="office-checkbox" value="1"><div class="office-card"><i class="ti-briefcase"></i> Registrar</div></label><label class="office-card-label"><input type="checkbox" name="office_ids[]" class="office-checkbox" value="2"><div class="office-card"><i class="ti-briefcase"></i> Cashier</div></label>
                                </div>
                            </div>

                            <div class="form-group m-b25">
                                <label class="form-label">2. Priority Type</label>
                                
                                <p><span class="badge badge-success">N/A</span></p>
                                <small class="text-muted">Priority comes from your verified student profile.</small>
                                <small class="text-muted d-block" style="font-weight: 600;">
                                    <i class="ti-info-alt"></i> PWD priority is applied automatically after admin approves the ID in your profile.
                                </small>
                            </div>
                            
                            <div class="form-group m-b25">
                                <label class="form-label">3. Select Date</label>
                                <div class="ss-calendar" data-schedule-calendar data-target="appointment_date" data-mode="booking">
    <div class="ss-calendar-switch" role="group" aria-label="Date entry method">
        <button type="button" class="ss-calendar-toggle active" data-date-mode="calendar"><i class="ti-calendar"></i> Calendar</button>
        <button type="button" class="ss-calendar-toggle" data-date-mode="manual"><i class="ti-pencil"></i> Enter date</button>
    </div>
    <div class="ss-calendar-view"><div class="ss-calendar-heading"><button type="button" data-month-shift="-1" aria-label="Previous month">‹</button><strong class="ss-calendar-month"></strong><button type="button" data-month-shift="1" aria-label="Next month">›</button></div>
    <div class="ss-calendar-days" aria-label="Select a date"></div><small class="ss-calendar-note">Select a date or use Enter date.</small></div>
</div>

                                <input type="date" name="appointment_date" id="appointment_date" class="custom-input" min="2026-11-11" max="2026-11-18" required>
                            </div>

                            <div class="form-group m-b25" id="time-container" style="display: none;">
                                <label class="form-label">4. Select Time Slot</label>
                                <input type="hidden" name="appointment_time" id="selected_time" required>
                                <div class="time-grid" id="slots-wrapper"></div>
                            </div>

                            <div class="form-group m-b30">
                                <label class="form-label">5. Purpose of Visit</label>
                                <select name="purpose_option" id="purpose_option" class="custom-input" required>
                                    <option value="">Select a common service</option>
                                    <option value="Others">Others</option>
                                </select>
                                <small class="purpose-helper">Choose a common service. Select Others if your purpose is not listed.</small>

                                <div id="purpose-block-alert" style="display:none; margin-top: 12px;" class="time-warning"></div>

                                <div id="custom-purpose-wrap" style="display: none; margin-top: 12px;">
                                    <textarea name="custom_purpose" id="custom_purpose" class="custom-input" rows="3" placeholder="Type your specific purpose here..."></textarea>
                                </div>
                                
                                <div class="instructions-box" id="validation-instructions">
                                    <h5><i class="ti-info-alt"></i> Important Reminder for ID Validation</h5>
                                    <p class="m-0" style="font-size: 0.95rem; color: #5c1f1f;">If you are requesting <strong>ID Validation at the ITC</strong>, you must secure the required documents by visiting the offices in this exact order:</p>

                                    <div class="route-path">
                                        <div class="route-step">
                                            <div class="route-step-num">1</div>
                                            <div class="route-step-text"><strong>Cashier</strong><span>Enrollment Receipt</span></div>
                                        </div>
                                        <i class="ti-angle-right route-arrow"></i>
                                        <div class="route-step">
                                            <div class="route-step-num">2</div>
                                            <div class="route-step-text"><strong>Clinic</strong><span>Medical Checkup</span></div>
                                        </div>
                                        <i class="ti-angle-right route-arrow"></i>
                                        <div class="route-step step-final">
                                            <div class="route-step-num">3</div>
                                            <div class="route-step-text"><strong>ITC</strong><span>Final ID Validation</span></div>
                                        </div>
                                    </div>

                                    <div class="time-warning">
                                        <i class="ti-time"></i> Queue transactions are strictly processed until 5:00 PM only.
                                    </div>
                                </div>
                            </div>

                            <div class="form-group border-top pt-4 text-right d-flex justify-content-between align-items-center">
                      
                                <button type="submit" name="submit_appointment" id="submit_btn" class="btn green radius-xl" style="background-color: var(--primary-green); padding: 12px 30px; font-size: 1.1rem; font-weight: 600;">
                                    Confirm Request
                                </button>
                            </div>
                        </form></div></main><script src="../../dashboard/assets/js/jquery.min.js"></script><script>
        $(document).ready(function() {
            const officeCheckboxes = $('.office-checkbox');
            const dateSelect = $('#appointment_date');
            const timeContainer = $('#time-container');
            const slotsWrapper = $('#slots-wrapper');
            const selectedTimeInput = $('#selected_time');
            const submitBtn = $('#submit_btn');
            const purposeSelect = $('#purpose_option');
            const customPurposeWrap = $('#custom-purpose-wrap');
            const customPurposeInput = $('#custom_purpose');
            const purposeBlockAlert = $('#purpose-block-alert');
            let slotsRequestId = 0;
            let purposeRequestId = 0;
            const officePurposeMap = {
                '1': [
                    'Medical Checkup',
                    'Dental',
                    'Injury/Wound Care'
                ],
                '2': [
                    'Payment'
                ],
                '3': [
                    'Valid ID',
                    'Teams/Tech Concern'
                ]
            };

            function getSelectedOffices() {
                let selected = [];
                $('.office-checkbox:checked').each(function() {
                    selected.push($(this).val());
                });
                return selected;
            }

            function updatePurposeOptions(selectedOfficeIds) {
                const previousValue = purposeSelect.val();
                const options = [];

                selectedOfficeIds.forEach(function(officeId) {
                    if (officePurposeMap[officeId]) {
                        officePurposeMap[officeId].forEach(function(service) {
                            if (!options.includes(service)) {
                                options.push(service);
                            }
                        });
                    }
                });

                purposeSelect.empty();
                purposeSelect.append('<option value="">Select a common service</option>');

                options.forEach(function(service) {
                    purposeSelect.append('<option value="' + service + '">' + service + '</option>');
                });

                purposeSelect.append('<option value="Others">Others</option>');

                if (options.includes(previousValue) || previousValue === 'Others') {
                    purposeSelect.val(previousValue);
                } else {
                    purposeSelect.val('');
                }

                toggleCustomPurposeField();
                checkPurposeBlock();
            }

            function refreshSubmitState() {
                const hasTime = !!selectedTimeInput.val();
                const hasPurposeBlock = purposeBlockAlert.is(':visible');
                submitBtn.prop('disabled', false);
                document.getElementById('appointmentForm').dataset.purposeBlocked = String(hasPurposeBlock);
            }

            function showPurposeBlock(message) {
                purposeBlockAlert.html('<i class="ti-na"></i> ' + message).show();
                refreshSubmitState();
            }

            function clearPurposeBlock() {
                purposeBlockAlert.hide().empty();
                refreshSubmitState();
            }

            function checkPurposeBlock() {
                const requestId = ++purposeRequestId;
                const selected = getSelectedOffices();
                const date = dateSelect.val();
                const purpose = purposeSelect.val();

                if (selected.length === 0 || !hasValidBookingDate() || !purpose || purpose === 'Others') {
                    clearPurposeBlock();
                    return;
                }

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        check_purpose_block: true,
                        office_ids: selected,
                        date: date,
                        purpose: purpose
                    },
                    success: function(response) {
                        if (requestId !== purposeRequestId) return;
                        if (response && response.blocked) {
                            showPurposeBlock(response.message || 'This purpose is unavailable today.');
                        } else {
                            clearPurposeBlock();
                        }
                    },
                    error: function() {
                        if (requestId !== purposeRequestId) return;
                        clearPurposeBlock();
                    }
                });
            }

            // Enable Date if at least one office is checked
            officeCheckboxes.on('change', function() {
                let selected = getSelectedOffices();
                if(selected.length > 0) {
                    dateSelect.prop('disabled', false);
                    updatePurposeOptions(selected);
                    fetchSlots(selected);
                } else {
                    dateSelect.prop('disabled', false);
                    dateSelect.val('');
                    fetchSlots([]);
                    timeContainer.slideUp();
                    slotsWrapper.empty();
                    selectedTimeInput.val('');
                    submitBtn.prop('disabled', false);
                    updatePurposeOptions([]);
                    clearPurposeBlock();
                }
            });

            function hasValidBookingDate() {
                const input = dateSelect[0];
                const date = input.value;
                return SmartServe.rules.weekdayDate(date) && date >= input.min && date <= input.max;
            }

            // Native date controls emit changes while a manually typed year is incomplete.
            // Clear the old time immediately, but only request availability for a usable date.
            dateSelect.on('input', function() {
                ++slotsRequestId;
                ++purposeRequestId;
                selectedTimeInput.val('');
                slotsWrapper.empty();
                timeContainer.hide();
                document.getElementById('appointmentForm').dataset.slotsLoading = 'false';
                clearPurposeBlock();
            });

            dateSelect.on('change', function() {
                fetchSlots(getSelectedOffices());
                checkPurposeBlock();
            });

            function fetchSlots(selectedOffices) {
                const requestId = ++slotsRequestId;
                const date = dateSelect.val();
                selectedTimeInput.val('');
                if (!hasValidBookingDate() || selectedOffices.length === 0) {
                    timeContainer.hide();
                    slotsWrapper.empty();
                    document.getElementById('appointmentForm').dataset.slotsLoading = 'false';
                    return;
                }

                timeContainer.slideDown();
                selectedTimeInput.val('');
                submitBtn.prop('disabled', false);
                document.getElementById('appointmentForm').dataset.slotsLoading = 'true';
                slotsWrapper.html(`<div class="w-100 text-center py-3" style="grid-column: 1/-1;"><i class="ti-reload fa-spin" style="color: var(--primary-green);"></i> Checking slots for selected offices...</div>`);

                $.ajax({
                    url: window.location.href, 
                    type: 'POST',
                    data: { fetch_slots: true, office_ids: selectedOffices, date: date },
                    dataType: 'json',
                    success: function(response) {
                        if (requestId !== slotsRequestId) return;
                        document.getElementById('appointmentForm').dataset.slotsLoading = 'false';
                        slotsWrapper.empty();
                        if (response.status === 'error') {
                            SmartServe.notify(response.msg, 'danger');
                            return;
                        }

                        function escapeHtml(str) {
                            return String(str)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                        }

                        $.each(response, function(time, slotData) {
                            const isLunchBreak = time === '12:00 PM';
                            const available = typeof slotData === 'object' ? Number(slotData.available || 0) : Number(slotData || 0);
                            const label = typeof slotData === 'object' ? (slotData.label || 'Unavailable') : (available > 0 ? `${available} slot(s) left` : 'Unavailable');
                            const note = typeof slotData === 'object' ? (slotData.note || '') : '';
                            const safeNote = escapeHtml(note);
                            const safeLabel = escapeHtml(label);

                            let html = available > 0 && !isLunchBreak
                                ? `<button type="button" class="time-slot available" data-time="${time}"><span class="time-text">${time}</span><span class="slot-count">${safeLabel}</span></button>`
                                : `<button type="button" class="time-slot unavailable" disabled title="${safeNote}"><span class="time-text">${time}</span><span class="slot-count">${isLunchBreak ? 'Lunch break' : safeLabel}</span>${safeNote ? `<small class="d-block mt-1" style="font-size:0.72rem;">${safeNote}</small>` : ''}</button>`;
                            slotsWrapper.append(html);
                        });
                    },
                    error: function() {
                        if (requestId !== slotsRequestId) return;
                        document.getElementById('appointmentForm').dataset.slotsLoading = 'true';
                        slotsWrapper.text('Unable to load available times. Please select the date again.');
                    }
                });
            }

            $(document).on('click', '.time-slot.available', function() {
                $('.time-slot').removeClass('selected');
                $(this).addClass('selected');
                selectedTimeInput.val($(this).data('time'));
                selectedTimeInput[0].dispatchEvent(new Event('change', {bubbles:true}));
                refreshSubmitState();
            });

            function toggleCustomPurposeField() {
                if (purposeSelect.val() === 'Others') {
                    customPurposeWrap.slideDown(120);
                    customPurposeInput.prop('required', true);
                } else {
                    customPurposeWrap.slideUp(120);
                    customPurposeInput.prop('required', false).val('');
                }
                checkPurposeBlock();
            }

            purposeSelect.on('change', toggleCustomPurposeField);
            updatePurposeOptions(getSelectedOffices());
            toggleCustomPurposeField();

            $('.block-response-form').each(function() {
                const form = $(this);
                const actionSelect = form.find('.block-action-select');
                const wraps = form.find('.reschedule-input-wrap');

                function toggleRescheduleFields() {
                    const isCancel = actionSelect.val() === 'cancel';
                    wraps.toggle(!isCancel);
                    wraps.find('input, select').prop('required', !isCancel);
                }

                actionSelect.on('change', toggleRescheduleFields);
                toggleRescheduleFields();
            });
        });
    </script></body></html>