# Backups and Disaster Recovery

> **What you'll learn:** the two numbers every backup plan must answer before you write a line of code, how our nightly backup script works line by line, why the admin panel deliberately refuses to let you download a backup, and the exact ordered steps to restore this site after a disaster.
>
> **Prerequisites:** you can read basic PHP (`$variable`, `foreach`, functions) and you have typed a command into a terminal. You do **not** need shell scripting, MySQL administration, cron, or Docker. Every term is defined the first time it appears.
>
> **Where it lives in our code:** `database/backup.sh` (the whole script), `DEPLOY.md` §7 (the production runbook — the primary source), `public_html/admin/health.php:55-61` (the `backup_age` check), `public_html/admin/index.php:45-58` (the dashboard health strip), `docker-compose.yml:39` (the dev backups mount), `SECURITY.md` SEC-20, and `docs/learn/08-stage-h.md` Part 4.

---

## 1. The one-paragraph version

A **backup** is a copy of your data, kept where the original disaster cannot reach. This site has two kinds worth copying: the **MySQL database** (every word the school types into the admin panel) and the **`media/` folder** (uploaded photos plus the smaller versions we generate from them). One shell script, `database/backup.sh`, does both. It runs once a night from a scheduled job. It reads the database password by asking PHP to load `config/config.php` — a file that lives *above* the public web folder — so the password never appears in the schedule file. It writes a compressed database dump into `~/backups/`, adds a compressed copy of `media/` on Sundays, deletes anything older than 14 days, and refuses to run if you point it at a folder the web server can serve. The admin dashboard shows *how old* the newest backup is and *how big* — nothing else, on purpose. There is no download button anywhere (`SECURITY.md` SEC-20).

---

## 2. The problem this solves

Something will go wrong: a deleted paragraph, a bad import that wipes a table, a compromised account, a failed disk. Backups answer all four. But "we take backups" is not a plan. A plan answers two questions, and they have standard names.

**RPO — Recovery Point Objective.** *How much recent data are we willing to lose?* Back up nightly at 01:30, lose the disk at 01:00 the next day, and everything typed in between is gone. RPO = "up to 24 hours".

**RTO — Recovery Time Objective.** *How long may the site stay broken while we fix it?* The clock starts when someone *notices*, not when the disaster happened.

Our honest answers:

| Question | Our answer | Source |
|---|---|---|
| RPO — database | **Up to 24 hours** of admin edits | one nightly cron entry, `DEPLOY.md:106-110` |
| RPO — uploaded media | **Up to 7 days** of new uploads | archive runs Sundays only, `database/backup.sh:48` |
| RPO — legacy photos + code | **Zero** | already in git — 478 files under `public_html/photos` |
| RTO | **A few hours**, and only if the one person who knows the steps is awake | derived from §4.8; not a contracted number |
| How long we may be wrong before losing the evidence | **14 days** | `database/backup.sh:55-56` |

That last row matters more than people expect. If a table quietly corrupts and nobody notices for three weeks, every surviving backup already contains the corruption. Retention is not about disk space — it is about *how long you might take to notice*.

---

## 3. How it works in general

### 3.1 The 3-2-1 rule

**3** copies of the data (the live one plus two backups), on **2** different kinds of storage (so one failure cannot take both), with **1** of them **off-site** (so a fire, a theft, or a compromised hosting account cannot reach it). `docs/learn/08-stage-h.md:349-351` states the same rule and adds the one that matters more: "a backup you have never restored is a hope, not a backup."

Our honest scorecard:

| 3-2-1 element | Status | Detail |
|---|---|---|
| Copy 1 — live | ✅ | production MySQL + `public_html/media/` |
| Copy 2 — backup | ✅ | `~/backups/db-*.sql.gz` + weekly `media-*.tar.gz` |
| Copy 3 — second backup | ⚠️ | 14 nightly dumps exist, all on the same disk |
| 2 media | ❌ | both copies sit on one shared-hosting filesystem |
| 1 off-site | ⚠️ manual | `DEPLOY.md:61` — "Pull nightly dumps down into dev", a human step |

We are honestly at about **2-1-0.5**. That is a budget-shaped compromise, not an oversight (§5), and the exact next step is in §6.

### 3.2 A dump is not a snapshot

