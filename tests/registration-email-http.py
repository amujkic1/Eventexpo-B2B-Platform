"""Local-only real parallel HTTP integration test; fixtures are removed on exit."""
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from pathlib import Path
import http.cookiejar
import json
import subprocess
import threading
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
env = dict(line.split('=', 1) for line in (ROOT / '.env.local').read_text().splitlines() if line and not line.startswith('#'))
BASE = 'http://localhost:' + env.get('EE_PORT', '8080')
CLI = ['docker', 'compose', '--env-file', '.env.local', '-f', 'compose.local.yaml', 'run', '--rm', '-T', 'cli', 'eval-file', '-']
def php(source):
    result = subprocess.run(CLI, input='<?php\n' + source, text=True, capture_output=True, cwd=ROOT, check=True)
    return result.stdout.strip()

fixture = json.loads(php("""
if ('local' !== wp_get_environment_type()) { throw new RuntimeException('Local tests only'); }
$id = wp_insert_post(array('post_type'=>'event','post_status'=>'publish','post_title'=>'Parallel email regression fixture'));
$organizer = get_user_by('login','organizer');
update_post_meta($id,'organizer_company_id',get_user_meta($organizer->ID,'company_id',true));
update_post_meta($id,'registration_fields',array(
 array('name'=>'work_email','label'=>'Work email','type'=>'email','required'=>false),
 array('name'=>'backup_email','label'=>'Backup email','type'=>'email','required'=>false),
 array('name'=>'full_name','label'=>'Name','type'=>'text','required'=>true)
));
echo wp_json_encode(array('id'=>$id,'public'=>get_permalink($id),'manage'=>b2b_get_manage_event_url($id)));
"""))

class Forms(HTMLParser):
    def __init__(self, html):
        super().__init__(); self.forms = []; self.current = None; self.feed(html)
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'form': self.current = {}; self.forms.append(self.current)
        if tag == 'input' and self.current is not None and attrs.get('name'):
            self.current[attrs['name']] = attrs.get('value', '')
    def handle_endtag(self, tag):
        if tag == 'form': self.current = None

def browser():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(client, url, data=None):
    url = urllib.parse.urljoin(BASE, url)
    assert urllib.parse.urlsplit(url).netloc == urllib.parse.urlsplit(BASE).netloc
    response = client.open(url, urllib.parse.urlencode(data).encode() if data is not None else None, timeout=30)
    html = response.read().decode()
    assert response.status == 200 and 'Fatal error' not in html
    return html

DUPLICATE = 'You have already registered for this event using this email address.'
try:
    anonymous = browser()
    html = request(anonymous, fixture['public'])
    form = next(f for f in Forms(html).forms if 'b2b_submit_registration' in f)
    assert html.count('name="work_email"') == 1
    for data in [dict(form, work_email='', full_name='Missing'), dict(form, work_email='invalid', full_name='Invalid'), dict(form, **{'work_email[]': 'bypass@example.com', 'backup_email': 'valid@example.com', 'full_name': 'Tampered'})]:
        if 'work_email[]' in data: data.pop('work_email', None)
        response = request(anonymous, fixture['public'], data)
        assert 'Please correct the highlighted fields.' in response
    print('PASS: missing, invalid and manipulated identity cannot bypass public validation')
    barrier = threading.Barrier(8)
    def submit(index):
        client = browser()  # Separate clients/connections; no login needed.
        data = dict(form, work_email='Concurrent@Example.com' if index % 2 else 'concurrent@example.com', full_name='Parallel attendee', backup_email=f'backup{index}@example.com')
        barrier.wait(timeout=15)
        return request(client, fixture['public'], data)
    with ThreadPoolExecutor(max_workers=8) as pool:
        results = list(pool.map(submit, range(8)))
    assert sum('Registration submitted successfully.' in html for html in results) == 1
    assert sum(DUPLICATE in html for html in results) == 7
    for html in results:
        if DUPLICATE in html:
            assert 'value="Parallel attendee"' in html and 'registration_success' not in urllib.parse.urlsplit(fixture['public']).query
    count = php(f"""
$event = {int(fixture['id'])};
global $wpdb;
$posts = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->posts p JOIN $wpdb->postmeta m ON p.ID=m.post_id WHERE p.post_type='event_registration' AND m.meta_key='event_id' AND m.meta_value=%d",$event));
$table=b2b_email_table(); $claims=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE event_id=%d AND registration_id>0",$event));
echo wp_json_encode(array('posts'=>(int)$posts,'claims'=>(int)$claims));
""")
    assert json.loads(count) == {'posts': 1, 'claims': 1}
    print('PASS: 8 simultaneous anonymous submissions create exactly 1 post and 1 claim; 7 duplicates are rejected')
    # A retry after the winning redirect still cannot create another registration.
    assert DUPLICATE in request(anonymous, fixture['public'], dict(form, work_email='CONCURRENT@EXAMPLE.COM', full_name='Retry'))
    print('PASS: browser retry is blocked and failed input stays in the form')
    logged_in = browser()
    request(logged_in, '/wp-login.php')
    request(logged_in, '/wp-login.php', {'log':'organizer','pwd':env['EE_ORGANIZER_PASSWORD'],'redirect_to':BASE+'/','testcookie':'1'})
    html = request(logged_in, fixture['manage'])
    edit = next(f for f in Forms(html).forms if f.get('b2b_field_action') == 'edit' and f.get('field_name') == 'work_email')
    edit.update(field_label='Renamed attendee email',field_type='email',field_required='1')
    html = request(logged_in, fixture['manage'], edit)
    delete = next(f for f in Forms(html).forms if f.get('b2b_field_action') == 'delete' and f.get('field_name') == 'backup_email')
    delete['confirm_delete'] = '1'
    request(logged_in, fixture['manage'], delete)
    public = request(logged_in, fixture['public'])
    form = next(f for f in Forms(public).forms if 'b2b_submit_registration' in f)
    assert 'Renamed attendee email' in public and 'name="backup_email"' not in public
    assert DUPLICATE in request(logged_in, fixture['public'], dict(form, work_email='concurrent@example.com', full_name='Signed in retry'))
    assert 'Registration submitted successfully.' in request(logged_in, fixture['public'], dict(form, work_email='signedin@example.com', full_name='Signed in attendee'))
    print('PASS: rename/deletion of custom fields preserve duplicate protection; authenticated registration succeeds')
finally:
    php(f"""
if ('local' !== wp_get_environment_type()) {{ throw new RuntimeException('Local only'); }}
$id={int(fixture['id'])};
if ('Parallel email regression fixture' !== get_the_title($id)) {{ throw new RuntimeException('Not a fixture'); }}
foreach(get_posts(array('post_type'=>'event_registration','post_status'=>'any','posts_per_page'=>-1,'meta_key'=>'event_id','meta_value'=>$id)) as $post) {{ wp_delete_post($post->ID,true); }}
global $wpdb; $wpdb->delete(b2b_email_table(),array('event_id'=>$id),array('%d')); wp_delete_post($id,true);
""")
