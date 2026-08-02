# 1 — Fundamentals: The Web, PHP, Databases & Our Tools

> Goal: after this file you understand how a website works, the whole PHP language,
> how databases work, and the modern tools we use. Every term is explained. Take your
> time and type the examples.

---

## Part A — How a website actually works

### A.1 Two computers talking

When you open a website, **two computers** talk to each other:

- **The client** — your browser (Chrome, Firefox…) on your phone or laptop.
- **The server** — a computer somewhere on the internet that *stores* the website and
  *sends it* to you.

The conversation looks like this:

```
YOU (browser)                          SERVER
     |                                    |
     |  1. "GET me the page /about.php"   |
     | ---------------------------------> |
     |                                    |  2. server runs some code,
     |                                    |     builds an HTML page
     |  3. Here is the HTML               |
     | <--------------------------------- |
     |                                    |
  4. browser draws the page
```

Each "please give me this" message is called an **HTTP request**. The reply is an
**HTTP response**. HTTP is just the agreed language ("protocol") they speak. HTTP**S**
is the same thing but encrypted (secure) — that's the padlock in your address bar.

### A.2 The three languages of a web page

The HTML the server sends can contain three things, and they have **different jobs**:

| Language | Job | Analogy |
|---|---|---|
| **HTML** | The **structure** — headings, paragraphs, images, buttons | The skeleton |
| **CSS** | The **look** — colors, fonts, spacing, layout | The skin & clothes |
| **JavaScript** | The **behavior** *in the browser* — clicking, animations | The muscles |

A tiny HTML page:

```html
<!DOCTYPE html>
<html>
  <head>
    <title>My Page</title>
    <link rel="stylesheet" href="style.css">  <!-- pulls in CSS -->
  </head>
  <body>
    <h1>Hello!</h1>
    <p>This is a paragraph.</p>
    <script src="app.js"></script>            <!-- pulls in JavaScript -->
  </body>
</html>
```

**Key idea:** HTML, CSS, and JavaScript run **in the browser** (client side). But *where
does the HTML come from?* Something on the server has to **produce** it. That "something"
is **PHP**.

### A.3 Client-side vs server-side (very important)

- **Server-side** = code that runs on the server *before* the page is sent. It can read
  the database, check passwords, and decide what HTML to build. The user **never sees**
  this code — only its output. **PHP is server-side.**
- **Client-side** = code that runs in the browser *after* the page arrives (CSS + JS).
  The user *can* see it (View Source).

> 🔒 Why this matters for security: never trust the client. Anyone can change what their
> browser sends. All important checks (is this user logged in? is this password correct?)
> must happen **server-side**, in PHP. We'll hammer this point in doc #2.

---

## Part B — What is PHP?

**PHP** is a programming language designed to build the HTML that servers send to
browsers. When a request comes in for `about.php`, the server runs the PHP code in that
file *top to bottom*, and whatever the code **prints** becomes the HTML response.

Our project uses **PHP version 8.3** (the "8.3" is just the version number — newer =
more features and speed).

### B.1 The magic tags

A `.php` file is mostly normal HTML, but anything between `<?php` and `?>` is **code that
runs on the server**:

```php
<h1>Welcome</h1>
<?php
  // This is PHP. It runs on the server.
  echo "Today's page was built by PHP.";
?>
<p>Goodbye</p>
```

`echo` means **"print this into the page."** The browser receives:

```html
<h1>Welcome</h1>
Today's page was built by PHP.
<p>Goodbye</p>
```

The browser has **no idea PHP was involved** — it only sees the final HTML. That's the
whole trick.

A shorthand you'll see everywhere: `<?= $x ?>` is exactly the same as `<?php echo $x; ?>`.

### B.2 Comments

Notes for humans; the computer ignores them:

```php
<?php
// single-line comment
# also a single-line comment
/* a comment that can
   span many lines */
```

---

## Part C — The PHP language, piece by piece

### C.1 Variables

A **variable** is a labeled box that stores a value. In PHP every variable name starts
with `$`:

```php
<?php
$name = "Priya";      // store the text "Priya"
$age  = 15;           // store the number 15
echo $name;           // prints: Priya
echo "Hello, $name";  // prints: Hello, Priya   (PHP swaps $name for its value)
```

`=` means **"put the value on the right into the box on the left."** It is *not* "equals"
like in maths.

### C.2 Data types (the kinds of values)

| Type | Example | Meaning |
|---|---|---|
| **string** | `"hello"`, `'world'` | Text. Always in quotes. |
| **int** (integer) | `42`, `-7` | Whole numbers. |
| **float** | `3.14` | Numbers with decimals. |
| **bool** (boolean) | `true`, `false` | Yes/no, on/off. |
| **null** | `null` | "Nothing / empty / not set." |
| **array** | `[1, 2, 3]` | A list of values (see below). |
| **object** | (from a class) | A bundle of data + functions (see OOP). |

```php
<?php
$title    = "St. Joseph's";   // string
$students = 2217;             // int
$passRate = 99.5;            // float
$isOpen   = true;            // bool
$nickname = null;            // null (we don't have one)
```

### C.3 Strings (text) in detail

Two kinds of quotes:

```php
<?php
$name = "Ravi";
echo "Hi $name";   // double quotes: variables INSIDE are replaced → "Hi Ravi"
echo 'Hi $name';   // single quotes: taken literally           → "Hi $name"
```

Joining strings uses a **dot** `.` (called concatenation):

```php
<?php
$first = "St.";
$last  = "Joseph's";
echo $first . " " . $last;   // "St. Joseph's"
```

Useful string helpers you'll meet in our code:

```php
<?php
strlen("hello");            // 5   — length
trim("  hi  ");            // "hi" — remove spaces at the ends
strtolower("HELLO");        // "hello"
str_replace("a", "@", "banana"); // "b@n@n@"
mb_substr("hello", 0, 3);   // "hel" — first 3 characters (the "mb_" version is
                            //         Unicode-safe, important for names/accents)
```

### C.4 Arrays (lists and dictionaries)

An **array** holds many values in one variable. There are two flavors.

**Indexed array** — a plain list, numbered from 0:

```php
<?php
$colors = ["red", "green", "blue"];
echo $colors[0];   // "red"   (counting starts at 0, not 1!)
echo $colors[2];   // "blue"
$colors[] = "gold"; // add "gold" to the end
```

**Associative array** — pairs of **key → value** (like a dictionary):

```php
<?php
$student = [
    "name"  => "Anita",
    "grade" => 10,
    "house" => "Blue",
];
echo $student["name"];   // "Anita"
$student["age"] = 15;    // add a new pair
```

Associative arrays are **everywhere** in our project — a database row comes back as an
associative array like `["id" => 5, "caption_title" => "St. Joseph's", ...]`.

### C.5 Operators (doing things with values)

```php
<?php
// Maths
2 + 3;   // 5
10 - 4;  // 6
3 * 4;   // 12
10 / 2;  // 5
10 % 3;  // 1   (remainder — "modulo". 10 ÷ 3 = 3 remainder 1)

// Comparisons — these give true/false
5 == 5;   // true   (equal in value)
5 === 5;  // true   (equal in value AND type — PREFER THIS ONE)
5 == "5"; // true   (loose: PHP converts types — can surprise you)
5 === "5";// false  (strict: number vs text are different)
5 != 6;   // true   (not equal)
5 > 3;    // true

// Logic — combine true/false
true && false;  // false  (AND: both must be true)
true || false;  // true   (OR: at least one true)
!true;          // false  (NOT: flips it)
```

> ✅ Rule of thumb: **use `===` and `!==`** (strict). It avoids weird bugs where `"0"`,
> `0`, and `false` get treated as the same thing.

### C.6 Control flow — making decisions

**if / else** — do different things based on a condition:

```php
<?php
$marks = 82;

if ($marks >= 90) {
    echo "Distinction";
} elseif ($marks >= 60) {
    echo "Pass";
} else {
    echo "Needs improvement";
}
// prints: Pass
```