A **dump** is a plain-text file of SQL commands that *rebuild* the database: `CREATE TABLE …` then thousands of `INSERT …` lines. Our dev drill file holds 27 `CREATE TABLE` statements — one per table — and is 246,474 bytes of text compressing to 48,001. Text compresses extremely well; that is why the script pipes it through `gzip`. A dump is portable (any MySQL 8 server reads it) and readable, but it is a single point in time — which is exactly why RPO is measured as "time since the last dump".

---

## 4. How we use it — every place in this codebase

### 4.1 What actually needs backing up

| Data | Lives where | In git? | Backed up by | Why |
|---|---|---|---|---|
| Database (all content) | MySQL | no | nightly `mysqldump` | The school types this. It exists nowhere else. |
| `public_html/media/` | disk | no (`.gitignore`) | weekly `tar` | Uploads + generated renditions. 136 MB today. |
| `public_html/photos/` | disk | **yes** — 478 files | git | Legacy photos, shipped with the code. |
| Application code | disk | **yes** | git + release zips | Reproducible from any clone. |

**The code is the least precious thing here**, and it is worth being clear why. The code is *already* backed up several times over by design: every developer's laptop holds a full clone, the remote holds another, and `DEPLOY.md:66` keeps the previous release zip on the host. Losing it costs you a `git clone` and an upload.

The database is the opposite. When the head teacher rewrites the admissions paragraph at 9 p.m., that text exists in exactly **one** place on Earth until the 01:30 job runs. No clone, no remote, no zip. That is what a backup is *for*.

### 4.2 `database/backup.sh`, line by line

A **shell script** is a list of terminal commands in a file, run top to bottom. `#!/bin/sh` on line 1 names the interpreter.

**Fail fast** — `database/backup.sh:15`

```sh
set -eu
```

`-e` stops the whole script the moment any command fails; `-u` makes an undefined variable an error. Without `-e`, a failed `mysqldump` would be followed happily by the retention step, which would delete good old backups and leave you with a broken new one.

**Locate ourselves** — `database/backup.sh:17-21`

```sh
SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)          # …/database
REPO_ROOT=$(dirname "$SCRIPT_DIR")                 # repo / account root
BACKUP_DIR=${BACKUP_DIR:-"$REPO_ROOT/backups"}     # NON-web (never under public_html)
PHP_BIN=${PHP_BIN:-php}
STAMP=$(date +%Y%m%d-%H%M)
```

`$0` is the script's own path, so it finds itself instead of depending on where you ran it from — cron jobs start in unpredictable directories. `${BACKUP_DIR:-…}` means "use that environment variable if set, otherwise this default". `STAMP` becomes `20260809-0133`.

**The webroot guard** — `database/backup.sh:23-27`

```sh
case "$BACKUP_DIR" in
  */public_html*) echo "refusing: BACKUP_DIR is inside the webroot" >&2; exit 1;;
esac
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" 2>/dev/null || true
```

The **webroot** is the folder the web server hands to the public — here `public_html/`. Any file inside it is a URL, so a dump inside it is a public download of your entire database. `SECURITY.md:126` lists `stjosephs.sql` and `public_html.zip` as typical shared-hosting incidents. The script refuses rather than trust you. `chmod 700` then means "only the account owner may read this folder" — which matters on **shared hosting**, where other customers run on the same machine.

**Read the credentials through PHP** — `database/backup.sh:30-38`

```sh
eval "$("$PHP_BIN" -d display_errors=0 -r '
  define("SJ_PUBLIC_ROOT", $argv[1] . "/public_html");
  $cfg = require $argv[1] . "/config/config.php";
  $db = $cfg["db"];
  foreach (["host","port","name","user","pass"] as $k) {
      $env = "DB_" . strtoupper($k);
      $v = getenv($env) !== false ? getenv($env) : $db[$k];
      printf("%s=%s\n", $env, escapeshellarg($v));
  }' "$REPO_ROOT")"
```

Read it inside out. `php -r '…'` runs PHP straight from the command line. It `require`s `config/config.php` — the gitignored file above the webroot that is the only home for production secrets (`SECURITY.md:139`). It prints five `KEY=value` lines. `eval` executes those as shell assignments, creating `$DB_HOST`, `$DB_PASS` and friends.

