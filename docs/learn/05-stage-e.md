# 5 — What We Built: Stage E (content into the database)

> Continues [`04-stage-c-and-d.md`](04-stage-c-and-d.md). Stage C made every page one
> clean Bootstrap-5 document; Stage D made it fast and deployable. **Stage E is the
> actual CMS work**: moving the words and photos out of the code and into the
> database, so the school can edit them in the admin panel — no programmer needed.
>
> Status: **C1 ✅ C2 ✅ C3 ✅ M1 ✅ M2 ✅ C4 ✅** · remaining: C5–C9 (academies,
> sports, infrastructure, achievements, gallery) + M3 (crop UI) + M4 (media library).
> This file grows as those land.

---

## The golden rule of every content phase

Each phase follows the same recipe, and the most important step is invisible:

1. **Seed the database with the *exact* text and photos the site already shows** —
   byte-for-byte. We prove it by comparing the page's visible text before and after
   ("diff = zero characters").
2. Make the page **render from the database** instead of from hard-coded HTML.
3. Give the admin panel a screen to **edit that content**.
4. Test the full loop: edit in admin → see it on the site → put it back.

Because of step 1, a visitor can never tell the difference — but after each phase,
one more part of the site is editable without touching code.

---

## Phase C1 — site-wide settings + a safe contact form

**Settings.** Strings that appear on *every* page (phone number, email, address,
school timings, the admissions band, social links, the copyright line) now live in
one `settings` table — 14 of them. The admin panel got a **Site Settings** screen;
change the phone number once and the footer *and* the contact section update
everywhere, instantly.

Two security details worth learning:

- The `settings` table is deliberately **not** part of the generic content system.
  It has its own API endpoint with a **hard-coded list of 14 allowed keys** and
  per-key validation (emails must be emails, URLs must be `https://`, the
  years-shown number must be 1–10). An attacker who somehow reached the endpoint
  still couldn't write arbitrary keys.
- **Bug fixed by design:** the old site *displayed* one email address but the
  clickable `mailto:` link pointed at a completely different one (bug #12). Now both
  come from the same setting — they literally cannot disagree again.

**The contact form (SEC-23).** The old form sent email **from the browser** using
EmailJS, with the sending keys visible in the page source — anyone could copy the
keys and burn the school's email quota with spam. Now:

- The EmailJS code and keys are **gone from the site entirely**.
- The form posts to our own server endpoint, which checks — in order — that the
  request comes from our site, that a hidden **honeypot** field is empty (bots fill
  every field; humans never see it), that the same visitor hasn't already sent
  **5 messages this hour**, and (in production) that the reCAPTCHA is genuine —
  verified on the **server**, where it can't be faked.
- Every accepted enquiry is **stored in the database** (so nothing is ever lost) and
  relayed to the school's mailbox when that's configured.

We tested each defense: a sixth message in an hour gets politely refused, a filled
honeypot gets a *fake* success (the bot thinks it worked; nothing is stored), and a
cross-site post is rejected.

## Phase C2 — the About page

The About page's four content blocks — **President**, **Principal**, **School
History**, **Rules & Regulations** — plus its top photo carousel are now database
rows. The clever bit: the Principal block on the About page and the one on the Home
page are **the same database row**, so editing it once updates both pages (we
verified exactly that).

We also proved the XSS defense here: we saved a message containing
`<script>alert(1)</script>` through the admin API and confirmed the sanitizer
stripped the dangerous parts on the way in — only the harmless text survived.

## Phase C3 — Staffs page + testimonials

The Staffs page (top carousel + three text blocks) and the Home page's three
**student testimonial cards** became editable lists. One subtlety: the testimonial
headings contain real line breaks (`Kishore N.E,` ⏎ `Alumni`), so the name is stored
as a *sanitized rich field* rather than plain text — plain text would have shown the
`<br>` literally. We ran the full list workflow — add a card, drag it to first
place, edit it, delete it — and the Home page reflected every step.

## Phase M1 — one API for every photo collection

The site has dozens of "just photos" collections (an academy's carousel, a
facility's slideshow, a gallery album's grid…). Rather than build separate
plumbing for each, they all share **one table** (`image_links`: *owner → photo →
position*) and now **one API** with four actions: list, attach, detach, reorder.

The security keystone: the API never trusts the request to say which table owns a
collection. The owner "type" is looked up in a **whitelist** (`page`, `section`,
`academy`, `facility`, `album`, `album_year` → their tables). We attacked our own
endpoint eleven ways — fake owner types, photos from someone else's collection,
missing CSRF tokens — and every attack was refused.

## Phase M2 — manage photos visually

Two upgrades to the admin panel:

1. **Drag to reorder, everywhere.** Every list (hero slides, ticker items,
   testimonials, timeline months…) can now be reordered by simply dragging rows —
   the ↑/↓ buttons still work too. We dragged the first Home hero slide below the
   second and watched the real Home page lead with the new first image.
2. **A "Manage photos" modal** for the M1 collections: a grid of thumbnails where
   you add photos (from the library or by uploading), drag them into order, and
   remove them. It's one reusable component — every photo collection from C4
   onwards gets it for free.

A fun production lesson surfaced here: our changed admin JavaScript **didn't load**
at first — the browser was still using its year-long cached copy, exactly as we
told it to in F1! The fix is the rule F1 established: bump `SJ_ASSET_VER` whenever
assets change. We hit our own cache rule and it worked as designed.

## Phase C4 — the four School Section pages

KG, Primary, High School and Higher Secondary were four ~330-line near-identical
files. Now they are **one template** (`views/pages/section.php`) fed by four tiny
controllers, with everything editable:

- the top photo carousel (per page),
- the intro text,
- the **timeline accordion** (41 months of school events across the four pages,
  each month a row; events are typed one per line and render exactly like the
  original `<br>`-separated lists),
- the **event blocks** at the bottom (photos alternate left/right automatically),
- the inner photo carousel — the **first real consumer of M1/M2**: its photos are
  `image_links` rows managed by the "Manage photos" modal.

**How did we move ~41 months of dates and 4 pages of text without a single typo?**
We didn't retype anything: a small script (`database/extract-sections.php`) *parses
the old pages* and generates the seed data mechanically. The proof it worked: all
four pages' visible text was **character-for-character identical** before and after
the switch — including Higher Secondary's 72-item toppers marquee.

---

## Where the project stands

| Stage | Status |
|---|---|
| A — Security · B — Platform · C — Front-end · D — Speed & deploy | ✅ done |
| **E — Content into the DB** | **C1 ✅ C2 ✅ C3 ✅ M1 ✅ M2 ✅ C4 ✅ — remaining: C5 (academies) C6 (sports) M3 (crop) C7 (infrastructure) C8 (achievements) M4 (media) C9 (gallery)** |
| F — Live-edit overlay · G — Revamp completion · H — Perf/SEO/ops | upcoming |

### Try it yourself
1. `./run.sh`, log in to `http://localhost:8090/admin/` → **Site Settings** — change
   the phone number, then refresh any page and look at the footer.
2. Open **School Sections → Kinder Garten** — add a timeline month, refresh
   `http://localhost:8090/kg.php`, and expand "Timeline - 2024".
3. In **Hero Carousel**, drag the first slide somewhere else — then reload the
   Home page.
