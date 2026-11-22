"""Local integration checks for the existing company/event HTTP contracts."""
from html.parser import HTMLParser
from pathlib import Path
import base64
import http.cookiejar
import json
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


def php(code):
    result = subprocess.run([str(ROOT / 'local/wp.sh'), 'eval-file', '-'], cwd=ROOT,
                            input='<?php\n' + code, text=True, capture_output=True, check=True)
    return json.loads(result.stdout)


class Forms(HTMLParser):
    def __init__(self, html):
        super().__init__()
        self.forms = []
        self.current = None
        self.textarea = None
        self.select = None
        self.feed(html)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'form':
            self.current = {}
            self.forms.append(self.current)
        if self.current is None:
            return
        if tag in ('input', 'button') and attrs.get('name') and attrs.get('type') not in ('file', 'checkbox', 'radio'):
            self.current[attrs['name']] = attrs.get('value', '')
        if tag == 'textarea':
            self.textarea = attrs['name']
            self.current[self.textarea] = ''
        if tag == 'select':
            self.select = attrs['name']
            self.current[self.select] = ''
        if tag == 'option' and self.select and 'selected' in attrs:
            self.current[self.select] = attrs.get('value', '')

    def handle_data(self, data):
        if self.textarea and self.current is not None:
            self.current[self.textarea] += data

    def handle_endtag(self, tag):
        if tag == 'form':
            self.current = None
        if tag == 'textarea':
            self.textarea = None
        if tag == 'select':
            self.select = None


def browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def request(client, url, data=None, headers=None):
    payload = data if isinstance(data, bytes) else (urllib.parse.urlencode(data).encode() if data is not None else None)
    req = urllib.request.Request(url, data=payload, headers=headers or {})
    try:
        response = client.open(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.geturl(), response.read().decode()


def form_with(html, key):
    return next(form for form in Forms(html).forms if key in form)


token = secrets.token_hex(6)
fixture = php('''
if ('local' !== wp_get_environment_type()) { throw new RuntimeException('Local tests only'); }
$owner=wp_insert_user(array('user_login'=>%s,'user_email'=>%s,'user_pass'=>'Workflow!12345','role'=>'subscriber'));
$other=wp_insert_user(array('user_login'=>%s,'user_email'=>%s,'user_pass'=>'Workflow!12345','role'=>'subscriber'));
if (is_wp_error($owner) || is_wp_error($other)) { throw new RuntimeException('Fixture users failed'); }
update_user_meta($owner,'company_name','HTTP workflow company');
update_user_meta($other,'company_name','Other HTTP workflow company');
$company=b2b_create_company_after_registration($owner,array());
$other_company=b2b_create_company_after_registration($other,array());
echo wp_json_encode(array('owner'=>$owner,'other'=>$other,'company'=>$company,'other_company'=>$other_company,
 'home'=>home_url('/'),'login'=>wp_login_url(),'profile'=>b2b_page_url('b2b_my_company','my-company'),
 'events'=>b2b_page_url('b2b_manage_event','manage-event')));
''' % tuple(json.dumps(value) for value in (
    'ee-http-' + token, 'ee-http-' + token + '@example.com',
    'ee-other-' + token, 'ee-other-' + token + '@example.com')))
event_id = 0
try:
    owner = browser()
    request(owner, fixture['login'])
    request(owner, fixture['login'], {'log': 'ee-http-' + token, 'pwd': 'Workflow!12345',
                                     'redirect_to': fixture['events'], 'testcookie': '1'})
    status, _, html = request(owner, fixture['profile'])
    assert status == 200
    profile = form_with(html, 'b2b_company_nonce')
    status, url, html = request(owner, fixture['profile'], dict(profile, company_description='Preserved description',
                                                              city='Sarajevo', country='Germany'))
    assert status == 200 and url == fixture['profile'] and 'Company profile updated successfully.' in html
    saved = php('echo wp_json_encode(array(get_post_meta(%d,"country",true),get_post_field("post_content",%d)));' % (fixture['company'], fixture['company']))
    assert saved == ['Germany', 'Preserved description']
    print('PASS: company POST preserves the response and saves profile data')

    boundary = 'ee-upload-' + token
    parts = []
    for key, value in form_with(html, 'b2b_company_nonce').items():
        parts.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + key + '"\r\n\r\n' + value + '\r\n').encode())
    parts.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="company_logo"; filename="fixture.gif"\r\nContent-Type: image/gif\r\n\r\n').encode())
    parts.append(base64.b64decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='))
    parts.append(('\r\n--' + boundary + '--\r\n').encode())
    _, _, html = request(owner, fixture['profile'], b''.join(parts), {'Content-Type': 'multipart/form-data; boundary=' + boundary})
    assert 'Company profile updated successfully.' in html
    assert php('echo wp_json_encode((bool)get_post_thumbnail_id(%d));' % fixture['company'])
    print('PASS: the existing WordPress media upload still saves the company logo')

    _, _, html = request(owner, fixture['events'])
    create = form_with(html, 'b2b_create_event')
    _, url, html = request(owner, fixture['events'], dict(create, event_title='HTTP workflow event'))
    event_id = int(urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)['event_id'][0])
    manage = php('echo wp_json_encode(b2b_get_manage_event_url(%d));' % event_id)
    details = form_with(html, 'b2b_save_event')
    details.update(event_description='Keep event description', start_date='2030-06-01', end_date='2030-06-02', country='France')
    _, _, html = request(owner, manage, details)
    assert 'Event updated successfully.' in html
    _, _, invalid = request(owner, manage, dict(details, end_date='2030-05-01'))
    assert 'End date cannot be before the start date.' in invalid and 'data-ee-tabs' in invalid
    _, _, invalid = request(owner, manage, dict(details, b2b_event_nonce='invalid'))
    assert 'Security check failed.' in invalid and 'data-ee-tabs' not in invalid
    print('PASS: draft creation, event save and existing validation/security response shapes are preserved')

    anonymous = browser()
    public_query = fixture['home'] + '?post_type=event&p=' + str(event_id)
    status, _, html = request(anonymous, public_query)
    assert status == 404 and 'Register for this event' not in html
    _, _, html = request(owner, manage)
    submit = form_with(html, 'b2b_submit_event')
    _, _, html = request(owner, manage, submit)
    assert 'Pending Approval' in html and 'Event details and registration fields are locked' in html
    _, _, blocked = request(owner, manage, dict(details, event_title='Blocked overwrite'))
    assert 'cannot be edited' in blocked
    assert php('echo wp_json_encode(get_the_title(%d));' % event_id) == 'HTTP workflow event'
    print('PASS: drafts are private; pending events reject stale edit requests')

    # Core REST cookie authentication uses the native WordPress nonce endpoint.
    status, _, nonce = request(owner, fixture['home'] + 'wp-admin/admin-ajax.php?action=rest-nonce')
    assert status == 200 and nonce.strip()
    status, _, _ = request(owner, fixture['home'] + 'wp-json/wp/v2/event/' + str(event_id),
                           {'status': 'publish'}, {'X-WP-Nonce': nonce.strip()})
    assert status == 403
    php('wp_update_post(array("ID"=>%d,"post_status"=>"draft")); echo "true";' % event_id)
    _, _, html = request(owner, manage)
    assert 'Save changes' in html and 'Submit for approval' in html
    php('wp_update_post(array("ID"=>%d,"post_status"=>"publish")); echo "true";' % event_id)
    _, _, html = request(owner, manage)
    details = form_with(html, 'b2b_save_event')
    request(owner, manage, dict(details, city='Mostar'))
    assert php('echo wp_json_encode(get_post_status(%d));' % event_id) == 'publish'
    _, _, html = request(anonymous, public_query)
    registration = form_with(html, 'b2b_submit_registration')
    identity = php('echo wp_json_encode(b2b_attendee_email_field_name(%d));' % event_id)
    _, _, html = request(anonymous, public_query, dict(registration, **{identity: 'http-attendee@example.com'}))
    assert 'Registration submitted successfully.' in html
    print('PASS: native REST denies organizer publication; published edits stay published and accept registrations')

    _, _, html = request(owner, manage)
    actions = Forms(html).forms
    approve = next(form for form in actions if form.get('new_status') == 'approved')
    _, _, html = request(owner, manage, approve)
    assert 'Registration status updated successfully.' in html
    assert php('echo wp_json_encode(get_post_meta(%d,"registration_status",true));' % int(approve['registration_id'])) == 'approved'
    assert 'Security check failed.' in request(owner, manage, dict(approve, b2b_status_nonce='invalid'))[2]
    other = browser()
    request(other, fixture['login'])
    request(other, fixture['login'], {'log': 'ee-other-' + token, 'pwd': 'Workflow!12345', 'testcookie': '1'})
    assert 'Access denied.' in request(other, manage)[2]
    assert 'Access denied.' in request(other, manage, dict(details, event_title='Other company overwrite'))[2]
    print('PASS: registration status POSTs keep nonce protection; other companies cannot view or edit the event')
finally:
    php('''
    require_once ABSPATH.'wp-admin/includes/user.php';
    foreach(get_posts(array('post_type'=>'event','post_status'=>'any','posts_per_page'=>-1,'author'=>%d)) as $event) {
        foreach(get_posts(array('post_type'=>'event_registration','post_status'=>'any','posts_per_page'=>-1,'meta_key'=>'event_id','meta_value'=>$event->ID)) as $registration) {wp_delete_post($registration->ID,true);}
        global $wpdb; $wpdb->delete(b2b_email_table(),array('event_id'=>$event->ID),array('%%d')); wp_delete_post($event->ID,true);
    }
    foreach(get_posts(array('post_type'=>'attachment','post_status'=>'inherit','post_parent'=>%d,'posts_per_page'=>-1)) as $attachment) {wp_delete_attachment($attachment->ID,true);}
    wp_delete_post(%d,true); wp_delete_post(%d,true); wp_delete_user(%d); wp_delete_user(%d); echo 'true';
    ''' % (fixture['owner'], fixture['company'], fixture['company'], fixture['other_company'], fixture['owner'], fixture['other']))
    print('PASS: disposable HTTP fixtures removed')