Two details are load-bearing. `escapeshellarg()` quotes each value, so a password containing a space or a `;` cannot become a second shell command. And environment variables win over the file (`getenv($env) !== false ? … : …`) — the same priority the application uses (`config/config.sample.php:8-11`), so dev-in-Docker and prod behave identically.

**Why not put the password in the cron line?** A **crontab** — the file listing scheduled jobs — is a normal file on a shared machine, and `ps`, which lists running processes, shows every argument of every running command *to every user on the box*. On shared hosting you have neighbours. `DEPLOY.md:113-114`: "Credentials are read from `config/config.php` by the script — nothing secret in the crontab."

**The dump** — `database/backup.sh:41-45`

```sh
DUMP="$BACKUP_DIR/db-$STAMP.sql.gz"
MYSQL_PWD="$DB_PASS" mysqldump \
  --single-transaction --quick --routines --triggers --no-tablespaces \
  -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" | gzip > "$DUMP"
```

`MYSQL_PWD="$DB_PASS" mysqldump …` sets the variable for that one command only — better than `-p<password>`, which *would* show in `ps`. Each flag earns its place:

| Flag | What it does | Why we need it |
|---|---|---|
| `--single-transaction` | Reads everything as one consistent point in time, without locking tables | The site keeps serving visitors during the backup |
| `--quick` | Streams rows out instead of buffering a whole table in RAM | Shared hosting gives you very little memory |
| `--routines` | Includes stored procedures and functions | They are schema too; a restore without them is incomplete |
| `--triggers` | Includes triggers | Same reason |
| `--no-tablespaces` | Skips the tablespace metadata section | **Required on shared hosting** — see below |

`--no-tablespaces` is the one you will not guess. Since MySQL 8.0.21 `mysqldump` tries to read tablespace information, which needs the server-wide `PROCESS` privilege. A shared-hosting database user is deliberately granted rights over *its own database only*, never server-wide, so the dump aborts with access denied. The flag tells `mysqldump` not to ask. The same flag appears in the renditions export at `DEPLOY.md:99`, for the same reason.

`| gzip > "$DUMP"` is a **pipe**: `mysqldump` writes SQL out, `gzip` compresses that stream, the result lands in the file. The dump is never written uncompressed, so 246 KB of SQL only ever occupies 48 KB of disk.

**The weekly media archive** — `database/backup.sh:48-52`

```sh
if [ "$(date +%u)" = "7" ] && [ -d "$REPO_ROOT/public_html/media" ]; then
    ARCH="$BACKUP_DIR/media-$STAMP.tar.gz"
    tar -czf "$ARCH" -C "$REPO_ROOT/public_html" media
```

`date +%u` gives the weekday as a number, 7 = Sunday. `tar` bundles a folder tree into one file: `-c` create, `-z` gzip, `-f` this filename. `-C` means "change into this directory first", so the archive holds a clean `media/…` path instead of `home/account/public_html/media/…`. Weekly, not nightly, because `media/` is 136 MB and changes rarely — nightly copies would fill the account's disk quota for almost no benefit.

**Retention** — `database/backup.sh:55-57`

```sh
find "$BACKUP_DIR" -name 'db-*.sql.gz'    -mtime +14 -delete
find "$BACKUP_DIR" -name 'media-*.tar.gz' -mtime +14 -delete
```

`find` walks the folder, `-mtime +14` matches files modified more than 14 days ago, `-delete` removes them. Note the `-name` filters: the script only ever deletes files it created. Point `BACKUP_DIR` somewhere wrong by accident and it still will not eat your documents.

### 4.3 Where the backups land

In development, `docker-compose.yml:39` mounts the host folder into the web container:

```yaml
      - ./backups:/var/www/backups
```

A **mount** makes one folder visible inside a container, so the admin panel running in Docker sees the same `backups/` folder you do. Two more layers keep those files out of public reach: `.gitignore` contains `/backups/` and `*.sql.gz`, so a dump can never be committed by accident; and `public_html/.htaccess:12` denies any request for a file ending `.sql`, `.gz`, `.zip`, `.tar`, `.bak` or `.dump`, so even a misplaced dump would not be served.

### 4.4 SEC-20 — status only, never a download

`public_html/admin/index.php:45` spells out the reason in the code:

```php
// ---- X2/X3 health strip: status only — deliberately NO download links (SEC-20)
```

