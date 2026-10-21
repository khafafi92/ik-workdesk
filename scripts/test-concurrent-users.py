import argparse
import concurrent.futures
import ctypes
import html
import http.cookiejar
import json
import math
import os
from pathlib import Path
import re
import socket
import subprocess
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone, timedelta


class ResourceSampler:
    def __init__(self):
        self.samples = []
        self.phase = 'startup'
        self.stop = threading.Event()
        self.thread = threading.Thread(target=self.run, daemon=True)

    def run(self):
        class Memory(ctypes.Structure):
            _fields_ = [('length', ctypes.c_ulong), ('load', ctypes.c_ulong),
                        *[(name, ctypes.c_ulonglong) for name in
                          ['total', 'available', 'page_total', 'page_available',
                           'virtual_total', 'virtual_available', 'extended']]]
        previous = None
        while not self.stop.wait(.5):
            idle, kernel, user = ctypes.c_ulonglong(), ctypes.c_ulonglong(), ctypes.c_ulonglong()
            ctypes.windll.kernel32.GetSystemTimes(ctypes.byref(idle), ctypes.byref(kernel), ctypes.byref(user))
            current = (idle.value, kernel.value + user.value)
            memory = Memory()
            memory.length = ctypes.sizeof(memory)
            ctypes.windll.kernel32.GlobalMemoryStatusEx(ctypes.byref(memory))
            cpu = None
            if previous and current[1] > previous[1]:
                cpu = round(100 * (1 - (current[0] - previous[0]) / (current[1] - previous[1])), 1)
            previous = current
            self.samples.append({'phase': self.phase, 'system_cpu_percent': cpu,
                                 'system_available_mb': round(memory.available / 1048576, 1)})


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, message, headers, new_url):
        return None


class Client:
    def __init__(self, number, base, credentials, samples, lock):
        self.number = number
        self.base = base
        self.email = f"load-{credentials['token']}-{number}@example.test"
        self.password = credentials['password']
        self.samples = samples
        self.lock = lock
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.cookies), NoRedirect())
        self.csrf = None
        self.snapshot = None
        self.update_uri = None

    def request(self, path, phase, method='GET', data=None, headers=None, expected=200):
        start = time.perf_counter()
        status, body, response_headers, error = 0, b'', {}, None
        try:
            request = urllib.request.Request(self.base + path, data=data, method=method,
                                             headers=headers or {})
            try:
                response = self.opener.open(request, timeout=120)
            except urllib.error.HTTPError as exception:
                response = exception
            with response:
                status = response.code
                response_headers = {key.lower(): value for key, value in response.headers.items()}
                body = response.read()
        except (OSError, urllib.error.URLError) as exception:
            error = type(exception).__name__
        sample = {'phase': phase, 'user': self.number, 'method': method,
                  'path': path, 'status': status, 'expected': expected,
                  'ms': round((time.perf_counter() - start) * 1000, 2),
                  'bytes': len(body), 'ok': status == expected, 'error': error}
        if 'location' in response_headers:
            sample['redirect_path'] = urllib.parse.urlparse(response_headers['location']).path
        sample['cookie_count'] = len(self.cookies)
        with self.lock:
            self.samples.append(sample)
        return body.decode('utf-8', errors='replace'), response_headers, sample

    def login_page(self, phase):
        body, _, sample = self.request('/login', phase)
        match = re.search(r'name="_token"\s+value="([^"]+)"', body)
        if not match:
            sample['ok'] = False
            sample['error'] = 'missing_csrf'
            return False
        self.csrf = html.unescape(match[1])
        return sample['ok']

    def login(self, phase):
        data = urllib.parse.urlencode({'email': self.email, 'password': self.password,
                                      '_token': self.csrf}).encode()
        _, headers, sample = self.request('/login', phase, 'POST', data,
            {'Content-Type': 'application/x-www-form-urlencoded'}, expected=302)
        location = urllib.parse.urlparse(headers.get('location', '')).path
        sample['ok'] = sample['ok'] and location in ['/dashboard', '/panel']
        if not sample['ok']:
            sample['error'] = 'login_not_confirmed'

    def verify(self, phase):
        body, _, sample = self.request('/profile', phase)
        sample['ok'] = sample['ok'] and self.email in body
        if not sample['ok']:
            sample['error'] = 'wrong_authenticated_identity'

    def page(self, path, phase):
        body, _, sample = self.request(path, phase)
        sample['ok'] = sample['ok'] and 'wire:snapshot=' in body
        if not sample['ok']:
            sample['error'] = 'panel_content_not_confirmed'
        if path == '/panel/service-desk' and sample['ok']:
            candidates = [html.unescape(value) for value in re.findall(r'wire:snapshot="([^"]+)"', body)]
            matches = [value for value in candidates if 'list-tickets' in json.loads(value)['memo']['name']]
            if not matches:
                matches = sorted(candidates, key=len, reverse=True)
            self.snapshot = matches[0]
            token = re.search(r'name="csrf-token"\s+content="([^"]+)"', body)
            uri = re.search(r'data-update-uri="([^"]+)"', body)
            self.csrf = html.unescape(token[1]) if token else self.csrf
            self.update_uri = urllib.parse.urlparse(html.unescape(uri[1])).path if uri else None

    def refresh(self, phase):
        if not self.snapshot or not self.update_uri:
            raise RuntimeError('Livewire snapshot or update endpoint missing')
        payload = json.dumps({'_token': self.csrf, 'components': [{'snapshot': self.snapshot,
            'updates': {}, 'calls': [{'path': '', 'method': '$refresh', 'params': []}]}]}).encode()
        body, _, sample = self.request(self.update_uri, phase, 'POST', payload,
            {'Content-Type': 'application/json', 'X-Livewire': 'true',
             'Accept': 'application/json', 'Referer': self.base + '/panel/service-desk'})
        try:
            result = json.loads(body)
            component = result['components'][0]
            self.snapshot = component['snapshot']
            sample['ok'] = sample['ok'] and bool(component.get('effects', {}).get('html'))
        except (ValueError, KeyError, IndexError, TypeError):
            sample['ok'] = False
            sample['error'] = 'invalid_livewire_response'


