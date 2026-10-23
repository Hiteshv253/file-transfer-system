// Host-side checks, with a fake Docker CLI. No real containers/volumes are changed.
// Run: node --test tests/docker-deployment.test.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawnSync} = require('node:child_process');
const {createHash} = require('node:crypto');
const {gzipSync} = require('node:zlib');
const YAML = require('yaml');
const root = path.resolve(__dirname, '..');
const bash = process.env.BASH_EXE || (process.platform === 'win32' ? 'C:/Program Files/Git/bin/bash.exe' : 'bash');
const posix = value => process.platform === 'win32' ? value.replaceAll('\\', '/').replace(/^([A-Z]):/i, (_, drive) => '/'+drive.toLowerCase()) : value;
const value = (file, key) => [...fs.readFileSync(file, 'utf8').matchAll(new RegExp(`^${key}='([^']*)'$`, 'gm'))].at(-1)?.[1];

function fixture() {
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'filevault-docker-test-'));
    const bin = path.join(dir, 'bin'), state = path.join(dir, 'state');
    fs.mkdirSync(bin); fs.mkdirSync(state);
    const stub = `#!/usr/bin/env bash
set -euo pipefail
printf '%s ' "$@" >> "$FAKE_STATE/calls"; printf '\\n' >> "$FAKE_STATE/calls"
if [[ "$1" == info ]]; then exit 0; fi
if [[ "$1" == volume && "$2" == inspect ]]; then
    volume="\${@: -1}"
    [[ -f "$FAKE_STATE/$volume" ]] || exit 1
    if [[ "$*" == *--format* ]]; then cat "$FAKE_STATE/$volume"; fi
    exit 0
fi
if [[ "$1" == volume && "$2" == create ]]; then
    printf '%s' "$DEPLOYMENT_ID" > "$FAKE_STATE/\${@: -1}"; exit 0
fi
if [[ "$1" == compose ]]; then
    if [[ "$*" == *'ps --status running -q app'* && -f "$FAKE_STATE/app-running" ]]; then echo app; fi
    if [[ "$*" == *' up '* && "$*" == *' app'* ]]; then touch "$FAKE_STATE/app-running"; fi
    if [[ "$*" == *' stop '* && -f "$FAKE_STATE/app-running" ]]; then unlink "$FAKE_STATE/app-running"; fi
    if [[ "$*" == *'SELECT COUNT('* ]]; then echo 0; fi
    exit 0
fi
exit 1
`;
    fs.writeFileSync(path.join(bin, 'docker'), stub, {mode: 0o755});
    const bashEnv = path.join(dir, 'bash-env');
    fs.writeFileSync(bashEnv, 'export PATH="$FAKE_BIN:$PATH"\n');
    const envFile = path.join(dir, 'deployment.env');
    const env = {...process.env, BASH_ENV: posix(bashEnv), FAKE_BIN: posix(bin), FAKE_STATE: posix(state),
        FILEVAULT_ENV_FILE: posix(envFile), BACKUP_DIR: posix(path.join(dir, 'backups')),
        APP_PORT: '8085', APP_URL: 'http://localhost:8085'};
    const run = (script, args = []) => spawnSync(bash, [posix(path.join(root, script)), ...args.map(posix)], {cwd: root, env, encoding: 'utf8'});
    const calls = () => fs.readFileSync(path.join(state, 'calls'), 'utf8');
    return {dir, state, envFile, run, calls};
}

test('Bash scripts parse; Compose exposes only app port and retains external data volumes', () => {
    const files = ['start.sh', 'stop.sh', 'backup.sh', 'restore.sh', 'migrate-existing.sh', ...fs.readdirSync(path.join(root, 'docker')).filter(f => f.endsWith('.sh')).map(f => 'docker/'+f)];
    for (const file of files) {
        const result = spawnSync(bash, ['-n', posix(path.join(root, file))], {encoding: 'utf8'});
        assert.equal(result.status, 0, `${file}: ${result.stderr}`);
    }
    const config = YAML.parse(fs.readFileSync(path.join(root, 'docker-compose.yml'), 'utf8'));
    assert.deepEqual(Object.keys(config.services), ['db', 'app', 'backup']);
    assert.equal(config.services.app.ports.length, 1);
    assert.ok(!config.services.db.ports && !config.services.backup.ports);
    for (const volume of Object.values(config.volumes)) assert.equal(volume.external, true);
    assert.ok(config.services.backup.volumes.some(v => typeof v === 'string' && v.endsWith(':ro')));
});

