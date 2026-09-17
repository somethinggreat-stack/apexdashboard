# Codex work log

Date: 2026-09-18. Project: `Z:\Projects\Apex Credit Dashboard`.

## Instructions and scope

- Preserve Claude's working tree. No reset, restore, stash, clean, checkout, or destructive Git commands.
- Reconstruct Phase 6 (Shared-PC Privacy / Local Chat State), audit privacy, repair confirmed gaps, run the six existing browser suites and full PHP suite.
- Make one clean local commit only if verification succeeds. Do not push; report and wait for PUSH.
- User additionally requested this document record every step and change. Entries below reconstruct work performed before that request; subsequent work will be appended.

## Initial reconstruction

1. Attempted `git status`, `git diff --stat`, `git diff`, `git log -10 --oneline` through the sandbox. No command ran: Windows sandbox helper executable could not be launched (path not found).
2. Requested elevated execution for the same read-only inspection. It succeeded. Later shell commands also require this escalation because the sandbox remains broken.
3. Found branch `main`, HEAD `84bf74f428feaae00d6aeb6aa1b2dab056b88a6b`, matching the locally stored `origin/main` reference. No fetch or push performed; live remote state has not been checked.
4. Contrary to the supplied handoff, Phase 6 was already committed. Its commit touches the master context, AuthController, chat view, realtime partial, and `TeamLogoutPrivacyTest.php`.
5. Original uncommitted work: `app/Http/Controllers/Admin/TeamMessageController.php`, `resources/views/admin/team-messages/index.blade.php`, and untracked `tests/Feature/TeamSearchJumpTest.php`. The original tracked diff was 142 insertions / 40 deletions. This is later search-jump, clipboard, image-viewer, and download work. It must be preserved and excluded from a privacy-only commit.
6. Read the complete initial tracked diff and ten-commit history. Read `APEX_TEAM_CHAT_MASTER_CONTEXT.md`, package/test configuration, logout privacy tests, and the untracked search-jump tests.
7. Searched for AGENTS, TODO, handoff, notes, phase/browser test files. No repository AGENTS file found. Inspected repository top-level names and most recently modified test files. Did not read `.env` or credential/signing files.
8. Read privacy-related cache, draft, notification, logout, retention model, script lifecycle, controller, middleware, routes, and login/layout code.
9. Located Claude's project memory and read `team-chat-audit-fix-phases.md` and `team-chat-roadmap.md`. These notes contain historical information and are not fully current.
10. Located browser harness in `C:/Users/DELL/AppData/Local/Temp/claude/z--Projects-Apex-Credit-Dashboard/9904c7ff-e917-4cc0-9648-adaff76483b4/scratchpad/`. It contains phase1, phase1b, phase2, phase3, phase5, phase6, phase7, reset/seed scripts, and a local SQLite database. Those browser scripts are outside Git.
11. Read Phase 6, reset script, seed setup, and Phase 1/1b entry points. The Phase 1b per-user reconnect-draft correction already exists in the scratchpad. It still has an obsolete-global-key fallback that should be replaced with a test assertion.
12. Checked local TCP listener on 8931 and its process command: an existing PHP 8.3 development server is running there with 20 MB upload / 30 MB POST limits. No server was killed or replaced.

## Baseline validation

13. Ran `C:/php83/php.exe artisan test` with `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` explicitly set. Result: **315 passed, 3 skipped, 1343 assertions**. Includes Claude's uncommitted search-jump tests. Skips are OpenSSL EC key-generation limitations in this Windows environment.
14. Ran the recovered `phase6.js` against localhost:8931. Result: **9/9 passed**, no page errors. It exercises same-profile sequential account switching and foreign/stale snapshot sweeping, but does not cover all requested edge cases.

## Confirmed gaps from code inspection

- Snapshot TTL of 24 hours does not prove seven-day message retention: a newly cached message may already be almost seven days old. Quotes/pins also need consideration.
- Logout calls `localStorage.clear()`, `sessionStorage.clear()`, deletes every IndexedDB database and cache, and returns origin-wide `Clear-Site-Data`. This also deletes unrelated application data.
- Old tabs have no explicit session-ended signal; async responses and timers can recreate local state after logout. Shared cookies also mean an old tab can issue requests under a newly signed-in account.
- Legacy global draft/notification keys are ignored by new code but not explicitly removed at startup.
- Recent emoji state is still global.
- Existing privacy tests primarily assert key spelling/header presence, rather than these behavioral boundaries.

## Changes in progress (not yet validated or committed)

15. Added `app/Http/Middleware/TeamChatSession.php`: derives an opaque session scope from session ID and authenticated admin ID; rejects supplied stale chat-session headers with 401; applies private/no-store to authenticated admin responses. This is an additional stale-tab guard, not a replacement for authentication/authorization.
16. An initial multi-file patch was rejected for an empty hunk; no changes from that call. A corrected patch then failed because the sandbox editor could not read existing files. New-file creation worked, but edits to existing files now use explicit, match-checked Node replacements through elevated PowerShell.
17. Registered the middleware in the authenticated admin route group.
18. Changed AuthController logout to flash the departing user's ID and opaque session scope to the login page for scoped cleanup, replacing the origin-wide Clear-Site-Data response.
19. Added `partials/team-chat-session.blade.php` and `partials/team-chat-logout.blade.php`. Included logout cleanup in both login pages and session bootstrap before realtime initialization.
20. Added `public/js/team-chat-privacy.js`: per-user session marker, legacy key removal, scoped storage/IndexedDB cleanup, cross-tab invalidation, DOM/draft teardown, pending fetch/XHR cancellation, history-restoration checks, and chat request session headers. This is still under development and needs race/error tests.
21. Updated the chat view: stop obsolete run/draft/reconnect writes after logout; scope recent emoji keys; require matching session and server cache expiry for cached snapshots; guard IndexedDB persistence; route logout and refresh through scoped cleanup instead of origin-wide erasure.
22. Updated realtime code to avoid local-storage writes/leader claims after stop, close its BroadcastChannel, and suppress stopped-session broadcasts.
23. The replacement batch stopped safely on a missing `function poll` match (the actual function is `loop`). Earlier replacements in that batch succeeded. The intended realtime loop guard and server `cacheExpiresAt` addition have **not yet been applied**.
24. Re-read the realtime loop and inspected `git diff --stat` to establish the partial edit state. No tests have yet been run against these changes.
25. Created this log at the user's request. All work remains uncommitted; no push has occurred.

## Next work

- Finish the pending narrow edits; review lifecycle races and scoped cleanup behavior.
- Add behavioral regression coverage, including delayed responses, second tabs, retention boundaries, unrelated storage preservation, and account-switch protections.
- Run all requested suites, inspect complete final diff, update master context, check artifacts/secrets, and stage only privacy changes if all required checks pass.
- Preserve the original later-phase edits and untracked search test outside the privacy commit.

## Continued work

26. Finished the interrupted edit batch: added the stopped-session guard to realtime `loop()`; added `cacheExpiresAt` to the controller open response, calculated from the oldest message, quoted message, or pin plus seven days. Empty snapshots expire after one day.
27. Confirmed to the user that this log will record every touched file, change, command/result, and failure, separately identifying inherited changes.

28. Syntax checks passed: `node --check public/js/team-chat-privacy.js`; PHP lint of new middleware and TeamMessageController.
29. Edited `tests/Feature/TeamLogoutPrivacyTest.php`: replaced origin-wide header assertions with scoped logout-session assertions; added stale-session read/send rejection, near-seven-day private-note snapshot expiry, and HTTP no-store tests. Existing per-user draft/key tests remain.
30. Edited realtime partial to discard a notification payload parsed after session shutdown. Edited privacy JS to clear prior-session state when the same user signs in again with a different session.
31. Next command runs the focused logout/privacy PHP suite with in-memory SQLite.

32. Focused PHP result: five passed, one failed because the new test used an unsupported `self` POST field (422 No conversation). Corrected only the test setup to send to `recipient_id = current VA`, the existing self-note API. Rerunning that suite and the recovered Phase 6 browser suite next.

33. Second focused PHP run: five passed, one failed (404) because self-sends require the self-conversation ID, not a recipient ID. Read the controller and existing TeamMessageTest self-note test; corrected the fixture to open Notes, then send using its conversation_id.
34. Browser Phase 6 after changes: 8/9 passed. The failing assertion required ALL localStorage to be empty; scoped cleanup correctly preserved unrelated `apex-known-new-clients`. All content-leakage checks passed. The retained test now checks that no chat keys remain, leaving unrelated data intact.
35. Read harness side-effect references and self-note test setup. Two rg commands with wildcard file paths failed on Windows; repeated successfully using directory paths plus `-g`. No files changed by those searches.
36. Created durable browser tests under `tests/Browser/TeamChat/`: phase1.cjs, phase1b.cjs, phase2.cjs, phase3.cjs, phase5.cjs, phase6.cjs, seed.php. These are copies of Claude's recovered scripts (scratch originals remain untouched). Changed only portable Playwright/Chromium/base-URL configuration, a localhost safety check, the Phase 1b removal of global-key fallback plus a poisoned legacy key, and the Phase 6 scoped-storage assertion described above. These are intentional regression assets, not accidental scratch artifacts.
37. Next: rerun focused PHP and establish a new disposable browser server/database on port 8932, leaving Claude's server and database intact.

38. Focused PHP suite now passes: 6 tests, 31 assertions. Added tests/Browser/TeamChat/run.cjs and README.md: each suite gets a newly created temporary SQLite file, synthetic seed, and dedicated PHP child process; occupied ports are refused, no existing DB is reset, and test completion is logged here. Configurable PHP/Playwright/Chromium paths avoid machine-specific checked-in paths. Starting all six recovered browser suites now.

- Browser run `phase1`: exit 0. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-QtwKm5`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase1b`: exit 0. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-J7olPA`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase2`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-A9JLlV`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.


# STOP-POINT HANDOFF ? user requested work stop

Recorded UTC: 2026-09-17T20:17:48.135Z.

**Status: implementation stopped, incomplete, uncommitted, and not approved for deployment. No commit or push was made.**

The user's final instruction was to stop work, document everything done and every file touched, then stop. All implementation/testing plans earlier in this document are therefore pending work, not authorization to continue automatically.

## Final actions after the stop request

39. Polled the already-running browser test process to recover output. Phase 1 completed 12/12; Phase 1b completed 7/7. Phase 2 initially reported failures L1, L2, L4.
40. Ran read-only final checks: git status --short, git diff --stat, git diff --check; read the tail of this log; inspected only Node/PHP/Chrome process metadata needed to identify the test runner.
41. Verified runner PID 24100 was Node running tests/Browser/TeamChat/run.cjs. Enumerated its descendant processes and stopped only that runner and the descendants present at that time: 24100, 14924, 35540, 25512, 25180, 13756, 16896, 2532, 26252, 28408, 34992, 8332, 27924, 15060. No broad process-name kill was used. No unrelated server/process was intentionally stopped.
42. Read the first 90 lines of phase2.cjs and the browser-test directory inventory. The test explicitly requires TEAM_CHAT_MAX_REQUEST_MB=6. The new runner did not set that variable; the page advertised 20 MB/file and 29 MB/request. This explains why a 7 MB test file was accepted and subsequent staged-file-count checks failed. The runner configuration has NOT been fixed because the user asked to stop.
43. Recovered the remaining queued output: Phase 2 completed 19/22 before termination; Phase 3 began and passed N1/N2 before being stopped. Phase 5 and the retained Phase 6 suite were not reached.
44. Read the final controller/auth/realtime/routes/privacy-test diffs; captured git rev-parse HEAD, tracked/untracked file inventory, and the last automatically recorded browser results.
45. Wrote this final handoff, file-by-file inventory, test results, limitations, full tracked working-tree diff, and full source snapshots of newly created implementation/test files into codexwork.md. The snapshots are documentation only; no production code was changed after the stop request.
46. The final verification will check that this document exists, contains every listed file snapshot and the final handoff, and that no test-runner process remains. No additional test suite, commit, or push will be run.

## File-by-file inventory: my changes

| File | What I changed |
| --- | --- |
| app/Http/Controllers/Admin/AuthController.php | Captures departing admin ID and opaque session scope before logout; replaces Clear-Site-Data with flashed chat_logout metadata for login-page scoped cleanup. Existing session invalidation and redirect destinations retained. |
| app/Http/Controllers/Admin/TeamMessageController.php | Added only cacheExpiresAt in the open JSON response: oldest loaded message, quoted message, or pin creation time plus seven days; one-day expiry for an empty snapshot. All search/focus/page-around-message edits were already Claude's. |
| app/Http/Middleware/TeamChatSession.php | New middleware. HMAC scope binds session ID and authenticated admin ID without exposing the raw session ID. Supplied mismatching X-Apex-Chat-Session returns 401; authenticated admin responses get private/no-store. Header remains optional for existing callers. |
| routes/web.php | Adds TeamChatSession to the authenticated admin middleware group. This affects all authenticated admin routes, so broader regression review remains necessary. |
| public/js/team-chat-privacy.js | New browser session lifecycle module: user-specific session marker; legacy key cleanup; scoped local/session storage and IndexedDB removal; stale cleanup token checks; same-user new-session cleanup; account-change signals; stop flag; aborts fetch/XHR; removes old chat/notification DOM; invalidates run/navigation counters; clears in-memory drafts; resets native badge; history visibility checks; X-Apex-Chat-Session headers on chat/logout requests. Not fully race-tested. |
| resources/views/partials/team-chat-session.blade.php | New once-only privacy asset/bootstrap include with authenticated user ID, opaque token, and login URL; asset version uses filemtime. |
| resources/views/partials/team-chat-logout.blade.php | New login-side cleanup include activated by flashed chat_logout metadata, intended to cover direct/tray logout without origin-wide storage deletion. |
| resources/views/admin/auth/chat-login.blade.php | Includes the logout cleanup partial in head. |
| resources/views/admin/auth/login.blade.php | Same cleanup include for ordinary dashboard login. |
| resources/views/admin/team-messages/index.blade.php | Adds stop guards for old script runs, draft persistence and reconnect; scopes recent emojis by user; requires cache ownership/session/TTL/non-future timestamp/server retention expiry; guards disk persistence and sweep startup; replaces logout/refresh origin-wide clearing with scoped cleanup. Claude's search, clipboard, lightbox and download edits remain. Some pre-existing comments describing origin-wide refresh are now stale and were not yet rewritten. |
| resources/views/partials/team-realtime.blade.php | Includes session bootstrap; suppresses storage writes, leader claims, poll execution and late payloads after stop; closes BroadcastChannel and clears native badge on session end; blocks stopped broadcasts/receives. |
| tests/Feature/TeamLogoutPrivacyTest.php | Replaces broad Clear-Site-Data assertions with scoped logout metadata assertions; adds stale-scope read/send rejection, near-retention private-note cache expiry, and no-store tests. Corrected two unsuccessful self-note test setup attempts; final focused suite passes. |
| tests/Browser/TeamChat/phase1.cjs | Recovered Claude suite, converted to .cjs; configurable Playwright/Chromium/base URL plus localhost-only guard. Original behavioral checks retained. |
| tests/Browser/TeamChat/phase1b.cjs | Same portability changes; requires the per-user reconnect draft key instead of falling back to global tc-draft; plants an unowned legacy draft that must not replace the per-user plain-string draft. |
| tests/Browser/TeamChat/phase2.cjs | Recovered upload suite with portability/local-only changes. Its documented 6 MB request-cap prerequisite remains; runner currently misses it. |
| tests/Browser/TeamChat/phase3.cjs | Recovered unread/seen/notification suite with portability/local-only changes. |
| tests/Browser/TeamChat/phase5.cjs | Recovered group/mentions/pins suite with portability/local-only changes. |
| tests/Browser/TeamChat/phase6.cjs | Recovered privacy suite with portability/local-only changes; logout assertion now requires no chat storage, rather than deleting all unrelated localStorage. Corrected copy not yet executed. |
| tests/Browser/TeamChat/seed.php | Copied synthetic account/conversation/message fixture from Claude's scratchpad. Uses only synthetic test credentials, not real accounts. |
| tests/Browser/TeamChat/run.cjs | New isolated runner: refuses occupied port; creates new temporary SQLite DB per suite; migrates/seeds; starts PHP on localhost; launches suite; records completion here; stops its own PHP process. Has incomplete defaults/configuration documented below. |
| tests/Browser/TeamChat/README.md | New runner/dependency instructions, isolation explanation, and draft/privacy regression intent. |
| codexwork.md | This chronological work record, per-file changes, test results, stop status, outstanding risks, exact diff and new-file source snapshots. |

**Count:** 8 previously tracked files edited; 13 new code/test/documentation files plus this log created. Of the 8 edited files, 2 already contained Claude's uncommitted changes.

## Claude's pre-existing changes preserved

- TeamMessageController.php: search-result message focus, messagePage helper, full-page and open-response focus ID, page-around-message retrieval.
- team-messages/index.blade.php: initial/search jump behavior, clipboard text/image selection, shared lightbox navigation, sequential downloads and session-error messages, header/sidebar search-link changes, Photos/Files/gallery handling.
- tests/Feature/TeamSearchJumpTest.php: pre-existing untracked search test, READ ONLY during this work; never edited or staged by me.
- Initial tracked diff: 2 files, 142 insertions, 40 deletions. Final tracked diff: 8 files, 222 insertions, 85 deletions. New untracked files are not included in that diffstat.
- No destructive Git operation, stash, checkout, restore, reset, clean, staging, commit, remote fetch, or push occurred.

## Final test matrix

| Check | Result | Scope / caveat |
| --- | --- | --- |
| Full PHP suite before my edits | 315 passed, 3 skipped, 1343 assertions | In-memory SQLite. Includes Claude's uncommitted search tests. Skips: Windows OpenSSL EC-key support. This is NOT a post-change full-suite pass. |
| Original scratch Phase 6 before my edits | 9/9 | Sequential shared-profile flow; no page errors. |
| JS syntax: privacy module | Passed | node --check. |
| PHP lint: session middleware/controller | Passed | php -l for both. |
| Focused privacy PHP attempt 1 | 5 passed, 1 failed | New self-note fixture incorrectly supplied self=1; response 422. |
| Focused privacy PHP attempt 2 | 5 passed, 1 failed | Incorrect self recipient_id fixture; response 404. |
| Focused privacy PHP final | 6 passed, 31 assertions | Fixture opens self conversation then sends by conversation_id. |
| Original scratch Phase 6 after edits | 8/9 | Failed broad all-localStorage-empty assertion; unrelated apex-known-new-clients preserved. All its other checks passed. Modified retained assertion has NOT yet run. |
| Retained Phase 1 | 12/12 | Fresh isolated DB on 8932. |
| Retained Phase 1b | 7/7 | Includes per-user plain-string draft restoration with poisoned global key. |
| Retained Phase 2 | 19/22 | Failures L1, L2, L4 under missing TEAM_CHAT_MAX_REQUEST_MB=6 prerequisite. Remaining upload, retry, draft, pin, group, avatar, forward, pagination-cursor and no-page-error checks passed. Must rerun after fixing runner configuration. |
| Retained Phase 3 | Interrupted | N1 and N2 passed; no complete result. |
| Retained Phase 5 | Not run | Runner stopped before reaching it. |
| Retained Phase 6 | Not run | Corrected scoped assertion unverified. |
| Extended privacy suite | Not created | Planned but no privacy-extra.cjs exists. |
| Full PHP suite after my changes | Not run | Still required. |
| git diff --check at stop | Passed | No whitespace errors; Git emitted LF-to-CRLF normalization notices. |

## Outstanding work and known limitations ? do not treat this phase as complete

1. The new runner must set TEAM_CHAT_MAX_REQUEST_MB=6 for the Phase 2 suite. Do not weaken production upload checks to satisfy incorrect test-server configuration.
2. run.cjs defaults and README mention privacy-extra, but that file was not created before the stop request. Running the default list will eventually report Unknown suite. This is an unfinished harness, explicitly left unchanged after the stop instruction.
3. Add and execute dedicated multi-tab and delayed-response tests, active-upload sign-out cancellation/confirmation, direct/tray logout, unrelated local/session/IndexedDB/cache preservation, delayed cleanup versus a newer login, same-user relogin, browser restart/refresh/BFCache, cache-message age/quotes/pins, legacy keys, and no transient private-note rendering. Existing test passes are not proof of all these invariants.
4. Review the new JS lifecycle for races, disabled-storage errors, stalled IndexedDB operations, stale-tab side effects, and old clients that do not supply the optional scope header. Optional header validation is extra protection, not a new authentication mechanism.
5. Review retention cleanup on already-open pages and physical stale-record removal. Server cacheExpiresAt and cache usability rejection were added, but strict long-running-page and exact-boundary behavior remains unverified.
6. Validate the user-specific cache sweep against the requirement not to erase another active user's state. Browsers normally share auth cookies across tabs in a profile; distinct profiles have distinct storage. More concurrency verification is needed.
7. Dashboard new-client notifier still has its existing global apex-known-new-clients key; it was deliberately not edited as part of the chat cleanup. Do not claim all dashboard notification storage is user-specific.
8. Native desktop/WebView2/tray behavior has not been executed. No desktop repo source or installer was modified.
9. APEX_TEAM_CHAT_MASTER_CONTEXT.md was read but NOT edited. It documents committed Phase 6, including now-obsolete broad cleanup/TTL claims relative to this unfinished working tree. Update it only when implementation is finalized.
10. Re-run all requested suites and the full PHP suite, inspect complete privacy-only diff, perform final artifact/secret review, and selectively stage only privacy work if later authorized to resume. Claude's later-phase edits share two files, so do not stage those files wholesale without separating hunks.
11. No clean Phase 6 follow-up commit exists. HEAD remains 84bf74f428feaae00d6aeb6aa1b2dab056b88a6b. The pre-existing Phase 6 commit was already present at takeover.

## Files generated indirectly by tools

- Laravel/PHP tests may update ignored compiled views, framework session/cache/log files, test attachment fixtures, and .phpunit.result.cache. These are generated test/runtime artifacts, not manually authored source changes; Git does not list them as new tracked changes.
- Baseline scratch Phase 6 exercised Claude's pre-existing local test server on 8931 with synthetic accounts and changed its test messages/browser storage through normal test actions. Its database/server were not reset or killed by me.
- New runner created disposable database.sqlite and server.log files outside the repository under:
  - C:/Users/DELL/AppData/Local/Temp/apex-codex-privacy-QtwKm5 (Phase 1)
  - C:/Users/DELL/AppData/Local/Temp/apex-codex-privacy-J7olPA (Phase 1b)
  - C:/Users/DELL/AppData/Local/Temp/apex-codex-privacy-A9JLlV (Phase 2)
  - C:/Users/DELL/AppData/Local/Temp/apex-codex-privacy-uENnuR (interrupted Phase 3)
- No temp database/log was copied into Git. The portable browser test files are intentional additions.

## Exact working-tree inventory at stop

```text
 M app/Http/Controllers/Admin/AuthController.php
 M app/Http/Controllers/Admin/TeamMessageController.php
 M resources/views/admin/auth/chat-login.blade.php
 M resources/views/admin/auth/login.blade.php
 M resources/views/admin/team-messages/index.blade.php
 M resources/views/partials/team-realtime.blade.php
 M routes/web.php
 M tests/Feature/TeamLogoutPrivacyTest.php
