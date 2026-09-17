# Team Chat browser regressions

Recovered from Claude's temporary harness, now retained in Git. These tests use synthetic accounts and must run against disposable localhost databases.

Requirements: PHP with SQLite and project vendor dependencies; Node; `playwright-core` and Chromium. Set `PHP_BINARY`, `PLAYWRIGHT_MODULE` (module name or absolute installed module path), and `CHROMIUM_PATH` if they are not available through defaults. No production credentials are needed.

Run from the project root:

```powershell
node tests/Browser/TeamChat/run.cjs
# Or a subset:
node tests/Browser/TeamChat/run.cjs phase1b phase6 privacy-extra
```

The runner refuses an occupied port (default 8932; override `TEST_PORT`), creates a NEW SQLite file in a temporary directory for every suite, migrates/seeds it, starts PHP with the original harness's 20 MB upload / 30 MB POST limits, and stops only its own server. It never resets the working database. Temporary databases/server logs are retained for diagnosis outside the repository. Test completion is appended to `codexwork.md`.

Phase 1b checks that plain-string draft format remains supported under the authenticated user's key. An unowned global draft is deliberately planted and must not be restored. Phase 6 requires chat storage cleanup while permitting unrelated application data to survive logout; the additional privacy suite checks preservation explicitly.
