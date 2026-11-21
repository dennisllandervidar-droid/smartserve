(function () {
    'use strict';
    if (window.SmartServe) return;

    const namePattern = /^[\p{L}\p{M} -]+$/u;
    const phonePattern = /^(?:09[0-9]{9}|\+639[0-9]{9})$/;
    const emailPattern = /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~\-]+@[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,63}$/;
    const nameFields = ['fname', 'mname', 'lname', 'new_fname', 'new_mname', 'new_lname', 'guest_name', 'manual_name_input'];
    const phoneFields = ['contact', 'new_contact', 'phone', 'telephone', 'mobile'];
    const emailFields = ['uname', 'username', 'email'];
    const newPasswords = ['password', 'pword', 'npass', 'new_password'];
    const confirmations = ['rpass', 'confirm', 'confirm_password'];
    const length = value => Array.from(value).length;
    const rules = {
        weekdayDate: value => {
            if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
            const date = new Date(value + 'T00:00:00Z');
            return !isNaN(date.getTime()) && date.toISOString().slice(0,10) === value && date.getUTCDay() > 0 && date.getUTCDay() < 6;
        },
        name: (value, required = true, max = 50) => (!value.trim() && !required) || (length(value.trim()) <= max && namePattern.test(value.trim()) && /\p{L}/u.test(value)),
        phone: (value, required = true) => (value === '' && !required) || phonePattern.test(value),
        email: (value, max = 50) => value.trim().length <= max && emailPattern.test(value.trim()) && !value.trim().startsWith('.') && !value.split('@')[0].endsWith('.') && !value.split('@')[0].includes('..') && value.split('@')[0].length <= 64,
        password: value => length(value) >= 8 && length(value) <= 72 && new TextEncoder().encode(value).length <= 72 && /[A-Za-z]/.test(value) && /[0-9]/.test(value) && /[^A-Za-z0-9\s]/u.test(value) && !/[\s\x00]/u.test(value),
        blockHour: value => typeof value === 'string' && /^(?:0[7-9]|1[0-6]):00$/.test(value),
        blockTimeRange: (start, end) => rules.blockHour(start) && rules.blockHour(end) && start < end
    };
    const ready = callback => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', callback, { once: true }) : callback();
    let notificationQueue = Promise.resolve();
    let errorId = 0;

    function dialog(message, type = 'danger', options = {}) {
        return new Promise(resolve => ready(() => {
            const previousFocus = document.activeElement;
            const backdrop = document.createElement('div');
            backdrop.className = 'ss-modal-backdrop';
            const modal = document.createElement('div');
            modal.className = 'ss-modal';
            type = type === 'error' ? 'danger' : type;
            modal.dataset.type = type;
            modal.setAttribute('role', 'alertdialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'ss-modal-title');
            modal.setAttribute('aria-describedby', 'ss-modal-message');
            const icons = { success: '<path d="m6 12 4 4 8-8"/>', danger: '<path d="m8 8 8 8m0-8-8 8"/>', warning: '<path d="M12 7v6m0 4h.01"/>', info: '<path d="M12 11v6m0-10h.01"/>' };
            const icon = document.createElement('div');
            icon.className = 'ss-modal-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/>' + (icons[type] || icons.info) + '</svg>';
            const title = document.createElement('h2');
            title.id = 'ss-modal-title';
            title.textContent = options.title || ({ success: 'Success', danger: 'Please check', warning: 'Confirm action', info: 'Notice' }[type] || 'Notice');
            const body = document.createElement('p');
            body.id = 'ss-modal-message';
            body.textContent = String(message);
            const actions = document.createElement('div');
            actions.className = 'ss-modal-actions';
            const ok = document.createElement('button');
            ok.type = 'button';
            ok.textContent = options.confirmText || (options.confirm ? 'Continue' : 'OK');
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'ss-modal-cancel';
            cancel.textContent = 'Cancel';
            if (options.confirm) actions.appendChild(cancel);
            actions.appendChild(ok);
            modal.append(icon, title, body, actions);
            backdrop.appendChild(modal);
            const inertElements = Array.from(document.body.children).filter(element => !element.inert);
            inertElements.forEach(element => { element.inert = true; });
            document.body.appendChild(backdrop);
            document.body.classList.add('ss-modal-open');
            const finish = result => {
                document.removeEventListener('keydown', keydown, true);
                document.removeEventListener('focusin', focusin, true);
                backdrop.remove();
                inertElements.forEach(element => { element.inert = false; });
                document.body.classList.remove('ss-modal-open');
                if (previousFocus && previousFocus.isConnected) previousFocus.focus();
                resolve(result);
            };
            const focusin = event => {
                if (backdrop.contains(event.target)) event.stopImmediatePropagation();
            };
            const keydown = event => {
                if (event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); finish(!options.confirm); }
                if (event.key === 'Tab') {
                    const buttons = options.confirm ? [cancel, ok] : [ok];
                    const index = buttons.indexOf(document.activeElement);
                    event.preventDefault();
                    buttons[(index + (event.shiftKey ? buttons.length - 1 : 1)) % buttons.length].focus();
                }
            };
            ok.addEventListener('click', () => finish(true));
            cancel.addEventListener('click', () => finish(false));
            document.addEventListener('keydown', keydown, true);
            document.addEventListener('focusin', focusin, true);
            (options.confirm ? cancel : ok).focus();
        }));
    }

    function notify(message, type = 'danger', options = {}) {
        const next = notificationQueue.then(() => dialog(message, type, options));
        notificationQueue = next.catch(() => {});
        return next;
    }

    function isNewPassword(input) {
        return newPasswords.includes(input.name) && !(input.name === 'password' && input.form && input.form.querySelector('[name="username"]'));
    }

    const fieldNames = {
        fname: 'First Name', mname: 'Middle Name', lname: 'Last Name', new_fname: 'First Name', new_mname: 'Middle Name', new_lname: 'Last Name',
        uname: 'Email Address', username: 'Email Address', email: 'Email Address', contact: 'Phone Number', new_contact: 'Phone Number',
        cpass: 'Current Password', npass: 'New Password', rpass: 'Confirm Password', pword: 'Password', new_password: 'New Password', confirm_password: 'Confirm Password',
        year: 'Year Level', course: 'Degree Program', account_priority: 'Priority Type', pwd_id: 'PWD ID Photo', otp: 'OTP Code',
        'office_ids[]': 'Offices / Departments', appointment_date: 'Appointment Date', appointment_time: 'Appointment Time', purpose_option: 'Purpose of Visit', custom_purpose: 'Other Purpose'
    };

    function fieldLabel(input) {
        const label = input.labels?.[0]?.textContent.trim().replace(/\s+/g, ' ').replace(/\s*\*$/, '');
        return input.dataset.ssLabel || (input.name === 'office_ids[]' ? fieldNames[input.name] : label) || fieldNames[input.name] || input.name.replace(/^new_/, '').replace(/[_-]/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) || 'This field';
    }

    function addPasswordToggle(input) {
        if (input.type !== 'password' || input.parentElement.querySelector('.toggle-password, .ss-password-toggle')) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'ss-password-field';
        input.before(wrapper);
        wrapper.append(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'ss-password-toggle';
        const label = fieldLabel(input);
        const render = () => {
            const shown = input.type === 'text';
            button.setAttribute('aria-label', (shown ? 'Hide ' : 'Show ') + label);
            button.setAttribute('aria-pressed', String(shown));
            button.title = (shown ? 'Hide ' : 'Show ') + label;
            button.innerHTML = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>' + (shown ? '<path d="m3 3 18 18"/>' : '') + '</svg>';
        };
        button.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            render();
        });
        wrapper.append(button);
        render();
    }

    function configure(input) {
        if (input.dataset.ssConfigured || !input.name) return;
        input.dataset.ssConfigured = 'true';
        const name = input.name;
        if (nameFields.includes(name) && name !== 'manual_name_input') {
            input.pattern = '[\\p{L}\\p{M} \\-]+';
            input.maxLength = input.maxLength > 0 ? input.maxLength : (name === 'guest_name' ? 100 : 50);
            input.title = 'Letters, spaces, and hyphens only.';
        } else if (phoneFields.includes(name)) {
            input.required = true;
            input.type = 'tel';
            input.inputMode = 'tel';
            input.pattern = '(?:09[0-9]{9}|\\+639[0-9]{9})';
            input.maxLength = input.value.trim().startsWith('0') ? 11 : 13;
            input.title = 'Use 09XXXXXXXXX (11 characters) or +639XXXXXXXXX (13 characters).';
            input.placeholder = '09XXXXXXXXX or +639XXXXXXXXX';
        } else if (emailFields.includes(name) || input.type === 'email') {
            input.type = 'email';
            input.maxLength = input.maxLength > 0 ? Math.min(input.maxLength, 50) : 50;
            input.title = 'Enter an email with a domain extension, such as sample@edu.ph.';
        } else if (['address', 'new_address'].includes(name)) {
            input.maxLength = 120;
        } else if (isNewPassword(input) || confirmations.includes(name)) {
            input.type = 'password';
            input.maxLength = 72;
            input.minLength = 8;
            // The shared rule permits all punctuation, and enforces bcrypt's byte limit.
            input.removeAttribute('pattern');
        } else if (name === 'otp') {
            input.inputMode = 'numeric';
            input.pattern = '[0-9]{6}';
            input.maxLength = 6;
        } else if (input.type === 'file' && name === 'image') {
            input.accept = 'image/jpeg,image/png';
        } else if (['title', 'question', 'office_name', 'office', 'course', 'reason', 'target_purpose', 'purpose_option', 'purpose'].includes(name) && input.tagName !== 'SELECT') input.maxLength = 255;
        else if (['details', 'answer'].includes(name)) input.maxLength = 10000;
        else if (['notes', 'office_notes', 'note', 'custom_purpose'].includes(name)) input.maxLength = 1000;
        if (name === 'cpass' || (name === 'password' && !isNewPassword(input))) input.removeAttribute('minlength');
        addPasswordToggle(input);
    }

    function fieldError(input) {
        if (input.disabled || ['submit', 'button', 'reset'].includes(input.type)) return '';
        input.setCustomValidity('');
        const value = input.value;
        const name = input.name;
        const form = input.form;
        const normalized = value.trim();
        if (form?.querySelector('[name="response_action"]')?.value === 'cancel' && ['reschedule_date', 'reschedule_time'].includes(name)) return '';
        if (normalized && ['appointment_date', 'reschedule_date'].includes(name) && !rules.weekdayDate(value)) return 'Choose a weekday (Monday to Friday). Weekend appointments are unavailable.';
        if (form?.querySelector('[name="block_type"]') && form.querySelector('[name="block_type"]').value !== 'time_range' && ['start_time', 'end_time'].includes(name)) return '';
        if (input.required && !normalized && !['checkbox', 'radio', 'file'].includes(input.type)) {
            const steps = { appointment_date: 'Please check your selected date (step 3).', appointment_time: 'Please select an available time (step 4).', purpose_option: 'Please select your purpose of visit (step 5).' };
            return form?.id === 'appointmentForm' && steps[name] ? steps[name] : fieldLabel(input) + ' is required.';
        }
        if (input.required && input.type === 'file' && !input.files?.length) return fieldLabel(input) + ' is required.';
        if (input.required && input.type === 'checkbox' && !input.checked) return fieldLabel(input) + ' is required.';
        if (form?.querySelector('[name="block_type"]')?.value === 'time_range' && ['start_time', 'end_time'].includes(name) && normalized) {
            if (!rules.blockHour(value)) return 'Select an hourly time from 7 AM to 4 PM.';
            const start = form.querySelector('[name="start_time"]');
            if (name === 'end_time' && rules.blockHour(start?.value) && start.value >= value) return 'End time must be later than start time.';
        }
        if (name === 'manual_name_input' && form && form.querySelector('[name="student_id"]')?.value) return '';
        if (normalized && nameFields.includes(name) && !rules.name(value, input.required, input.maxLength > 0 ? input.maxLength : 100)) return 'Use letters, spaces, and hyphens only.';
        if (value && phoneFields.includes(name) && !rules.phone(value)) return 'Use 09XXXXXXXXX (11 characters) or +639XXXXXXXXX (13 characters), without spaces or other characters.';
        if (normalized && (emailFields.includes(name) || input.type === 'email') && !rules.email(value, input.maxLength)) return 'Enter a valid email with a domain extension, such as sample@edu.ph.';
        if (isNewPassword(input) && !rules.password(value)) return length(value) <= 72 && new TextEncoder().encode(value).length > 72
            ? 'Password is too long. Please shorten it or use fewer accented characters or symbols.'
            : 'Use 8–72 characters with a letter, a number, and a special character, without spaces.';
        if (confirmations.includes(name) && form) {
            const password = form.querySelector('[name="npass"], [name="new_password"], [name="password"], [name="pword"]');
            if (password && value !== password.value) return 'Passwords do not match.';
        }
        if (['image', 'pwd_id'].includes(name) && input.files?.length) {
            if (input.files[0].size > 2 * 1024 * 1024) return 'Image must be at most 2 MB.';
            if (!['image/jpeg', 'image/png'].includes(input.files[0].type)) return 'Only JPG and PNG images are allowed.';
        }
        if (normalized && input.maxLength > 0 && length(value) > input.maxLength) return 'Use at most ' + input.maxLength + ' characters.';
        if (['address', 'new_address', 'title', 'question', 'answer', 'details', 'notes', 'office_notes', 'note', 'reason', 'custom_purpose'].includes(name) && /[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/.test(value)) return 'Please remove unsupported control characters.';
        if (!input.validity.valid) return input.title || input.validationMessage || 'Please enter a valid value.';
        return '';
    }

    function showFieldError(input, message) {
        if (message) input.dataset.ssTouched = 'true';
        input.setCustomValidity(message);
        input.classList.toggle('ss-invalid', !!message);
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
        const group = input.name === 'office_ids[]' ? input.closest('.office-grid') : null;
        if (group) {
            group.classList.toggle('ss-invalid', !!message);
            group.setAttribute('aria-invalid', String(!!message));
        }
        if (!input.dataset.ssErrorId) input.dataset.ssErrorId = 'ss-field-error-' + (++errorId);
        let error = document.getElementById(input.dataset.ssErrorId);
        if (message && !error && (input.type !== 'hidden' || input.name === 'appointment_time')) {
            error = document.createElement('span');
            error.id = input.dataset.ssErrorId;
            error.className = 'ss-field-error';
            const wrapper = input.parentElement;
            // Office cards depend on input + .office-card. Keep feedback outside the labels.
            const anchor = group || (input.name === 'appointment_time' ? input.form.querySelector('#slots-wrapper') : null)
                || (wrapper.matches('.ss-password-field, .position-relative') && wrapper.querySelector('.toggle-password, .ss-password-toggle') ? wrapper : input);
            anchor.insertAdjacentElement('afterend', error);
            input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        }
        if (error) { error.textContent = message; error.hidden = !message; }
    }

    function validateForm(form, show = true) {
        let first = null;
        const messages = [];
        const add = (input, message) => {
            if (!input) return;
            if (show) showFieldError(input, message);
            if (message) {
                first = first || input;
                const label = fieldLabel(input);
                messages.push(message.toLowerCase().includes(label.toLowerCase()) ? message : label + ': ' + message);
            }
        };
        for (const input of form.querySelectorAll('input, textarea, select')) {
            configure(input);
            add(input, fieldError(input));
        }
        const purpose = form.querySelector('[name="purpose_option"]');
        if (form.id === 'appointmentForm') {
            const date = form.querySelector('[name="appointment_date"]');
            const time = form.querySelector('[name="appointment_time"]');
            if (!date?.value) add(date, 'Please check your selected date (step 3).');
            if (!time?.value) add(time, 'Please select an available time (step 4).');
            if (!purpose?.value) add(purpose, 'Please select your purpose of visit (step 5).');
            if (date?.value && (date.value < date.min || date.value > date.max)) add(date, 'Please choose a date within the next seven days.');
            if (form.dataset.slotsLoading === 'true') add(time, 'Please wait while available times are checked.');
            if (form.dataset.purposeBlocked === 'true') add(purpose, 'This purpose is blocked for the selected date. Please select another date.');
        }
        const custom = form.querySelector('[name="custom_purpose"]');
        if (purpose?.value === 'Others' && custom && !custom.value.trim()) add(custom, 'Please describe your purpose of visit.');
        const offices = form.querySelectorAll('[name="office_ids[]"]');
        if (offices.length && !Array.from(offices).some(input => input.checked)) add(offices[0], 'Please select at least one office.');
        const blockType = form.querySelector('[name="block_type"]');
        const start = form.querySelector('[name="start_time"]');
        const end = form.querySelector('[name="end_time"]');
        if (blockType?.value === 'time_range') {
            if (!start?.value) add(start, 'Start time is required.');
            if (!end?.value) add(end, 'End time is required.');
            if (start?.value && end?.value && start.value >= end.value) add(end, 'End time must be later than start time.');
        }
        const targetPurpose = form.querySelector('[name="target_purpose"]');
        if (blockType?.value === 'specific_purpose' && !targetPurpose?.value) add(targetPurpose, 'Please select a purpose to block.');
        const dateStart = form.querySelector('[name="start_date"]');
        const dateEnd = form.querySelector('[name="end_date"]');
        if (dateStart?.value && dateEnd?.value && dateStart.value > dateEnd.value) add(dateEnd, 'End date must be on or after start date.');
        const officeNotes = document.querySelector('#office_notes');
        if (officeNotes && form.contains(officeNotes) && officeNotes.value.length > 1000) add(officeNotes, 'Notes must be at most 1000 characters.');
        if (show && first) first = form.querySelector('input.ss-invalid, textarea.ss-invalid, select.ss-invalid') || first;
        return { valid: !first, first, messages: Array.from(new Set(messages)) };
    }

    function focusInvalidField(input) {
        const target = input.name === 'office_ids[]' ? input.closest('.office-grid')
            : input.type === 'hidden' ? input.parentElement : input;
        if (!target) return;
        if (target !== input) target.tabIndex = -1;
        target.focus({ preventScroll: true });
        target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
    }

    window.SmartServe = {
        rules, notify, validateForm,
        confirm: (message, options = {}) => notify(message, 'warning', { ...options, confirm: true }),
        notifyFromServer: payload => notify(payload.message, payload.type).then(() => {
            if (payload.redirect === 'back') history.back();
            else if (payload.redirect) window.location.assign(payload.redirect);
        })
    };

    ready(() => {
        const setup = root => {
            root.querySelectorAll('form').forEach(form => { form.noValidate = true; });
            root.querySelectorAll('input, textarea, select').forEach(configure);
        };
        setup(document);
        new MutationObserver(records => {
            for (const record of records) for (const node of record.addedNodes) {
                if (node.nodeType !== 1 || node.closest('.ss-modal-backdrop')) continue;
                if (node.tagName === 'FORM') node.noValidate = true;
                if (node.matches('input, textarea, select')) configure(node);
                setup(node);
            }
        }).observe(document.body, { childList: true, subtree: true });

        document.addEventListener('input', event => {
            const input = event.target;
            if (!input.matches('input, textarea, select')) return;
            if (phoneFields.includes(input.name)) input.maxLength = input.value.trim().startsWith('0') ? 11 : 13;
            if (input.dataset.ssTouched) showFieldError(input, fieldError(input));
            if (isNewPassword(input) && input.form) input.form.querySelectorAll('[name="confirm_password"], [name="rpass"]').forEach(confirm => {
                if (confirm.dataset.ssTouched) showFieldError(confirm, fieldError(confirm));
            });
        });
        document.addEventListener('focusout', event => {
            const input = event.target;
            if (!input.matches('input, textarea, select') || input.type === 'hidden' || input.name === 'manual_name_input') return;
            if (nameFields.includes(input.name) || emailFields.includes(input.name) || ['address', 'new_address'].includes(input.name)) input.value = input.value.trim();
            input.dataset.ssTouched = 'true';
            showFieldError(input, fieldError(input));
        });
        document.addEventListener('change', event => {
            const input = event.target;
            if (!input.matches('input, textarea, select')) return;
            if (input.dataset.ssTouched) showFieldError(input, fieldError(input));
            if (input.name === 'office_ids[]' && input.form?.querySelector('[name="office_ids[]"]:checked')) input.form.querySelectorAll('[name="office_ids[]"]').forEach(office => showFieldError(office, ''));
        });
        document.addEventListener('reset', event => setTimeout(() => {
            event.target.querySelectorAll('input, textarea, select').forEach(input => { delete input.dataset.ssTouched; showFieldError(input, ''); });
        }, 0));

        document.addEventListener('submit', event => {
            const form = event.target;
            const result = validateForm(form);
            if (!result.valid) {
                event.preventDefault();
                event.stopImmediatePropagation();
                notify(result.messages.join('\n'), 'danger', { title: 'Please check your form' }).then(() => focusInvalidField(result.first));
                return;
            }
            const confirmation = event.submitter?.dataset.ssConfirm || form.dataset.ssConfirm;
            if (confirmation && !form.dataset.ssConfirmed) {
                event.preventDefault();
                event.stopImmediatePropagation();
                const submitter = event.submitter;
                window.SmartServe.confirm(confirmation).then(confirmed => {
                    if (!confirmed) return;
                    form.dataset.ssConfirmed = 'true';
                    form.requestSubmit(submitter || undefined);
                    delete form.dataset.ssConfirmed;
                });
            }
        }, true);
    });
}());