def parallel(clients, action):
    barrier = threading.Barrier(len(clients))
    def synchronized(client):
        barrier.wait(timeout=30)
        return action(client)
    with concurrent.futures.ThreadPoolExecutor(max_workers=len(clients)) as executor:
        list(executor.map(synchronized, clients))


def summarize(samples, elapsed):
    values = sorted(sample['ms'] for sample in samples)
    percentile = lambda fraction: values[max(0, math.ceil(len(values) * fraction) - 1)]
    return {'requests': len(samples), 'failures': sum(not row['ok'] for row in samples),
            'p50_ms': percentile(.5), 'p95_ms': percentile(.95), 'max_ms': max(values),
            'wall_seconds': round(elapsed, 3), 'requests_per_second': round(len(samples) / elapsed, 3),
            'statuses': {str(status): sum(row['status'] == status for row in samples)
                         for status in sorted(set(row['status'] for row in samples))}}


def main():
    parser = argparse.ArgumentParser()
    for name in ['root', 'runtime', 'output', 'php']:
        parser.add_argument('--' + name, required=True)
    args = parser.parse_args()
    runtime, output = Path(args.runtime), Path(args.output)
    credentials = json.loads((runtime / 'credentials.json').read_text())
    samples, summaries, processes, logs = [], [], [], []
    lock = threading.Lock()
    resources = ResourceSampler()
    resources.thread.start()
    sockets, ports = [], []
    for _ in range(8):
        listener = socket.socket()
        listener.bind(('127.0.0.1', 0))
        ports.append(listener.getsockname()[1])
        sockets.append(listener)
    for listener in sockets:
        listener.close()
    try:
        for number, port in enumerate(ports):
            log = open(runtime / f'worker-{number}.log', 'wb')
            logs.append(log)
            processes.append(subprocess.Popen([args.php, '-d', 'opcache.enable_cli=1',
                '-d', 'opcache.memory_consumption=256', '-S', f'127.0.0.1:{port}',
                '-t', str(Path(args.root) / 'public'), str(runtime / 'router.php')],
                cwd=args.root, env=os.environ.copy(), stdout=log, stderr=log,
                creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0)))
        for port in ports:
            deadline = time.monotonic() + 30
            while True:
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=1):
                        break
                except OSError:
                    if time.monotonic() > deadline:
                        raise RuntimeError('PHP test worker failed to start')
                    time.sleep(.1)

        paths = ['/panel', '/panel/service-desk', '/panel/work-tasks', '/panel/it-devices',
                 '/panel/it-inspections', '/panel/attendance-results',
                 '/panel/reports/work-logs', '/panel/meeting-bookings']

        smoke = Client(1, f'http://127.0.0.1:{ports[0]}', credentials, samples, lock)
        smoke.login_page('smoke')
        smoke.login('smoke')
        smoke.verify('smoke')
        if any(not row['ok'] for row in samples):
            print(json.dumps(samples), flush=True)
            raise RuntimeError('Single-user authentication preflight failed')

        def phase(name, clients, action, workers):
            resources.phase = name
            before = len(samples)
            start = time.perf_counter()
            parallel(clients, action)
            result = {'phase': name, 'workers': workers, 'users': len(clients),
                      **summarize(samples[before:], time.perf_counter() - start)}
            summaries.append(result)
            print(json.dumps(result), flush=True)
            (output / 'http-samples.json').write_text(json.dumps(samples, indent=2))
            (output / 'http-summary.json').write_text(json.dumps(summaries, indent=2))

        for workers in [1, 8]:
            clients = [Client(i + 1, f'http://127.0.0.1:{ports[i % workers]}',
                              credentials, samples, lock) for i in range(40)]
            prefix = f'{workers}worker'
            phase(prefix + '-login-form', clients, lambda c: c.login_page(prefix + '-login-form'), workers)
            phase(prefix + '-login-burst', clients, lambda c: c.login(prefix + '-login-burst'), workers)
            phase(prefix + '-identity', clients, lambda c: c.verify(prefix + '-identity'), workers)
            if any(not row['ok'] for row in samples if row['phase'].startswith(prefix)):
                raise RuntimeError('Login or independent identity verification failed')

            def warm(client):
                for path in paths:
                    client.page(path, prefix + '-warmup')
            phase(prefix + '-warmup', clients[:workers], warm, workers)
            for count in [1, 10, 20, 40]:
                name = prefix + f'-mixed-{count}'
                def browse(client):
                    for iteration in range(3):
                        client.page(paths[(client.number + iteration - 1) % len(paths)], name)
                        time.sleep(.5)
                phase(name, clients[:count], browse, workers)

            name = prefix + '-service-desk-40'
            phase(name, clients, lambda c: c.page('/panel/service-desk', name), workers)
            name = prefix + '-livewire-40'
            def refresh(client):
                for _ in range(3):
                    client.refresh(name)
                    time.sleep(.5)
            phase(name, clients, refresh, workers)
            name = prefix + '-logout-40'
            def logout(client):
                data = urllib.parse.urlencode({'_token': client.csrf}).encode()
                _, _, sample = client.request('/logout', name, 'POST', data,
                    {'Content-Type': 'application/x-www-form-urlencoded'}, expected=302)
                sample['ok'] = sample['ok'] and sample.get('redirect_path') == '/login'
                _, _, sample = client.request('/panel', name + '-denied', expected=302)
                sample['ok'] = sample['ok'] and sample.get('redirect_path') in ['/login', '/panel/login']
            phase(name, clients, logout, workers)

        timestamp = datetime.now(timezone(timedelta(hours=7))).isoformat()
        (output / 'run.json').write_text(json.dumps({'completed_at_wib': timestamp,
            'workers': [1, 8], 'think_time_seconds': .5, 'iterations_per_mixed_user': 3,
            'thresholds_ms': {'login_p95': 5000, 'page_p95': 2000, 'livewire_p95': 2000},
            'any_http_or_content_failures': any(not row['ok'] for row in samples),
            'worker_model': '1 or 8 PHP built-in servers; clients assigned round robin, no proxy; not PHP-FPM'}, indent=2))
        if any(not row['ok'] for row in samples):
            raise RuntimeError('At least one HTTP status or content validation failed')
    finally:
        for process in processes:
            process.terminate()
        for process in processes:
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait(timeout=10)
        for log in logs:
            log.close()
        resources.stop.set()
        resources.thread.join(timeout=2)
        (output / 'system-resources.json').write_text(json.dumps(resources.samples, indent=2))


if __name__ == '__main__':
    main()
