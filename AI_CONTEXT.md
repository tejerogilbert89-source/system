# AI context brief - read this before helping with this project

If you're an AI assistant (Claude, ChatGPT, Gemini, Copilot, or anything
else) being asked to help build a feature for this project: paste this
whole file into the chat first, before describing the task. It contains
decisions already made for this codebase - please follow them rather than
suggesting your own defaults.

## What this project is

A Library Management System for a school library. Capstone / thesis
project. Modules: OPAC (public catalog), user management, borrowing,
reservations, fines, inventory, reports, audit trails.

## Hard constraints - do not suggest alternatives to these

- **No frameworks.** Plain PHP 8.2+, HTML, CSS, JS, MySQL only. Do not
  suggest Laravel, Composer, Node build tools, or any package manager for
  the PHP side.
- **PDO only, prepared statements only.** Never build SQL with string
  concatenation. Never use `mysqli`.
- **Sessions for auth**, not JWTs or third-party auth services.
- **Money is always `DECIMAL(10,2)`**, never float.

## Folder structure (grouped by feature, not by file type)

```
public/            <- the only folder a browser can reach. Router lives here.
core/              <- shared code every feature uses (Database, Auth, Csrf, Audit)
features/<name>/   <- one folder per feature
  routes.php           returns [[method, path, absolute file path], ...]
  pages/*.php          the actual screens
  *.php                feature-specific classes (e.g. Loan.php, Policy.php)
templates/         <- header.php, footer.php, layout.php (shared page shell)
database/          <- schema.sql (full schema), seed.php (starting data)
config/            <- config.php (real, gitignored), config.sample.php (template)
```

## Conventions that must be followed exactly

1. **Class file names must exactly match the class name inside** -
   `Loan.php` must contain `class Loan`. The autoloader
   (`core/autoload.php`) finds classes this way; a mismatch means the
   class silently fails to load.
2. **Every feature owns a `routes.php`** returning an array of
   `[HTTP method, URL path, absolute file path via __DIR__]`. The router
   (`public/index.php`) collects these from every feature automatically -
   never hardcode a route inside `index.php` itself.
3. **Features do not query another feature's database tables directly.**
   If the fines feature needs loan data, it calls a method on
   circulation's `Loan` class - it does not write its own `SELECT` on the
   `loans` table. This keeps features safe to change independently.
4. **Every form that changes data includes `Csrf::field()`**, and the
   handler calls `Csrf::verify($_POST['csrf_token'] ?? null)` before doing
   anything else.
5. **Every action that changes data calls `Audit::log(...)`** right after
   the change succeeds. See `features/auth/pages/login.php` for the
   pattern (logs both success and failure).
6. **Passwords are hashed with `password_hash($pw, PASSWORD_ARGON2ID)`**,
   verified with `password_verify()`. Never store or compare plain text.
7. **Pages return HTML by just writing it directly** (no separate template
   engine) - the router wraps every page's output in
   `templates/header.php` and `templates/footer.php` automatically via
   `templates/layout.php`. A page can still safely call
   `header('Location: ...'); exit;` at any point to redirect, because
   output is buffered until the page finishes running.

## Design system - reuse it, don't invent new styles

Colors and components are already defined in
`public/assets/shared/style.css` as CSS variables and classes. Use the
existing classes (`.btn`, `.btn-primary`, `.btn-secondary`, `.card`,
`.badge` + `.badge-available/out/overdue/hold`, `.stat-number`, standard
`<table>` styling) rather than writing new one-off CSS for a new page.

- Colors: `--pine #1B4332`, `--moss #2D6A4F`, `--sage #E3ECD9`,
  `--paper #FAF9F4`, `--ink #1B1F1C`, `--brass #B8935A`
- Fonts: Fraunces (serif) for headings, Inter (sans) for body/UI/tables

## Database

Full schema already exists in `database/schema.sql` (all modules, ~26
tables) - don't create new tables casually; check if one already fits.
Starting data comes from `database/seed.php`. Test login:
`ADMIN-0001` / `admin123`.

## For more detail

- `README.md` - full local setup instructions, including the Virtual Host
  configuration required to run this locally.
- `CONTRIBUTING.md` - Git branching workflow and who owns which feature
  folder.
