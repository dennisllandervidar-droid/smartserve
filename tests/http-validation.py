"""Check that invalid direct requests stop before database access.

Start Apache in XAMPP, then run: python tests/http-validation.py
Apache is required to verify the private upload directory's .htaccess protection.
"""
import json
import sys
from datetime import date
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

base = sys.argv[1].rstrip('/') if len(sys.argv) > 1 else 'http://localhost/smartserve'
checks = 0

def send(path, values, expected_status=422, ajax=False):
    global checks
    request = Request(base + path, data=urlencode(values, doseq=True).encode())
    try:
        with urlopen(request, timeout=5) as response:
            status, body = response.status, response.read().decode()
    except HTTPError as response:
        status, body = response.code, response.read().decode()
    assert status == expected_status, (path, status)
    assert 'Fatal error' not in body and '<?php' not in body, path
    if ajax:
        result = json.loads(body)
        assert result['status'] == 'error' and result['errors'], path
    else:
        assert 'SmartServe.notifyFromServer' in body and '/assets/js/forms.js' in body, path
    checks += 1
    return body

registration = {'submit_verify': '', 'fname': 'María-José', 'lname': 'Peña', 'uname': 'sample@edu', 'contact': '+639123456789', 'address': '123-A Rizal St.', 'password': 'Secure#123', 'confirm_password': 'Secure#123', 'year': '1st Year', 'course': 'BS Computer Science'}
send('/register.php', registration)
send('/register.php', dict(registration, uname='sample@edu.ph', contact='091234567890'))
for phone in ['09123456789 ', ' 09123456789', '09 123456789', '09123-456789']:
    send('/register.php', dict(registration, uname='sample@edu.ph', contact=phone))
send('/register.php', dict(registration, uname='sample@edu.ph', account_priority='PWD'))
send('/register.php', dict(registration, uname='sample@edu.ph', **{'fname[]': 'Juan', 'fname': ''}))
send('/login.php', {'submit': '', 'username': 'sample@edu', 'password': 'legacy'})
for role in ['admin', 'office', 'student']:
    profile = {'save_profile': '', 'new_fname': 'Juan', 'new_lname': 'Cruz', 'new_address': '123-A Manila'}
    send('/' + role + '/update-profile.php', profile)
    for phone in ['', '   ', '0912345678', '09123456789 ', '09 123456789', '09123-456789', '+6391234567890']:
        send('/' + role + '/update-profile.php', dict(profile, new_contact=phone))
    send('/' + role + '/update-profile.php', {'save_profile': '', 'new_fname': 'Juan2', 'new_lname': 'Cruz', 'new_contact': '+639123456789', 'new_address': '123-A Manila'})
    send('/' + role + '/update-password.php', {'save_pass': '', 'cpass': 'legacy', 'npass': 'Secure#123', 'rpass': 'Mismatch#123'})
send('/forgot-password.php', {'send_otp': '', 'email': 'sample@edu'})
send('/forgot-password.php', {'verify_otp': '', 'otp': '123abc'})
send('/forgot-password.php', {'reset_password': '', 'new_password': 'weak', 'confirm_password': 'weak'})
send('/kiosk.php', {'action': 'submit_walkin', 'guest_name': 'Guest9', 'office_id': '1', 'purpose_option': 'Payment'}, 200, True)
send('/office/queue.php', {'action': 'scan_qr', 'tracking_no': '<script>'}, 200, True)
send('/student/booking.php', {'fetch_slots': 'true', 'office_ids': '1', 'date': 'not-a-date'}, 200, True)
send('/office/block-schedules.php', {'submit_block': '', 'block_type': 'time_range', 'block_date': '2026-02-31', 'start_time': '13:00', 'end_time': '12:00', 'reason': 'Unavailable'})
for start, end in [('10:00', '07:00'), ('10:00', '10:00'), ('06:00', '08:00'), ('15:00', '17:00'), ('07:30', '09:00'), ('07:00', '08:30'), ('07:00:00', '16:00')]:
    send('/office/block-schedules.php', {'submit_block': '', 'block_type': 'time_range', 'block_date': date.today().isoformat(), 'start_time': start, 'end_time': end, 'reason': 'Unavailable'})
send('/admin/announcement.php', {'add_announcement': '', 'title': '  ', 'details': '<p><br></p>', 'date': '2026-02-31T09:00'})
try:
    urlopen(base + '/admin/reports.php?start_date=2026-10-12&end_date=2026-10-11', timeout=5)
    raise AssertionError('Reversed GET date filter was accepted')
except HTTPError as response:
    assert response.code == 422
    checks += 1
print(f'Passed {checks} direct HTTP validation checks; no database writes.')
for path in ['/pwd-id.php?id=1','/storage/private/pwd/example.png']:
    try:
        response = urlopen(base + path, timeout=5)
        raise AssertionError('Private ID access must be denied: ' + path)
    except HTTPError as response:
        assert response.code == 403, (path,response.code)
print('Passed 2 private ID access checks.')
