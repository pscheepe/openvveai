#!/usr/bin/env python3
"""Integration test against a caller-supplied disposable MariaDB socket.

Creates and removes only randomized test databases and users of its own.
No source database, external mail service, or AI provider is contacted.
"""
import argparse
import http.client
import json
import os
from pathlib import Path
import re
import socket
import subprocess
import sys
import tempfile
import time
import urllib.parse
import uuid
import zipfile

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tools'))
from create_instance import create_instance, php_string


def run(args, **kw):
    return subprocess.run(args, capture_output=True, text=True, **kw)


def main(args):
    token = uuid.uuid4().hex[:10]
    databases, users, checks = [], [], []
    base = Path(tempfile.mkdtemp(prefix='vve-clean-integration-'))
    server = None
    log = None

    def sql(query):
        result = run([args.mysql, '--no-defaults', '--socket='+args.socket, '-u', 'root', '-N'], input=query)
        if result.returncode:
            raise AssertionError('Test database SQL failed: '+result.stderr)
        return result.stdout.strip()

    def check(label, condition):
        if not condition:
            raise AssertionError(label)
        checks.append(label)

    class Client:
        def __init__(self, host):
            self.host, self.cookie, self.csrf = host, '', ''

        def request(self, path, data=None, raw=None, content_type=None, extra=None):
            headers = {'Host':self.host}
            if self.cookie:
                headers['Cookie'] = self.cookie
            if self.csrf:
                headers['X-CSRF-Token'] = self.csrf
            body = None
            if data is not None:
                body = urllib.parse.urlencode(data).encode()
                headers['Content-Type'] = 'application/x-www-form-urlencoded'
            if raw is not None:
                body = raw
                headers['Content-Type'] = content_type or 'application/json'
            headers.update(extra or {})
            conn = http.client.HTTPConnection('127.0.0.1', port, timeout=20)
            conn.request('POST' if body is not None else 'GET', path, body, headers)
            response = conn.getresponse()
            text = response.read().decode('utf8', errors='replace')
            cookies = [v for k,v in response.getheaders() if k.lower() == 'set-cookie']
            if cookies:
                self.cookie = cookies[-1].split(';',1)[0]
            status = response.status
            conn.close()
            return status, text, cookies

        def json(self, path, **kw):
            status, text, _ = self.request(path, **kw)
            try:
                value = json.loads(text)
            except Exception:
                raise AssertionError(f'{self.host} {path}: HTTP {status}, {text[:400]}')
            if status != 200 or not value.get('ok'):
                raise AssertionError(f'{self.host} {path}: HTTP {status}, {value}')
            return value

    try:
        hosts, paths = {}, []
        for suffix in ['a','b']:
            name = 'vve_clean_'+token+'_'+suffix
            user = 'vc_'+token+'_'+suffix
            sql(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4;')
            databases.append(name)
            sql(f"CREATE USER '{user}'@'localhost' IDENTIFIED BY 'Synthetic-Database-Only-483!'; GRANT ALL ON `{name}`.* TO '{user}'@'localhost';")
            users.append(user)
            path = base / suffix
            host = 'demo-'+suffix+'.example.invalid'
            create_instance(path, 'Demo VvE '+suffix.upper(), host)
            hosts[host] = str(path)
            paths.append(path)
            config = "<?php return ['host'=>'localhost','database'=>"+php_string(name)+",'username'=>"+php_string(user)+",'password'=>'Synthetic-Database-Only-483!','socket'=>"+php_string(args.socket)+"];"
            (path/'private/config.php').write_text(config)
            password_hash = run([args.php,'-r',"echo password_hash('Synthetic-Login-Password-483!', PASSWORD_DEFAULT);"]).stdout
            (path/'private/initial-admin.php').write_text("<?php return ['username'=>'admin','name'=>'Demo admin','password_hash'=>"+php_string(password_hash)+"]; ")
            installed = run([args.php,str(path/'install/install.php')])
            check('empty install '+suffix, installed.returncode == 0 or (_ for _ in ()).throw(AssertionError(installed.stderr)))
            result = run([args.php,str(path/'install/check.php')])
            check('all business tables initially empty '+suffix, result.returncode == 0)
            check('initialization refuses a second run '+suffix, run([args.php,str(path/'install/install.php')]).returncode != 0)
            sql(f"REVOKE ALL PRIVILEGES ON `{name}`.* FROM '{user}'@'localhost'; GRANT SELECT, INSERT, UPDATE, DELETE, SHOW VIEW ON `{name}`.* TO '{user}'@'localhost';")
        with socket.socket() as s:
            s.bind(('127.0.0.1',0))
            port = s.getsockname()[1]
        env = dict(os.environ, VVE_TEST_INSTANCES=json.dumps(hosts))
        log = (base/'server.log').open('w')
        server = subprocess.Popen([args.php,'-d','display_errors=0','-S',f'127.0.0.1:{port}',str(ROOT/'tests/router.php')],env=env,stdout=log,stderr=log)
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.2): break
            except OSError: time.sleep(.1)
        a, b = Client('demo-a.example.invalid'), Client('demo-b.example.invalid')
        unknown = Client('wrong.example.invalid')
        check('unknown hostname rejected', unknown.request('/')[0] == 421)
        check('plain HTTP refused by application', a.request('/',extra={'X-Test-Plain-Http':'1'})[0] == 400)
        for client in [a,b]:
            check('anonymous API denied '+client.host, client.request('/api.php?action=meta')[0] != 200)
            status, text, cookies = client.request('/', data={'action':'login','username':'admin','password':'Synthetic-Login-Password-483!'})
            check('admin login '+client.host, status == 302)
            check('secure host-only cookie '+client.host, bool(cookies) and 'secure' in cookies[-1].lower() and 'httponly' in cookies[-1].lower() and 'domain=' not in cookies[-1].lower())
            client.csrf = client.json('/api.php?action=session')['csrf']
            meta = client.json('/api.php?action=meta')
            check('all modules available '+client.host, all(k in meta['entities'] for k in ['contacts','addresses','relations','Activa','maintenance_plans','meetings','actions']))
            check('internal instance marker hidden '+client.host, 'VveInstallation' not in meta['entities'])
            for page in ['index.php','documents.php','projects.php','assistant.php','settings.php','user_management.php','email_settings.php','ai_settings.php','backup_settings.php','activity_log.php','version_log.php']:
                status, text, _ = client.request('/'+page)
                check('page '+client.host+'/'+page, status == 200 and 'Fatal error' not in text and 'SQLSTATE' not in text)
            client.json('/project_api.php?action=list')
            client.json('/document_api.php?action=list')
        check('distinct cookie namespaces', a.cookie.split('=')[0] != b.cookie.split('=')[0])
        meta = a.json('/api.php?action=meta')
        records = {}
        for entity, wanted in [
            ('contacts', {'achternaam':'Fictief-A'}),
            ('addresses', {'straat':'Fictieve teststraat'}),
            ('relations', {'naam':'Fictief testbedrijf A'}),
            ('Activa', {'naam':'Fictieve lift A'}),
            ('maintenance_plans', {'naam':'Fictief onderhoud A'}),
            ('meetings', {'soort':'Bestuursvergadering','datum':'2026-09-21'}),
            ('actions', {'actie':'Fictieve actie A'}),
            ('Sleutels', {'functie':'Fictieve sleutel A'}),
        ]:
            columns = meta['entities'][entity]['columns']
            mapping = {re.sub('[^a-z0-9]','',c.lower()):c for c in columns}
            fields = {mapping[key]:value for key,value in wanted.items() if key in mapping}
            if entity == 'actions': fields[mapping['vergaderingid']] = records['meetings']
            if 'actief' in mapping: fields[mapping['actief']] = 'Waar'
            saved = a.json('/api.php?action=save&entity='+entity,raw=json.dumps(fields).encode())
            records[entity] = saved['id']
            got = a.json('/api.php?action=get&entity='+entity+'&id='+str(saved['id']))
            check('create and read '+entity, bool(got.get('row')))
            other = b.json('/api.php?action=list&entity='+entity+'&status=__all__')
            check('B has no A records in '+entity, not other.get('rows'))
        project = a.json('/project_api.php?action=save',raw=json.dumps({'Naam':'Fictief project A','Status':'groen'}).encode())
        project_id = project.get('project', {}).get('ProjectID')
        check('project creation', bool(project_id))
        extra = a.json('/maintenance_extra_api.php?id='+str(records['maintenance_plans']))
        check('maintenance details without DDL', bool(extra.get('ok')))
        for endpoint, entity in [('maintenance_export_pdf.php','maintenance_plans'),('project_export_pdf.php',None)]:
            record_id = records[entity] if entity else project_id
            status, text, _ = a.request('/'+endpoint+'?id='+str(record_id))
            check('PDF export '+endpoint, status == 200 and text.startswith('%PDF'))
        forged = Client(b.host)
        forged.cookie = b.cookie.split('=')[0]+'='+a.cookie.split('=',1)[1]
        check('A session cannot authenticate to B', forged.request('/api.php?action=meta')[0] != 200)
        check('CSRF cannot cross VvEs', b.request('/document_api.php?action=create_folder',data={'name':'Rejected'},extra={'X-CSRF-Token':a.csrf})[0] != 200)
        folder = a.json('/document_api.php?action=create_folder',data={'name':'Synthetic A folder'})['id']
        boundary = 'VveSyntheticBoundary'
        content = 'SYNTHETIC-ONLY-A-482 onderhoud fictieve lift.'
        body = (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="synthetic-a.txt"\r\nContent-Type: text/plain\r\n\r\n{content}\r\n--{boundary}--\r\n').encode()
        uploaded = a.json('/document_api.php?action=upload&map='+str(folder),raw=body,content_type='multipart/form-data; boundary='+boundary)
        doc_id = uploaded.get('id', uploaded.get('document_id'))
        check('upload returns document identifier', bool(doc_id))
        status, text, _ = a.request('/document_download.php?id='+str(doc_id))
        check('A can download own file', status == 200 and content in text)
        status, text, _ = b.request('/document_download.php?id='+str(doc_id))
        check('B cannot download A identifier', status != 200 and content not in text)
        listing = b.json('/document_api.php?action=list&q=SYNTHETIC-ONLY-A-482')
        check('B search cannot find A data', 'synthetic-a.txt' not in json.dumps(listing))
        answer = b.json('/assistant_api.php',data={'question':'Zoek documenten over SYNTHETIC-ONLY-A-482'})
        check('B assistant cannot retrieve A sources', not answer.get('sources'))
        check('B uploads remain empty', not list((paths[1]/'private/uploads').rglob('*')))
        for path in ['/private/config.php','/../private/config.php','/uploads/synthetic-a.txt','/install/install.php']:
            check('private URL denied '+path, a.request(path)[0] == 404)
        # Misconfiguration must fail closed even if the wrong DB credentials are valid.
        original = (paths[1]/'private/config.php').read_text()
        (paths[1]/'private/config.php').write_text((paths[0]/'private/config.php').read_text())
        try:
            status, text, _ = b.request('/api.php?action=meta')
            check('wrong database binding rejected', status != 200)
        finally:
            (paths[1]/'private/config.php').write_text(original)
        backup_code = "require "+php_string(str(paths[0]/'public/backup_lib.php'))+"; runLocalBackup(backupConfig(),false,true);"
        backed_up = run([args.php,'-r',backup_code])
        check('per-VvE backup succeeds', backed_up.returncode == 0 or (_ for _ in ()).throw(AssertionError(backed_up.stderr)))
        archives = list((paths[0]/'private/backups').rglob('*.zip'))
        check('application archive created', len(archives) == 1)
        with zipfile.ZipFile(archives[0]) as archive:
            names = archive.namelist()
            check('backup contains own users and configuration', 'vve-instance/private/users.php' in names and 'vve-instance/private/config.php' in names)
            check('backup contains own documents', any('/private/uploads/bibliotheek/' in n for n in names))
            check('backup excludes sessions and recursive backups', not any('/private/sessions/' in n or '/private/backups/' in n for n in names))
        result = {'passed':len(checks),'checks':checks,'log':str(base/'server.log'),'note':'Local HTTP harness simulates HTTPS; live Apache/SSL still require verification.'}
        (ROOT/'TEST_RESULTS.json').write_text(json.dumps(result,indent=2)+'\n')
        print(json.dumps({'passed':len(checks),'status':'ok'}))
    finally:
        if server:
            server.terminate()
            server.wait(timeout=10)
        if log: log.close()
        for name in databases: sql(f'DROP DATABASE `{name}`;')
        for user in users: sql(f"DROP USER '{user}'@'localhost';")
        print('Test workspace:',base)


if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--php',required=True)
    p.add_argument('--mysql',required=True)
    p.add_argument('--socket',required=True)
    main(p.parse_args())