The strip computes the newest dump's age and size (`public_html/admin/index.php:51-58`) and renders three read-only tiles: disk free, image count, last DB backup. The health endpoint does the same for robots at `public_html/admin/health.php:56-61`:

```php
$bdir = sj_config()['backup_dir'] ?? (dirname(SJ_PUBLIC_ROOT) . '/backups');
$newest = 0;
foreach (glob($bdir . '/db-*.sql.gz') ?: [] as $f) {
    $newest = max($newest, (int)filemtime($f));
}
$check('backup_age', $newest > 0, $newest ? round((time() - $newest) / 3600, 1) . ' h' : 'none found');
```

`filemtime()` reads a file's modification time. Nothing opens or serves the dump. Line 64 unsets `backup_age` from `$gating`, so a missing backup reports but does not turn the endpoint red — dev boxes legitimately have none.

**Why a download button would be a serious risk.** A dump is the entire database in one file: every content row, plus the `admin_users` table with its password hashes. A download endpoint turns any single admin-session compromise — a stolen cookie, a borrowed laptop, a phished password — into total data exfiltration in one click, silently, in seconds. Without it, the same attacker can only read and edit through the panel: slow, noisy, and reversible from the backups. `docs/learn/08-stage-h.md:371-373`: "a leaked admin session must not be able to exfiltrate the whole database in one click."

### 4.5 How it runs in production

There is no SSH on MilesWeb shared hosting (`DEPLOY.md:3`), so the schedule is configured through the hosting control panel. `DEPLOY.md:106-110`:

> mPanel → **Cron Jobs** → one nightly entry (e.g. 01:30):
>
> ```
> /bin/sh /home/<account>/database/backup.sh
> ```

**Cron** is the Unix scheduler — a table of "run this command at this time"; mPanel is a web form over it. Note what is *not* on that line: no hostname, no username, no password. That is §4.2's point made visible.

### 4.6 The restore drill that was actually performed

`DEPLOY.md:117-123` records both the procedure and its result:

```
gunzip -c backups/db-YYYYmmdd-HHMM.sql.gz | docker compose exec -T db \
  sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot drill_restore'
```

> (create `drill_restore` first, compare row counts, then drop it — verified 2026-08-09: images 478/478, seo_meta 41/41, image_renditions 988/988.)

The shape is the lesson. Restore into a **scratch database** — a throwaway, here `drill_restore` — never over the live one. **Compare row counts** between source and restored copy: three tables checked, all three matched exactly. Then drop the scratch so it cannot be mistaken for real data later. The dev database still holds those counts today (478 / 41 / 988), and the drill artifact is still on disk as `backups/db-drill.sql.gz`.

### 4.7 The data-direction rule

Once the site is live, `CLAUDE.md` states: "After first prod ship, **prod DB is the source of truth** — pull dumps down, never push a dev DB up." `DEPLOY.md:60-62` says the same. This is a backup rule in different clothes: your development database is a stale copy of production, so pushing it over production is a *deliberate* deletion of every edit the school made since you last pulled. Backups flow **down** only.

### 4.8 Restoring for real — the ordered checklist

1. **Put the site in a known state.** Take the admin panel out of use so nobody edits mid-restore. If the cause was a compromise, change the hosting and database passwords in mPanel *first*, or you are restoring into an attacker's hands.
2. **Pick the dump deliberately.** Newest is usually right — but if the problem is corruption, the newest dump contains it. Check its age against when the trouble started.
3. **Prove the file is intact first:** `gunzip -t <file>` reports a corrupt archive without extracting anything.
4. **Restore into a scratch database**, exactly as the drill does. Never import straight over the live database on your first attempt.
5. **Compare row counts** for the tables that matter (`images`, `seo_meta`, `image_renditions`, plus whatever was damaged). They must match the dump's source.
6. **Import into the real database** via mPanel's DB tool, then **restore media** by extracting the newest `media-*.tar.gz` over `public_html/media/` and re-checking permissions — `755` for directories, never `777` (`DEPLOY.md:31-32`).
7. **Verify the site.** Hit `https://<site>/admin/health.php?token=<health_token>` and expect every gating check `true` (`DEPLOY.md:46-48`). Then click through: home, one page per family, admin login, one edit.
8. **Write down what happened**, including what the RPO gap actually cost. That note is what justifies the next improvement in §6.

