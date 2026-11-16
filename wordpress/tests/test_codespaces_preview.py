#!/usr/bin/env python3
"""Isolated lifecycle tests. Docker is simulated; this is NOT a WordPress/Codespaces run."""
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess
import tempfile
import unittest

FAKE_DOCKER = r'''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
root = Path(os.environ['PREVIEW_TEST_STATE'])
args = sys.argv[1:]
with (root / 'calls.jsonl').open('a') as f:
    f.write(json.dumps(args) + '\n')
if args[:1] == ['info'] or args[:2] == ['compose', 'version']:
    sys.exit(0)
if args[:1] != ['compose']:
    sys.exit(9)
i = 1
while i < len(args) and args[i] in ('--project-name', '--env-file', '-f'):
    i += 2
operation = args[i:]
if operation[:1] == ['run']:
    command = operation[3:]
    if command == ['wp', 'core', 'is-installed']:
        sys.exit(0 if (root / 'database-installed').exists() else 1)
    if command == ['sh', '/project/scripts/setup-wp.sh']:
        (root / 'database-installed').touch()
        if (root / 'fail-setup').exists():
            sys.exit(7)
sys.exit(0)
'''


class PreviewLifecycle(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.wp = self.root / 'wordpress'
        (self.wp / 'scripts').mkdir(parents=True)
        source = Path(__file__).resolve().parents[1] / 'scripts/codespaces-preview.sh'
        self.script = self.wp / 'scripts/codespaces-preview.sh'
        shutil.copyfile(source, self.script)
        self.bin = self.root / 'bin'
        self.bin.mkdir()
        docker = self.bin / 'docker'
        docker.write_text(FAKE_DOCKER)
        docker.chmod(0o755)
        self.env = dict(os.environ)
        self.env.update(
            PATH=str(self.bin) + os.pathsep + self.env['PATH'],
            CODESPACES='true',
            CODESPACE_NAME='pawline-preview-example',
            GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN='app.github.dev',
            PREVIEW_TEST_STATE=str(self.root),
        )
        self.password_file = self.wp / '.codespaces/preview.env'
        self.marker = self.wp / '.codespaces/setup-complete'

    def run_preview(self, action='start', env=None):
        return subprocess.run(
            ['bash', str(self.script), action], env=env or self.env,
            capture_output=True, text=True, timeout=10,
        )

    def calls(self):
        path = self.root / 'calls.jsonl'
        return [json.loads(x) for x in path.read_text().splitlines()] if path.exists() else []

    def command_count(self, suffix):
        return sum(call[-len(suffix):] == suffix for call in self.calls())

    def test_refuses_execution_outside_codespaces(self):
        env = dict(self.env, CODESPACES='false')
        result = self.run_preview(env=env)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(self.password_file.exists())
        self.assertEqual(self.calls(), [])

    def test_refuses_invalid_forwarding_domain_before_starting_services(self):
        env = dict(self.env, GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN='example.com/path')
        result = self.run_preview(env=env)
        self.assertNotEqual(result.returncode, 0)
        self.assertFalse(self.password_file.exists())
        self.assertEqual(self.calls(), [])

    def test_refuses_invalid_codespace_name(self):
        result = self.run_preview(env=dict(self.env, CODESPACE_NAME='bad/name'))
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(self.calls(), [])

    def test_first_start_creates_private_credentials_and_uses_external_https_url(self):
        result = self.run_preview()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertTrue(self.marker.exists())
        values = dict(line.split('=', 1) for line in self.password_file.read_text().splitlines())
        for key in ('DB_PASSWORD', 'DB_ROOT_PASSWORD', 'WP_ADMIN_PASSWORD'):
            self.assertEqual(len(values[key]), 48)
            self.assertNotIn(values[key], result.stdout + result.stderr)
        self.assertEqual(len({values[x] for x in ('DB_PASSWORD','DB_ROOT_PASSWORD','WP_ADMIN_PASSWORD')}), 3)
        self.assertEqual(stat.S_IMODE(self.password_file.stat().st_mode), 0o600)
        url = 'https://pawline-preview-example-8080.app.github.dev'
        self.assertEqual(self.command_count(['wp', 'option', 'update', 'home', url]), 1)
        self.assertEqual(self.command_count(['wp', 'option', 'update', 'siteurl', url]), 1)

    def test_resume_preserves_credentials_and_does_not_repeat_setup_or_demodata(self):
        first = self.run_preview()
        self.assertEqual(first.returncode, 0, first.stderr)
        credentials = self.password_file.read_bytes()
        second = self.run_preview()
        self.assertEqual(second.returncode, 0, second.stderr)
        self.assertEqual(self.password_file.read_bytes(), credentials)
        self.assertEqual(self.command_count(['sh', '/project/scripts/setup-wp.sh']), 1)
        self.assertEqual(self.command_count(['sh', '/project/scripts/demo-data.sh']), 1)

    def test_partial_setup_failure_is_retried_without_replacing_credentials(self):
        (self.root / 'fail-setup').touch()
        first = self.run_preview()
        self.assertNotEqual(first.returncode, 0)
        self.assertFalse(self.marker.exists())
        credentials = self.password_file.read_bytes()
        (self.root / 'fail-setup').unlink()
        second = self.run_preview()
        self.assertEqual(second.returncode, 0, second.stderr)
        self.assertTrue(self.marker.exists())
        self.assertEqual(self.password_file.read_bytes(), credentials)
        self.assertEqual(self.command_count(['sh', '/project/scripts/setup-wp.sh']), 2)

    def test_stop_preserves_data_and_never_removes_volumes(self):
        self.assertEqual(self.run_preview().returncode, 0)
        credentials = self.password_file.read_bytes()
        stopped = self.run_preview('stop')
        self.assertEqual(stopped.returncode, 0, stopped.stderr)
        self.assertEqual(self.password_file.read_bytes(), credentials)
        self.assertTrue(self.marker.exists())
        self.assertEqual(self.command_count(['stop']), 1)
        self.assertFalse(any('down' in c or '--volumes' in c for c in self.calls()))

    def test_does_not_overwrite_existing_local_env_or_print_admin_password(self):
        local_env = self.wp / '.env'
        local_env.write_text('LOCAL_DATA_MUST_SURVIVE=yes\n')
        self.assertEqual(self.run_preview().returncode, 0)
        self.assertEqual(local_env.read_text(), 'LOCAL_DATA_MUST_SURVIVE=yes\n')
        values = dict(x.split('=', 1) for x in self.password_file.read_text().splitlines())
        access = self.run_preview('zugang')
        self.assertEqual(access.returncode, 0)
        self.assertIn('WP_ADMIN_PASSWORD', access.stdout)
        self.assertNotIn(values['WP_ADMIN_PASSWORD'], access.stdout + access.stderr)


if __name__ == '__main__':
    unittest.main(verbosity=2)