**foreach** — do something for every item in an array (the most common loop in our code):

```php
<?php
$slides = ["photo1.jpg", "photo2.jpg", "photo3.jpg"];

foreach ($slides as $slide) {
    echo "<img src='$slide'>";
}
```

With key + value:

```php
<?php
$student = ["name" => "Anita", "grade" => 10];

foreach ($student as $key => $value) {
    echo "$key is $value";   // "name is Anita", then "grade is 10"
}
```

You'll also see `$i => $item` where `$i` is the position number (0, 1, 2…), used to do
things like "make the first slide active":

```php
<?php
foreach ($slides as $i => $slide) {
    $active = ($i === 0) ? "active" : "";   // only the first one
    echo "<div class='slide $active'>...</div>";
}
```

That last line uses the **ternary operator** `condition ? valueIfTrue : valueIfFalse` — a
one-line if/else.

**while** — repeat as long as a condition holds:

```php
<?php
$count = 0;
while ($count < 3) {
    echo $count;   // 0, 1, 2
    $count = $count + 1;   // (or: $count++)
}
```

### C.7 Functions — reusable blocks of code

A **function** is a named recipe. You define it once, then "call" it whenever you need it.

```php
<?php
// Define a function called "greet" that takes one input ("parameter")
function greet($name) {
    return "Hello, " . $name;   // "return" = give this value back to the caller
}

// Call it
echo greet("Sam");   // "Hello, Sam"
echo greet("Maya");  // "Hello, Maya"
```

- **Parameters** are the inputs in the parentheses (`$name`).
- **`return`** hands a value back. (`echo` prints; `return` gives back.)

Modern PHP lets you declare the **types** of inputs and output — this catches mistakes:

```php
<?php
// Takes a string, returns a string:
function shout(string $text): string {
    return strtoupper($text) . "!";
}
echo shout("hi");   // "HI!"
```