?? app/Http/Middleware/TeamChatSession.php
?? codexwork.md
?? public/js/team-chat-privacy.js
?? resources/views/partials/team-chat-logout.blade.php
?? resources/views/partials/team-chat-session.blade.php
?? tests/Browser/
?? tests/Feature/TeamSearchJumpTest.php
```

## Exact tracked diff at stop

This is the COMPLETE working-tree diff from HEAD, including Claude's inherited edits in the two overlapping files. Those inherited hunks are identified above; do not attribute the entire patch to Codex. The added files are captured separately below because ordinary git diff does not include untracked files.

````diff
diff --git a/app/Http/Controllers/Admin/AuthController.php b/app/Http/Controllers/Admin/AuthController.php
index 4a9452d..0cc3775 100644
--- a/app/Http/Controllers/Admin/AuthController.php
+++ b/app/Http/Controllers/Admin/AuthController.php
@@ -124,6 +124,8 @@ class AuthController extends Controller
 
     public function logout(Request $request)
     {
+        $chatLogout = ['uid' => (string) Auth::guard('admin')->id(),
+            'token' => \App\Http\Middleware\TeamChatSession::token($request)];
         if ($id = Auth::guard('admin')->id()) {
             \App\Models\ActivityLog::create([
                 'admin_id'    => $id,
@@ -145,11 +147,9 @@ class AuthController extends Controller
             ? route('admin.chat-login')
             : route('admin.login');
 
-        // Wipe this browser's stored chat data (saved chats, drafts, notification list) on the
-        // way out, so the next VA on a shared PC starts clean — this also covers sign-outs that
-        // never touch the chat page, like the desktop app's tray menu. Browsers only honour it
-        // over HTTPS, so the chat page clears the same things itself as well.
-        return redirect()->to($to)->header('Clear-Site-Data', '"storage"');
+        // The login page also cleans up direct/tray logout on HTTP. Avoid origin-wide
+        // Clear-Site-Data: unrelated dashboard data and other accounts must survive.
+        return redirect()->to($to)->with('chat_logout', $chatLogout);
     }
 
     private function throttleKey(Request $request): string
diff --git a/app/Http/Controllers/Admin/TeamMessageController.php b/app/Http/Controllers/Admin/TeamMessageController.php
index 3b0ef5b..692869e 100644
--- a/app/Http/Controllers/Admin/TeamMessageController.php
+++ b/app/Http/Controllers/Admin/TeamMessageController.php
@@ -108,13 +108,12 @@ class TeamMessageController extends Controller
 
         $pinned = collect(); $mentionables = []; $notifyLevel = 'all'; $hasMoreOlder = false;
 
+        $focusId = 0;
+
         if ($active) {
-            // Load only the newest page; older messages come in on demand ("load earlier").
-            $messages = $active->messages()->visibleTo($me->id)
-                ->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins')
-                ->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
-            $hasMoreOlder = $messages->isNotEmpty()
-                && $active->messages()->visibleTo($me->id)->where('id', '<', $messages->first()->id)->exists();
+            // Normally the newest page; with ?m=<id> (a search result) the page AROUND that
+            // message, so the app can jump straight to it. Older messages come in on demand.
+            [$messages, $hasMoreOlder, $focusId] = $this->messagePage($active, $me, (int) $request->query('m'));
             $pinned = $active->messages()->visibleTo($me->id)->whereNull('deleted_at')->whereNotNull('pinned_at')->with('sender')
                 ->orderByDesc('pinned_at')->limit(10)->get();
             $this->markRead($active, $me);
@@ -150,6 +149,7 @@ class TeamMessageController extends Controller
             'isSelf' => $isSelf, 'selfConv' => $selfConv, 'selfLast' => $selfLast,
             'tz' => self::TZ,
             'uploadLimits' => self::uploadLimits(),
+            'focusId' => $focusId,
         ]);
     }
 
@@ -360,6 +360,37 @@ class TeamMessageController extends Controller
         ])->header('Cache-Control', 'no-store');
     }
 
+    /**
+     * One page of a conversation: the newest messages, or — when `m` names a message in it —
+     * the page around that message so a search result can be opened at the right spot.
+     *
+     * @return array{0:\Illuminate\Support\Collection,1:bool,2:int} [messages, hasMoreOlder, focusId]
+     */
+    private function messagePage(Conversation $conv, Admin $me, int $m = 0): array
+    {
+        $base = fn () => $conv->messages()->visibleTo($me->id)
+            ->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins');
+
+        $focusId = 0;
+        if ($m > 0 && $conv->messages()->visibleTo($me->id)->whereKey($m)->exists()) {
+            $focusId = $m;
+        }
+
+        if ($focusId) {
+            $half   = (int) floor(self::PAGE / 2);
+            $before = $base()->where('id', '<=', $focusId)->orderByDesc('id')->limit($half)->get()->reverse()->values();
+            $after  = $base()->where('id', '>', $focusId)->orderBy('id')->limit($half)->get();
+            $messages = $before->concat($after)->values();
+        } else {
+            $messages = $base()->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
+        }
+
+        $hasMoreOlder = $messages->isNotEmpty()
+            && $conv->messages()->visibleTo($me->id)->where('id', '<', $messages->first()->id)->exists();
+
+        return [$messages, $hasMoreOlder, $focusId];
+    }
+
     /** A message I already sent with this client id, if I'm still in its conversation. */
     private function sentWithUuid(Admin $me, string $uuid): ?TeamMessage
     {
@@ -548,15 +579,12 @@ class TeamMessageController extends Controller
         }
         abort_unless($active || $peer, 404);
 
-        $messages = collect(); $hasMore = false; $pinned = collect();
+        $messages = collect(); $hasMore = false; $pinned = collect(); $focusId = 0;
         $members = collect(); $addable = collect(); $mentionables = []; $notifyLevel = 'all';
 
         if ($active) {
-            $messages = $active->messages()->visibleTo($me->id)
-                ->with('sender', 'replyTo.sender', 'attachments', 'deletedByAdmin', 'mentionedAdmins')
-                ->orderByDesc('id')->limit(self::PAGE)->get()->reverse()->values();
-            $hasMore = $messages->isNotEmpty()
-                && $active->messages()->visibleTo($me->id)->where('id', '<', $messages->first()->id)->exists();
+            // ?m=<id> (a search result) opens the page AROUND that message instead of the newest.
+            [$messages, $hasMore, $focusId] = $this->messagePage($active, $me, (int) $request->query('m'));
             $pinned = $active->messages()->visibleTo($me->id)->whereNull('deleted_at')->whereNotNull('pinned_at')->with('sender')
                 ->orderByDesc('pinned_at')->limit(10)->get();
             // Prefetch (hover warm-up) must NOT mark the thread read — only a real open does.
@@ -624,6 +652,12 @@ class TeamMessageController extends Controller
             'onlineCount'  => $isGroup ? $this->groupOnlineCount($active, $me->id) : 0,
             'messages'   => $messages->map(fn ($m) => $this->present($m, $me->id))->values(),
             'hasMore'    => (bool) $hasMore,
+            // Expire when the oldest cached message, quote or pin reaches retention.
+            // A snapshot's age alone cannot enforce a message's seven-day lifetime.
+            'cacheExpiresAt' => $messages->concat($messages->pluck('replyTo')->filter())->concat($pinned)
+                ->min(fn ($message) => $message->created_at->copy()->addDays(7)->getTimestampMs())
+                ?? now()->addDay()->getTimestampMs(),
+            'focusId'    => $focusId,   // a search result: jump to and highlight this message
             'readUpTo'   => $active ? $this->readUpTo($active, $me->id) : 0,
             'watermarks' => $active ? $this->watermarks($active, $me->id) : [],
             'pinned'     => $pinned->map(fn ($m) => [
diff --git a/resources/views/admin/auth/chat-login.blade.php b/resources/views/admin/auth/chat-login.blade.php
index 82dceb5..d607475 100644
--- a/resources/views/admin/auth/chat-login.blade.php
+++ b/resources/views/admin/auth/chat-login.blade.php
@@ -1,6 +1,7 @@
 <!DOCTYPE html>
 <html lang="en">
 <head>
+@include('partials.team-chat-logout')
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="robots" content="noindex, nofollow, noarchive">
diff --git a/resources/views/admin/auth/login.blade.php b/resources/views/admin/auth/login.blade.php
index dabd521..9fee46d 100644
--- a/resources/views/admin/auth/login.blade.php
+++ b/resources/views/admin/auth/login.blade.php
@@ -1,6 +1,7 @@
 <!DOCTYPE html>
 <html lang="en">
 <head>
+@include('partials.team-chat-logout')
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">
 <meta name="robots" content="noindex, nofollow, noarchive">
diff --git a/resources/views/admin/team-messages/index.blade.php b/resources/views/admin/team-messages/index.blade.php
index 5e89137..c27dc36 100644
--- a/resources/views/admin/team-messages/index.blade.php
+++ b/resources/views/admin/team-messages/index.blade.php
@@ -154,6 +154,7 @@
                  {{-- The teammate of any 1:1 chat (same as the /open payload), so the chat has one
                       identity whether it was loaded in full or swapped in place. --}}
                  @if ($peer && ! $isGroup && ! $isSelf) data-peer="{{ $peer->id }}" @endif
+                 @if (!empty($focusId)) data-focus="{{ $focusId }}" @endif
                  data-group="{{ $isGroup ? 1 : 0 }}" data-last="{{ $messages->last()->id ?? 0 }}"
                  data-first="{{ $messages->first()->id ?? 0 }}" data-more="{{ $hasMoreOlder ? 1 : 0 }}">
                 @if ($hasMoreOlder)
@@ -696,7 +697,7 @@
     }
     // Is the VA actually looking at this window? Drives read/seen (see poll's read= flag).
     function isViewing(){ return !document.hidden && document.hasFocus(); }
-    function runAlive(){ return RUN === window.__tcRun; }
+    function runAlive(){ return !window.__tcPrivacyStopped && RUN === window.__tcRun; }
     function viewAlive(gen){ return runAlive() && gen === VIEW_GEN; }
     function chatAlive(key){ return runAlive() && key === chatKey(); }
 
@@ -1057,6 +1058,8 @@
     }
 
     toBottom();
+    // Loaded from a search result (?m=<id>): land on that message, not at the bottom.
+    if (box.dataset.focus) setTimeout(function () { jumpToMessage(box.dataset.focus); }, 60);
     if (input) input.focus(); // ready to type the moment the chat opens
     // Restore a draft stashed during a reconnect, so nothing typed is ever lost.
     var restoredDraft = null;
@@ -1261,16 +1264,24 @@
     function handlePaste(e){
         var dt = e.clipboardData || window.clipboardData; if (!dt) return;
         var files = [];
+        var named = false;   // a real file copied from Explorer (has its own name)
         if (dt.files && dt.files.length) {
             // Files copied from the OS file manager arrive here with real names.
-            Array.prototype.forEach.call(dt.files, function (f) { files.push(namedFile(f)); });
+            Array.prototype.forEach.call(dt.files, function (f) { if (f.name) named = true; files.push(namedFile(f)); });
         } else if (dt.items && dt.items.length) {
             // A pasted screenshot/image comes through as an item of kind "file", no name.
             Array.prototype.forEach.call(dt.items, function (it) {
-                if (it.kind === 'file') { var f = it.getAsFile(); if (f) files.push(namedFile(f)); }
+                if (it.kind === 'file') { var f = it.getAsFile(); if (f) { if (f.name) named = true; files.push(namedFile(f)); } }
             });
         }
-        if (files.length) { e.preventDefault(); addFiles(files); }   // let plain-text paste through untouched
+        // Copying cells from Excel (or a table from Word/a browser) puts BOTH a picture and the
+        // text on the clipboard. The VA means the text — paste it, and ignore the picture.
+        // A screenshot has no text, and a file copied from Explorer keeps its own name, so
+        // both of those still attach.
+        var text = '';
+        try { text = dt.getData('text/plain') || ''; } catch (err) {}
+        var onlyImages = files.length > 0 && files.every(function (f) { return /^image\//.test(f.type || ''); });
+        if (files.length && !(text.trim() && onlyImages && !named)) { e.preventDefault(); addFiles(files); }
     }
     // Attach to ONE element only. The thread panel catches a composer paste as it bubbles
     // up from the textarea, so a single listener handles it exactly once.
@@ -1299,6 +1310,7 @@
     // one chat's draft never drops another's.
     (function () { var t = readDraftTexts(); Object.keys(t).forEach(function (k) { if (!DRAFTS[k] && t[k]) DRAFTS[k] = { body: t[k] }; }); })();
     function persistDraftTexts(){
+        if (window.__tcPrivacyStopped) return;
         var out = {};
         Object.keys(DRAFTS).forEach(function (k) { if (DRAFTS[k] && DRAFTS[k].body && DRAFTS[k].body.trim()) out[k] = DRAFTS[k].body; });
         try { sessionStorage.setItem(DRAFT_TEXT_KEY, JSON.stringify(out)); } catch (e) {}
@@ -1568,6 +1580,7 @@
     // if the session is truly gone, the sign-in). No scary "CSRF token mismatch".
     var reconnecting = false;
     function reconnect(draft){
+        if (window.__tcPrivacyStopped) return;
         if (reconnecting) return; reconnecting = true;
         // Stash the unsent text with the chat it belongs to (and its client id), so after the
         // reload it goes back into THAT chat only, and a resend can't duplicate it.
@@ -1686,14 +1699,14 @@
     ];
 
     function getRecent(){
-        try { var r = JSON.parse(localStorage.getItem('tc-recent-emoji') || '[]'); return (r && r.length) ? r : DEFAULT_EMOJI.slice(); }
+        try { var r = JSON.parse(localStorage.getItem('tc-recent-emoji:u' + @json((string) $me->id)) || '[]'); return (r && r.length) ? r : DEFAULT_EMOJI.slice(); }
         catch (e) { return DEFAULT_EMOJI.slice(); }
     }
     function recordRecent(emoji){
         try {
             var r = getRecent().filter(function (x) { return x !== emoji; });
             r.unshift(emoji); r = r.slice(0, 8);
-            localStorage.setItem('tc-recent-emoji', JSON.stringify(r));
+            localStorage.setItem('tc-recent-emoji:u' + @json((string) $me->id), JSON.stringify(r));
         } catch (e) {}
     }
     function renderQuickEmojis(){
@@ -1722,13 +1735,33 @@
         lbIndex = (lbIndex + d + lbImages.length) % lbImages.length;   // wrap around
         lbShow();
     }
+    // Scroll a message into view and flash it (search results, quote and pinned jumps).
+    function jumpToMessage(id){
+        if (!id) return false;
+        var t = box.querySelector('.tc-msg[data-id="' + id + '"]'); if (!t) return false;
+        t.scrollIntoView({ behavior: 'smooth', block: 'center' });
+        t.classList.add('tc-flash');
+        setTimeout(function () { t.classList.remove('tc-flash'); }, 1600);
+        return true;
+    }
+    window.tcJumpTo = jumpToMessage;
+
+    // Open the viewer on `link`, with the prev/next arrows walking every image in the SAME
+    // place it was opened from (the thread, the Files table, the Photos grid or the gallery).
+    function openLightbox(container, link){
+        var sel = 'a.tc-att-img, a.tc-photo, a[data-lightbox]';
+        lbImages = Array.prototype.map.call((container || document).querySelectorAll(sel), function (a) { return a.getAttribute('href'); })
+            .filter(function (h, i, all) { return h && all.indexOf(h) === i; });
+        var href = link.getAttribute('href');
+        if (lbImages.indexOf(href) < 0) lbImages = [href];
+        lbIndex = Math.max(0, lbImages.indexOf(href));
+        lightbox.hidden = false; lbShow();
+    }
     box.addEventListener('click', function (e) {
         var img = e.target.closest('.tc-att-img'); if (!img) return;
         e.preventDefault();
         // Every image in the thread becomes navigable (including the hidden "+N" ones).
-        lbImages = Array.prototype.map.call(box.querySelectorAll('.tc-att-img'), function (a) { return a.getAttribute('href'); });
-        lbIndex = Math.max(0, lbImages.indexOf(img.getAttribute('href')));
-        lightbox.hidden = false; lbShow();
+        openLightbox(box, img);
     });
     if (lightbox){
         document.getElementById('tcLbClose').addEventListener('click', closeLightbox);
@@ -1782,9 +1815,13 @@
                 if (total) { bar.style.width = Math.round(loaded / total * 100) + '%'; sub.textContent = fmtBytes(loaded) + ' / ' + fmtBytes(total); }
                 else sub.textContent = fmtBytes(loaded) + ' downloaded';
             },
+            waiting: function (n) { sub.textContent = n > 1 ? 'Waiting (' + n + ' in queue)…' : 'Waiting…'; },
             done: function () {
                 el.classList.remove('indet'); el.classList.add('done');
-                bar.style.width = '100%'; sub.textContent = 'Saved to your Downloads';
+                bar.style.width = '100%';
+                // The bytes are here and the file has been handed to the browser — say that,
+                // rather than claiming it is already sitting in the Downloads folder.
+                sub.textContent = 'Downloaded — check your Downloads folder';
                 el.querySelector('.tc-dl-ic').innerHTML = DONE_ICON;
                 dismiss(4500);
             },
@@ -1799,13 +1836,36 @@
         return api;
     }
 
-    // Stream a file to the user's Downloads with a live progress card.
-    window.tcDownload = function (url, nameHint){
-        var card = dlCard(nameHint);
+    // Downloads run ONE AT A TIME. Each file is held in memory while it downloads, so
+    // "Download selected" on a set of big zips used to hold all of them at once and could
+    // take the window down with it.
+    var dlQueue = [], dlBusy = false;
+    function dlNext(){
+        if (dlBusy) return;
+        var job = dlQueue.shift(); if (!job) return;
+        dlBusy = true;
+        runDownload(job.url, job.name, job.card).then(function (){
+            dlBusy = false;
+            if (dlQueue.length) dlNext();
+        });
+    }
+    function runDownload(url, nameHint, card){
         // Streaming fetch (WebView2 / Chromium) gives us byte-level progress.
-        if (window.fetch && window.ReadableStream){
-            fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (resp){
+        if (!window.fetch || !window.ReadableStream){
+            // Old fallback: let the browser handle it.
+            var a0 = document.createElement('a'); a0.href = url; a0.rel = 'noopener'; TCB(a0); a0.click(); a0.remove();
+            card.el.classList.remove('indet'); card.done();
+            return Promise.resolve();
+        }
+        // Accept anything + the ajax header: if the session has expired the server answers
+        // 401 instead of a login PAGE, which used to be saved as "<the zip's name>".
+        return fetch(url, { credentials: 'same-origin', cache: 'no-store',
+                headers: { 'Accept': '*/*', 'X-Requested-With': 'XMLHttpRequest' } })
+            .then(function (resp){
+                if (resp.status === 401 || resp.status === 419 || resp.redirected) throw new Error('signed-out');
                 if (!resp.ok) throw new Error('HTTP ' + resp.status);
+                var ct = (resp.headers.get('Content-Type') || '').toLowerCase();
+                if (ct.indexOf('text/html') === 0) throw new Error('signed-out');   // a page, not a file
                 var name = nameFromDisposition(resp.headers.get('Content-Disposition'), nameHint);
                 card.setName(name);
                 var total = parseInt(resp.headers.get('Content-Length') || '0', 10) || 0;
@@ -1818,17 +1878,21 @@
                         return pump();
                     });
                 })();
-            }).then(function (out){
-                saveBlob(out.blob, out.name); card.done();
-            }).catch(function (err){
-                card.fail('Couldn’t download — tap the file to retry');
+            })
+            .then(function (out){ saveBlob(out.blob, out.name); card.done(); })
+            .catch(function (err){
+                var signedOut = err && err.message === 'signed-out';
+                card.fail(signedOut ? 'Your session ended — sign in again, then download it'
+                                    : 'Couldn’t download — tap the file to retry');
+                if (signedOut && window.apexToast) window.apexToast('Your session ended — sign in again to download files');
                 console && console.warn && console.warn('download failed', err);
             });
-        } else {
-            // Old fallback: let the browser handle it, just show a generic "saved" note.
-            var a = document.createElement('a'); a.href = url; a.rel = 'noopener'; TCB(a); a.click(); a.remove();
-            card.el.classList.remove('indet'); card.done();
-        }
+    }
+    window.tcDownload = function (url, nameHint){
+        var card = dlCard(nameHint);
+        dlQueue.push({ url: url, name: nameHint, card: card });
+        if (dlBusy) card.waiting(dlQueue.length);
+        dlNext();
     };
     function saveBlob(blob, name){
         var u = URL.createObjectURL(blob);
@@ -1989,10 +2053,10 @@
             if (!m.length && !f.length){ hsRes.innerHTML = '<div class="tc-hs-empty">No messages or files found.</div>'; positionHsRes(); hsRes.hidden = false; return; }
             var h = '';
             if (m.length){ h += '<div class="tc-hs-group">Messages</div>' + m.map(function (x) {
-                return '<a class="tc-hs-item" href="?c=' + x.conversation_id + HS_SA + '"><span class="tc-hs-ic">' + CHAT_IC + '</span><span class="tc-hs-meta"><span class="tc-hs-title">' + esc(x.title) + '</span><span class="tc-hs-sub">' + esc(x.sender) + ': ' + esc(x.snippet) + '</span></span></a>';
+                return '<a class="tc-hs-item" data-jump="' + (x.message_id || '') + '" href="?c=' + x.conversation_id + (x.message_id ? '&m=' + x.message_id : '') + HS_SA + '"><span class="tc-hs-ic">' + CHAT_IC + '</span><span class="tc-hs-meta"><span class="tc-hs-title">' + esc(x.title) + '</span><span class="tc-hs-sub">' + esc(x.sender) + ': ' + esc(x.snippet) + '</span></span></a>';
             }).join(''); }
             if (f.length){ h += '<div class="tc-hs-group">Files</div>' + f.map(function (x) {
-                return '<a class="tc-hs-item" href="?c=' + x.conversation_id + HS_SA + '"><span class="tc-hs-ic">' + FILE_IC + '</span><span class="tc-hs-meta"><span class="tc-hs-title">' + esc(x.name) + '</span><span class="tc-hs-sub">' + esc(x.title) + ' · ' + esc(x.size) + '</span></span></a>';
+                return '<a class="tc-hs-item" data-jump="' + (x.message_id || '') + '" href="?c=' + x.conversation_id + (x.message_id ? '&m=' + x.message_id : '') + HS_SA + '"><span class="tc-hs-ic">' + FILE_IC + '</span><span class="tc-hs-meta"><span class="tc-hs-title">' + esc(x.name) + '</span><span class="tc-hs-sub">' + esc(x.title) + ' · ' + esc(x.size) + '</span></span></a>';
             }).join(''); }
             hsRes.innerHTML = h; positionHsRes(); hsRes.hidden = false;
         }
@@ -2143,7 +2207,7 @@
         document.getElementById('tcGalleryClose').addEventListener('click', function () { galleryModal.hidden = true; });
         galleryModal.addEventListener('click', function (e) {
             if (e.target === galleryModal){ galleryModal.hidden = true; return; }
-            var a = e.target.closest('a[data-lightbox]'); if (a){ e.preventDefault(); lbImg.src = a.getAttribute('href'); lightbox.hidden = false; }
+            var a = e.target.closest('a[data-lightbox]'); if (a){ e.preventDefault(); openLightbox(galleryModal, a); }
         });
     }
     function openGallery(){
@@ -2168,7 +2232,7 @@
         gbody.innerHTML = html;
         gbody.onclick = function (e){
             var lb = e.target.closest('a[data-lightbox]');
-            if (lb){ e.preventDefault(); lbImg.src = lb.getAttribute('href'); lightbox.hidden = false; return; }
+            if (lb){ e.preventDefault(); openLightbox(gbody, lb); return; }
             var fl = e.target.closest('a[data-dlname]');
             if (fl){ e.preventDefault(); downloadUrls([{ url: fl.getAttribute('href'), name: fl.getAttribute('data-dlname') }]); }
         };
@@ -2306,7 +2370,7 @@
     if (filesScroll){
         filesScroll.addEventListener('click', function (e) {
             var img = e.target.closest('a[data-lightbox]');
-            if (img){ e.preventDefault(); lbImg.src = img.getAttribute('href'); lightbox.hidden = false; return; }
+            if (img){ e.preventDefault(); openLightbox(filesScroll, img); return; }
             var nameLink = e.target.closest('a[data-dlname]');
             if (nameLink){ e.preventDefault(); downloadUrls([{ url: nameLink.getAttribute('href'), name: nameLink.getAttribute('data-dlname') }]); return; }
             var dl = e.target.closest('.tc-fdl');
@@ -2323,6 +2387,13 @@
             if (th){ var k = th.dataset.sort; if (fSort.key === k) fSort.dir *= -1; else { fSort.key = k; fSort.dir = k === 'ts' ? -1 : 1; } renderFiles(); }
         });
     }
+    // Photos tab: open the viewer (arrows walk the whole grid). Without this the click
+    // followed the link and navigated the app away from the chat to the raw image.
+    if (photosScroll) photosScroll.addEventListener('click', function (e) {
+        var img = e.target.closest('a.tc-photo, a[data-lightbox]');
+        if (img){ e.preventDefault(); openLightbox(photosScroll, img); }
+    });
+
     var upBtn = document.getElementById('tcFilesUpload');
     if (upBtn && fileInput) upBtn.addEventListener('click', function () {
         // The composer is hidden on the Files/Photos tabs — bring the VA to Chat, where the
@@ -3026,6 +3097,8 @@
         applyReadReceipts(data.readUpTo);
         updateSeen();
         toBottom();
+        // Opened from a search result: land on that message instead of the bottom.
+        if (data.focusId) setTimeout(function () { jumpToMessage(data.focusId); }, 30);
 
         if (typeof switchTab === 'function') switchTab('chat');
         galleryData = null;   // per-conversation; re-fetched when Files/Photos is opened
@@ -3078,7 +3151,7 @@
     // ---- Tier 2 prefetch cache: q -> { data, ts } (short TTL; a real open reconciles via poll) ----
     var PREFETCH_TTL = 25000;
     var cache = {};
-    function fresh(q){ var c = cache[q]; return c && (Date.now() - c.ts) < PREFETCH_TTL ? c.data : null; }
+    function fresh(q){ var c = cache[q]; return !window.__tcPrivacyStopped && c && usable(c) && (Date.now() - c.ts) < PREFETCH_TTL ? c.data : null; }
     function prefetch(a){
         if (!window.tcOpenUrl) return;
         var q = rowQuery(a); if (!q) return;
@@ -3104,7 +3177,9 @@
     var SNAP_TTL = 24 * 60 * 60 * 1000;
     function idbKey(q){ return 'u' + ME_ID + '|' + q; }
     function usable(rec){
-        return !!(rec && rec.data && String(rec.uid || '') === ME_ID && rec.ts && (Date.now() - rec.ts) < SNAP_TTL);
+        return !!(!window.__tcPrivacyStopped && rec && rec.data && String(rec.uid || '') === ME_ID
+            && rec.token === window.ApexChatPrivacy.token() && rec.ts <= Date.now()
+            && (Date.now() - rec.ts) < SNAP_TTL && Number(rec.data.cacheExpiresAt) > Date.now());
     }
 
     var idbP = null;
@@ -3134,7 +3209,7 @@
     // written by the previous version, which had no owner stamp.
     function idbSweep(){
         idb().then(function (db){
-            if (!db) return;
+            if (!db || !window.ApexChatPrivacy.live()) return;
             try {
                 var st = db.transaction('threads','readwrite').objectStore('threads');
                 var cur = st.openCursor();
@@ -3149,7 +3224,7 @@
     }
     idbSweep();
     function idbSet(key, val){
-        idb().then(function (db){ if (!db) return; try {
+        idb().then(function (db){ if (!db || !window.ApexChatPrivacy.live() || !usable(val)) return; try {
             db.transaction('threads','readwrite').objectStore('threads').put(val, idbKey(key));
         } catch (e){} });
     }
@@ -3168,7 +3243,12 @@
 
     // Every saved chat is stamped with its owner and the time, so it can only ever be painted
     // back for that same VA, and only for a day.
-    function persist(q, data){ var now = Date.now(); cache[q] = { data: data, ts: now }; idbSet(q, { data: data, ts: now, uid: ME_ID }); }
+    function persist(q, data){
+        if (!window.ApexChatPrivacy.live()) return;
+        var rec = { data: data, ts: Date.now(), uid: ME_ID, token: window.ApexChatPrivacy.token() };
+        if (!usable(rec)) { delete cache[q]; idbDel(q); return; }
+        cache[q] = rec; idbSet(q, rec);
+    }
 
     function applyData(data, url){
         window.tcApplyOpen(data, url, true);
@@ -3208,7 +3288,7 @@
         // Tier 3: paint from the on-disk snapshot immediately, then refresh in the background.
         idbGet(q).then(function (snap){
             if (!ticketLive(ticket)) return;   // the VA already clicked something else
-            if (snap && snap.data){
+            if (snap && usable(snap)){
                 var before = sig(snap.data);
                 window.tcApplyOpen(snap.data, url, true);
                 // Real refresh: marks read + authoritative header/pins/messages; re-apply only if changed.
@@ -3281,25 +3361,6 @@
 (function () {
     var btn = document.getElementById('tcRefresh'); if (!btn) return;
 
-    function delDB(name){ return new Promise(function (res){ try { var r = indexedDB.deleteDatabase(name); r.onsuccess = r.onerror = r.onblocked = function(){ res(); }; } catch (e) { res(); } }); }
-
-    function clearAll(){
-        var jobs = [];
-        // 1. Web/Cache Storage (service-worker + fetch caches).
-        try { if (window.caches && caches.keys) jobs.push(caches.keys().then(function (ks){ return Promise.all(ks.map(function (k){ return caches.delete(k); })); })); } catch (e) {}
-        // 2. Every IndexedDB database (thread cache + anything else this origin holds).
-        try {
-            if (indexedDB.databases) {
-                jobs.push(indexedDB.databases().then(function (dbs){
-                    return Promise.all((dbs || []).map(function (d){ return d && d.name ? delDB(d.name) : null; }));
-                }).catch(function(){ return delDB('apexChat'); }));
-            } else { jobs.push(delDB('apexChat')); }
-        } catch (e) {}
-        // 3. Unregister service workers (a fresh one re-registers on reload).
-        try { if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) jobs.push(navigator.serviceWorker.getRegistrations().then(function (regs){ return Promise.all(regs.map(function (r){ return r.unregister(); })); })); } catch (e) {}
-        return Promise.all(jobs);
-    }
-
     // Sign out: ask first if something is still sending, then wipe this browser's chat data
     // (saved chats, drafts, notification list) so the next VA on a shared PC starts clean.
     var outLink = document.querySelector('.tc-logout');
@@ -3308,9 +3369,7 @@
         function go(){
             var done = false;
             var leave = function () { if (done) return; done = true; location.href = outLink.href; };
-            try { localStorage.clear(); } catch (e2) {}
-            try { sessionStorage.clear(); } catch (e2) {}
-            clearAll().then(leave, leave);
+            window.ApexChatPrivacy.logout().then(leave, leave);
             setTimeout(leave, 1500);   // never hang on a blocked clear
         }
         if (window.tcSendInFlight && window.tcSendInFlight() && !window.__tcAllowLeave){
@@ -3331,9 +3390,7 @@
         var done = false;
         function reload(){ if (done) return; done = true; location.reload(); }
         // Key/value stores are synchronous — clear them first (NOT cookies → stay logged in).
-        try { localStorage.clear(); } catch (e) {}
-        try { sessionStorage.clear(); } catch (e) {}
-        clearAll().then(reload).catch(reload);
+        window.ApexChatPrivacy.logout().then(reload, reload);
         setTimeout(reload, 1500);   // never hang if a clear is slow/blocked
     });
 })();
@@ -3472,7 +3529,7 @@
         else {
             var sa = @js(request()->boolean('standalone')) ? '&standalone=1' : '';
             srBox.innerHTML = '<div class="tc-section">Messages</div>' + msgs.map(function (m) {
-                return '<a class="tc-sr-item" href="?c=' + m.conversation_id + sa + '"><span class="tc-sr-title">' + esc(m.title)
+                return '<a class="tc-sr-item" data-jump="' + (m.message_id || '') + '" href="?c=' + m.conversation_id + (m.message_id ? '&m=' + m.message_id : '') + sa + '"><span class="tc-sr-title">' + esc(m.title)
                     + '</span><span class="tc-sr-snip">' + esc(m.sender) + ': ' + esc(m.snippet) + '</span></a>';
             }).join('');
             srBox.hidden = false;
@@ -3575,7 +3632,9 @@
     };
 
     document.addEventListener('click', function (e) {
-        var a = e.target.closest('a.tc-contact, a.tc-sr-item');
+        // Header-search results (.tc-hs-item) are included so a result opens in place, keeping
+        // whatever is typed, instead of a full page load.
+        var a = e.target.closest('a.tc-contact, a.tc-sr-item, a.tc-hs-item');
         if (!a || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
         if (a.getAttribute('target') === '_blank' || !samePath(a.href)) return;
         e.preventDefault();
diff --git a/resources/views/partials/team-realtime.blade.php b/resources/views/partials/team-realtime.blade.php
index 78c4dd8..4be4ba3 100644
--- a/resources/views/partials/team-realtime.blade.php
+++ b/resources/views/partials/team-realtime.blade.php
@@ -38,6 +38,7 @@
   :root[data-theme="dark"] .apex-nc-head b, :root[data-theme="dark"] .apex-nc-tx b { color:#e2e8f0; }
   :root[data-theme="dark"] .apex-nc-item:hover { background:#182444; }
 </style>
+@include('partials.team-chat-session')
 <script>
 (function () {
     'use strict';
@@ -111,7 +112,7 @@
     var ME_ID = @json((string) (Auth::guard('admin')->id() ?? ''));
     var U = ':u' + ME_ID;
     var LKEY = 'apex-team-last-msg' + U, NKEY = 'apex-team-notifs' + U, NLKEY = 'apex-team-last-notified' + U;
-    function ls(k, v){ try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
+    function ls(k, v){ if (window.__tcPrivacyStopped) return null; try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
     var lastId = parseInt(ls(LKEY) || '0', 10) || 0;
     var primed = lastId > 0;               // if we have a baseline, deliver new msgs (no backlog spam)
     var seen = {};                          // id -> 1, per-tab UI dedupe
@@ -131,6 +132,7 @@
     function leaderFresh(){ var l = leader(); return l && (Date.now() - l.ts < 8000); }
     function amLeader(){ var l = leader(); return l && l.id === TAB; }
     function claim(){
+        if (window.__tcPrivacyStopped) return;
         var l = leader(), fresh = l && (Date.now() - l.ts < 8000), vis = !document.hidden;
         // Claim if: no fresh leader, I already am, or I'm visible and the current leader is hidden.
         if (!fresh || (l && l.id === TAB) || (vis && l && !l.vis)) {
@@ -142,14 +144,16 @@
 
     // ---------- cross-tab bus ----------
     var bc = ('BroadcastChannel' in window) ? new BroadcastChannel('apex-team' + U) : null;
+    window.addEventListener('apex:chat-session-ended', function () { if (bc) bc.close(); nativeBadge(0); });
     if (bc) bc.onmessage = function (e) {
+        if (window.__tcPrivacyStopped) return;
         var d = e.data || {};
         if (d.kind === 'active'){ if (d.conv) activeByTab[d.tab] = String(d.conv); else delete activeByTab[d.tab]; return; }
         if (d.kind === 'seen'){ hydrate(); renderPanel(); return; }
         if (d.kind === 'unread'){ applyUnread(d.unread, d.perConv); return; }   // authoritative counts from the leader
         if (d.kind === 'data'){ ingest(d.data, false); }   // from the leader — update in-app UI, don't re-notify/re-broadcast
     };
-    function post(o){ if (bc) bc.postMessage(o); }
+    function post(o){ if (bc && !window.__tcPrivacyStopped) bc.postMessage(o); }
 
     // ---------- desktop notification permission ----------
     function permission(){ return ('Notification' in window) ? Notification.permission : 'denied'; }
@@ -349,6 +353,7 @@
     var timer = null;
     function schedule(){ clearTimeout(timer); timer = setTimeout(loop, document.hidden ? 8000 : 4000); }
     function loop(){
+        if (window.__tcPrivacyStopped) return;
         claim();
         if (!amLeader()){ schedule(); return; }   // a peer is the poller — we update via BroadcastChannel
         // No stored position yet (new install / after Refresh): ask where history ends instead
@@ -356,6 +361,7 @@
         fetch(POLL_URL + '?after=' + lastId + (primed ? '' : '&prime=1'), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
             .then(function (r) { return r.ok ? r.json() : null; })
             .then(function (data) {
+                if (window.__tcPrivacyStopped) return;
                 if (!data){ schedule(); return; }
                 // Authoritative unread every poll — so counts also DROP when read elsewhere.
                 applyUnread(data.unread, data.perConv);
diff --git a/routes/web.php b/routes/web.php
index bef9399..41efd1f 100644
--- a/routes/web.php
+++ b/routes/web.php
@@ -73,7 +73,7 @@ Route::prefix('admin')->name('admin.')->group(function () {
         Route::post('chat-login', [Admin\AuthController::class, 'chatLogin'])->name('chat-login.attempt');
     });
 
-    Route::middleware(['auth:admin', \App\Http\Middleware\LogActivity::class, \App\Http\Middleware\TrackPresence::class])->group(function () {
+    Route::middleware(['auth:admin', \App\Http\Middleware\TeamChatSession::class, \App\Http\Middleware\LogActivity::class, \App\Http\Middleware\TrackPresence::class])->group(function () {
         Route::match(['get', 'post'], 'logout', [Admin\AuthController::class, 'logout'])->name('logout');
 
         // Self-service profile — any authenticated admin (super, VA, leads agent)
diff --git a/tests/Feature/TeamLogoutPrivacyTest.php b/tests/Feature/TeamLogoutPrivacyTest.php
index 7aa5f1e..c26e8b7 100644
--- a/tests/Feature/TeamLogoutPrivacyTest.php
+++ b/tests/Feature/TeamLogoutPrivacyTest.php
@@ -7,7 +7,7 @@ use Illuminate\Foundation\Testing\RefreshDatabase;
 use Tests\TestCase;
 
 /**
- * Shared-PC privacy: signing out tells the browser to drop this site's stored data, so the
+ * Shared-PC privacy: signing out asks the login page to remove only the departing user's chat data, so the
  * next VA on the same computer starts with nothing of the previous one's.
  */
 class TeamLogoutPrivacyTest extends TestCase
@@ -38,7 +38,9 @@ class TeamLogoutPrivacyTest extends TestCase
             ->actingAs($va, 'admin')->get('/admin/logout')
             ->assertRedirect(route('admin.chat-login'));
 
-        $this->assertSame('"storage"', $res->headers->get('Clear-Site-Data'));
+        $this->assertNull($res->headers->get('Clear-Site-Data'));
+        $res->assertSessionHas('chat_logout.uid', (string) $va->id);
+        $res->assertSessionHas('chat_logout.token');
         $this->assertGuest('admin');
     }
 
@@ -48,7 +50,9 @@ class TeamLogoutPrivacyTest extends TestCase
 
         $res = $this->actingAs($va, 'admin')->get('/admin/logout')->assertRedirect(route('admin.login'));
 
-        $this->assertSame('"storage"', $res->headers->get('Clear-Site-Data'));
+        $this->assertNull($res->headers->get('Clear-Site-Data'));
+        $res->assertSessionHas('chat_logout.uid', (string) $va->id);
+        $res->assertSessionHas('chat_logout.token');
     }
 
     public function test_the_chat_page_stamps_local_state_with_the_signed_in_user(): void
@@ -72,4 +76,36 @@ class TeamLogoutPrivacyTest extends TestCase
         $this->assertStringContainsString("'apex-team-notifs' + U", $pageB);
         $this->assertStringContainsString("'apex-team-leader' + U", $pageB);
     }
+
+    public function test_old_tab_cannot_read_or_send_using_a_new_accounts_cookie(): void
+    {
+        $va = $this->va();
+        $this->actingAs($va, 'admin')->withHeaders([
+            'User-Agent' => 'ApexDesktop/1.0', 'X-Apex-Chat-Session' => 'former-session',
+        ]);
+        $this->getJson('/admin/team-messages/notifications')->assertUnauthorized();
+        $this->getJson('/admin/team-messages/open?self=1')->assertUnauthorized();
+        $this->postJson('/admin/team-messages', ['body' => 'must not send', 'self' => 1])->assertUnauthorized();
+        $this->assertDatabaseMissing('team_messages', ['body' => 'must not send']);
+    }
+
+    public function test_notes_snapshot_expires_when_its_oldest_message_reaches_seven_days(): void
+    {
+        $va = $this->va();
+        $this->actingAs($va, 'admin')->withHeader('User-Agent', 'ApexDesktop/1.0');
+        $conv = $this->getJson('/admin/team-messages/open?self=1')->assertOk()->json('conversation_id');
+        $this->postJson('/admin/team-messages', ['body' => 'private retention note', 'conversation_id' => $conv])->assertSuccessful();
+        $message = \App\Models\TeamMessage::where('body', 'private retention note')->firstOrFail();
+        $created = now()->subDays(6)->subHours(23)->startOfSecond();
+        $message->forceFill(['created_at' => $created])->save();
+        $this->getJson('/admin/team-messages/open?self=1')->assertOk()
+            ->assertJsonPath('cacheExpiresAt', $created->copy()->addDays(7)->getTimestampMs());
+    }
+
+    public function test_chat_pages_are_not_stored_in_the_http_cache(): void
+    {
+        $res = $this->actingAs($this->va(), 'admin')->withHeader('User-Agent', 'ApexDesktop/1.0')
+            ->get('/admin/team-messages?standalone=1')->assertOk();
+        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
+    }
 }
````

## Exact source snapshots of newly created files

These capture every new implementation/test file listed in the inventory, excluding this self-referential log. They are snapshots for review, not additional changes. Claude's pre-existing untracked TeamSearchJumpTest.php is intentionally excluded because I did not create or edit it.

### app/Http/Middleware/TeamChatSession.php

````text
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamChatSession
{
    public static function token(Request $request): string
    {
        return hash_hmac('sha256', $request->session()->getId().':'.auth('admin')->id(), (string) config('app.key'));
    }

    public function handle(Request $request, Closure $next): Response
    {
        // An old tab shares the browser's NEW auth cookie. Never let it read or send
        // as that new account, even before it receives the cross-tab logout event.
        $scope = $request->header('X-Apex-Chat-Session');
        if ($scope !== null && ! hash_equals(self::token($request), $scope)) {
            return response()->json(['message' => 'Your chat session has ended.'], 401)
                ->header('Cache-Control', 'no-store');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}

````

### public/js/team-chat-privacy.js

````text
/* Session lifetime for Team Chat. No message content belongs in the session marker. */
(function () {
    'use strict';
    if (window.ApexChatPrivacy) return;
    var current = null, stopped = false, requests = new Set(), uploads = new Set();
    var prefix = 'apex-team-session:u';
    var legacy = ['tc-draft', 'tc-drafts', 'tc-recent-emoji', 'apex-team-last-msg',
        'apex-team-notifs', 'apex-team-last-notified', 'apex-team-leader'];
    function read(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function removeLegacy() {
        [localStorage, sessionStorage].forEach(function (s) {
            legacy.forEach(function (k) { try { s.removeItem(k); } catch (e) {} });
        });
    }
    function live() {
        if (!current || stopped) return false;
        try { return localStorage.getItem(prefix + current.uid) === current.token; }
        catch (e) { return !stopped; }
    }
    function clear(scope) {
        if (!scope || !scope.uid) return Promise.resolve();
        // A delayed cleanup from a former login must not wipe a newer login of this user.
        var marker = read(prefix + scope.uid);
        if (marker && marker !== scope.token) return Promise.resolve();
        removeLegacy();
        [localStorage, sessionStorage].forEach(function (s) {
            try { Object.keys(s).forEach(function (k) {
                if (/^(apex-team-|tc-drafts?:u|tc-recent-emoji:u)/.test(k) && k.endsWith(':u' + scope.uid)) s.removeItem(k);
            }); } catch (e) {}
        });
        return new Promise(function (resolve) {
            try {
                var r = indexedDB.open('apexChat', 1);
                r.onupgradeneeded = function () { r.result.createObjectStore('threads'); };
                r.onerror = function () { resolve(); };
                r.onsuccess = function () {
                    var db = r.result;
                    var t = db.transaction('threads', 'readwrite'), c = t.objectStore('threads').openCursor();
                    c.onsuccess = function () {
                        var row = c.result; if (!row) return;
                        var v = row.value;
                        if (String(row.key).startsWith('u' + scope.uid + '|') &&
                            (!v.token || v.token === scope.token)) row.delete();
                        // Unstamped legacy snapshots cannot safely be assigned to any VA.
                        else if (!v.uid && !/^u\d+\|/.test(String(row.key))) row.delete();
                        row.continue();
                    };
                    t.oncomplete = t.onabort = t.onerror = function () { db.close(); resolve(); };
                };
            } catch (e) { resolve(); }
        });
    }
    function stop(navigate) {
        if (stopped) return;
        stopped = true;
        window.__tcPrivacyStopped = true;
        window.__tcAllowLeave = true;
        window.__tcRun = (window.__tcRun || 0) + 1;
        window.__tcNavSeq = (window.__tcNavSeq || 0) + 1;
        window.__tcDrafts = {};
        requests.forEach(function (c) { c.abort(); });
        uploads.forEach(function (x) { x.abort(); });
        if (window.__tcReg) window.__tcReg.cleanup();
        window.dispatchEvent(new Event('apex:chat-session-ended'));
        // Scrub the old document immediately, before any network navigation or pending reply.
        document.querySelectorAll('.tc-wrap, .apex-nc-panel, .apex-nc-bell').forEach(function (e) { e.remove(); });
        try { window.__TAURI__.core.invoke('set_unread', { count: 0 }); } catch (e) {}
        clear(current);
        if (navigate) location.replace(current.login);
    }
    function check() { if (!live()) stop(true); return live(); }
    function chatUrl(url) {
        try { var u = new URL(url, location.href); return u.origin === location.origin && /^\/admin\/(team-messages(?:\/|$)|logout$)/.test(u.pathname); }
        catch (e) { return false; }
    }
    function abandoned() { return new Promise(function () {}); }
    function start(scope) {
        if (current) return;
        current = scope;
        removeLegacy();
        var previous = read(prefix + scope.uid);
        if (previous && previous !== scope.token) clear({ uid: scope.uid, token: previous });
        // Cookies are shared across tabs of this origin. A newly authenticated account
        // invalidates old tabs; separate browser profiles have separate storage.
        try {
            Object.keys(localStorage).forEach(function (k) {
                if (k.startsWith(prefix) && k !== prefix + scope.uid) {
                    clear({ uid: k.slice(prefix.length), token: localStorage.getItem(k) });
                }
            });
            localStorage.setItem(prefix + scope.uid, scope.token);
        } catch (e) {}
        window.addEventListener('storage', function (e) { if (e.key === prefix + scope.uid || e.key === null) check(); });
        window.addEventListener('focus', check);
        window.addEventListener('visibilitychange', check);
        // A history/BFCache restoration must not paint a former user's document.
        window.addEventListener('pagehide', function () { document.documentElement.style.visibility = 'hidden'; });
        window.addEventListener('pageshow', function () { if (check()) document.documentElement.style.visibility = ''; });
        var fetch0 = window.fetch;
        window.fetch = function (url, opts) {
            if (!chatUrl(url instanceof Request ? url.url : url)) return fetch0.apply(this, arguments);
            if (!check()) return abandoned();
            opts = Object.assign({}, opts || {});
            opts.headers = new Headers(opts.headers || (url instanceof Request ? url.headers : undefined));
            opts.headers.set('X-Apex-Chat-Session', scope.token);
            var ctl = new AbortController(), signal = opts.signal || (url instanceof Request ? url.signal : null);
            if (signal) { if (signal.aborted) ctl.abort(); else signal.addEventListener('abort', function () { ctl.abort(); }, { once: true }); }
            opts.signal = ctl.signal; requests.add(ctl);
            return fetch0.call(this, url, opts).then(function (r) {
                requests.delete(ctl);
                if (!live()) return abandoned();
                if (r.status === 401 || r.redirected) { stop(true); return abandoned(); }
                return r;
            }, function (e) { requests.delete(ctl); if (!live()) return abandoned(); throw e; });
        };
        var open0 = XMLHttpRequest.prototype.open, send0 = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url) { this.__apexChat = chatUrl(url); return open0.apply(this, arguments); };
        XMLHttpRequest.prototype.send = function () {
            if (this.__apexChat) {
                if (!check()) { this.abort(); return; }
                this.setRequestHeader('X-Apex-Chat-Session', scope.token);
                var x = this; uploads.add(x);
                x.addEventListener('loadend', function () { uploads.delete(x); if (x.status === 401) stop(true); });
            }
            return send0.apply(this, arguments);
        };
    }
    window.ApexChatPrivacy = { start: start, clear: clear, live: live,
        token: function () { return current && current.token; },
        logout: function () { stop(false); return clear(current); } };
})();

````

### resources/views/partials/team-chat-logout.blade.php

````text
@if (session('chat_logout'))
<script src="{{ asset('js/team-chat-privacy.js') }}?v={{ filemtime(public_path('js/team-chat-privacy.js')) }}"></script>
<script>window.ApexChatPrivacy.clear(@json(session('chat_logout')));</script>
@endif

````

### resources/views/partials/team-chat-session.blade.php

````text
@once
<script src="{{ asset('js/team-chat-privacy.js') }}?v={{ filemtime(public_path('js/team-chat-privacy.js')) }}"></script>
<script>
window.ApexChatPrivacy.start({
    uid: @json((string) Auth::guard('admin')->id()),
    token: @json(\App\Http\Middleware\TeamChatSession::token(request())),
    login: @json(route('admin.chat-login'))
});
</script>
@endonce

````

### tests/Browser/TeamChat/README.md

````text
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

````

### tests/Browser/TeamChat/phase1.cjs

````text
// Phase 1 browser checks — message integrity & wrong-chat bugs.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok, detail }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? '  ' + JSON.stringify(detail) : '')); }

async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 820 } });
  const p = await ctx.newPage();
  p.errs = [];
  p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts', { timeout: 30000 });
  return p;
}
const rowByName = (name) => `a.tc-contact:has(.tc-c-name:text-is("${name}"))`;
async function openRow(p, name) { await p.click(rowByName(name)); }
async function waitHeader(p, name, t = 15000) {
  await p.waitForFunction(n => { const h = document.querySelector('.tc-thread-head .tc-th-name'); return h && h.textContent.trim() === n; }, name, { timeout: t });
}
async function headerName(p) { return p.evaluate(() => (document.querySelector('.tc-thread-head .tc-th-name') || {}).textContent?.trim()); }
async function msgTexts(p) { return p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim())); }
async function msgIds(p) { return p.evaluate(() => [...document.querySelectorAll('#tcMessages [data-id]')].map(e => +e.dataset.id)); }
// Post a message as the page's user through the real endpoint.
async function apiSend(p, fields) {
  return p.evaluate(async (f) => {
    const fd = new FormData();
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    for (const k in f) fd.append(k, f[k]);
    const r = await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
    return r.json();
  }, fields);
}
async function apiThread(p, conv) {
  return p.evaluate(async (c) => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=0', { headers: { 'Accept': 'application/json' } })).json()).messages, conv);
}
async function type(p, text) { await p.click('#tcInput'); await p.fill('#tcInput', text); }

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  const abid = await login(browser, 'va0@apex.test');
  const SUPER_ID = await abid.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad')?.dataset.peer);
  if (!SUPER_ID) throw new Error('super id not found');
  const sent = async (p, f) => { const r = await apiSend(p, f); if (!r || !r.ok) throw new Error('api send failed ' + JSON.stringify(r)); return r; };
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' });
  await sup.waitForSelector('#tcContacts');

  // ---- 1. Double Enter sends once ------------------------------------------------------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain');
    const bodies = [];
    await sup.route('**/admin/team-messages', async (route) => {
      if (route.request().method() !== 'POST') return route.continue().catch(()=>{});
      bodies.push(route.request().postData() || '');
      await sleep(1500); return route.continue().catch(()=>{});
    });
    await type(sup, 'double-enter-test');
    await sup.press('#tcInput', 'Enter'); await sup.press('#tcInput', 'Enter'); await sup.press('#tcInput', 'Enter');
    const ro = await sup.evaluate(() => document.getElementById('tcInput').readOnly);
    await sup.waitForFunction(() => !document.getElementById('tcInput').readOnly, null, { timeout: 15000 }).catch(()=>{});
    await sup.unroute('**/admin/team-messages');
    await sleep(800);
    const conv = await sup.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
    const server = (await apiThread(sup, conv)).filter(m => m.body === 'double-enter-test').length;
    const shown = (await msgTexts(sup)).filter(t => t === 'double-enter-test').length;
    check('1. triple Enter = one request, one message', bodies.length === 1 && server === 1 && shown === 1, { requests: bodies.length, server, shown, readOnlyWhileSending: ro });
    check('1b. composer cleared after send', (await sup.inputValue('#tcInput')) === '');
  }

  // ---- 2. Retry after a failed send re-uses the id → no duplicate -------------------------
  {
    let n = 0; const uuids = [];
    await sup.route('**/admin/team-messages', async (route) => {
      if (route.request().method() !== 'POST') return route.continue().catch(()=>{});
      n++;
      const m = (route.request().postData() || '').match(/name="client_uuid"\r\n\r\n([^\r]+)/); uuids.push(m && m[1]);
      if (n === 1) { await route.fetch(); return route.abort('connectionreset').catch(()=>{}); }   // server saved it, reply lost
      return route.continue().catch(()=>{});
    });
    await type(sup, 'retry-test');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => !document.getElementById('tcInput').readOnly, null, { timeout: 15000 }).catch(()=>{});
    await sleep(300);
    const kept = await sup.inputValue('#tcInput');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 }).catch(()=>{});
    await sup.unroute('**/admin/team-messages');
    await sleep(3500);
    const conv = await sup.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
    const server = (await apiThread(sup, conv)).filter(m => m.body === 'retry-test').length;
    const shown = (await msgTexts(sup)).filter(t => t === 'retry-test').length;
    check('2. lost reply + retry = one message (same client id)', server === 1 && shown === 1 && uuids[0] && uuids[0] === uuids[1] && kept === 'retry-test',
      { requests: n, sameId: uuids[0] === uuids[1], server, shown, textKeptAfterFailure: kept });
  }

  // ---- 3. Slow send, switch chat: nothing lands in the new chat ---------------------------
  {
    await sup.route('**/admin/team-messages', async (route) => {
      if (route.request().method() !== 'POST') return route.continue().catch(()=>{});
      await sleep(2500); return route.continue().catch(()=>{});
    });
    await type(sup, 'slow-send-to-abid');
    await sup.press('#tcInput', 'Enter');
    await sleep(200);
    await openRow(sup, 'Mujeeb Rahman'); await waitHeader(sup, 'Mujeeb Rahman');
    const roInB = await sup.evaluate(() => document.getElementById('tcInput').readOnly);
    await type(sup, 'typing in mujeeb');
    await sleep(4000);
    await sup.unroute('**/admin/team-messages');
    const inB = (await msgTexts(sup)).includes('slow-send-to-abid');
    const draftB = await sup.inputValue('#tcInput');
    const abidPreview = await sup.evaluate(n => { const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n); return r && r.querySelector('[data-preview-text]')?.textContent; }, 'Abid Hussain');
    check('3. slow send then switch: not shown in other chat, other chat composer untouched', !inB && draftB === 'typing in mujeeb' && !roInB,
      { shownInMujeeb: inB, mujeebDraft: draftB, mujeebReadOnly: roInB, abidPreview });
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(1500);
    check('3b. the message is in the right chat', (await msgTexts(sup)).filter(t => t === 'slow-send-to-abid').length === 1);
    await sup.fill('#tcInput', '');
  }

  // ---- 4. Slow open of A, then B: B stays on screen ---------------------------------------
  {
    await sup.evaluate(async () => { try { indexedDB.deleteDatabase('apexChat'); } catch (e) {} });
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await openRow(sup, 'Ubaid Dogar'); await waitHeader(sup, 'Ubaid Dogar');
    await sleep(2500);   // let idle prefetch settle
    await sup.route('**/admin/team-messages/open?**', async (route) => {
      const u = route.request().url();
      if (u.includes('prefetch=1')) return route.abort().catch(()=>{});
      if (u.includes('c=3')) { await sleep(2500); }   // Raja's DM (conv 3) is slow
      return route.continue().catch(()=>{});
    });
    await sup.evaluate(async () => { try { indexedDB.deleteDatabase('apexChat'); } catch (e) {} });
    await openRow(sup, 'Raja Khuram');
    await sleep(150);
    await openRow(sup, 'Umair Sajid');
    await sleep(5000);
    const h = await headerName(sup);
    const active = await sup.evaluate(() => document.querySelector('a.tc-contact.active .tc-c-name')?.textContent.trim());
    check('4. click A (slow) then B: ends on B', h === 'Umair Sajid' && active === 'Umair Sajid', { header: h, activeRow: active, url: sup.url() });
    await sup.unroute('**/admin/team-messages/open?**');
  }

  // ---- 5. A late thread poll for A never lands in B -------------------------------------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(1200);
    const convA = await sup.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
    await sup.route('**/admin/team-messages/thread?**', async (route) => {
      if (route.request().url().includes('c=' + convA + '&')) await sleep(3000);
      return route.continue().catch(()=>{});
    });
    await sleep(3500);   // a poll for A is now in flight (held)
    await sent(abid, { recipient_id: SUPER_ID, body: 'late-poll-from-abid' });
    await sleep(300);
    await openRow(sup, 'Mujeeb Rahman'); await waitHeader(sup, 'Mujeeb Rahman');
    await sleep(5000);
    await sup.unroute('**/admin/team-messages/thread?**');
    const inB = (await msgTexts(sup)).includes('late-poll-from-abid');
    check('5. late poll for A does not add A\'s message to B', !inB, { leakedIntoMujeeb: inB });
  }

  // ---- 6. Opening a never-messaged teammate: previous chat is no longer "open" -------------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(800);
    await openRow(sup, 'Sanwal Khan'); await waitHeader(sup, 'Sanwal Khan'); await sleep(800);
    await sent(abid, { recipient_id: SUPER_ID, body: 'while-you-are-on-sanwal' });
    await sup.waitForFunction(n => {
      const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n);
      return r && r.querySelector('[data-badge]');
    }, 'Abid Hussain', { timeout: 20000 }).catch(() => {});
    const st = await sup.evaluate(n => {
      const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n);
      return { badge: r?.querySelector('[data-badge]')?.textContent, preview: r?.querySelector('[data-preview-text]')?.textContent };
    }, 'Abid Hussain');
    check('6. previous chat keeps badge + preview while on a never-messaged teammate', st.badge && st.preview === 'while-you-are-on-sanwal', st);
  }

  // ---- 7. First message in a new group doesn't take over the creator's DM row -------------
  {
    const san = await login(browser, 'va5@apex.test');
    await san.goto(CHAT, { waitUntil: 'domcontentloaded' }); await san.waitForSelector('#tcContacts');
    await sleep(6000);   // let Sanwal's notifier take its first (baseline) poll
    const sanId = await san.evaluate(() => fetch('/admin/team-messages/presence').then(() => null));
    const sanAdminId = await sup.evaluate(n => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n)?.dataset.peer, 'Sanwal Khan');
    const gid = await sup.evaluate(async (id) => {
      const r = await fetch('/admin/team-messages/group', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: 'Brand New Group', members: [+id] }) });
      return (await r.json()).conversation_id;
    }, sanAdminId);
    await sent(sup, { conversation_id: gid, body: 'first-group-message' });
    await san.waitForFunction(() => [...document.querySelectorAll('a.tc-contact')].some(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Brand New Group'), null, { timeout: 20000 }).catch(() => {});
    const st = await san.evaluate(() => {
      const rows = [...document.querySelectorAll('a.tc-contact')];
      const dm = rows.find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad');
      const g = rows.find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Brand New Group');
      return { dmConv: dm?.dataset.conversation || null, dmPreview: dm?.querySelector('[data-preview-text]')?.textContent || null,
               groupRow: !!g, groupConv: g?.dataset.conversation, groupPreview: g?.querySelector('[data-preview-text]')?.textContent, groupBadge: g?.querySelector('[data-badge]')?.textContent };
    });
    check('7. new group gets its own row; creator DM row untouched', !st.dmConv && st.groupRow && String(st.groupConv) === String(gid) && /first-group-message/.test(st.groupPreview || ''), st);
    // Clicking the new row opens the group.
    await openRow(san, 'Brand New Group').catch(()=>{}); await waitHeader(san, 'Brand New Group').catch(()=>{});
    check('7b. the new group row opens the group', (await msgTexts(san)).includes('first-group-message'));
    san.errsCopy = san.errs;
    results.sanErrs = san.errs;
  }

  // ---- 8. Teammate's message sent just before mine isn't skipped, and order is kept --------
  {
    await openRow(sup, 'Abid Hussain'); await waitHeader(sup, 'Abid Hussain'); await sleep(1500);
    await sup.route('**/admin/team-messages/thread?**', route => route.abort().catch(()=>{}));   // polls blocked
    await sleep(3200);
    const t = await sent(abid, { recipient_id: SUPER_ID, body: 'teammate-before-mine' });
    await type(sup, 'mine-after-teammate');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 }).catch(()=>{});
    const shownEarly = (await msgTexts(sup)).includes('teammate-before-mine');
    await sup.unroute('**/admin/team-messages/thread?**');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'teammate-before-mine'), null, { timeout: 15000 }).catch(() => {});
    const texts = await msgTexts(sup);
    const ids = await msgIds(sup);
    const sorted = [...ids].sort((a, b) => a - b);
    const iT = texts.indexOf('teammate-before-mine'), iM = texts.indexOf('mine-after-teammate');
    check('8. teammate message just before my send still appears, in order', iT >= 0 && iM > iT && JSON.stringify(ids) === JSON.stringify(sorted),
      { teammateShownBeforeUnblock: shownEarly, teammateIndex: iT, mineIndex: iM, idsSorted: JSON.stringify(ids) === JSON.stringify(sorted) });
  }

  // ---- 9. No JS errors ----------------------------------------------------------------------
  check('9. no page errors (super, abid, sanwal)', !sup.errs.length && !abid.errs.length && !(results.sanErrs || []).length, { sup: sup.errs, abid: abid.errs, san: results.sanErrs });

  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });

````

### tests/Browser/TeamChat/phase1b.cjs

````text
// Phase 1 regression checks — features the changes touch.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? '  ' + JSON.stringify(detail) : '')); }
async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 820 } });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = async (p, n) => p.click(row(n));
const head = async (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
const convOf = p => p.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
const serverCount = (p, c, body) => p.evaluate(async ([c, b]) => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=0', { headers: { Accept: 'application/json' } })).json()).messages.filter(m => m.body === b).length, [c, body]);

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const p = await login(browser, 'super@apex.test');
  await p.goto(CHAT, { waitUntil: 'domcontentloaded' }); await p.waitForSelector('#tcContacts');

  // a. DM → group → DM swaps, members button present for the group.
  await open(p, 'Abid Hussain'); await head(p, 'Abid Hussain');
  await open(p, 'CFPB Screenshots'); await head(p, 'CFPB Screenshots');
  const hasMembers = await p.$('#tcMembersBtn');
  const gText = (await texts(p)).includes('Group hello');
  await open(p, 'Mujeeb Rahman'); await head(p, 'Mujeeb Rahman');
  const seenHidden = await p.evaluate(() => document.getElementById('tcSeen').hidden);
  check('a. DM → group → DM swap works (members btn, group msgs, Seen-by hidden on DM)', hasMembers && gText && seenHidden);

  // b. Back button returns to the previous chat.
  await p.goBack(); await head(p, 'CFPB Screenshots').catch(() => {});
  const back1 = await p.evaluate(() => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim());
  check('b. browser Back goes to the previous chat', back1 === 'CFPB Screenshots', { header: back1 });

  // c. A send that fails after switching away is put back when the VA returns — and resends once.
  await open(p, 'Raja Khuram'); await head(p, 'Raja Khuram');
  const rajaConv = await convOf(p);
  let first = true;
  await p.route('**/admin/team-messages', async route => {
    if (route.request().method() !== 'POST') return route.continue().catch(() => {});
    if (first) { first = false; await sleep(1500); return route.abort('failed').catch(() => {}); }
    return route.continue().catch(() => {});
  });
  await p.fill('#tcInput', 'come-back-to-me');
  await p.press('#tcInput', 'Enter');
  await sleep(200);
  await open(p, 'Ubaid Dogar'); await head(p, 'Ubaid Dogar');
  await sleep(2500);
  const ubaidBox = await p.inputValue('#tcInput');
  await open(p, 'Raja Khuram'); await head(p, 'Raja Khuram'); await sleep(600);
  const restored = await p.inputValue('#tcInput');
  await p.press('#tcInput', 'Enter');
  await p.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 }).catch(() => {});
  await p.unroute('**/admin/team-messages');
  await sleep(1500);
  const cnt = await serverCount(p, rajaConv, 'come-back-to-me');
  check('c. failed send after switching is restored in its own chat and resends once', ubaidBox === '' && restored === 'come-back-to-me' && cnt === 1, { ubaidBox, restored, serverCount: cnt });

  // d. Session expired on send → reload keeps the draft in the SAME chat only.
  await p.route('**/admin/team-messages', async route => {
    if (route.request().method() !== 'POST') return route.continue().catch(() => {});
    return route.fulfill({ status: 419, contentType: 'application/json', body: '{"message":"CSRF token mismatch."}' }).catch(() => {});
  });
  await p.fill('#tcInput', 'draft-after-419');
  await Promise.all([p.waitForEvent('load', { timeout: 20000 }).catch(() => {}), p.press('#tcInput', 'Enter')]);
  await p.unroute('**/admin/team-messages');
  await p.waitForSelector('#tcInput', { timeout: 20000 }); await sleep(800);
  const afterReload = await p.inputValue('#tcInput');
  const hdr = await p.evaluate(() => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim());
  await open(p, 'Ubaid Dogar'); await head(p, 'Ubaid Dogar');
  const otherBox = await p.inputValue('#tcInput');
  check('d. 419 reconnect restores the draft into the same chat only', afterReload === 'draft-after-419' && hdr === 'Raja Khuram' && otherBox === '', { afterReload, hdr, otherBox });

  // e. Old-format (plain string) draft from the previous app version still restores.
  // (the stash key is per signed-in user since the shared-PC work — a plain string under it
  //  is still restored, which is what the old app version wrote)
  await p.evaluate(() => {
    const m = document.documentElement.innerHTML.match(/RECONNECT_DRAFT_KEY = '([^']+)' \+ "(\d+)"/);
    if (!m) throw Error('Per-user reconnect draft key missing');
    sessionStorage.setItem(m[1] + m[2], 'legacy draft text');
    sessionStorage.setItem('tc-draft', 'unowned legacy draft must never restore');
  });
  await p.reload({ waitUntil: 'domcontentloaded' }); await p.waitForSelector('#tcInput'); await sleep(500);
  check('e. legacy plain-text draft restores after update', (await p.inputValue('#tcInput')) === 'legacy draft text');
  await p.fill('#tcInput', '');

  // f. Normal sends + a file still work; message yourself works.
  await p.setInputFiles('#tcFile', { name: 'proof.zip', mimeType: 'application/zip', buffer: Buffer.from('PK zip bytes') });
  await p.fill('#tcInput', 'file-with-text');
  await p.press('#tcInput', 'Enter');
  await p.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'proof.zip'), null, { timeout: 15000 }).catch(() => {});
  const fileShown = await p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'proof.zip'));
  await p.click('a.tc-self-row'); await head(p, 'Notes (You)').catch(() => {});
  await p.fill('#tcInput', 'note-to-self'); await p.press('#tcInput', 'Enter');
  await p.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'note-to-self'), null, { timeout: 15000 }).catch(() => {});
  const noteShown = (await texts(p)).includes('note-to-self');
  check('f. file send + notes-to-self still work', fileShown && noteShown, { fileShown, noteShown });

  check('g. no page errors', !p.errs.length, p.errs);
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });

````

### tests/Browser/TeamChat/phase2.cjs

````text
// Phase 2 browser checks — uploads, attachments & file sending.
// Server must run with TEAM_CHAT_MAX_REQUEST_MB=6 and PHP upload_max_filesize=20M.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const MB = 1024 * 1024;
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
const only = process.argv[2] ? process.argv[2].split(',') : null;
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }
const want = id => !only || only.includes(id);

async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 } });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
const attNames = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].map(e => e.textContent.trim()));
const chips = p => p.evaluate(() => [...document.querySelectorAll('#tcPending .tc-chip-name')].map(e => e.textContent.trim()));
const lastToast = p => p.evaluate(() => { const t = [...document.querySelectorAll('.apex-toast, .toast, [class*="toast"]')].map(e => e.textContent.trim()).filter(Boolean); return t[t.length - 1] || ''; });
const file = (name, bytes) => ({ name, mimeType: 'application/zip', buffer: Buffer.alloc(bytes, 7) });
const markNoReload = p => p.evaluate(() => { window.__noReload = 1; });
const stillNoReload = p => p.evaluate(() => window.__noReload === 1);
const convOf = p => p.evaluate(() => document.getElementById('tcMessages').dataset.conversation);
const thread = (p, c) => p.evaluate(async c => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=0', { headers: { Accept: 'application/json' } })).json()).messages, c);
const sendApi = (p, f) => p.evaluate(async f => { const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); for (const k in f) fd.append(k, f[k]); return (await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })).json(); }, f);
async function throttleUpload(p, bytesPerSec) {
  const cdp = await p.context().newCDPSession(p);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: bytesPerSec });
  return async () => { await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 }); await cdp.detach(); };
}
// Toasts: capture every toast text shown.
async function captureToasts(p) {
  await p.evaluate(() => {
    window.__toasts = [];
    const orig = window.apexToast;
    window.apexToast = function (t) { window.__toasts.push(String(t)); if (orig) return orig.apply(this, arguments); };
  });
}
const toasts = p => p.evaluate(() => window.__toasts || []);

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  const abid = await login(browser, 'va0@apex.test');
  const SUPER_ID = await abid.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad')?.dataset.peer);
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
  await captureToasts(sup);

  // ---- L. Limits enforced before upload -------------------------------------------------
  if (want('L')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await captureToasts(sup);
    const limits = await sup.evaluate(() => document.getElementById('tcAttach').title);
    await sup.setInputFiles('#tcFile', [file('too-big.zip', 7 * MB)]);
    await sleep(200);
    const c1 = await chips(sup); const t1 = (await toasts(sup)).slice(-1)[0] || '';
    await sup.setInputFiles('#tcFile', [file('four-a.zip', 4 * MB)]);
    await sup.setInputFiles('#tcFile', [file('four-b.zip', 4 * MB)]);
    await sleep(200);
    const c2 = await chips(sup); const t2 = (await toasts(sup)).slice(-1)[0] || '';
    check('L1. a file over the per-file limit is refused, named, not staged', c1.length === 0 && /too-big\.zip/.test(t1) && /MB/.test(t1), { chips: c1, toast: t1 });
    check('L2. a file that would push the message over the total is refused', JSON.stringify(c2) === '["four-a.zip"]' && /four-b\.zip/.test(t2) && /separate message/.test(t2), { chips: c2, toast: t2 });
    check('L3. attach button shows the limits', /up to .* MB each, .* MB per message/.test(limits) && /server: upload_max_filesize 20M/.test(limits), limits);
    // 413 from the edge (Cloudflare's HTML page): clear message, files kept, no reload.
    await markNoReload(sup);
    await sup.route('**/admin/team-messages', r => r.request().method() === 'POST'
      ? r.fulfill({ status: 413, contentType: 'text/html', body: '<html><head><title>413 Payload Too Large</title></head><body><center>cloudflare</center></body></html>' }).catch(() => {})
      : r.continue().catch(() => {}));
    await sup.fill('#tcInput', 'edge says no');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => !document.getElementById('tcInput').readOnly, null, { timeout: 15000 });
    await sup.unroute('**/admin/team-messages');
    const t3 = (await toasts(sup)).slice(-1)[0] || '';
    check('L4. a 413 shows "too large", keeps text + files, no reload', /Too large for the server/.test(t3) && (await stillNoReload(sup)) && (await sup.inputValue('#tcInput')) === 'edge says no' && (await chips(sup)).length === 1, { toast: t3 });
    // Now it goes through (same content).
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 30000 });
    await sleep(800);
    const conv = await convOf(sup);
    const n = (await thread(sup, conv)).filter(m => m.body === 'edge says no').length;
    check('L5. retry after the 413 sends exactly once, with its file', n === 1 && (await attNames(sup)).includes('four-a.zip'), { serverCount: n });
  }

  // ---- T. Slow upload (> 60 s) completes; progress text shown --------------------------
  if (want('T')) {
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman'); await captureToasts(sup);
    await sup.setInputFiles('#tcFile', [file('slow-upload.zip', 2 * MB)]);
    const unthrottle = await throttleUpload(sup, 26 * 1024);   // ~80 s for 2 MB
    const t0 = Date.now();
    await sup.fill('#tcInput', 'slow-upload-msg');
    await sup.press('#tcInput', 'Enter');
    await sleep(8000);
    const label = await sup.evaluate(() => { const l = document.getElementById('tcProgressLabel'); return l && !l.hidden ? l.textContent : null; });
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'slow-upload.zip'), null, { timeout: 200000 }).catch(() => {});
    const secs = Math.round((Date.now() - t0) / 1000);
    await unthrottle();
    const shown = (await attNames(sup)).includes('slow-upload.zip');
    const tt = await toasts(sup);
    check('T1. an upload taking over 60 s completes (no "timed out")', shown && secs > 60 && !tt.some(x => /timed out|stalled/i.test(x)), { seconds: secs, toasts: tt });
    check('T2. progress text shows percent and MB', /Uploading… \d+%.*MB of .*MB/.test(label || ''), label);
  }

  // ---- R. Session token expired → refresh + automatic retry, files kept, no reload ------
  if (want('R')) {
    await open(sup, 'Raja Khuram'); await head(sup, 'Raja Khuram'); await markNoReload(sup);
    let n419 = 0, posts = 0;
    await sup.route('**/admin/team-messages', r => {
      if (r.request().method() !== 'POST') return r.continue().catch(() => {});
      posts++;
      if (n419++ === 0) return r.fulfill({ status: 419, contentType: 'application/json', body: '{"message":"CSRF token mismatch."}' }).catch(() => {});
      return r.continue().catch(() => {});
    });
    await sup.setInputFiles('#tcFile', [file('after-419.zip', 300 * 1024)]);
    await sup.fill('#tcInput', 'after-419-msg');
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 20000 }).catch(() => {});
    await sup.unroute('**/admin/team-messages');
    await sleep(800);
    const conv = await convOf(sup);
    const n = (await thread(sup, conv)).filter(m => m.body === 'after-419-msg').length;
    check('R1. expired token: new token fetched, sent once with its file, no reload', n === 1 && posts === 2 && (await stillNoReload(sup)) && (await attNames(sup)).includes('after-419.zip'), { posts, serverCount: n });
  }

  // ---- D. Drafts per chat --------------------------------------------------------------
  if (want('D')) {
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar');
    await sup.fill('#tcInput', 'draft for ubaid');
    await sup.setInputFiles('#tcFile', [file('ubaid-file.zip', 1000)]);
    // quote a message
    await sup.evaluate(() => { const m = [...document.querySelectorAll('#tcMessages .tc-msg')].pop(); m.querySelector('.tc-dots').click(); });
    await sup.click('#tcMenu [data-act="reply"]').catch(() => {});
    const replyShown = await sup.evaluate(() => !document.getElementById('tcReply').hidden);
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid');
    const sajidBox = await sup.inputValue('#tcInput'); const sajidChips = await chips(sup);
    await sup.fill('#tcInput', 'draft for sajid');
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar'); await sleep(300);
    const ub = { text: await sup.inputValue('#tcInput'), chips: await chips(sup), reply: await sup.evaluate(() => !document.getElementById('tcReply').hidden) };
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid'); await sleep(300);
    const sj = await sup.inputValue('#tcInput');
    check('D1. switching chats keeps each chat\'s text, files and quote', sajidBox === '' && sajidChips.length === 0 && ub.text === 'draft for ubaid' && JSON.stringify(ub.chips) === '["ubaid-file.zip"]' && (ub.reply === replyShown) && sj === 'draft for sajid', { sajidBox, sajidChips, ubaid: ub, replyShown, sajid: sj });
    // Reload keeps the text of both.
    await sup.reload({ waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcInput'); await sleep(500);
    const afterReload = await sup.inputValue('#tcInput');
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar'); await sleep(300);
    const ubAfter = await sup.inputValue('#tcInput');
    check('D2. drafts\' text survives a reload', afterReload === 'draft for sajid' && ubAfter === 'draft for ubaid', { afterReload, ubAfter });
    // Sending clears that chat's draft for good.
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => document.getElementById('tcInput').value === '', null, { timeout: 15000 });
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid');
    await open(sup, 'Ubaid Dogar'); await head(sup, 'Ubaid Dogar'); await sleep(300);
    check('D3. a sent draft does not come back', (await sup.inputValue('#tcInput')) === '');
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid'); await sup.fill('#tcInput', ''); await sup.dispatchEvent('#tcInput', 'input');
  }

  // ---- C. Typing right after opening survives the background refresh ---------------------
  if (want('C')) {
    await open(sup, 'Raja Khuram'); await head(sup, 'Raja Khuram'); await sleep(600);   // snapshot stored
    await open(sup, 'Umair Sajid'); await head(sup, 'Umair Sajid'); await sleep(600);
    const rajaConv = 3;
    const rajaAdmin = await sup.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Raja Khuram')?.dataset.peer);
    const raja = await login(browser, 'va2@apex.test');
    await sendApi(raja, { recipient_id: SUPER_ID, body: 'new-while-away' });   // cached copy is now stale
    await sup.route('**/admin/team-messages/open?**', async r => { if (!r.request().url().includes('prefetch=1')) await sleep(1500); return r.continue().catch(() => {}); });
    await open(sup, 'Raja Khuram');
    await sleep(150);
    await sup.fill('#tcInput', 'typed-immediately');
    await sleep(3500);
    await sup.unroute('**/admin/team-messages/open?**');
    const kept = await sup.inputValue('#tcInput');
    const got = (await texts(sup)).includes('new-while-away');
    check('C1. text typed while the chat refreshes after opening is kept', kept === 'typed-immediately' && got, { kept, freshMessageShown: got });
    await sup.fill('#tcInput', ''); await sup.dispatchEvent('#tcInput', 'input');
    await raja.context().close();
  }

  // ---- P. Pin / unpin during an upload: no reload, upload finishes, bar works -------------
  if (want('P')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await markNoReload(sup); await captureToasts(sup);
    await sup.setInputFiles('#tcFile', [file('pin-upload.zip', 600 * 1024)]);
    const unthrottle = await throttleUpload(sup, 40 * 1024);   // ~15 s
    await sup.fill('#tcInput', 'uploading-while-pinning');
    await sup.press('#tcInput', 'Enter');
    await sleep(1500);
    // pin the latest text message via its menu
    await sup.evaluate(() => { const m = [...document.querySelectorAll('#tcMessages .tc-msg:not(.mine), #tcMessages .tc-msg')].filter(e => e.querySelector('.tc-dots')).shift(); m.querySelector('.tc-dots').click(); });
    await sup.click('#tcMenu [data-act="pin"]');
    await sup.waitForSelector('#tcPinned', { timeout: 15000 }).catch(() => {});
    const pinnedBar = !!(await sup.$('#tcPinned'));
    const pinIcons = await sup.evaluate(() => document.querySelectorAll('#tcMessages .tc-pin-ic').length);
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'pin-upload.zip'), null, { timeout: 60000 }).catch(() => {});
    await unthrottle();
    const uploaded = (await attNames(sup)).includes('pin-upload.zip');
    check('P1. pinning during an upload: no reload, bar + 📌 appear, upload completes', pinnedBar && pinIcons === 1 && uploaded && (await stillNoReload(sup)), { pinnedBar, pinIcons, uploaded });
    // Full page load → the pinned bar opens on click (double-toggle fixed), then unpin in place.
    await sup.reload({ waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcPinnedHead'); await markNoReload(sup);
    await sup.click('#tcPinnedHead'); await sleep(200);
    const opened = await sup.evaluate(() => !document.getElementById('tcPinnedDrop').hidden);
    await sup.click('#tcPinnedDrop [data-unpin]');
    await sup.waitForFunction(() => !document.getElementById('tcPinned'), null, { timeout: 15000 }).catch(() => {});
    const gone = !(await sup.$('#tcPinned')) && (await sup.evaluate(() => document.querySelectorAll('#tcMessages .tc-pin-ic').length)) === 0;
    check('P2. pinned bar opens on click after a full load; unpin updates in place', opened && gone && (await stillNoReload(sup)), { opened, gone });
  }

  // ---- G. Group rename / add / remove during an upload: no reload ------------------------
  if (want('G')) {
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots'); await markNoReload(sup);
    await sup.setInputFiles('#tcFile', [file('group-upload.zip', 500 * 1024)]);
    const unthrottle = await throttleUpload(sup, 40 * 1024);
    await sup.fill('#tcInput', 'group-upload-msg'); await sup.press('#tcInput', 'Enter');
    await sleep(1000);
    await sup.click('#tcMembersBtn'); await sleep(300);
    await sup.fill('#tcRenameName', 'CFPB Screens (renamed)'); await sup.click('#tcRenameBtn');
    await head(sup, 'CFPB Screens (renamed)').catch(() => {});
    const rowName = await sup.evaluate(() => [...document.querySelectorAll('a.tc-contact .tc-c-name')].some(e => e.textContent.trim() === 'CFPB Screens (renamed)'));
    const before = await sup.evaluate(() => document.querySelectorAll('#tcMembers .tc-member-row').length);
    await sup.check('#tcMembers .tc-member-add-list input >> nth=0'); await sup.click('#tcAddMembersBtn');
    await sup.waitForFunction(n => document.querySelectorAll('#tcMembers .tc-member-row').length === n + 1, before, { timeout: 15000 }).catch(() => {});
    const after = await sup.evaluate(() => document.querySelectorAll('#tcMembers .tc-member-row').length);
    const modalOpen = await sup.evaluate(() => !document.getElementById('tcMembers').hidden);
    const headCount = await sup.evaluate(() => document.getElementById('tcMembersBtn')?.textContent.trim());
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'group-upload.zip'), null, { timeout: 60000 }).catch(() => {});
    await unthrottle();
    const uploaded = (await attNames(sup)).includes('group-upload.zip');
    const sysShown = await sup.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-sys')].some(e => /added/.test(e.textContent)));
    check('G1. rename + add member in place during an upload (no reload)', rowName && after === before + 1 && modalOpen && String(after) === headCount && uploaded && sysShown && (await stillNoReload(sup)),
      { rowName, before, after, headCount, modalOpen, uploaded, sysShown });
    // remove the member just added
    await sup.click('#tcMembers .tc-member-remove >> nth=-1');
    await sup.click('#tcConfirmModal button:has-text("Remove")').catch(async () => { await sup.click('#tcConfirmModal .tc-confirm-row button >> nth=-1'); });
    await sup.waitForFunction(n => document.querySelectorAll('#tcMembers .tc-member-row').length === n, before, { timeout: 15000 }).catch(() => {});
    check('G2. removing a member updates in place', (await sup.evaluate(() => document.querySelectorAll('#tcMembers .tc-member-row').length)) === before && (await stillNoReload(sup)));
    await sup.click('#tcMembersClose');
  }

  // ---- A. Profile photo change / remove without a reload ---------------------------------
  if (want('A')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await markNoReload(sup);
    const png0 = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DwnwEJMDEgAwYGAHtUAvtXt9cMAAAAAElFTkSuQmCC', 'base64');
    await sup.setInputFiles('#tcFile', [{ name: 'thumb.png', mimeType: 'image/png', buffer: png0 }]);
    await sleep(400);
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DwnwEJMDEgAwYGAHtUAvtXt9cMAAAAAElFTkSuQmCC', 'base64');
    // (clicking "Change photo" opens the OS file dialog; setting the input fires the same handler)
    await sup.setInputFiles('#tcAvFile', { name: 'me.png', mimeType: 'image/png', buffer: png });
    await sup.waitForSelector('#tcCropMask:not([hidden])', { timeout: 10000 });
    await sup.click('#tcCropSave');
    await sup.waitForFunction(() => !!document.querySelector('#tcMeAvatar .tc-avatar.has-img img'), null, { timeout: 15000 }).catch(() => {});
    const hasImg = await sup.evaluate(() => ({ me: !!document.querySelector('#tcMeAvatar .tc-avatar.has-img img'), self: !!document.querySelector('.tc-self-av .tc-avatar.has-img img') }));
    await sup.click('#tcMeAvatar'); await sup.click('#tcAvRemove');
    await sup.waitForFunction(() => !document.querySelector('#tcMeAvatar .tc-avatar.has-img'), null, { timeout: 15000 }).catch(() => {});
    const mono = await sup.evaluate(() => document.querySelector('#tcMeAvatar .tc-avatar')?.textContent.trim());
    const thumbOk = await sup.evaluate(async () => { const i = document.querySelector('#tcPending img'); return i ? (i.complete ? i.naturalWidth > 0 : await new Promise(r => { i.onload = () => r(true); i.onerror = () => r(false); })) : null; });
    check('A0. staged image thumbnail renders (blob preview allowed)', thumbOk !== false, { thumbOk });
    check('A1. photo change + remove update in place, no reload', hasImg.me && hasImg.self && mono === 'UA' && (await stillNoReload(sup)), { hasImg, mono });
  }

  // ---- F. Forward a file message → recipient gets the file --------------------------------
  if (want('F')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain');
    await sup.setInputFiles('#tcFile', [file('forward-me.zip', 2000)]);
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-att-name')].some(e => e.textContent.trim() === 'forward-me.zip'), null, { timeout: 15000 });
    await sup.evaluate(() => { const els = [...document.querySelectorAll('#tcMessages .tc-msg')].filter(e => [...e.querySelectorAll('.tc-att-name')].some(n => n.textContent.trim() === 'forward-me.zip')); els.pop().querySelector('.tc-dots').click(); });
    await sup.click('#tcMenu [data-act="forward"]');
    await sup.click('#tcFwdList .tc-fwd-row:has-text("Mujeeb Rahman")');
    await sleep(1200);
    const mujeeb = await login(browser, 'va1@apex.test');
    await mujeeb.goto(CHAT, { waitUntil: 'domcontentloaded' });
    await open(mujeeb, 'Umair Arshad'); await head(mujeeb, 'Umair Arshad'); await sleep(800);
    const got = await mujeeb.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg')].some(m => m.querySelector('.tc-fwd') && [...m.querySelectorAll('.tc-att-name')].some(n => n.textContent.trim() === 'forward-me.zip')));
    check('F1. forwarding a file-only message delivers the file', got);
    results.mujeebErrs = mujeeb.errs;
  }

  // ---- K. A batch of new messages advances the refresh cursor (no re-fetch loop) ----------
  if (want('K')) {
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await sleep(1500);
    await sup.route('**/admin/team-messages/thread?**', r => r.abort().catch(() => {}));
    await sleep(3200);
    for (let i = 0; i < 3; i++) await sendApi(abid, { recipient_id: SUPER_ID, body: 'batch-' + i });
    await sup.unroute('**/admin/team-messages/thread?**');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'batch-2'), null, { timeout: 15000 });
    await sleep(500);
    const resp = await sup.waitForResponse(r => r.url().includes('/admin/team-messages/thread?'), { timeout: 15000 });
    const again = (await resp.json()).messages.length;
    const dupes = (await texts(sup)).filter(t => /^batch-/.test(t)).length;
    check('K1. after a batch arrives, the next refresh fetches nothing again', again === 0 && dupes === 3, { refetched: again, shown: dupes });
  }

  // ---- V. Leave group → chat list, desktop layout kept ----------------------------------
  if (want('V')) {
    await open(sup, 'Credit Repair CFPB'); await head(sup, 'Credit Repair CFPB');
    await sup.click('#tcMembersBtn'); await sleep(300);
    await sup.click('#tcLeaveBtn');
    await sup.click('#tcConfirmModal .tc-confirm-row button >> nth=-1');
    await sup.waitForFunction(() => !/c=7/.test(location.search), null, { timeout: 15000 }).catch(() => {});
    await sleep(1200);
    const st = await sup.evaluate(() => ({ url: location.href, standalone: /standalone=1/.test(location.search), wrap: !!document.querySelector('.tc-wrap'),
      rowGone: ![...document.querySelectorAll('a.tc-contact .tc-c-name')].some(e => e.textContent.trim() === 'Credit Repair CFPB'), dashboardNav: !!document.querySelector('.pro-sidebar, .pro-nav') }));
    check('V1. leaving a group returns to the chat list in the desktop layout', st.standalone && st.wrap && st.rowGone && !st.dashboardNav, st);
  }

  check('Z. no page errors', !sup.errs.length && !abid.errs.length && !(results.mujeebErrs || []).length, { sup: sup.errs, abid: abid.errs, mujeeb: results.mujeebErrs });
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });

````

### tests/Browser/TeamChat/phase3.cjs

````text
// Phase 3 browser checks — unread, seen & notification state.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
const only = process.argv[2] ? process.argv[2].split(',') : null;
const want = id => !only || only.includes(id);
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }

// Record every OS notification the page tries to show, and (for the desktop-app checks)
// every native command it invokes.
const INSTRUMENT = ({ tauri }) => {
  window.__notifs = []; window.__invokes = [];
  const N = function (title, opts) { window.__notifs.push({ title, body: opts && opts.body }); this.close = function () {}; };
  N.permission = 'granted'; N.requestPermission = () => Promise.resolve('granted');
  Object.defineProperty(window, 'Notification', { value: N, writable: true, configurable: true });
  if (tauri) {
    window.__TAURI__ = {
      core: { invoke: function (cmd, args) { window.__invokes.push([cmd, args]); return Promise.resolve(); } },
      notification: {
        isPermissionGranted: () => Promise.resolve(true),
        sendNotification: function (o) { window.__notifs.push({ title: o.title, body: o.body, native: true }); },
      },
      window: { getCurrentWindow: () => ({ show() {}, unminimize() {}, setFocus() {} }) },
    };
  }
};

async function login(browser, email, opts = {}) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1280, height: 820 } });
  await ctx.grantPermissions(['notifications'], { origin: B });
  await ctx.addInitScript(INSTRUMENT, { tauri: !!opts.tauri });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); p.ctx = ctx; return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const notifs = p => p.evaluate(() => window.__notifs || []);
const invokes = p => p.evaluate(() => window.__invokes || []);
const badgeOf = (p, name) => p.evaluate(n => {
  const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === n);
  return r ? (r.querySelector('[data-badge]')?.textContent || null) : 'no-row';
}, name);
const sendApi = (p, f) => p.evaluate(async f => { const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); for (const k in f) fd.append(k, f[k]); const r = await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const j = await r.json(); if (!j.ok) throw new Error('send failed'); return j; }, f);
const readUpTo = (p, c) => p.evaluate(async c => (await (await fetch('/admin/team-messages/thread?c=' + c + '&after=999999&read=0', { headers: { Accept: 'application/json' } })).json()).readUpTo, c);
const ticksBlue = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg.mine .tc-btick')].map(e => e.classList.contains('read')));

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const abid = await login(browser, 'va0@apex.test');
  const SUPER_ID = await abid.evaluate(() => [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Umair Arshad')?.dataset.peer);
  const ABID_CONV = 1;

  // ---- N. Refresh / first launch must not replay the backlog ---------------------------
  if (want('N')) {
    // 40 older messages exist from the seed + these.
    for (let i = 0; i < 35; i++) await sendApi(abid, { recipient_id: SUPER_ID, body: 'history-' + i });

    const sup = await login(browser, 'super@apex.test');
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await sleep(14000);   // several poll cycles
    const firstLaunch = await notifs(sup);
    check('N1. first launch shows no notifications for existing history', firstLaunch.length === 0, { notifications: firstLaunch.length, sample: firstLaunch.slice(0, 2) });

    // A message that arrives AFTER priming is still delivered.
    await sendApi(abid, { recipient_id: SUPER_ID, body: 'after-priming' });
    await sup.waitForFunction(() => (window.__notifs || []).length > 0, null, { timeout: 20000 }).catch(() => {});
    const afterPrime = await notifs(sup);
    check('N2. a message sent after priming still notifies', afterPrime.length === 1 && /after-priming/.test(afterPrime[0].body || ''), afterPrime);

    // Refresh button wipes local state → must NOT replay the history.
    await sup.evaluate(() => { window.__notifs = []; });
    await sup.click('#tcRefresh');
    await sup.waitForFunction(() => document.readyState === 'complete' && !!document.getElementById('tcContacts'), null, { timeout: 30000 }).catch(() => {});
    await sleep(14000);
    const afterRefresh = await notifs(sup);
    check('N3. Refresh does not replay old messages', afterRefresh.length === 0, { notifications: afterRefresh.length, sample: afterRefresh.slice(0, 3) });

    // …and delivery still works after a Refresh.
    await sendApi(abid, { recipient_id: SUPER_ID, body: 'after-refresh' });
    await sup.waitForFunction(() => (window.__notifs || []).length > 0, null, { timeout: 20000 }).catch(() => {});
    const afterRefreshNew = await notifs(sup);
    check('N4. new messages still notify after a Refresh', afterRefreshNew.length === 1 && /after-refresh/.test(afterRefreshNew[0].body || ''), afterRefreshNew);
    await sup.ctx.close();
  }

  // ---- S. Seen / read only when the window is really in front ---------------------------
  if (want('S')) {
    const sup = await login(browser, 'super@apex.test');
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await open(sup, 'Abid Hussain'); await head(sup, 'Abid Hussain'); await sleep(1500);

    // The app goes to the tray / behind another window: the page is no longer focused.
    await sup.evaluate(() => {
      Object.defineProperty(document, 'hasFocus', { value: () => false, configurable: true });
      window.dispatchEvent(new Event('blur'));
    });
    const focusState = await sup.evaluate(() => ({ hidden: document.hidden, focus: document.hasFocus() }));

    const sent = (await sendApi(abid, { recipient_id: SUPER_ID, body: 'sent-while-you-were-away' })).message.id;
    await sleep(9000);

    const away = {
      readUpTo: await readUpTo(abid, ABID_CONV),          // what the SENDER sees (blue ticks)
      senderTicks: await (async () => { await open(abid, 'Umair Arshad'); await head(abid, 'Umair Arshad'); await sleep(1200); return ticksBlue(abid); })(),
      badge: await badgeOf(sup, 'Abid Hussain'),
      shown: await sup.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'sent-while-you-were-away')),
      notified: (await notifs(sup)).length, focusState,
    };
    check('S1. away: not marked read, no blue tick, badge shows, message still arrives',
      away.readUpTo < sent && !away.senderTicks.slice(-1)[0] && away.badge === '1' && away.shown && away.notified >= 1, Object.assign({ sent }, away));

    // Coming back to the window marks it read and clears the badge.
    await sup.evaluate(() => {
      Object.defineProperty(document, 'hasFocus', { value: () => true, configurable: true });
      window.dispatchEvent(new Event('focus'));
    });
    await sleep(9000);
    const back = {
      readUpTo: await readUpTo(abid, ABID_CONV),
      badge: await badgeOf(sup, 'Abid Hussain'),
      senderTicks: await (async () => { await sleep(1500); return ticksBlue(abid); })(),
    };
    check('S2. back at the window: marked read, badge clears, sender sees the blue tick',
      back.readUpTo >= sent && back.badge === null && back.senderTicks.slice(-1)[0] === true, Object.assign({ sent }, back));
    await sup.ctx.close();
  }

  // ---- B. Taskbar unread dot (desktop app bridge) ---------------------------------------
  if (want('B')) {
    const sup = await login(browser, 'super@apex.test', { tauri: true });
    await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman'); await sleep(1500);
    await sup.evaluate(() => { window.__invokes = []; });
    await sendApi(abid, { recipient_id: SUPER_ID, body: 'dot-please' });
    await sup.waitForFunction(() => (window.__invokes || []).some(i => i[0] === 'set_unread' && i[1] && i[1].count > 0), null, { timeout: 20000 }).catch(() => {});
    const set = (await invokes(sup)).filter(i => i[0] === 'set_unread');
    const lit = set.filter(i => i[1].count > 0);
    check('B1. the app is asked to light the taskbar dot with the unread count', lit.length > 0, { calls: set.slice(-3) });

    // Reading the chat lowers it; reading everything clears it.
    const peak = (await invokes(sup)).filter(i => i[0] === 'set_unread').slice(-1)[0][1].count;
    for (const n of ['Abid Hussain', 'Mujeeb Rahman', 'Raja Khuram', 'Ubaid Dogar', 'Umair Sajid', 'CFPB Screenshots', 'Credit Repair CFPB']) {
      await open(sup, n).catch(() => {}); await sleep(900);
    }
    await sup.waitForFunction(() => (window.__invokes || []).slice(-1)[0]?.[1]?.count === 0, null, { timeout: 20000 }).catch(() => {});
    const last = (await invokes(sup)).filter(i => i[0] === 'set_unread').slice(-1)[0];
    check('B2. the dot follows the real unread total and clears when caught up', last && last[1].count === 0 && peak > 0, { peak, last });
    check('B3. native toast used in the app (not a browser notification)', (await notifs(sup)).every(n => n.native), await notifs(sup));
    await sup.ctx.close();
  }

  check('Z. no page errors', !abid.errs.length, abid.errs);
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });

````

### tests/Browser/TeamChat/phase5.cjs

````text
// Phase 5 browser checks — groups, mentions, pins & membership.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
const only = process.argv[2] ? process.argv[2].split(',') : null;
const want = id => !only || only.includes(id);
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }

async function login(browser, email) {
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 } });
  const p = await ctx.newPage(); p.errs = []; p.on('pageerror', e => p.errs.push(e.message));
  await p.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await p.fill('#email', email); await p.fill('#password', 'password123');
  await Promise.all([p.waitForURL(/team-messages/, { timeout: 30000 }), p.click('.submit')]);
  await p.waitForSelector('#tcContacts'); p.ctx = ctx; return p;
}
const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
const rowNames = p => p.evaluate(() => [...document.querySelectorAll('a.tc-contact .tc-c-name')].map(e => e.textContent.trim()));
const captureToasts = p => p.evaluate(() => { window.__toasts = []; const o = window.apexToast; window.apexToast = function (t) { window.__toasts.push(String(t)); if (o) return o.apply(this, arguments); }; });
const toasts = p => p.evaluate(() => window.__toasts || []);
const sendApi = (p, f) => p.evaluate(async f => { const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); for (const k in f) fd.append(k, f[k]); const r = await fetch('/admin/team-messages', { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }); const j = await r.json(); if (!j.ok) throw new Error(JSON.stringify(j)); return j; }, f);
// Open the 3-dots menu of the message whose text matches, then click an action.
async function msgAction(p, text, act) {
  await p.evaluate(t => {
    const el = [...document.querySelectorAll('#tcMessages .tc-msg')].filter(m => (m.querySelector('.tc-text')?.textContent || '').trim() === t).pop();
    el.querySelector('.tc-dots').click();
  }, text);
  await p.click(`#tcMenu [data-act="${act}"]`);
}

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  const sup = await login(browser, 'super@apex.test');
  const abid = await login(browser, 'va0@apex.test');
  await sup.goto(CHAT, { waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcContacts');
  await captureToasts(sup);

  // ---- C. Create group while a group chat is open ---------------------------------------
  if (want('C')) {
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots');   // a GROUP is open
    await sup.click('#tcNewGroup');
    await sup.fill('#tcNewGroupName', 'Made While A Group Was Open');
    await sup.click('#tcGroupModal .tc-group-members input >> nth=0');
    await sup.click('#tcGroupCreate');
    await head(sup, 'Made While A Group Was Open', 20000).catch(() => {});
    const st = await sup.evaluate(() => ({ header: document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim(), url: location.search }));
    check('C1. "Create group" works while another group chat is open', st.header === 'Made While A Group Was Open' && /c=\d+/.test(st.url), st);
    check('C2. the new group is in the sidebar', (await rowNames(sup)).includes('Made While A Group Was Open'));
    // The open group's header name is untouched by the modal's field.
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots');
    await sup.click('#tcNewGroup');
    const boxes = await sup.evaluate(() => ({ modalInput: document.querySelector('#tcGroupModal #tcNewGroupName')?.value, header: document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() }));
    check('C3. the modal field is separate from the open group\'s name', boxes.modalInput === '' && boxes.header === 'CFPB Screenshots', boxes);
    await sup.click('#tcGroupClose');
  }

  // ---- M. Editing keeps mentions ---------------------------------------------------------
  if (want('M')) {
    await open(sup, 'CFPB Screenshots'); await head(sup, 'CFPB Screenshots');
    await sup.fill('#tcInput', 'ping @Abid Hussain');
    // pick the mention from the autocomplete so it is sent as an id
    await sup.evaluate(() => { const i = document.getElementById('tcInput'); i.value = 'ping @Abid'; i.dispatchEvent(new Event('input')); });
    await sup.waitForSelector('#tcMentionPop:not([hidden])', { timeout: 8000 }).catch(() => {});
    await sup.click('#tcMentionPop .tc-mention-opt >> nth=0').catch(() => {});
    await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-mention')].length > 0, null, { timeout: 15000 });
    const before = await sup.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg')].pop().querySelector('.tc-text').textContent.trim());

    await msgAction(sup, before, 'edit');
    await sup.evaluate(() => { const i = document.getElementById('tcInput'); i.value = i.value + ' today'; });
    await sup.press('#tcInput', 'Enter');
    await sleep(1500);
    const after = await sup.evaluate(() => {
      const el = [...document.querySelectorAll('#tcMessages .tc-msg')].pop();
      return { text: el.querySelector('.tc-text').textContent.trim(), mentions: [...el.querySelectorAll('.tc-mention')].map(m => m.textContent.trim()) };
    });
    check('M1. an edit keeps the @mention highlighted and recorded', /today$/.test(after.text) && after.mentions.length === 1, after);

    // Abid still has the mention badge on that group.
    await abid.goto(CHAT, { waitUntil: 'domcontentloaded' }); await abid.waitForSelector('#tcContacts');
    const badge = await abid.evaluate(() => {
      const r = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'CFPB Screenshots');
      return !!r?.querySelector('.tc-mention-badge');
    });
    check('M2. the mention still counts for the mentioned VA', badge);
  }

  // ---- P. Pins: deleting a pinned message clears the pin -----------------------------------
  if (want('P')) {
    await open(sup, 'Mujeeb Rahman'); await head(sup, 'Mujeeb Rahman');
    await sup.fill('#tcInput', 'pin-then-delete'); await sup.press('#tcInput', 'Enter');
    await sup.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'pin-then-delete'), null, { timeout: 15000 });
    await msgAction(sup, 'pin-then-delete', 'pin');
    await sup.waitForSelector('#tcPinned', { timeout: 15000 });
    await msgAction(sup, 'pin-then-delete', 'delete').catch(async () => {
      await sup.evaluate(() => { const el = [...document.querySelectorAll('#tcMessages .tc-msg')].pop(); el.querySelector('.tc-dots').click(); });
      await sup.click('#tcMenu [data-act="delete"]');
    });
    await sup.click('#tcDeleteModal button:has-text("Delete for everyone")').catch(async () => { await sup.click('#tcDeleteModal .tc-confirm-row button >> nth=-1'); });
    await sleep(2500);
    const st = await sup.evaluate(() => ({ bar: !!document.getElementById('tcPinned'), icons: document.querySelectorAll('#tcMessages .tc-pin-ic').length, attachmentGhost: (document.getElementById('tcPinned')?.textContent || '').includes('Attachment') }));
    check('P1. deleting a pinned message removes the pin (no "📎 Attachment" ghost)', !st.bar && st.icons === 0 && !st.attachmentGhost, st);
    // Reload: the pin stays gone and nothing is left to fail on unpin.
    await sup.reload({ waitUntil: 'domcontentloaded' }); await sup.waitForSelector('#tcMessages'); await sleep(600);
    check('P2. still gone after a reload', !(await sup.$('#tcPinned')));
  }

  // ---- R. Removed from a group: a clear state, not silence ---------------------------------
  if (want('R')) {
    const gid = await sup.evaluate(async () => {
      const abidRow = [...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Abid Hussain');
      const r = await fetch('/admin/team-messages/group', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ name: 'Kick Test', members: [Number(abidRow.dataset.peer)] }) });
      return (await r.json()).conversation_id;
    });
    const abidId = await sup.evaluate(() => Number([...document.querySelectorAll('a.tc-contact')].find(a => a.querySelector('.tc-c-name')?.textContent.trim() === 'Abid Hussain').dataset.peer));
    await sendApi(sup, { conversation_id: gid, body: 'members only' });

    await abid.goto(CHAT + '&c=' + gid, { waitUntil: 'domcontentloaded' }).catch(() => {});
    await abid.goto(B + '/admin/team-messages?c=' + gid + '&standalone=1', { waitUntil: 'domcontentloaded' });
    await abid.waitForSelector('#tcMessages'); await captureToasts(abid); await sleep(1000);
    const sawIt = (await texts(abid)).includes('members only');

    // Super removes Abid while his chat is open.
    await sup.evaluate(async ([gid, abidId]) => {
      const fd = new FormData(); fd.append('_token', document.querySelector('meta[name="csrf-token"]').content); fd.append('_method', 'DELETE');
      await fetch('/admin/team-messages/group/' + gid + '/members/' + abidId, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    }, [gid, abidId]);

    await abid.waitForFunction(() => (window.__toasts || []).some(t => /no longer/i.test(t)), null, { timeout: 20000 }).catch(() => {});
    await sleep(1200);
    const st = await abid.evaluate(() => ({
      toasts: window.__toasts || [],
      notice: document.querySelector('#tcMessages .tc-thread-empty')?.textContent.trim() || '',
      rowGone: ![...document.querySelectorAll('a.tc-contact .tc-c-name')].some(e => e.textContent.trim() === 'Kick Test'),
      composerOff: document.getElementById('tcInput').disabled,
      oldMessagesGone: ![...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'members only'),
    }));
    check('R1. removal shows a plain notice, clears the chat and the row', sawIt && /no longer/i.test(st.notice) && st.rowGone && st.composerOff && st.oldMessagesGone && st.toasts.some(t => /no longer/i.test(t)), st);
    check('R2. no raw model error text anywhere', !JSON.stringify(st).includes('No query results'), st.toasts);

    // Reopening that group from a stale cache/URL doesn't show its content.
    await abid.goto(B + '/admin/team-messages?c=' + gid + '&standalone=1', { waitUntil: 'domcontentloaded' });
    await abid.waitForSelector('#tcContacts'); await sleep(800);
    const after = await abid.evaluate(() => ({ shown: [...document.querySelectorAll('#tcMessages .tc-text')].map(e => e.textContent.trim()), header: document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() || null }));
    check('R3. the removed group cannot be reopened from its URL', !after.shown.includes('members only'), after);
  }

  check('Z. no page errors', !sup.errs.length && !abid.errs.length, { sup: sup.errs, abid: abid.errs });
  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });

