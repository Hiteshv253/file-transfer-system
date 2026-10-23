"""Opt-in live test. Creates/removes one QA user and four QA files only.

Set FILEVAULT_TEST_URL, FILEVAULT_TEST_EMAIL, FILEVAULT_TEST_PASSWORD.
"""
import http.cookiejar
import json
import os
import re
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ['FILEVAULT_TEST_URL'].rstrip('/')


class Client:
    def __init__(self):
        self.web = urllib.request.build_opener(urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.token = None

    def get(self, path):
        return self.web.open(BASE + path, timeout=30)

    def page(self, path):
        response = self.get(path)
        text = response.read().decode('utf-8')
        token = re.search(r'name="_token"\s+value="([^"]+)"', text)
        if token:
            self.token = token.group(1)
        return text

    def post(self, path, fields):
        fields = list(fields.items()) if isinstance(fields, dict) else fields
        request = urllib.request.Request(BASE + path,
            urllib.parse.urlencode([('_token', self.token)] + fields).encode(),
            headers={'Referer': BASE + '/files'})
        response = self.web.open(request, timeout=30)
        text = response.read().decode('utf-8')
        token = re.search(r'name="_token"\s+value="([^"]+)"', text)
        if token:
            self.token = token.group(1)
        return text

    def login(self, email, password):
        self.page('/files/login')
        for attempt in range(2):
            try:
                return self.post('/files/login', {'email': email, 'password': password})
            except urllib.error.HTTPError as error:
                if error.code != 429 or attempt:
                    raise
                wait = int(error.headers.get('Retry-After', '60')) + 1
                assert wait <= 65, 'Login rate limit is longer than this test allows'
                print(f'INFO: respecting login rate limit; retrying in {wait}s', flush=True)
                time.sleep(wait)

    def upload(self, name, content):
        boundary = 'qa' + uuid.uuid4().hex
        data = (f'--{boundary}\r\nContent-Disposition: form-data; name="upload_key"\r\n\r\n{uuid.uuid4()}\r\n'
            f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: text/plain\r\n\r\n').encode()
        data += content + f'\r\n--{boundary}--\r\n'.encode()
        request = urllib.request.Request(BASE + '/files/upload', data, headers={
            'X-CSRF-TOKEN': self.token, 'Accept': 'application/json',
            'Content-Type': f'multipart/form-data; boundary={boundary}'})
        response = self.web.open(request, timeout=30)
        assert response.status == 201
        return json.loads(response.read())['document']['id']

    def status(self, path):
        try:
            response = self.get(path)
            response.read()
            return response.status
        except urllib.error.HTTPError as error:
            return error.code


def pending_ids(page, prefix):
    result = []
    for row in re.findall(r'<tr>(.*?)</tr>', page, re.S):
        if prefix not in row:
            continue
        found = re.search(r'value="(\d+)"[^>]*data-select-item="requests"', row)
        if found:
            result.append(int(found.group(1)))
    return result


admin = Client()
qa_user = None
documents = []
prefix = 'filevault-admin-qa-' + uuid.uuid4().hex
try:
    assert 'data-tab="users"' in admin.login(os.environ['FILEVAULT_TEST_EMAIL'], os.environ['FILEVAULT_TEST_PASSWORD'])
    email = prefix + '@example.test'
    old_password, new_password = 'Qa1' + secrets.token_hex(12), 'Qa2' + secrets.token_hex(12)
    fields = [('name', 'FileVault QA temporary account'), ('email', email),
        ('password', old_password), ('password_confirmation', old_password)]
    fields += [('permissions[]', permission) for permission in
        ['view_own', 'view_shared', 'upload', 'preview', 'download', 'request_delete']]
    admin.post('/files/users', fields)
    users = admin.page('/files?tab=users')
    for card in re.findall(r'<article class="user-card">(.*?)</article>', users, re.S):
        if email in card:
            qa_user = int(re.search(r'/files/users/(\d+)/password', card).group(1))
    assert qa_user, 'Temporary QA account was not created (check user quota)'
    owner = Client()
    assert 'id="upload-form"' in owner.login(email, old_password)
    admin.post(f'/files/users/{qa_user}/password', {'password': new_password, 'password_confirmation': new_password})
    assert 'id="email"' in owner.page('/files'), 'Old session must return to login'
    assert 'id="email"' in Client().login(email, old_password), 'Old password must fail'
    owner = Client()
    assert 'id="upload-form"' in owner.login(email, new_password)
    print('PASS: password reset, old-session revocation, old/new password login')
    for n in range(4):
        documents.append(owner.upload(prefix + f'-{n}.txt', b'FileVault bulk operation verification'))
    denied = None
    try:
        owner.post('/files/bulk-delete', [('ids[]', documents[0])])
    except urllib.error.HTTPError as error:
        denied = error.code
    assert denied == 403
    print('PASS: regular user cannot call admin bulk-delete endpoint')
    for document in documents[:2]:
        owner.post(f'/files/{document}/request-deletion', {'reason': 'Temporary QA bulk review'})
    ids = pending_ids(admin.page('/files?tab=requests'), prefix)
    assert len(ids) == 2
    rejected = admin.post('/files/requests/bulk-review', [('ids[]', value) for value in ids] + [('decision', 'rejected')])
    assert 'Rejected 2 requests. 0 failed.' in rejected
    assert all(owner.status(f'/files/{value}/download') == 200 for value in documents[:2])
    print('PASS: bulk rejection retains both selected files')
    for document in documents[:2]:
        owner.post(f'/files/{document}/request-deletion', {'reason': 'Temporary QA approval'})
    ids = pending_ids(admin.page('/files?tab=requests'), prefix)
    assert len(ids) == 2
    approved = admin.post('/files/requests/bulk-review', [('ids[]', value) for value in ids] + [('decision', 'approved')])
    assert 'Approved 2 requests. 0 failed.' in approved
    assert all(owner.status(f'/files/{value}/download') == 404 for value in documents[:2])
    print('PASS: bulk approval deletes both selected files')
    deleted = admin.post('/files/bulk-delete', [('ids[]', value) for value in documents[2:]])
    assert 'Deleted 2 files. 0 failed.' in deleted
    assert all(owner.status(f'/files/{value}/download') == 404 for value in documents[2:])
    print('PASS: bulk deletion removes both selected files')
finally:
    errors = []
    for document in documents:
        try:
            if admin.status(f'/files/{document}/download') == 200:
                admin.post(f'/files/{document}/delete', {})
        except Exception as error:
            errors.append(str(error))
    if qa_user:
        try:
            admin.post(f'/files/users/{qa_user}/delete', {})
        except Exception as error:
            errors.append(str(error))
    assert not errors, 'QA cleanup failed: ' + '; '.join(errors)
    print('PASS: temporary QA user and files removed; audit history retained')