A real one from our project (`public_html/_libs/edit.php`) — it makes text safe to show
in a page (you'll fully understand *why* in doc #2):

```php
<?php
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
```

### C.8 Superglobals — data PHP gives you about the request

PHP automatically fills special arrays with information about the current request. They
are called **superglobals** (available everywhere). The important ones:

| Superglobal | Contains | Example |
|---|---|---|
| `$_GET` | Values from the URL after `?` | `/search.php?q=math` → `$_GET['q']` is `"math"` |
| `$_POST` | Values from a submitted form | `$_POST['username']` |
| `$_SESSION` | Data remembered across pages for one user | `$_SESSION['admin_id']` |
| `$_COOKIE` | Small data stored in the browser | `$_COOKIE['SJADMIN']` |
| `$_SERVER` | Info about the request/server | `$_SERVER['REQUEST_METHOD']` is `"GET"` or `"POST"` |
| `$_FILES` | Uploaded files | `$_FILES['photo']` |

```php
<?php
// A form was submitted with method="post"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';   // ?? '' means "or empty if missing"
    echo "You typed: " . $username;
}
```

> 🔒 **Golden rule:** everything in `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES` comes from
> the user and could be **anything**, including an attack. Never trust it blindly. This is
> the root of almost all security in doc #2.

The `??` above is the **null-coalescing operator**: `$a ?? $b` means "use `$a`, but if it
doesn't exist or is null, use `$b` instead." It prevents "undefined variable" errors.

### C.9 Splitting code across files: include & require

Real projects don't put everything in one file. You **include** other files so their code
becomes part of the current one:

```php
<?php
require 'config.php';        // run config.php here. If missing → fatal error, stop.
include 'sidebar.php';       // same, but if missing → warning, keep going.
require_once 'helpers.php';  // require, but only once even if called again.
```

Our old code used this a lot. Every page starts with:

```php
<?php include "_libs/load.php"; ?>
```

…which loads all the shared machinery (database connection, helper functions, etc.). We
have since upgraded to a more modern system (Composer autoloading — Part F).

---

## Part D — Object-Oriented Programming (OOP)

So far we've written **functions** floating around. As a project grows, that gets messy.
**OOP** organizes related data and functions into **classes**. This is the "modern PHP"
style we are moving our project toward.

### D.1 Class vs object

- A **class** is a *blueprint* (e.g., the plan for a car).
- An **object** is a *thing built from the blueprint* (e.g., an actual car).

```php
<?php
class Car {
    public string $color;          // a "property" (data the object holds)

    public function honk(): string {   // a "method" (a function inside a class)
        return "Beep!";
    }
}

// Build an object from the blueprint:
$myCar = new Car();     // "new" creates an object
$myCar->color = "red";  // "->" reaches inside the object to a property
echo $myCar->color;     // "red"
echo $myCar->honk();    // "Beep!"  (calling a method)
```

- **Property** = a variable that belongs to the object (`$color`).
- **Method** = a function that belongs to the object (`honk()`).
- **`->`** = "reach into this object."

### D.2 The constructor

A special method named `__construct` runs automatically when you create the object — used
to set it up:

```php
<?php
class Student {
    public string $name;

    public function __construct(string $name) {
        $this->name = $name;   // "$this" means "this particular object"
    }
}

$s = new Student("Anita");
echo $s->name;   // "Anita"
```

`$this` is how a method refers to *its own* object.

### D.3 Visibility: public / private

Controls who can touch a property or method:

- **`public`** — anyone can use it.
- **`private`** — only code *inside the same class* can use it. This "hides" internal
  details so the rest of the program can't break them.

```php
<?php
class BankAccount {
    private int $balance = 0;   // hidden — outside code can't set it directly

    public function deposit(int $amount): void {
        $this->balance += $amount;   // only this class can change balance
    }
    public function getBalance(): int {
        return $this->balance;
    }
}
```

`void` as a return type means "this method returns nothing."

### D.4 Static methods

A **static** method belongs to the *class itself*, not to any one object. You call it with
`::` and don't need `new`. We use this style a lot in our new `src/` code because these
are utilities, not "things."

```php
<?php
class MathHelper {
    public static function double(int $n): int {
        return $n * 2;
    }
}

echo MathHelper::double(5);   // 10   — note "::" and no "new"
```

A **real** one from our project (`src/Content/Sanitizer.php`) — cleans dangerous HTML:

```php
<?php
namespace SJ\Content;

final class Sanitizer
{
    public static function html(string $html): string
    {
        // ... strips out anything dangerous ...
        return $clean;
    }
}
```

You'd call it as `\SJ\Content\Sanitizer::html($userText)`. (`final` means "no one may
build a modified version of this class" — a safety lock.)

### D.5 Why bother with OOP?

- **Organization** — related code lives together (all image logic in an `Image` class).
- **Reuse** — build many objects from one blueprint.
- **Safety** — `private` hides internals so they can't be misused.
- **Autoloading** — one class per file with a predictable name means the computer can find
  and load it automatically (next part).

---

## Part E — Namespaces (organizing class names)

As you add hundreds of classes, two of them might want the same name (e.g., two `Config`
classes). A **namespace** is like a **folder for class names** — it keeps them from
clashing.

```php
<?php
namespace SJ\Core;    // this file's classes live in the "SJ\Core" namespace

class Config { /* ... */ }
```

The full name of that class is now `SJ\Core\Config`. In our project:

- `SJ\Core\Config` — configuration loader
- `SJ\Core\Db` — database connection
- `SJ\Content\Sanitizer` — HTML cleaner
- `SJ\Content\Registry` — the list of what's editable
- `SJ\Admin\Audit` — the activity log
- `SJ\View\Layout` — the page layout engine

`SJ` is short for **St. Joseph's** — our project's personal prefix.

To use a class from another namespace, you either write its full name with a leading
backslash (`\SJ\Core\Config::all()`) or add a `use` line at the top:

```php
<?php
use SJ\Core\Config;   // "I want to call it just 'Config' in this file"

$settings = Config::all();
```

---

## Part F — Composer & Autoloading (finding classes automatically)

### F.1 The problem

With one class per file, you'd have to write `require` for *every* class you use. In a big
app that's hundreds of `require` lines. Painful and error-prone.

### F.2 The solution: an autoloader

An **autoloader** is code that says: "whenever you use a class you haven't loaded yet, I'll
figure out which file it's in and load it for you." You never write `require` for classes
again.

The rule it follows is a community standard called **PSR-4**. It maps a namespace prefix to
a folder:

> "`SJ\` means the `src/` folder." So `SJ\Core\Db` → `src/Core/Db.php`.
> The namespace path *is* the folder path. Simple and predictable.

### F.3 Composer

**Composer** is the standard tool that (a) generates this autoloader and (b) downloads
third-party libraries. Its config file is `composer.json`. Ours (simplified):

```json
{
    "autoload": {
        "psr-4": { "SJ\\": "src/" }
    }
}
```

That one line means "the `SJ\` namespace lives in `src/`." After running
`composer dump-autoload`, Composer writes a `vendor/autoload.php` file. Load that **once**
at startup and every `SJ\...` class just works:

```php
<?php
require 'vendor/autoload.php';    // do this once (we do it in public_html/bootstrap.php)

$db = \SJ\Core\Db::pdo();          // autoloader finds src/Core/Db.php automatically
```

> In our project you rarely run Composer by hand — `./run.sh` refreshes the autoloader for
> you. Just remember: **new class file in `src/`? The autoloader will find it** as long as
> its namespace matches its folder.

---

## Part G — Databases & SQL

### G.1 What is a database?

A **database** is an organized store of data that survives after the program stops. Think
of a set of **spreadsheets**:

- A **table** = one spreadsheet (e.g., `hero_slides`).
- A **column** = a field/attribute (e.g., `caption_title`).
- A **row** (or "record") = one entry (e.g., one specific slide).

We use **MySQL** (version 8), a popular free database. Example table `hero_slides`:

| id | page_id | caption_title | image_id | position | is_active |
|----|---------|---------------|----------|----------|-----------|
| 1  | 1       | St. Joseph's  | 42       | 0        | 1         |
| 2  | 1       | Annual Day    | 43       | 1        | 1         |

- **`id`** — a unique number for each row (the **primary key**). No two rows share it.
- **`is_active`** — `1` (shown) or `0` (hidden). Booleans are often stored as 1/0.

### G.2 SQL — the language for talking to a database

**SQL** (Structured Query Language) is how you ask the database to do things. The four core
operations are nicknamed **CRUD**: Create, Read, Update, Delete.

**READ** rows (`SELECT`):

```sql
SELECT * FROM hero_slides;                          -- every column, every row
SELECT caption_title FROM hero_slides;              -- just one column
SELECT * FROM hero_slides WHERE is_active = 1;      -- only rows matching a condition
SELECT * FROM hero_slides ORDER BY position;        -- sorted
SELECT * FROM hero_slides WHERE page_id = 1 ORDER BY position;  -- combined
```

**CREATE** a row (`INSERT`):

```sql
INSERT INTO hero_slides (page_id, caption_title, position)
VALUES (1, 'New Slide', 5);
```

**UPDATE** existing rows:

```sql
UPDATE hero_slides SET caption_title = 'Changed' WHERE id = 2;
```

> ⚠️ `WHERE` is critical. `UPDATE hero_slides SET is_active = 0;` with **no** `WHERE`
> would hide **every** slide. Always target the exact rows.

**DELETE** rows:

```sql
DELETE FROM hero_slides WHERE id = 2;
```

### G.3 Relationships & foreign keys

Tables connect to each other. A `hero_slides` row has an `image_id` of `42`, which points
to the row with `id = 42` in the `images` table. That pointer is a **foreign key** — it
links two tables. This avoids repeating the whole image's details on every slide; you just
store its id and look it up.

### G.4 Defining tables — the schema

The **schema** is the blueprint of all tables. Ours lives in `database/schema.sql`. A table
is created with `CREATE TABLE`:

```sql
CREATE TABLE hero_slides (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  -- auto-numbered unique id
  page_id       SMALLINT UNSIGNED NOT NULL,               -- must have a value
  caption_title VARCHAR(120) NOT NULL DEFAULT '',         -- text up to 120 chars
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1
);
```

- `INT`, `SMALLINT`, `TINYINT` — whole numbers of different sizes.
- `VARCHAR(120)` — text up to 120 characters.
- `NOT NULL` — this column must always have a value.
- `DEFAULT 1` — if you don't provide one, use this.
- `AUTO_INCREMENT` — the database assigns the next number automatically.

---

## Part H — How PHP talks to the database: PDO

### H.1 What is PDO?

**PDO** (PHP Data Objects) is PHP's built-in, safe way to run SQL from PHP code. You:

1. **Connect** to the database (once).
2. **Prepare** a SQL statement.
3. **Execute** it (optionally with values).
4. **Fetch** the results.

### H.2 Connecting

Our connection lives in `src/Core/Db.php`. Simplified:

```php
<?php
$pdo = new PDO(
    'mysql:host=db;dbname=stjosephs;charset=utf8mb4',  // where the DB is
    'username',
    'password',
    [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,  // crash loudly on errors
        PDO::ATTR_EMULATE_PREPARES => false,                  // use REAL prepared stmts
    ]
);
```

We keep **one** connection and reuse it (a "singleton") so we don't reconnect on every
query. In our code you just call `db()` and get that shared connection.

### H.3 Reading data

```php
<?php
$stmt = db()->query('SELECT * FROM hero_slides WHERE is_active = 1');
$rows = $stmt->fetchAll();   // an array of rows; each row is an associative array

foreach ($rows as $row) {
    echo $row['caption_title'];   // access a column by name
}
```

### H.4 Prepared statements & placeholders — THE most important habit

**Never** paste user input straight into a SQL string. Instead, put a **placeholder** `?`
where the value goes, then pass the value separately. The database treats it strictly as
*data*, never as commands.

```php
<?php
// ✅ SAFE — the value is sent separately, as pure data
$stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ?');
$stmt->execute([$username]);       // the ? becomes $username, safely
$user = $stmt->fetch();            // one row (or false if none)
```

Compare with the dangerous version:

```php
<?php
// ❌ NEVER DO THIS — user text becomes part of the command
$sql = "SELECT * FROM admin_users WHERE username = '$username'";
```

If `$username` were `' OR '1'='1`, the dangerous version would log the attacker in. This
attack is called **SQL injection** and prepared statements make it impossible. Our whole
project follows one rule: **values always go through `?` placeholders.** (Much more in
doc #2.)

Multiple values, in order:

```php
<?php
$stmt = db()->prepare('UPDATE hero_slides SET caption_title = ? WHERE id = ?');
$stmt->execute([$newTitle, $id]);   // first ? = $newTitle, second ? = $id
```

### H.5 A real repository function from our code

We wrap SQL in small functions called "repositories" (`public_html/_libs/repo.php`) so
pages don't write SQL directly. Here's a real one:

```php
<?php
function repo_hero_slides(int $pageId, bool $includeInactive = false): array
{
    $sql = 'SELECT * FROM hero_slides WHERE page_id = ?'
         . ($includeInactive ? '' : ' AND is_active = 1')
         . ' ORDER BY position, id';
    $st = db()->prepare($sql);
    $st->execute([$pageId]);              // $pageId goes in safely via the ?
    return repo_attach_images($st->fetchAll());
}
```

The page just calls `repo_hero_slides(1)` and gets a clean array of slides — no SQL in the
page. This separation (pages ask *what*, repositories know *how*) keeps things tidy.

---

## Part I — Sessions & Cookies (remembering a user)

HTTP has **no memory** — each request is a stranger. So how does the site remember you're
logged in as you click around? With **sessions** and **cookies**.

- A **cookie** is a tiny piece of text the server asks the browser to store and send back
  on every future request. Think of it as a coat-check ticket.
- A **session** is data the *server* keeps for one user, found using the cookie.

Flow when an admin logs in:

```
1. Admin submits correct password.
2. Server creates a session, stores $_SESSION['admin_id'] = 5 on the SERVER.
3. Server sends back a cookie: "SJADMIN = a1b2c3..." (a random ticket).
4. Browser sends that cookie on every later request.
5. Server reads the cookie → finds the session → sees admin_id = 5 → "logged in!"
```

In code:

```php
<?php
session_start();               // open/resume the session
$_SESSION['admin_id'] = 5;      // remember something (server-side)
// ...later, on another page...
if (!empty($_SESSION['admin_id'])) {
    echo "You are logged in.";
}
```

> 🔒 The cookie holds only a **random ticket**, never the password or "is_admin=true".
> If it held `is_admin=true`, anyone could edit their cookie to become admin. The real
> data stays on the server. Our session code (`public_html/_libs/edit.php`) also makes the
> cookie `HttpOnly` (JavaScript can't read it) and expires it after inactivity — doc #2.

---

## Part J — Docker (running everything the same way everywhere)

### J.1 The problem it solves

"It works on my machine" is a classic developer headache — code behaves differently
depending on which PHP/MySQL versions are installed. **Docker** fixes this by packaging the
app *and* the exact PHP + MySQL it needs into **containers** — self-contained boxes that run
identically on any computer.

- A **container** = a lightweight, isolated mini-computer running one thing (e.g., "PHP +
  Apache web server", or "MySQL database").
- An **image** = the frozen blueprint a container is started from.
- **docker-compose** = a way to describe *several* containers that work together, in one
  file (`docker-compose.yml`).

### J.2 Our setup

We have two containers:

1. **web** — PHP 8.3 + the Apache web server. Serves the site.
2. **db** — MySQL 8. Stores the data.

`./run.sh` starts both, waits for the database, and loads sample data. You never install
PHP or MySQL on your own laptop — Docker runs them for you. In **production** (the real
MilesWeb hosting) there is no Docker; we just upload the files. Docker is only for
development convenience.

> Don't over-study Docker right now. For daily work you only need: `./run.sh` to start,
> `./run.sh stop` to stop, `./run.sh reset` to wipe the database.

---

## Part K — Putting it ALL together: one request through our app

Let's trace what happens when a visitor opens the **home page**. This ties every concept
together.

```
1. Browser requests  GET /  (which is public_html/index.php)

2. index.php runs (it's a "thin controller" — just coordinates):
      require '_libs/load.php';          // loads config, DB, helpers, autoloader
      $page      = repo_page('index');   // ← PDO SELECT: get the page row
      $principal = repo_profile('principal');  // ← PDO SELECT: principal's info
      $features  = repo_unique_features();      // ← PDO SELECT: the feature blocks
      SJ\View\Layout::render('home', [...]);    // hand data to the layout engine

3. The Layout engine (src/View/Layout.php):
      - runs views/pages/home.php  → builds the body HTML using the data
      - wraps it in views/layout.php → adds <head>, fonts, <body>

4. While building the body, helper functions run:
      e($principal['person_name'])   → escapes text so it's safe to show
      img_tag($f['image'], ...)      → builds an <img> tag for a photo

5. index.php has now PRINTED a full HTML page.

6. Apache sends that HTML back to the browser.

7. Browser draws it, then loads the CSS and JS it references.
```

Every arrow above is a concept you just learned: routing to a PHP file, `require`,
functions, PDO `SELECT`s returning associative arrays, a class method (`Layout::render`),
`foreach` loops building HTML, and `e()`/escaping for safety.

---

## You now know the fundamentals ✅

You can read almost any file in this repo now. When you hit something unfamiliar, come back
to the relevant part above.

Next: **[`02-security.md`](02-security.md)** — the attacks that target web apps, and the
exact defenses we built. This is the most important file for working on admin code.
