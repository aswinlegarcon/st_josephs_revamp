# Monitoring and Health Checks

> **What you'll learn:** why **you** will never be the first to notice the site is broken,
> the three layers of monitoring (uptime, health, logs), why a page can return "200 OK" while
> the database is dead, and then — check by check — exactly what our `/admin/health.php`
> endpoint tests, which checks turn the response red, and why two of them deliberately do not.
>
> **Prerequisites:** basic PHP (`if`, `function`, `try`/`catch`, arrays). **No** operations
> or hosting background needed; every term is explained on first use.
>
> **Where it lives in our code:** `public_html/admin/health.php` is the whole endpoint (72
> lines); `public_html/admin/index.php:45-73` draws the dashboard health strip;
> `src/Core/Db.php:62-70` turns a dead database into an HTTP 503; `DEPLOY.md` §5 and §8 are
> the runbook.

---

## 1. The one-paragraph version

**Monitoring** means: something automatically watches the site and tells a human when it
breaks. Ours has three layers. An outside robot (UptimeRobot) fetches two URLs every five
minutes. One is our **health endpoint** — a small PHP file that tests the things that matter
(is the database answering, is the disk full, are the images there) and returns `HTTP 503`
if any of them fail. The third layer is human: the admin dashboard shows a strip with disk
space, image count and backup age. That is all — no metrics dashboards, no on-call rota. For
one school website with one part-time maintainer, that is the right amount.

---

## 2. The problem this solves

The uncomfortable truth about running a website: **you will not notice it is down. A parent
will.** You are not on the site at 6 a.m.; a parent is. They open `stjosephsondipudur.com`
to check the exam timetable, get a blank page, and tell another parent. Eventually someone
tells the principal, and the principal calls you. That chain takes hours, and the only people
who knew were the ones who could not fix it. Monitoring collapses it into: *the server broke
→ an e-mail arrived four minutes later → it was fixed before assembly.*

The second problem is subtler. **"The website loads" is not the same as "the website
works."** A PHP page can render its header, navigation and footer perfectly while every piece
of content is missing, because the database died. The browser sees a page; a naive checker
sees success; the parent sees an empty page. So the check must go deeper than "did a page
come back".

---

## 3. How it works in general

Monitoring is described in three layers. Learn these words; they are the whole vocabulary.

| Layer | Plain definition | The question it answers |
|---|---|---|
| **Uptime** | Something *outside* your server fetches a URL on a timer and records whether it got an answer. | *Is it answering at all?* |
| **Health** | The server runs its own self-test — database reachable, disk not full, files present — and reports pass/fail. | *Is it answering **correctly**?* |
| **Logs / alerts** | A written record of what happened, plus a rule that notifies a human. | *What actually went wrong?* |

Every HTTP response carries a three-digit **status code** saying how it went. You need four:
**200 OK** (here is your page), **404 Not Found** (no such thing here), **500 Internal Server
Error** (the code crashed), and **503 Service Unavailable** (the server is up but cannot
serve — a dependency is down). 503 is the polite "I am sick" code, and the one monitoring
robots understand best.

**Why 200 lies.** Imagine a page that fetches news items inside a `try`/`catch` so it never
crashes. The database is dead, the `catch` runs, the page prints an empty list and finishes
normally. PHP has no reason to send anything but 200, so your uptime robot stays happy while
the site shows nothing. A **health check** fixes that by deliberately touching the
dependencies and *changing the status code* when one is broken — a URL whose status code is
a truthful summary of the system.

---

## 4. How we use it — every place in this codebase

### 4.1 The endpoint: `public_html/admin/health.php`

**Who is allowed in** (`public_html/admin/health.php:11-18`):

```php
// Access: a matching token, OR an authenticated admin.
$token = (string)(sj_config()['health_token'] ?? '');
$given = (string)($_GET['token'] ?? '');
$allowed = (is_admin()) || ($token !== '' && hash_equals($token, $given));
if (!$allowed) {
    http_response_code(404); // don't reveal the endpoint to strangers
    exit;
}
```

