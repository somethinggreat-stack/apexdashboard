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

Phase 9 covers the Files/Photos tabs paging past one page of shared files, and an @mention added by *editing* an older message still reaching the person (which the id-ordered notification poll can never find on its own). It is the one suite that needs extra seeding — the runner loads `seed-files.php` for it, so it has more shared files than fit in a page.

Phase 10 checks that Team Chat shows Pakistan time whatever the PC's clock says. It runs Chromium in a timezone that is on a different calendar *date* from Pakistan at that moment (Kiritimati or Midway, whichever differs), then asserts the message stamp, the sidebar row time, the day separator and the Photos tab's date headings all still read Pakistan.

Phase 11 covers all four owner-control steps: a VA is offered no button and is refused the address outright, while the super admin reaches it from the chat and sees groups they were never added to — with no message text on the page. It also sends a real announcement and checks the read count appears, opens the files page, and has the owner remove a VA's message and confirms the sender is told who removed it.