---

## 5. Why this is the right approach here

The alternatives are all real, and all better in isolation:

| Alternative | What it gives | Why not here |
|---|---|---|
| The host's own backup service | Zero code; someone else's problem | Restores are a support ticket; you cannot drill or verify it, and an unverified backup is §3's "hope" |
| Managed database with point-in-time recovery | RPO of seconds | Needs a managed database service. Shared hosting gives one MySQL database and a web form. Wrong platform, plus a recurring bill |
| Manual mPanel exports | No script to maintain | Depends on a human remembering, forever. `docs/learn/08-stage-h.md:352` — "Backups must be automatic (humans forget)" |
| A paid off-site backup service | Real 3-2-1 | Monthly cost, an API key to store on a shared host, a second system to learn. Genuinely the right *next* step — §6 |

Now the constraints, which are what actually decide it: shared hosting (`DEPLOY.md:3`), **no SSH** — the only scheduler is an mPanel web form; a **school budget**, so recurring costs need justifying; and, decisively, **one person** will run the restore, probably at night, probably stressed, having done it once before during a drill.

That last constraint is why the plan is boring on purpose. One shell script, no dependencies beyond `php`, `mysqldump`, `gzip`, `tar` and `find` — all already on the host. One cron line. One `gunzip | mysql` restore command written down in `DEPLOY.md`. A drill that was actually performed, with recorded numbers, so the person restoring has seen it work once. A clever plan only its author can execute has an RTO of "however long it takes to reach the author".

---

## 6. How this scales

**At 10× the data.** The database is text, and text stays small: today's dump is 246 KB of SQL and 48 KB gzipped, so 10× is roughly 2.5 MB of SQL, still under a megabyte compressed, still seconds to produce. `--single-transaction` and `--quick` mean the site never blocks while it runs. The database is not the problem.

`media/` is. At 136 MB now, 10× is ~1.4 GB per Sunday archive, kept two weeks — ~3 GB of backups plus the live copy. Shared-hosting disk quotas notice that. The `tar` also takes minutes rather than seconds, and shared hosts kill long-running cron jobs.

**When nightly full dumps stop being viable:** roughly when the dump takes longer than a few minutes, or the file is large enough that keeping 14 hurts. Then you move to **incremental** backups — a full copy weekly, and only the *changes* in between. MySQL's mechanism is the **binary log** ("binlog"), a running record of every statement that modified data. Weekly dump plus binlogs gives point-in-time recovery: restore Sunday's dump, replay the log to 09:59 on Wednesday, one minute before the bad `DELETE`. That turns a 24-hour RPO into seconds. Binlogs are usually unavailable on shared hosting — reaching for them is the signal you have outgrown the platform.

