# Learn This Project — From Zero

Welcome! 👋 This folder teaches you **everything** you need to work on the St. Joseph's
school website, starting from **absolute zero**. You do **not** need to know PHP,
databases, or web development. We explain every word.

By the end you will understand:

- How websites work (browser ↔ server).
- The PHP language, from `echo "hello"` to classes and namespaces.
- Databases and SQL, and how PHP talks to them safely.
- The modern tools we use: Composer, Docker, autoloading.
- What "secure code" means and the exact attacks we defend against.
- How **our** code is organized and why, file by file.

## Read in this order

| # | File | What it teaches |
|---|------|-----------------|
| 1 | [`01-fundamentals.md`](01-fundamentals.md) | The web, PHP A–Z, databases + SQL, PDO, sessions, Composer, Docker. **Start here.** |
| 2 | [`02-security.md`](02-security.md) | What each attack is (in plain words) and exactly how we block it in our code. |
| 3 | [`03-what-we-built.md`](03-what-we-built.md) | Stages A & B — security + the modern code structure, phase by phase. |
| 4 | [`04-stage-c-and-d.md`](04-stage-c-and-d.md) | Stages C & D — the Bootstrap-5 front-end revamp + speed/deploy work. |
| 5 | [`05-stage-e.md`](05-stage-e.md) | Stage E — content moves into the database; the admin panel learns to edit it. |
| 6 | [`06-stage-f.md`](06-stage-f.md) | Stage F — the live-edit overlay: change the site by clicking on the site. |
| 7 | [`07-stage-g.md`](07-stage-g.md) | Stage G — CSS consolidation, the last legacy dirs deleted, W3C: 368 → 0 errors, pixel-frozen. |
| 8 | [`08-stage-h.md`](08-stage-h.md) | Stage H — image renditions, **SEO explained in depth**, performance proof, backups & monitoring. |

## How to study

1. **Read slowly.** Each section builds on the last.
2. **Type the examples yourself.** Reading code is not the same as writing it.
3. **Keep the repo open beside you.** When a doc mentions a file like
   `public_html/_libs/db.php`, open it and read the real thing.
4. **Run the app** (see below) and click around. Seeing it live makes it click.

## Run the app on your machine

You only need **Docker** installed. Everything else (PHP, the database) runs *inside*
Docker, so you don't install them on your computer.

```bash
./run.sh          # build everything, start it, load sample data
# then open http://localhost:8090/          (the public website)
#      and  http://localhost:8090/admin/    (the admin panel — user: admin, pass: admin123)

./run.sh stop     # stop it
./run.sh reset    # wipe the database and start fresh
```

> The first login will force you to set a new, strong password — that's a security
> feature we built (you'll learn why in doc #2).

## The big picture in one paragraph

This is a **school website** (42 pages) that we are upgrading. The old version had all
its text and images typed directly into the code, so only a programmer could change
anything. We are turning it into a **CMS** (Content Management System): the content now
lives in a **database**, and there is an **admin panel** where non-programmers can edit
pages, upload photos, and reorder things — no coding needed. We're also making the code
clean, organized, secure, and fast. These docs teach you the technology behind all of that.

Ready? Open [`01-fundamentals.md`](01-fundamentals.md).
