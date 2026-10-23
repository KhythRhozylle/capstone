# Performance investigation and verification

The running local server uses Symfony 8.1, PHP CGI 8.4.12 and development profiling.
Changes preserve the existing Twig/Stimulus/Turbo interface, validation, access scopes,
CSRF checks, writes and booking locks.

## Findings and changes

- OPcache was installed but absent from the loaded PHP extensions. Added project-local
  `php.ini` settings, loaded by Symfony CLI, to cache compiled PHP scripts. Timestamp
  checks remain enabled so edits take effect. Restarted the local server to apply them.
  This does not modify the global PHP installation. Other web servers must load equivalent
  settings through their own PHP configuration. Avoid loading the extension twice if a
  different machine already enables it globally.
- Homepage staff lookup hydrated every user, including all students and their profiles,
  before filtering roles in PHP. Role candidates are now filtered in SQL before hydration;
  exact role checks and the implicit ROLE_USER behavior remain intact.
- Dashboard statistics issued four count queries per campus and one count query per enum
  case. Replaced those loops with grouped queries, preserving zero counts, ordering and
  live results. Resolution-time statistics now hydrate timestamps instead of complete
  concern entities. The regression test enforces at most 24 queries for eight campuses.
- Appointment, concern, service and document tables loaded related entities while rendering
  rows. Fetch joins now preload campus, office and (for staff tables) student/profile data.
  Tests verify that accessing the displayed relations produces zero additional queries.
- Notification badges repeated the server-rendered count immediately on connection and on
  rapid focus events. They now wait for the existing 15-second refresh interval, throttle
  repeated focus events, retain the last count on errors and abort stalled requests.
- Specialty controllers were eagerly downloaded on unrelated pages. Seven controllers now
  use the installed StimulusBundle lazy-loading mechanism, including the 24 KB appointment
  calendar. The existing AssetMapper fingerprinting/browser cache and Turbo navigation remain.
- Office-hours selection bypassed Turbo via native `form.submit()`. It now uses
  `requestSubmit()` to retain the normal submit event and Turbo handling.
- A shared Turbo submission guard prevents Enter or a second submit button from issuing a
  duplicate request during an active submission. It resets on completion, failure and
  navigation, and communicates processing with `aria-busy` without altering button labels.
- Slot requests could finish after the date was cleared, overwriting the newer message.
  Requests are now cancelled before validating the new selection, stale responses are
  ignored, HTTP errors are checked, and requests time out with the existing recovery message.
  Search and calendar updates also have timeouts and release their busy state on failure.
  Calendar filters are cleared before reconnecting to avoid duplicate options on Turbo back navigation.

## Local measurements

Same homepage URL, local Symfony server, development mode, no login cookie:

| Measurement | Total response time |
| --- | --- |
| Before OPcache | 4.728 s |
| First request after server restart / cache warmup | 22.347 s |
| Subsequent warm request | 1.075 s |
| Three further warm requests | 1.372 s, 0.946 s, 0.864 s |

The median of the final three warm requests is about 80% below the original sample.
These are local observations, not a production benchmark or a browser rendering measurement.
Cache rebuilds and cold development starts remain expensive. Production should use
`APP_ENV=prod`, `APP_DEBUG=0`, warmed Symfony caches, compiled AssetMapper assets and
an optimized Composer autoloader; do not switch a development session blindly to production.

## Validation

- `php bin/phpunit` exercises HTTP navigation, rendered forms, CSRF/access controls,
  booking, notifications and service behavior. `PerformanceTest` uses an isolated in-memory SQLite database.
- `node --test tests/JavaScript/*.test.mjs` covers controller behavior, stale responses,
  timeout recovery, notification throttling and submission guard cleanup.
- `symfony console lint:container`, `lint:twig templates/` and `lint:yaml config/` validate wiring and syntax.
- Public/home images already use lazy loading below the fold; the hero retains high priority.
  No image artwork, layout or styling was changed.
- Mail is explicitly routed synchronously in the existing Messenger configuration. Moving
  real SMTP delivery to the existing async transport requires a provisioned queue and a
  supervised consumer. No emails were moved to an unattended queue during this change.

## Test results and remaining limitations

- All three new PHP performance tests pass (164 assertions).
- The complete PHP run finished: 272 tests, 5,233 assertions, 39 errors and 22 failures.
  It is not a clean baseline. Confirmed examples include tests looking up retired
  `osas@norsu.edu.ph` / `discipline@norsu.edu.ph` accounts, navigation label expectations,
  and a previous image path. Those fixture and design expectations were not changed here.
- Container, 105 Twig templates and 25 YAML files passed linting.
- The homepage returns HTTP 200 against the existing MySQL database after role filtering.
  The generated Stimulus controller map exposes the specialty controllers through dynamic imports.
- No automated real-browser runner is installed in this repository. HTTP/kernel and JavaScript
  controller tests do not substitute for a visual browser audit or production load testing.

Symfony documents project-local PHP overrides in its [CLI documentation](https://symfony.com/doc/current/setup/symfony_cli.html).
PHP documents loading OPcache in its [installation documentation](https://www.php.net/manual/en/opcache.installation.php).