Three things to notice. A **token** is a long random string acting as a password for a URL;
ours is `'health_token'` in `config/config.php` (see `config/config.sample.php:34`), a file
**above the webroot** and gitignored, so the real value is never in git. `hash_equals()`
compares in **constant time** — the same duration however many characters match, so nobody
can guess the token one character at a time by timing replies. And a wrong token gets
**404**, not 403: "forbidden" would confirm something exists here, while 404 tells a
stranger nothing.

Each check appends `['ok' => bool]` plus an optional human-readable `value` to a `$checks`
array via a small helper (`health.php:20-23`). **Every check the file performs**, in order.
"Gates?" means: does a failure here turn the whole response red (HTTP 503)?

| Check | Line | What it tests | Gates? |
|---|---|---|---|
| `php_version` | `health.php:25` | PHP is 8.1 or newer; reports the exact version | ✅ yes |
| `ext_gd` | `health.php:26` | The **GD** image library is loaded (we resize uploads with it) | ✅ yes |
| `ext_pdo_mysql` | `health.php:27` | The MySQL database driver is loaded | ✅ yes |
| `ext_exif` | `health.php:28` | The EXIF extension is loaded (reads photo orientation) | ✅ yes |
| `db_connect` | `health.php:30-31` | Runs `SELECT 1` — the database really answers | ✅ yes |
| `schema` | `health.php:33-36` | `SELECT COUNT(*) FROM admin_users` > 0 — tables exist and are populated | ✅ yes |
| `media_writable` | `health.php:38-39` | `public_html/media` exists and is writable, so uploads work | ✅ yes |
| `opcache` | `health.php:41` | PHP's bytecode cache is on (a speed setting) | ✅ yes |
| `https` | `health.php:43-44` | The request arrived over HTTPS | ❌ **no** |
| `disk_free` | `health.php:47-49` | More than **200 MB** free on disk | ✅ yes |
| `images` | `health.php:50-53` | `SELECT COUNT(*) FROM images` > 0 — content is present | ✅ yes |
| `sitemap` | `health.php:54` | `public_html/sitemap.xml` exists (search engines need it) | ✅ yes |
| `backup_age` | `health.php:56-61` | Hours since the newest `db-*.sql.gz` backup file | ❌ **no** |

The gating rule is one expression (`health.php:63-66`):

```php
$gating = $checks;
unset($gating['https'], $gating['backup_age']);
$allOk = array_reduce($gating, static fn($c, $x) => $c && $x['ok'], true);
http_response_code($allOk ? 200 : 503);
```

Read it as: *everything gates, except these two, which we remove from the list first.*

**Why `https` does not gate.** The dev stack runs on plain `http://localhost:8090`
(`docker-compose.yml:41`). If HTTPS gated, the endpoint would be permanently red on every
developer machine — and a permanently-red check is a check nobody looks at (`health.php:42`).

**Why `backup_age` does not gate — a deliberate decision.** A missing or stale backup is
serious, but it is not an *outage*. If the last backup is 30 hours old the site is still
serving parents perfectly. Gating it would mean an alert fires because a cron job slipped,
and — worse — a real outage would look identical to a late backup, since both show the same
red 503. The code says exactly this at `health.php:55`: *"reported, not gating (dev boxes
and pre-cron prod have none)"*. Backup age is surfaced instead where a human reads it
calmly: the dashboard strip (§4.3). The rule worth memorising: **gate on "cannot serve",
report on "should look into".**

### 4.2 The exact response shape

`Content-Type: application/json` and `Cache-Control: no-store` are set at `health.php:8-9`
(`no-store` = no browser or proxy may cache it; a cached health check is a lie).
`sj_admin_headers()` at `health.php:7` adds the admin security headers from
`src/Admin/Auth.php:90-103`. The body is built at `health.php:67-71`. A real dev response,
trimmed:

```json
{
    "ok": true,
    "asset_ver": "20260809.2",
    "checks": {
        "php_version": { "ok": true,  "value": "8.3.33" },
        "ext_gd":      { "ok": true },
        "db_connect":  { "ok": true },
        "schema":      { "ok": true,  "value": "admin_users=1" },
        "https":       { "ok": false },
        "disk_free":   { "ok": true,  "value": "37.43 GB" },
        "images":      { "ok": true,  "value": "478" },
        "backup_age":  { "ok": true,  "value": "5.2 h" }
    }
}
```

Note `"https": { "ok": false }` sitting happily beside `"ok": true` — the non-gating rule at
work. **Is it safe to expose?** Be honest, because an outside company's servers fetch this every
five minutes. It **does** disclose the exact PHP version, our asset-version string, the
admin-user count, free disk in GB, the image count and backup age in hours. It does **not**
disclose database credentials, hostnames, filesystem paths, table contents, stack traces or
session data: failures are fixed strings — `'unreachable'` (`health.php:31`), `'missing
tables'` (`health.php:36`) — never the raw exception text.

The PHP version alone is mildly useful to someone hunting known bugs, which is exactly why
the endpoint is token-gated and 404s everyone else rather than being public. The honest
trade-off: the token travels in the **query string**, so it can land in server and proxy
access logs. It is a read-only status token, not a login — but treat it as a secret, rotate
it if a log is ever shared, and never paste a real one into a document or a ticket.

### 4.3 The dashboard health strip: `public_html/admin/index.php`

The editor is not going to `curl` a JSON endpoint, so the dashboard shows three tiles
(`public_html/admin/index.php:45-58`, rendered at `:65-73`):

| Tile | Value shown | Turns red when |
|---|---|---|
| 💽 Disk free | GB to one decimal (`index.php:47-49`) | 200 MB or less free — the same threshold as the endpoint |
| 🖼️ Images | count from `SELECT COUNT(*) FROM images` (`index.php:50`) | never — the flag is hardcoded `true`; it is a "does that look right?" number |
| 🗄️ Last DB backup | e.g. `5.2 h ago · 47 KB` (`index.php:51-58`) | no backup found, **or** the newest is older than **48 hours** |

Two details worth carrying away. First, the strip is *stricter* than the endpoint: the
endpoint only asks "does any backup exist", the strip asks "is it fresh". That is the
layering again — the loud channel stays quiet, the quiet channel is picky. Second, the strip
shows **status only, never a download link** (`index.php:45`): per `SECURITY.md` SEC-20, a
stolen admin session must not be able to exfiltrate the whole database in one click. Red
styling is one CSS rule, `public_html/admin/assets/panel.css:236`.

### 4.4 How a dead database becomes a 503

Follow the code. `db()` (`src/helpers.php:28-31`) returns `Db::pdo()`. If the *connection
itself* cannot be made, `src/Core/Db.php:62-70` does not throw — it ends the request:

```php
} catch (PDOException $e) {
    if (\PHP_SAPI === 'cli') { … exit(1); }
    \http_response_code(503);
    \header('Content-Type: text/plain; charset=utf-8');
    exit("Database is not reachable. Start the stack with ./run.sh and try again.\n");
}
```

So during a full outage the health URL answers **503 with a short plain-text body**, not
JSON — the `catch` at `health.php:31` only catches failures on a connection that already
opened. Either way the code is 503, which is all the robot reads, and the message names no
host, user or password.

### 4.5 The outage drill

This was actually performed, in Docker, and recorded in `DEPLOY.md:134-136`: *"Outage
behaviour verified in dev: DB stopped → health returned 503 within one request; DB restarted
→ 200."* `docs/learn/08-stage-h.md:392-393` records the same drill. The point of a drill is
not the code — it is proving that the alarm you have never heard actually makes a sound.

### 4.6 External monitoring: the two UptimeRobot URLs

**UptimeRobot** is a free service whose servers fetch your URLs on a timer and e-mail you
when the result changes. `DEPLOY.md:125-132` documents two monitors, free tier, **5-minute
interval**:

| # | URL | Type | Fails when |
|---|---|---|---|
| 1 | `https://stjosephsondipudur.com/` | keyword monitor, expects `St.Joseph` | the site is down **or** returns a blank/error page no longer containing the word |
| 2 | `https://stjosephsondipudur.com/admin/health.php?token=<health_token>` | HTTP monitor | any non-200 response |

The keyword monitor is the clever half: it catches the "200 OK but the page is empty" case
from §2 with no code at all.

Five minutes and e-mail are right for a school: five minutes of undetected downtime is
invisible to parents, the free tier covers it, and e-mail reaches the one maintainer with no
paid paging product. An SMS at 3 a.m. would be theatre — nobody is fixing a school website
at 3 a.m. And the reason an **external** checker is not optional: **a server cannot report
its own death.** If the machine is powered off, the network is cut, or PHP will not start,
nothing is left running to send the e-mail. Only a watcher somewhere else notices silence.

### 4.7 What we deliberately do **not** have

No metrics dashboard (graphs of requests per second). No log aggregation (shipping logs to a
searchable central service). No on-call rotation. PHP errors simply land in the host's
`error_log`, read through mPanel → Error Log (`DEPLOY.md:135-136`). That is proportion, not
laziness: each of those is a system that itself needs maintaining, and for one school
website the honest answer is three cheap layers and nothing more.

---

## 5. Why this is the right approach here

The constraints are fixed and unusually tight: **MilesWeb shared hosting, no SSH, no Docker,
no Composer on the server** (`DEPLOY.md:3-5`), a school budget, and one part-time maintainer
who needs *one unambiguous signal*.

| Alternative | Why not here |
|---|---|
| **Paid APM** (Datadog, New Relic — "Application Performance Monitoring": an agent inside your app reporting traces and metrics) | Needs an agent installed on the server; with no SSH we cannot install one. Monthly cost against a school budget, for graphs nobody has time to read. |
| **The host's own monitoring** | mPanel can tell you the *server* is up. It cannot know our database is unreachable or `media/` went read-only. It watches its machine, not our application. |
| **Cron + e-mail** (a scheduled script that mails you on failure) | Runs *on the box being watched* — if the box dies, so does the watcher. Fine as a supplement, useless as the primary alarm. |
| **Nothing** | The default, and the real status quo of most school sites. It works exactly until it doesn't, and then the principal calls. |

Our stack costs zero, installs nothing on the server, and produces one signal a
non-specialist can act on: an e-mail saying the site is down. `health.php` is 72 lines of
plain PHP that ships with the site and needs no infrastructure of its own.

---

## 6. How this scales

Nothing here strains at 10× traffic — two fetches every five minutes is not load. What grows
is the *number of things that can independently fail*. Add each layer only when a real event
demands it:

| When | Add | Why |
|---|---|---|
| Parents start asking "is it down, or is it me?" | **Status-page publishing** (UptimeRobot's free public status page) | Answers the question without anyone phoning the office. |
| More than one person can fix things | **Alert routing** — different alerts to different people, escalation if unacknowledged | One inbox is a single point of failure made of a human. |
| "It broke and I can't tell why" happens twice | **Log shipping** — errors to a searchable service instead of a file you must open mPanel to read | On shared hosting, reading `error_log` is genuinely painful. |
| Logging in, or the contact form, breaks silently | **Synthetic user journeys** — a scripted robot that logs in and submits a form, not just fetches a page | A page can be 200-green while the one flow that matters is broken. |

**The exact next step, if this site grows:** split the single endpoint into two URLs — a
*liveness* check (is PHP running at all; touches no database; cheap) and a *readiness*
check (the full dependency test we have today). That one split is what lets you tell "the
web server died" apart from "the database died" without reading a log, and it is the
foundation every larger monitoring setup builds on.

---

## 7. Gotchas and mistakes to avoid

- **Alerting on everything until people ignore alerts.** The number-one failure of real
  monitoring: if the alarm cries wolf twice a week, a genuine outage gets swiped away
  half-asleep. Exactly why `backup_age` does not gate (`health.php:55`).
- **A health check so heavy it becomes the outage.** Ten expensive queries called every 30
  seconds is a small denial-of-service attack on yourself. Ours runs three trivial queries
  (`SELECT 1`, two `COUNT(*)`) plus cheap filesystem calls, every five minutes.
- **A check that passes because it never touches the database.** An endpoint that only
  prints `OK` is decoration; it goes green while everything behind it burns. Add a
  dependency, add a check that *actually uses* it.
- **Monitoring from one location only.** If your single checker's network has a bad moment
  you get a false alarm; if a whole region cannot reach your host you get silence. Use a
  service that re-verifies from another location before declaring an outage.
- **Leaking version or path details.** Our response carries the exact PHP version and an
  admin-user count — acceptable *because* it is token-gated and 404s everyone else
  (`health.php:15-18`). Never let a health endpoint become public, and never echo raw
  exception text: it can carry file paths, hostnames and credentials.
- **Forgetting to re-enable a monitor after maintenance.** You pause it for a deploy, the
  deploy runs long, you forget. The site is now unwatched while you *believe* it is watched
  — worse than knowing it is not. Use a timed pause if the tool offers one, and make
  "re-enable monitors" the last line of the deploy checklist.

---

## 8. Try it yourself

> ⚠️ **Only ever do this on the dev stack** (`localhost:8090`). Stopping a database is
> exactly the sort of command that must never be aimed at production.

**1. No token, then with one.** Look up `health_token` in your local `config/config.php`
(gitignored — never paste it anywhere shared):

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8090/admin/health.php  # → 404
curl -s "http://localhost:8090/admin/health.php?token=<your dev health_token>"   # → the JSON
```

Read the JSON: find `"ok": true`, then `"https": { "ok": false }`, and convince yourself why
the overall result is still green. Check every key against the table in §4.1.

**2. Break the database on purpose, then bring it back:**

```bash
docker compose stop db
curl -s -w "\nSTATUS: %{http_code}\n" "http://localhost:8090/admin/health.php?token=<token>"
# → STATUS: 503   ← the alarm; everything above exists to turn this into an e-mail
docker compose start db
```

MySQL needs a few seconds to become ready — `docker-compose.yml:14-19` pings it every 3
seconds with a 30-second start period. Re-run the curl until it returns 200 again.

**3. Look at the human side.** Open `http://localhost:8090/admin/` and find the health strip
above the cards. Compare its three numbers with the JSON you just read — they come from the
same two sources (`disk_free_space()` and a `glob()` over `backups/db-*.sql.gz`).

---

## 9. Where to read more

**In this repo**

- [`../08-stage-h.md`](../08-stage-h.md) — Part 4 (backups) and **Part 5 (monitoring: the
  three layers)**, plus the outage drill as recorded.
- [`../../../DEPLOY.md`](../../../DEPLOY.md) — **§5** the endpoint reference, **§7** backups and
  the restore drill, **§8** the two UptimeRobot monitors.
- [`../../../SECURITY.md`](../../../SECURITY.md) — SEC-20 (why there is no backup-download
  endpoint) and SEC-22 (shared-hosting specifics: permissions, error log, session path).
- [`sessions-and-cookies.md`](sessions-and-cookies.md) — what `is_admin()` does, since
  "already logged in as admin" is the endpoint's second access route.

**Outside**

- MDN, [HTTP response status codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status),
  especially [503 Service Unavailable](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/503).
- Google SRE Book, [*Monitoring Distributed Systems*](https://sre.google/sre-book/monitoring-distributed-systems/) —
  the origin of "alert on symptoms, not causes", and the clearest argument for why noisy
  alerts are worse than none.
- Kubernetes docs, [Liveness, Readiness and Startup Probes](https://kubernetes.io/docs/concepts/configuration/liveness-readiness-startup-probes/) —
  read it for the *liveness vs readiness* distinction in §6, not for Kubernetes itself.