test('first startup generates secrets; repeat startup retains config/data and backs up before app migration', () => {
    const f = fixture();
    const first = f.run('start.sh');
    assert.equal(first.status, 0, first.stderr + first.stdout);
    const saved = fs.readFileSync(f.envFile, 'utf8');
    assert.equal(value(f.envFile, 'DATA_INITIALIZED'), 'true');
    assert.ok(value(f.envFile, 'APP_KEY').startsWith('base64:'));
    assert.equal(value(f.envFile, 'DB_PASSWORD').length, 48);
    const callsBefore = f.calls().length;
    const again = f.run('start.sh');
    assert.equal(again.status, 0, again.stderr);
    assert.equal(fs.readFileSync(f.envFile, 'utf8'), saved);
    const repeated = f.calls().slice(callsBefore);
    assert.ok(!repeated.includes('volume create'));
    assert.ok(repeated.indexOf('--entrypoint /usr/local/bin/filevault-backup') < repeated.indexOf('up -d --no-build --wait'));
    assert.equal(f.run('stop.sh').status, 0);
    assert.ok(fs.existsSync(path.join(f.state, value(f.envFile, 'FILES_VOLUME'))));
});

test('missing or mismatched initialized volumes fail closed instead of silently creating empty storage', () => {
    const f = fixture(); assert.equal(f.run('start.sh').status, 0);
    const name = value(f.envFile, 'FILES_VOLUME'), volume = path.join(f.state, name);
    const label = fs.readFileSync(volume);
    fs.unlinkSync(volume);
    const before = f.calls().length;
    const missing = f.run('start.sh');
    assert.notEqual(missing.status, 0);
    assert.match(missing.stderr, /missing/);
    assert.ok(!f.calls().slice(before).includes('volume create'));
    fs.writeFileSync(volume, 'wrong-deployment');
    assert.notEqual(f.run('start.sh').status, 0);
    fs.writeFileSync(volume, label);
});

test('restore allocates new volumes and keeps old ones; tampered snapshot fails before stopping services', () => {
    const f = fixture(); assert.equal(f.run('start.sh').status, 0);
    const oldNames = ['DB_VOLUME', 'FILES_VOLUME', 'RUNTIME_VOLUME'].map(key => value(f.envFile, key));
    const snapshot = path.join(f.dir, 'snapshot'); fs.mkdirSync(snapshot);
    fs.writeFileSync(path.join(snapshot, 'database.sql.gz'), gzipSync('CREATE TABLE example (id INT);\n'));
    const uploads = path.join(f.dir, 'uploads'); fs.mkdirSync(uploads); fs.writeFileSync(path.join(uploads, 'example'), 'kept');
    const tar = spawnSync('tar', ['-cf', path.join(snapshot, 'files.tar'), '-C', uploads, '.'], {encoding: 'utf8'});
    assert.equal(tar.status, 0, tar.stderr);
    fs.copyFileSync(f.envFile, path.join(snapshot, 'deployment.env'));
    fs.writeFileSync(path.join(snapshot, 'manifest.json'), '{"format_version":1}\n');
    fs.writeFileSync(path.join(snapshot, 'integrity.txt'), 'checked\n');
    const files = ['database.sql.gz', 'files.tar', 'deployment.env', 'manifest.json', 'integrity.txt'];
    const sums = files.map(name => `${createHash('sha256').update(fs.readFileSync(path.join(snapshot, name))).digest('hex')}  ${name}\n`).join('');
    fs.writeFileSync(path.join(snapshot, 'SHA256SUMS'), sums);
    const restore = f.run('restore.sh', [snapshot]);
    assert.equal(restore.status, 0, restore.stdout + restore.stderr);
    for (const name of oldNames) assert.ok(fs.existsSync(path.join(f.state, name)), 'Old volume must not be removed');
    assert.match(value(f.envFile, 'FILES_VOLUME'), /^filevault_uploads_restore_/);
    assert.ok(fs.readdirSync(f.dir).some(file => file.startsWith('deployment.env.pre-restore.')));
    fs.appendFileSync(path.join(snapshot, 'files.tar'), 'tampered');
    const before = f.calls().length;
    assert.notEqual(f.run('restore.sh', [snapshot]).status, 0);
    assert.ok(!f.calls().slice(before).includes(' stop '), 'Bad backup must not stop a running deployment');
});
