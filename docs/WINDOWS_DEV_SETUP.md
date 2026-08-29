# Running the St. Joseph's website on a Windows machine

The whole stack (PHP 8.3, Apache, MySQL 8) runs **inside Docker** — nothing
is installed on the host on Linux, and the same is true on Windows. That
means **no source file needs to be edited to "port" the project**. Setting
up a Windows dev machine is: install two tools, clone with the right
line-ending setting, create the two per-machine files (the launcher does it
for you), and run.

---

## 1. What has to change — the honest list

| What | Change needed | Who does it |
|---|---|---|
| Project source (PHP/JS/CSS/SQL/Docker files) | **Nothing.** Identical on Windows. | — |
| `.gitattributes` | Already in the repo — forces LF endings so Windows Git can't corrupt `run.sh` / `.sql` files. | done once, committed |
| `.env` | Per-machine, gitignored. Auto-created from `.env.example` on first run. | `run.sh` (automatic) |
| `config/config.php` | Per-machine, gitignored. Auto-created from `config/config.sample.php` on first run. | `run.sh` (automatic) |
| Git line-ending setting | `git config --global core.autocrlf input` **before cloning**. | you, once |

That is the complete list. Do **not** edit `run.sh`, `docker-compose.yml`
or `Dockerfile` "for Windows" — they are already cross-platform.

---

## 2. Install the two tools (once)

1. **Docker Desktop for Windows** — https://www.docker.com/products/docker-desktop/
   - Requires Windows 10/11 64-bit with virtualization enabled in the
     BIOS/UEFI (usually already on; Task Manager → Performance → CPU shows
     "Virtualization: Enabled").
   - During install keep the default **"Use WSL 2 based engine"**. Windows
     will install WSL 2 automatically if it's missing (one reboot).
   - After install, **start Docker Desktop and wait for the whale icon to
     go steady** — every `docker` command needs it running.
2. **Git for Windows** — https://git-scm.com/download/win
   - This also installs **Git Bash**, which is the terminal you'll run
     `./run.sh` from. Keep the defaults during install.

---

## 3. Clone the repository (once)

Open **Git Bash** and run, in this order:

```bash
git config --global core.autocrlf input
git clone https://github.com/aswinlegarcon/st_josephs_revamp.git
cd st_josephs_revamp
```

Why the first line matters: Git for Windows defaults to converting every
text file to Windows CRLF endings on checkout. That silently breaks
`run.sh` (bash fails with `$'\r': command not found`) and the SQL files
that MySQL auto-loads on first boot. `autocrlf input` + the repo's
`.gitattributes` guarantee files stay LF.

> Already cloned before this setting? Fix the working copy in place:
> ```bash
> git config core.autocrlf input
> git rm -r --cached . -q && git checkout -- . && git status
> ```

**Where to clone:** any normal path works (e.g. `C:\Users\you\projects\`).
If the site feels slow in dev, cloning inside the WSL file system instead
(`\\wsl$\Ubuntu\home\you\...`, working from a WSL terminal) makes Docker's
file mounts several times faster — optional, not required.

---

## 4. First run

With **Docker Desktop running**, in Git Bash inside the project folder:

```bash
./run.sh
```

What it does on Windows, exactly as on Linux:

1. Creates `.env` from `.env.example` (dev DB credentials) and
   `config/config.php` from `config/config.sample.php` — only if missing.
2. Builds the PHP/Apache image and starts both containers
   (first run downloads images — allow a few minutes).
3. Waits for MySQL to answer, loads `database/schema.sql`
   (first boot only), refreshes the autoloader and runs the seeder
   (idempotent — safe every time).
4. Prints the URLs when ready:
   - Public site: **http://localhost:8090/**
   - Admin panel: **http://localhost:8090/admin/** (dev seed:
     `admin` / `admin123` — dev only, never used in production)

The `chmod 777` line in `run.sh` is a Linux-ism that safely does nothing on
Windows — Docker Desktop mounts are already writable for the container, so
image uploads work without it.

Day-to-day commands (all from Git Bash in the project folder):

```bash
./run.sh          # start (or restart) everything
./run.sh stop     # stop the containers
./run.sh reset    # stop AND delete the database volume — full fresh start
```

Editing code needs no restart: the project folder is mounted into the
container, so PHP/CSS/JS changes are live on refresh. Remember the repo
rule: after changing any CSS/JS, bump `SJ_ASSET_VER` in
`public_html/bootstrap.php` or the browser will keep serving the cached
old file.

### Without Git Bash (plain PowerShell — fallback only)

`run.sh` is the supported path, but its steps translate 1:1 if you ever
need them:

```powershell
Copy-Item .env.example .env                              # first time only
Copy-Item config\config.sample.php config\config.php     # first time only
docker compose up -d --build
docker compose exec -T web php /var/www/database/seed.php
```

---

## 5. Windows-specific problems and their fixes

| Symptom | Cause → fix |
|---|---|
| `./run.sh: line N: $'\r': command not found` | CRLF endings from a clone made before section 3. Run the "already cloned" fix above. |
| `error during connect: ... dockerDesktopLinuxEngine` | Docker Desktop isn't running. Start it, wait for the whale, retry. |
| `Bind for 0.0.0.0:8090 failed: port is already allocated` | Another app owns port 8090 (or 3307 for the DB). Find it with `netstat -ano \| findstr 8090` and stop it, or change the **left** side of the port mapping in `docker-compose.yml` (e.g. `"8091:80"`) — then browse to that port instead. |
| WSL 2 install loop / "virtualization not enabled" | Enable Intel VT-x / AMD-V in BIOS, and in Windows Features turn on "Virtual Machine Platform" + "Windows Subsystem for Linux", reboot. |
| Very slow page loads in dev | Bind mounts from `C:\` are slow-ish. Either accept it (dev only), exclude the project folder from Windows Defender real-time scanning, or clone inside WSL (section 3). |
| Uploads fail in the admin Media Library | Confirm `public_html/media/` exists in the project folder (run.sh creates it). Docker Desktop mounts are writable by default — if it still fails, `./run.sh stop` then `./run.sh`. |
| `mysqladmin: command not found` noise during the wait loop | Harmless — the loop polls until MySQL is up; it retries for 90 s. |

---

## 6. What this manual does NOT cover on purpose

- **Production** never involves Windows or Docker — prod is MilesWeb shared
  hosting, deploy = file upload per `DEPLOY.md`.
- **The database content** on a new machine is the seeded dev content. To
  work against real content, restore a prod dump into the dev DB (see
  `DEPLOY.md` §backups — pull dumps down, never push a dev DB up).
- The dev seed login (`admin`/`admin123`) exists only on freshly seeded dev
  databases and must never be created in production.