````

### tests/Browser/TeamChat/phase6.cjs

````text
// Shared-PC privacy checks — one browser profile, two VAs one after the other.
const path = require('path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const B = process.env.TEST_BASE_URL || 'http://127.0.0.1:8932';
if (!['127.0.0.1', 'localhost'].includes(new URL(B).hostname)) throw Error('Tests require a local disposable server');
const CHAT = B + '/admin/team-messages?standalone=1';
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36 ApexDesktop/1.0';
const sleep = ms => new Promise(r => setTimeout(r, ms));
const results = [];
function check(name, ok, detail) { results.push({ name, ok: !!ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail !== undefined ? '  ' + JSON.stringify(detail) : '')); }

const row = n => `a.tc-contact:has(.tc-c-name:text-is("${n}"))`;
const open = (p, n) => p.click(row(n));
const head = (p, n, t = 15000) => p.waitForFunction(x => document.querySelector('.tc-thread-head .tc-th-name')?.textContent.trim() === x, n, { timeout: t });
const texts = p => p.evaluate(() => [...document.querySelectorAll('#tcMessages .tc-msg .tc-text')].map(e => e.textContent.trim()));
// Everything this browser profile has stored for the chat.
const localState = p => p.evaluate(async () => {
  const ls = Object.keys(localStorage).map(k => k + ' = ' + String(localStorage.getItem(k)).slice(0, 120));
  const ss = Object.keys(sessionStorage).map(k => k + ' = ' + String(sessionStorage.getItem(k)).slice(0, 120));
  let idb = [];
  try {
    const db = await new Promise(res => { const r = indexedDB.open('apexChat', 1); r.onsuccess = () => res(r.result); r.onerror = () => res(null); r.onupgradeneeded = () => { try { r.result.createObjectStore('threads'); } catch (e) {} }; });
    if (db) {
      idb = await new Promise(res => {
        const out = []; const st = db.transaction('threads', 'readonly').objectStore('threads'); const c = st.openCursor();
        c.onsuccess = () => { const cur = c.result; if (!cur) return res(out); out.push({ key: String(cur.key), uid: cur.value && cur.value.uid, ts: cur.value && cur.value.ts, body: JSON.stringify(cur.value && cur.value.data).slice(0, 400) }); cur.continue(); };
        c.onerror = () => res(out);
      });
      db.close();
    }
  } catch (e) {}
  return { ls, ss, idb };
});

async function signIn(page, email) {
  await page.goto(B + '/admin/chat-login', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', email); await page.fill('#password', 'password123');
  await Promise.all([page.waitForURL(/team-messages/, { timeout: 30000 }), page.click('.submit')]);
  await page.waitForSelector('#tcContacts');
}
async function signOut(page) {
  await page.click('.tc-logout');
  await page.waitForURL(/chat-login/, { timeout: 30000 });
  await page.waitForSelector('#email');
}

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || chromium.executablePath() });
  // ONE browser profile, as on a shared PC: same context for both VAs.
  const ctx = await browser.newContext({ userAgent: UA, viewport: { width: 1300, height: 860 } });
  const page = await ctx.newPage(); const errs = []; page.on('pageerror', e => errs.push(e.message));

  // ---- VA #1 uses the chat: opens chats, writes a private note and a draft ----------------
  await signIn(page, 'va0@apex.test');           // Abid
  await open(page, 'Umair Arshad'); await head(page, 'Umair Arshad'); await sleep(1200);
  await page.fill('#tcInput', 'abid-private-draft');
  await page.dispatchEvent('#tcInput', 'input');
  await page.click('a.tc-self-row'); await head(page, 'Notes (You)', 20000).catch(() => {});
  await page.fill('#tcInput', 'abid-secret-note'); await page.press('#tcInput', 'Enter');
  await page.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'abid-secret-note'), null, { timeout: 15000 });
  // Leave and re-open the notes so the SAVED copy really contains the private note.
  await open(page, 'Umair Arshad'); await head(page, 'Umair Arshad'); await sleep(800);
  await page.click('a.tc-self-row'); await head(page, 'Notes (You)', 20000).catch(() => {});
  await page.waitForFunction(() => [...document.querySelectorAll('#tcMessages .tc-text')].some(e => e.textContent.trim() === 'abid-secret-note'), null, { timeout: 15000 }).catch(() => {});
  await sleep(1200);
  await open(page, 'Umair Arshad'); await head(page, 'Umair Arshad'); await sleep(1500);   // snapshots saved
  const before = await localState(page);
  const abidStored = JSON.stringify(before);
  check('P0. VA #1 does have local state while signed in', before.idb.length > 0 && before.ls.length > 0, { idb: before.idb.length, ls: before.ls.length });

  // ---- VA #1 signs out --------------------------------------------------------------------
  await signOut(page);
  await sleep(800);
  const cleared = await localState(page);
  check('P1. signing out leaves no saved chats, drafts or notification data',
    cleared.idb.length === 0 && !cleared.ls.some(k => /^(apex-team-|tc-)/.test(k)) && cleared.ss.length === 0, cleared);
  check('P2. nothing of VA #1\'s content remains on the PC',
    !JSON.stringify(cleared).includes('abid-secret-note') && !JSON.stringify(cleared).includes('abid-private-draft'), cleared);

  // ---- VA #2 signs in on the same PC ------------------------------------------------------
  await signIn(page, 'va1@apex.test');           // Mujeeb
  const seen = [];
  page.on('response', () => {});
  await open(page, 'Umair Arshad');
  // Watch the thread continuously for any flash of the other VA's content.
  for (let i = 0; i < 20; i++) { seen.push(...(await texts(page))); await sleep(150); }
  await head(page, 'Umair Arshad');
  await page.click('a.tc-self-row'); await head(page, 'Notes (You)', 20000).catch(() => {});
  for (let i = 0; i < 20; i++) { seen.push(...(await texts(page))); await sleep(150); }
  const notes = await texts(page);
  const draftBox = await page.inputValue('#tcInput');

  check('P3. VA #2 never sees VA #1\'s private notes (not even for a moment)', !seen.includes('abid-secret-note') && !notes.includes('abid-secret-note'), { notes });
  check('P4. VA #2 never sees VA #1\'s messages or draft', !seen.some(t => /abid-private-draft/.test(t)) && draftBox === '', { draftBox });

  // ---- A snapshot from another user / an old one is never painted --------------------------
  // Plant one of each directly in the browser's store, the way a previous version left them.
  const convId = await page.evaluate(() => document.getElementById('tcMessages')?.dataset.conversation || '');
  await page.evaluate(async ({ me, convId }) => {
    const db = await new Promise(res => { const r = indexedDB.open('apexChat', 1); r.onsuccess = () => res(r.result); r.onerror = () => res(null); });
    const put = (key, val) => new Promise(res => { const t = db.transaction('threads', 'readwrite').objectStore('threads').put(val, key); t.onsuccess = t.onerror = () => res(); });
    const fake = { conversation_id: convId, title: 'Umair Arshad', messages: [{ id: 999999, body: 'LEAKED-FROM-OTHER-USER', mine: false, at: '9:00 AM', sender: { name: 'X', mono: 'X', color: '#000' } }], pinned: [], readUpTo: 0 };
    await put('u999|c=' + convId, { data: fake, ts: Date.now(), uid: '999' });                    // another user's
    await put('u' + me + '|with=999', { data: fake, ts: Date.now() - (3 * 24 * 60 * 60 * 1000), uid: String(me) });  // mine, 3 days old
    await put('legacy|c=' + convId, { data: fake, ts: Date.now() });                               // pre-update, unstamped
    db.close();
  }, { me: await page.evaluate(() => window.__ME_ID_FOR_TEST || document.querySelector('meta[name="csrf-token"]') ? null : null) || (await page.evaluate(() => { const m = /var ME_ID = "(\d+)"/.exec(document.documentElement.innerHTML); return m ? m[1] : ''; })), convId });

  await page.goto(CHAT, { waitUntil: 'domcontentloaded' }); await page.waitForSelector('#tcContacts');
  await open(page, 'Umair Arshad');
  const flashes = [];
  for (let i = 0; i < 20; i++) { flashes.push(...(await texts(page))); await sleep(150); }
  check('P5. a saved chat belonging to another user is never shown', !flashes.includes('LEAKED-FROM-OTHER-USER'), { sample: flashes.slice(0, 3) });

  await sleep(1500);
  const after = await localState(page);
  check('P6. foreign and stale saved chats are swept off the PC',
    after.idb.every(r => String(r.uid) === String(after.idb[0] && after.idb[0].uid)) && !JSON.stringify(after.idb).includes('LEAKED-FROM-OTHER-USER'), after.idb.map(r => ({ key: r.key, uid: r.uid })));

  check('P7. every stored key is tied to a user', after.ls.concat(after.ss).every(k => /:u\d+|apex-known-new-clients/.test(k)), after.ls.concat(after.ss));
  check('Z. no page errors', !errs.length, errs);

  await browser.close();
  const failed = results.filter(r => !r.ok);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error('SCRIPT ERROR', e); process.exit(2); });

