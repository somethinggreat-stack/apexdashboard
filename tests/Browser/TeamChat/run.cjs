// Each suite gets a new SQLite database and its own local PHP process. Never reads .env DB settings.
const fs = require('fs');
const os = require('os');
const path = require('path');
const net = require('net');
const { spawn, spawnSync } = require('child_process');
const root = path.resolve(__dirname, '../../..');
const php = process.env.PHP_BINARY || 'php';
const port = Number(process.env.TEST_PORT || 8932);
const suites = process.argv.slice(2);
if (!suites.length) suites.push('phase1', 'phase1b', 'phase2', 'phase3', 'phase5', 'phase6', 'phase7', 'phase8', 'phase9', 'phase10');
const wait = ms => new Promise(r => setTimeout(r, ms));
async function listening() {
    return new Promise(resolve => {
        const s = net.connect(port, '127.0.0.1');
        s.once('connect', () => { s.destroy(); resolve(true); });
        s.once('error', () => resolve(false));
    });
}
function command(args, env) {
    const r = spawnSync(php, args, { cwd: root, env, encoding: 'utf8' });
    if (r.status !== 0) throw Error(r.error || r.stdout + r.stderr);
}
(async () => {
    if (await listening()) throw Error('Test port already in use; refusing to reuse an unknown server');
    let failed = false;
    for (const suite of suites) {
        if (!/^[a-z0-9-]+$/.test(suite) || !fs.existsSync(path.join(__dirname, suite + '.cjs'))) throw Error('Unknown suite');
        const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'apex-codex-privacy-'));
        const db = path.join(dir, 'database.sqlite'); fs.writeFileSync(db, '');
        const env = { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: db,
            SESSION_DRIVER: 'file', CACHE_STORE: 'array', QUEUE_CONNECTION: 'sync',
            TEST_BASE_URL: 'http://127.0.0.1:' + port, APP_URL: 'http://127.0.0.1:' + port,
            // phase2 checks the upload limits, which are the smallest of the chat cap, PHP's
            // settings and the edge cap — it needs the small cap to exercise them.
            TEAM_CHAT_MAX_REQUEST_MB: '6' };
        console.log('\nRUN ' + suite + ' (isolated SQLite: ' + db + ')');
        command(['artisan', 'migrate', '--force'], env);
        command(['artisan', 'tinker', '--execute', "require base_path('tests/Browser/TeamChat/seed.php');"], env);
        // phase9 needs more shared files than fit in one gallery page; nothing else wants them.
        if (suite === 'phase9') {
            command(['artisan', 'tinker', '--execute', "require base_path('tests/Browser/TeamChat/seed-files.php');"], env);
        }
        const log = fs.openSync(path.join(dir, 'server.log'), 'a');
        const server = spawn(php, ['-d', 'upload_max_filesize=20M', '-d', 'post_max_size=30M',
            '-S', '127.0.0.1:' + port, path.join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
            { cwd: path.join(root, 'public'), env, stdio: ['ignore', log, log], windowsHide: true });
        try {
            for (let n = 0; !(await listening()); n++) { if (n > 100) throw Error('Server startup timeout'); await wait(100); }
            const result = await new Promise((resolve, reject) => {
                const child = spawn(process.execPath, [path.join(__dirname, suite + '.cjs')], { cwd: root, env, stdio: 'inherit', windowsHide: true });
                child.once('error', reject); child.once('exit', resolve);
            });
            if (result !== 0) failed = true;
            fs.appendFileSync(path.join(root, 'codexwork.md'), '\n- Browser run `' + suite + '`: exit ' + result + '. Disposable database/log directory: `' + dir + '`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.\n');
        } finally {
            server.kill();
            await new Promise(resolve => { if (server.exitCode !== null) resolve(); else server.once('exit', resolve); });
            fs.closeSync(log);
        }
    }
    process.exitCode = failed ? 1 : 0;
})().catch(e => { console.error(e); process.exitCode = 2; });
