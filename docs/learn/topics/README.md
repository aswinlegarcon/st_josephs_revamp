# Topics — One Subject at a Time

The documents in [`docs/learn/`](../) tell the project's story **in order**: what we built,
stage by stage. The documents in *this* folder do the opposite. Each one takes a **single
technical subject** and explains it properly, from first principles, for someone who knows
only very basic PHP.

Every topic doc has the same nine sections, so you always know where to look:

| Section | Answers |
|---|---|
| 1. The one-paragraph version | *What is this, in short?* |
| 2. The problem this solves | *Why does it exist at all?* |
| 3. How it works in general | *The concept, independent of our code* |
| 4. How we use it — every place in this codebase | *Where is it in OUR files?* (with `file:line`) |
| 5. Why this is the right approach here | *What else could we have done, and why didn't we?* |
| 6. How this scales | *What happens at 10× — and what breaks first?* |
| 7. Gotchas and mistakes to avoid | *What has actually gone wrong* |
| 8. Try it yourself | *Commands to run right now* |
| 9. Where to read more | *Our docs, then the internet* |

You do not have to read them in order. Read the one you need. But if you are brand new,
the **Start here** group below is the shortest path to being useful.

---

## Start here

| Topic | What it teaches |
|---|---|
| [How PHP Serves a Web Page](how-php-serves-a-page.md) | Browser → DNS → Apache → PHP → HTML. Superglobals, `include`, headers, why PHP forgets everything between requests. |
| [Project Architecture — Layers, Controllers and Views](architecture-and-layers.md) | The seams in our code: thin controller → repository → layout engine → shell → partials. Why one template serves 18 pages. |
| [Composer, Namespaces and Autoloading](composer-and-autoloading.md) | Classes, namespaces, PSR-4, and the one line in `bootstrap.php` that makes `SJ\…` work. Also: why `vendor/` is committed. |

Then read [`../09-request-lifecycle.md`](../09-request-lifecycle.md), which follows one
real request through all three.

## Data

| Topic | What it teaches |
|---|---|
| [PDO — Talking to MySQL Safely](pdo-and-sql.md) | Prepared statements, SQL injection shown as a live attack, and why identifiers can never be placeholders. |
| [Database Schema Design](database-schema-design.md) | Every table we have, why it looks like that, and the column patterns (`slug`, `sort_order`, `is_active`, `*_html`) that repeat everywhere. |
| [Migrations and Seeding](migrations-and-seeding.md) | Why schema changes need scripts, the additive-only rule, and **idempotency** — the property that lets you run the seeder twice safely. |
| [The Repository Pattern](the-repository-pattern.md) | Why views never query the database, how JOINs kill the N+1 problem, and how the ≤ 12 queries/page budget is enforced. |
| [The Registry Pattern](the-registry-pattern.md) | The single array that decides what the CMS can edit. The heart of the admin panel — read this before touching the API. |

## Security

| Topic | What it teaches |
|---|---|
| [Output Escaping and XSS](output-escaping-and-xss.md) | Why every echoed value goes through `e()`, the one exception (`_html` columns), and sanitising on write instead of on read. |
| [CSRF — Cross-Site Request Forgery](csrf-protection.md) | The attack where *your own browser* betrays you, and the token that stops it. |
| [Sessions and Cookies](sessions-and-cookies.md) | What a session physically is, every cookie flag we set, idle vs absolute timeouts, id rotation, and where the files live on disk. |
| [Passwords and Authentication](passwords-and-authentication.md) | Hashing vs encryption, bcrypt, lockout, why every login failure returns the same message. |
| [Security Headers, CSP and .htaccess](security-headers-and-htaccess.md) | Each header, the attack it blocks, and why we send them from PHP as well as Apache. |
| [Audit Logging](audit-logging.md) | Who changed what, when — and what we deliberately never write down. |

Read these alongside [`../02-security.md`](../02-security.md) and the normative catalogue
[`SECURITY.md`](../../../SECURITY.md).

## Front end

| Topic | What it teaches |
|---|---|
| [CSS Architecture and the Visual Freeze](css-architecture-and-visual-freeze.md) | The cascade, specificity, design tokens, our load order — and the rule that forbids "improving" the look during the migration. |
| [Front-End JavaScript](frontend-javascript.md) | The DOM, `fetch`, every script we ship, and the live-edit overlay traced end to end. No build step, no framework. |
| [Valid HTML and Accessibility](html-validation-and-accessibility.md) | Why 368 validation errors mattered, what a screen reader needs, and why accessibility fixes were allowed under the visual freeze. |

## Media, speed and findability

| Topic | What it teaches |
|---|---|
| [File Uploads and the Image Pipeline](images-and-the-media-pipeline.md) | The most dangerous feature in any CMS, plus presets, renditions, crops, and the `<picture>` bug that took a day to find. |
| [Caching and Cache-Busting](caching-and-cache-busting.md) | Cache too little and the site crawls; cache too much and edits vanish. How `SJ_ASSET_VER` resolves it in one line. |
| [Measuring Web Performance](performance-measurement.md) | LCP, CLS, TTFB, page weight, the SQL query counter — and how to reproduce our numbers yourself. |
| [SEO](seo.md) | How search engines find and rank pages, and every tag we emit. The deep theory is in [`../08-stage-h.md`](../08-stage-h.md). |

## Running it

| Topic | What it teaches |
|---|---|
| [Docker and Environments](docker-and-environments.md) | Containers explained from zero, our `Dockerfile`/`compose`/`run.sh`, and where dev honestly differs from production. |
| [Deploying to Shared Hosting](deploying-to-shared-hosting.md) | No SSH, no build step, deploy = file upload — and how that single constraint shaped the whole codebase. |
| [Backups and Disaster Recovery](backups-and-disaster-recovery.md) | RPO/RTO, the 3-2-1 rule, our nightly script, and why an untested backup is only a hope. |
| [Monitoring and Health Checks](monitoring-and-health-checks.md) | You will not notice the site is down — a parent will. What our health endpoint checks and why. |

---

## How to use these while you work

1. **Before you change something,** read the topic doc for the area you are touching.
   Section 7 ("Gotchas") is where previous mistakes are recorded so you do not repeat them.
2. **Section 4 is a map.** It lists real files and line numbers. Open them.
3. **Section 8 is homework.** Run it. Reading about a session file is nothing like finding
   yours in `/tmp` and reading `admin_id|i:1;` with your own eyes.
4. **Do not trust a line number blindly.** Code moves. The file path and the surrounding
   snippet are the durable parts; if a line number is off by a few, the doc is still right
   about the *what* and *why* — fix the number as you pass.
5. **If you find something wrong, fix the doc in the same commit as the code.** These
   documents are only useful while they are true.

## The rules that constrain everything here

Before your first change, read [`CLAUDE.md`](../../../CLAUDE.md). Three of its rules will
affect you on day one:

- **Security checklist** — anything touching admin, API, `src/`, `database/` or a page that
  renders database content must satisfy `SECURITY.md` §4 before it ships.
- **Visual freeze** — until the migration is finished, no page may change how it looks.
  Refactor freely; move a pixel and you have broken the rule.
- **File writing** — this repo is developed on an EDR-monitored machine. Never create or
  modify source files with shell redirection, heredocs or `sed -i`. Use an editor.