````

### tests/Browser/TeamChat/run.cjs

````text
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
if (!suites.length) suites.push('phase1', 'phase1b', 'phase2', 'phase3', 'phase5', 'phase6', 'privacy-extra');
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
            TEST_BASE_URL: 'http://127.0.0.1:' + port, APP_URL: 'http://127.0.0.1:' + port };
        console.log('\nRUN ' + suite + ' (isolated SQLite: ' + db + ')');
        command(['artisan', 'migrate', '--force'], env);
        command(['artisan', 'tinker', '--execute', "require base_path('tests/Browser/TeamChat/seed.php');"], env);
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

````

### tests/Browser/TeamChat/seed.php

````text
<?php
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\TeamMessage;

$super = Admin::create(['email'=>'super@apex.test','password'=>bcrypt('password123'),'full_name'=>'Umair Arshad']);
$super->role='super'; $super->save();

$names = ['Abid Hussain','Mujeeb Rahman','Raja Khuram','Ubaid Dogar','Umair Sajid'];
$firstConv = null;
foreach ($names as $i=>$n){
    $va = Admin::create(['email'=>'va'.$i.'@apex.test','password'=>bcrypt('password123'),'full_name'=>$n]);
    $va->role='va'; $va->parent_admin_id=$super->id; $va->save();
    $c = Conversation::create(['type'=>'dm','data_owner_id'=>$super->id,'dm_key'=>'dm:'.min($super->id,$va->id).'-'.max($super->id,$va->id)]);
    $c->participants()->createMany([
        ['admin_id'=>$super->id,'role'=>'member'],
        ['admin_id'=>$va->id,'role'=>'member'],
    ]);
    $last=null;
    for($m=0;$m<12;$m++){
        $last = TeamMessage::create(['conversation_id'=>$c->id,'type'=>'text','sender_id'=>($m%2?$super->id:$va->id),'body'=>"Message {$m} with {$n}"]);
    }
    $c->update(['last_message_id'=>$last->id]);
    if(!$firstConv) $firstConv=$c;
}

