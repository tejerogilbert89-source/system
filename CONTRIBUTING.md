# Working together on this project

This is a quick guide for anyone on the team, so we all work the same way
and don't step on each other's changes.

## Before you touch any code

1. Follow the setup steps in `README.md` on your own PC - your own local
   database, your own `config/config.php`, and your own Virtual Host
   (`lms.local`). These are personal to your computer and are never shared
   through Git.
2. Make sure `git status` never shows `config/config.php` as a file to be
   committed. If it does, stop and ask - it means something is set up
   wrong.

## Who owns what

Each feature lives in its own folder under `features/`, and mostly doesn't
need to touch any other feature's files. Stick to your own folder as much
as possible - this is the single biggest thing that keeps us from getting
merge conflicts.

| Person | Owns |
|---|---|
| _(fill in)_ | `features/auth/`, `features/opac/` |
| _(fill in)_ | `features/circulation/` |
| _(fill in)_ | `features/fines/`, `features/inventory/` |
| _(fill in)_ | `features/reports/`, `features/dashboard/` |

Shared files - `core/`, `templates/`, `public/assets/shared/style.css`,
`database/schema.sql` - are used by everyone. If you need to change one of
these, say so in the group chat first, so two people don't edit the same
shared file at the same time.

## The workflow: branches, not direct commits to `main`

`main` should always be code that actually works. Nobody edits `main`
directly - everyone works on their own branch, and merges it in once it's
done.

**Every time you start work, first make sure you have the latest code:**
```
git checkout main
git pull origin main
```

**Then create a branch for what you're about to do:**
```
git checkout -b feature/circulation
```
Name it after the feature or task, not your own name.

**While you work, commit in small, clear steps:**
```
git add .
git commit -m "Add checkout page"
```
Small commits ("Add checkout page") are easier to fix than one giant commit
("finished half the feature").

**When you're done (or want feedback), push your branch:**
```
git push origin feature/circulation
```

**Then open a Pull Request on GitHub:**
Go to the repository, you'll see a banner offering to open a Pull Request
for your branch. Write a short description of what it does, and either
merge it yourself or ask a teammate to glance over it first.

**After any Pull Request gets merged, everyone else should run:**
```
git checkout main
git pull origin main
```
...before starting their next task, so nobody is building on old code.

## If Git shows a merge conflict

Don't guess. Stop, and either ask a teammate who knows Git better, or paste
the exact conflict message to Claude for help. Conflicts are easy to fix
calmly and easy to make worse by clicking around randomly.

## Quick checklist before you start any task

- [ ] `git checkout main && git pull origin main`
- [ ] `git checkout -b feature/whatever-you're-doing`
- [ ] Stick to your assigned feature folder
- [ ] Commit in small pieces, with clear messages
- [ ] Push your branch, open a Pull Request, don't merge straight into `main` without at least glancing it over