**Getting copies off-site** is the real gap (§3.1) and the cheapest fix. **The exact next step:** make the off-site copy automatic and scheduled — after the nightly dump succeeds, push the newest `db-*.sql.gz` to storage on a different provider (the school's own cloud drive is enough) and record the result where the health strip can see it, so a silent off-site failure shows on the dashboard the way a stale backup does. Everything after that is a platform change, not a script change.

---

## 7. Gotchas and mistakes to avoid

**Backups nobody has ever restored.** The default failure. The cron is green, and the file turns out to be 0 bytes, or missing a table, or unreadable by the target MySQL version — discovered at the worst possible moment. The only cure is the drill (`DEPLOY.md:117`, quarterly); the only proof is matching row counts.

**Dumps stored inside the webroot.** `public_html/backup.sql` is a public URL, and scanners look for exactly these names (`SECURITY.md:126`). `database/backup.sh:23-25` refuses to write there — do not "temporarily" override `BACKUP_DIR` to dodge a permissions error.

**Credentials in the crontab.** `mysqldump -u user -pSecret123 …` in a scheduled job leaks twice: the crontab file, and `ps` output while the job runs, visible to co-tenants. Read them from `config/config.php` (§4.2) and pass the password via `MYSQL_PWD`.

**A retention window shorter than your detection time.** Fourteen days assumes you notice within fourteen days. If nobody looks at the achievements page for a month, that assumption is false. Either watch the data more often or keep a longer-lived copy — but know which you chose.

**Forgetting the media directory.** `public_html/media/` is gitignored except its `.htaccess`, so a database-only backup restores a site where every uploaded photo is a broken image. The Sunday `tar` is not optional; it is half the backup.

**Assuming a git repo is a content backup.** Git holds the *code* and the 478 legacy files in `public_html/photos`. It holds none of the database and none of `media/`. A clean `git clone` after a disaster gives you a perfectly working website with no content in it.

**Assuming `set -eu` covers everything.** It stops on a failed command, but a job that never *starts* — wrong path, disabled schedule, expired account — fails silently. That is what `backup_age` is for: the dashboard tile goes red once the newest dump passes 48 hours (`public_html/admin/index.php:58`).

---

## 8. Try it yourself

Start the stack with `./run.sh`. One quirk first, which the script's own header admits (`database/backup.sh:7-8`): in Docker, `php` exists only in the `web` container and `mysqldump` only in the `db` container, so `backup.sh` cannot run end-to-end inside either. On production both live on the same account, which is where the script is meant to run. For the dev drill you do the two halves by hand.

**1. Produce a dump with the script's exact flags.** Credentials come from `.env.example` (dev throwaways — `DB_USER=stjosephs`, `DB_PASS=stjosephs_pw`, `DB_NAME=stjosephs`):

```sh
docker compose exec -T db sh -c \
  'MYSQL_PWD=stjosephs_pw exec mysqldump --single-transaction --quick \
     --routines --triggers --no-tablespaces -u stjosephs stjosephs' \
  | gzip > backups/db-drill2.sql.gz
```

**2. Inspect what you produced** — never trust a backup you have not looked at:

```sh
gunzip -t backups/db-drill2.sql.gz          # integrity check; silence means OK
ls -lh backups/                             # size — tens of KB, not 0
gunzip -c backups/db-drill2.sql.gz | head -6
gunzip -c backups/db-drill2.sql.gz | grep -c '^CREATE TABLE'   # expect 27
```

**3. Do a real restore drill.** Create a scratch database, import, compare:

```sh
docker compose exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE drill_restore"'

gunzip -c backups/db-drill2.sql.gz | docker compose exec -T db \
  sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot drill_restore'

docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e \
  "SELECT (SELECT COUNT(*) FROM images), (SELECT COUNT(*) FROM seo_meta), \
          (SELECT COUNT(*) FROM image_renditions);" drill_restore'
```

Expect `478  41  988` — the numbers `DEPLOY.md:123` recorded on 2026-08-09. Anything else means the backup is incomplete, and you learned it in a drill instead of an outage.

**4. Clean up.** A stale scratch database is a trap for the next person:

```sh
docker compose exec -T db sh -c \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "DROP DATABASE drill_restore"'
```

**5. Watch the dashboard react.** Open `http://localhost:8090/admin/` and read the "Last DB backup" tile — your new file's age and size. Then `touch -d '3 days ago'` the dump and reload: the tile turns red, because `public_html/admin/index.php:58` gates on 48 hours. Confirm the same value over JSON at `/admin/health.php?token=<health_token>`. Note there is no download link anywhere on that page — SEC-20 working as designed.

---

## 9. Where to read more

**In this repo**

| Document | What it adds |
|---|---|
| [`../08-stage-h.md`](../08-stage-h.md) Part 4 | The narrative version of X2, and Part 5 on monitoring |
| [`../../../DEPLOY.md`](../../../DEPLOY.md) §7 | The primary source: the cron line, the restore command, the recorded drill |
| [`../../../SECURITY.md`](../../../SECURITY.md) SEC-20 | Backup exposure, plus SEC-22 on shared-hosting permissions |
| [`../../../CLAUDE.md`](../../../CLAUDE.md) | Deploy cautions, including the data-direction rule |
| [`migrations-and-seeding.md`](migrations-and-seeding.md) | Why an additive-only migration policy makes restores safer |

**Outside**

- MySQL — [`mysqldump` reference](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html): every flag in §4.2, including why `--no-tablespaces` exists.
- MySQL — [point-in-time recovery with the binary log](https://dev.mysql.com/doc/refman/8.0/en/point-in-time-recovery-binlog.html): the §6 upgrade path.
- CISA — [Data Backup Options](https://www.cisa.gov/sites/default/files/publications/data_backup_options.pdf): the official guidance behind the 3-2-1 rule in §3.1.