// A group too (to test the fallback-to-navigation path).
$g = Conversation::create(['type'=>'group','name'=>'CFPB Screenshots','data_owner_id'=>$super->id,'icon'=>'🔥']);
$g->participants()->createMany([
    ['admin_id'=>$super->id,'role'=>'admin'],
    ['admin_id'=>Admin::where('email','va0@apex.test')->first()->id,'role'=>'member'],
    ['admin_id'=>Admin::where('email','va1@apex.test')->first()->id,'role'=>'member'],
]);
$gm = TeamMessage::create(['conversation_id'=>$g->id,'type'=>'text','sender_id'=>$super->id,'body'=>'Group hello']);
$g->update(['last_message_id'=>$gm->id]);

echo "SEEDED super=super@apex.test pass=password123 firstConv={$firstConv->id} group={$g->id}\n";

// Second group (for group→group swap tests).
$g2 = \App\Models\Conversation::create(['type'=>'group','name'=>'Credit Repair CFPB','data_owner_id'=>$super->id,'icon'=>'💬']);
$g2->participants()->createMany([
    ['admin_id'=>$super->id,'role'=>'admin'],
    ['admin_id'=>\App\Models\Admin::where('email','va2@apex.test')->first()->id,'role'=>'member'],
    ['admin_id'=>\App\Models\Admin::where('email','va3@apex.test')->first()->id,'role'=>'member'],
    ['admin_id'=>\App\Models\Admin::where('email','va4@apex.test')->first()->id,'role'=>'member'],
]);
$g2m = \App\Models\TeamMessage::create(['conversation_id'=>$g2->id,'type'=>'text','sender_id'=>\App\Models\Admin::where('email','va2@apex.test')->first()->id,'body'=>'Second group hello']);
$g2->update(['last_message_id'=>$g2m->id]);
echo "GROUP2 id={$g2->id}\n";

// A VA with no conversations at all (like a newly added teammate).
$san = \App\Models\Admin::create(['email'=>'va5@apex.test','password'=>bcrypt('password123'),'full_name'=>'Sanwal Khan']);
$san->role='va'; $san->parent_admin_id=$super->id; $san->save();
echo "SANWAL id={$san->id}\n";

````

- Browser run `phase1`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-cT10ui`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase1b`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-SVAO0V`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase2`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-IgeY1Z`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase3`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-PhbkEM`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase5`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-QIxPw5`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase6`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-ieWLZR`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase7`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-RMwkBj`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.

- Browser run `phase8`: exit 1. Disposable database/log directory: `C:\Users\DELL\AppData\Local\Temp\apex-codex-privacy-JTZegy`. Runner migrated a NEW SQLite file, seeded synthetic accounts, started its own localhost PHP process, ran the suite, and stopped only that process.
