#!/usr/bin/env python3
"""Create an unconfigured VvE instance. Refuses to overwrite existing instances."""
import argparse
import json
import re
import shutil
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def php_string(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"


def create_instance(destination, name, host):
    if not re.fullmatch(r'[a-z0-9][a-z0-9.-]+', host) or '/' in host or '..' in host:
        raise ValueError('Use a lowercase hostname without https:// or a path')
    if not name.strip() or any(ord(c) < 32 for c in name):
        raise ValueError('Invalid VvE name')
    if destination.exists():
        raise ValueError('Destination already exists; refusing to overwrite')
    release = ROOT / 'release'
    if not (release / 'schema.json').is_file():
        raise ValueError('Build the release first')
    destination.mkdir(parents=True)
    shutil.copytree(release / 'public', destination / 'public')
    private = destination / 'private'
    private.mkdir(mode=0o700)
    instance_id = uuid.uuid4().hex
    (private / 'tenant.php').write_text("<?php\nreturn ['id'=>" + php_string(instance_id) + ",'name'=>" + php_string(name) + ",'host'=>" + php_string(host) + "];\n")
    for directory in ['uploads', 'sessions', 'rate-limits', 'backups', 'tmp']:
        (private / directory).mkdir(mode=0o700)
    for filename in ['config.example.php', 'lookups.php', 'initial-admin.example.php']:
        shutil.copyfile(ROOT / 'templates' / filename, private / filename)
    for p in private.glob('*.php'):
        p.chmod(0o600)
    (private / '.htaccess').write_text('Require all denied\n')
    (destination / '.htaccess').write_text('Options -Indexes\nRequire all denied\n')
    install = destination / 'install'
    install.mkdir()
    (install / '.htaccess').write_text('Require all denied\n')
    for filename in ['install.php', 'create_admin.php', 'check.php']:
        shutil.copyfile(ROOT / 'templates' / filename, install / filename)
    shutil.copyfile(release / 'schema.json', install / 'schema.json')
    (destination / 'INSTANCE.txt').write_text(
        f'VvE: {name}\nHost: {host}\nDocument-root: this instance/public\n'
        'NOT INSTALLED. Configure a NEW empty database in private/config.php.\n'
        'Then use install/create_admin.php and install/install.php from SSH.\n'
        'Never copy private/ to a different VvE. Use create_instance.py again.\n'
    )
    return instance_id


if __name__ == '__main__':
    p = argparse.ArgumentParser()
    p.add_argument('--destination', type=Path, required=True)
    p.add_argument('--name', required=True)
    p.add_argument('--host', required=True)
    a = p.parse_args()
    create_instance(a.destination, a.name, a.host)
    print(json.dumps({'created': str(a.destination), 'host': a.host, 'configured': False}))
