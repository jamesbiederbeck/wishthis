"""HTTP smoke tests for the pre-install flow (no database or third-party Python packages)."""
import os
from pathlib import Path
import socket
import subprocess
import time
import urllib.error
import urllib.request
import re
import tempfile

ROOT = Path(__file__).resolve().parent.parent

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

for prefix in ('', '/wishthis', '/apps/wishes'):
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    with tempfile.TemporaryDirectory() as sessions, tempfile.TemporaryFile() as log:
        server = subprocess.Popen(
            [os.environ.get('PHP_BINARY', 'php'), '-d', 'session.save_path=' + sessions,
             '-S', f'127.0.0.1:{port}', '-t', str(ROOT)],
            env={**os.environ, 'WISHTHIS_BASE_PATH': prefix}, stdout=log, stderr=log,
        )
        try:
            for attempt in range(100):
                if server.poll() is not None:
                    raise RuntimeError('PHP server exited')
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1):
                        break
                except OSError:
                    time.sleep(.05)
            opener = urllib.request.build_opener(NoRedirect)
            def get(path):
                try:
                    return opener.open(f'http://127.0.0.1:{port}' + path, timeout=10)
                except urllib.error.HTTPError as response:
                    return response
            # Proxy-stripped requests: the response must still contain the public prefix.
            with get('/index.php') as response:
                assert response.status == 302, response.read().decode()
                assert response.headers['Location'] == prefix + '/index.php?page=install'
                assert 'path=' + prefix + '/' in response.headers['Set-Cookie']
            with get('/index.php?page=install') as response:
                html = response.read().decode()
                assert response.status == 200
                assert 'Warning' not in html and 'Fatal error' not in html
                assert f'action="{prefix}/index.php?page=install"' in html
                assert f'wishthis.basePath = "{prefix.replace(chr(47), chr(92)+chr(47))}"' in html
                for path in re.findall(r'(?:src|href|action)="(/[^"\s]*)"', html):
                    assert path.startswith(prefix + '/'), path
            print('PASS HTTP prefix', prefix or '/')
        finally:
            server.terminate()
            server.wait(timeout=10)
