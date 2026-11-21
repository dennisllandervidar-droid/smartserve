"""Local public-toggle checks. Restore the original testing flag in finally."""
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE = 'http://localhost/smartserve/'
CONFIG = Path(__file__).resolve().parents[1] / 'global' / 'kiosk-config.php'
checks = 0


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def client():
    return urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())


def request(browser, path='after-hours-testing.php', data=None, method=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, method=method)
    try:
        response = browser.open(req, timeout=20)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.headers, response.read().decode('utf-8')


def check(condition, label):
    global checks
    checks += 1
    if not condition:
        raise AssertionError(label)


def token(html):
    return re.search(r'name="csrf_token" value="([a-f0-9]{64})"', html).group(1)


def enabled(html):
    return 'role="switch" aria-checked="true"' in html


original = CONFIG.read_bytes()
original_flag = re.search(rb"'allow_after_hours_testing' => (true|false)", original).group(1) == b'true'
browser = client()
try:
    status, headers, html = request(browser)
    check(status == 200, 'Anonymous visitor can open the page without signing in')
    check('no-store' in headers.get('Cache-Control', ''), 'State is not cached')
    check(enabled(html) == original_flag, 'Initial switch reflects the shared setting')
    check('href="index.php"' in html and 'href="kiosk.php"' in html, 'Home and kiosk navigation are present')
    csrf = token(html)

    for data, expected in [
        ({'allow_after_hours_testing': '1'}, 403),
        ({'csrf_token': 'wrong', 'allow_after_hours_testing': '1'}, 403),
        ({'csrf_token[]': csrf, 'allow_after_hours_testing': '1'}, 403),
        ({'csrf_token': csrf, 'allow_after_hours_testing': 'true'}, 422),
        ({'csrf_token': csrf, 'allow_after_hours_testing[]': '1'}, 422),
        ({'csrf_token': csrf}, 422),
    ]:
        status, _, _ = request(browser, data=data)
        check(status == expected, f'Malformed or unverified save is rejected: {list(data)} expected {expected}, got {status}')
        check(CONFIG.read_bytes() == original, 'Rejected save preserves original configuration')

    status, _, html = request(browser, 'after-hours-testing.php?allow_after_hours_testing=1')
    check(status == 200 and enabled(html) == original_flag, 'GET query cannot change the setting')
    status, headers, _ = request(browser, method='PUT')
    check(status == 405 and headers.get('Allow') == 'GET, HEAD, POST', 'Unsupported methods are rejected')

    for value in [True, False]:
        status, headers, _ = request(browser, data={'csrf_token': csrf, 'allow_after_hours_testing': str(int(value))})
        check(status == 303 and headers.get('Location') == 'after-hours-testing.php', 'Save redirects to GET')
        status, _, html = request(browser)
        check(status == 200 and enabled(html) == value, 'Saved switch shows requested state')
        status, _, fresh_html = request(client())
        check(status == 200 and enabled(fresh_html) == value, 'Another anonymous session sees persisted state')
        expected_file = re.sub(rb"('allow_after_hours_testing' => )(true|false)", lambda m: m[1] + (b'true' if value else b'false'), original)
        check(CONFIG.read_bytes() == expected_file, 'Save changes only the config boolean')
        status, _, kiosk = request(client(), 'kiosk.php')
        check(status == 200, 'Kiosk still loads')
        check(('After-hours testing is enabled' in kiosk) == value, 'Kiosk reads the saved flag immediately')
        status, _, _ = request(browser, data={'csrf_token': csrf, 'allow_after_hours_testing': str(int(value))})
        check(status == 303, 'Repeated save redirects safely')
        status, _, html = request(browser)
        check(status == 200 and 'No changes needed.' in html, 'Unchanged save reports no changes')
        check(CONFIG.read_bytes() == expected_file, 'Repeated save leaves config unchanged')
        status, _, html = request(browser)
        check('No changes needed.' not in html, 'Refreshing does not repeat a save notification')
finally:
    # Restore through the same route; never overwrite unrelated file edits.
    status, _, html = request(browser)
    status, _, _ = request(browser, data={'csrf_token': token(html), 'allow_after_hours_testing': str(int(original_flag))})
    if status != 303:
        raise RuntimeError('Could not restore the original kiosk testing setting')
    if CONFIG.read_bytes() != original:
        raise RuntimeError('Configuration differs from its original content after restoration')

print(f'Passed {checks} public kiosk-toggle HTTP checks. Original setting restored; no appointments or messages created.')
