# Git Workflow

This repo uses a simple local flow:

- `main` is the deploy branch.
- `develop` is the working branch.
- Pull requests from `develop` to `main` use a real **merge commit**
  (GitHub's "Create a merge commit" button — the only option enabled on this
  repo). Not squash, not rebase-merge. A merge commit leaves every commit's
  original SHA untouched, so `develop`'s tip becomes an ancestor of the new
  `main` tip and the sync step below is a plain fast-forward — no reset, no
  force-push, ever.

## Before A PR Is Merged

Rebase if `develop` still contains unmerged work and `main` has moved
forward, so the PR stays current:

```bash
git checkout develop
git fetch origin
git rebase origin/main
git push --force-with-lease origin develop
```

## After A PR Is Merged

Fast-forward `develop` to the updated `main`:

```bash
git checkout develop
git fetch origin
git merge --ff-only origin/main
git push origin develop
```

If this ever refuses to fast-forward, something merged into `main` outside
this flow (e.g. a squash or rebase-merge slipped through) — fall back to
`git reset --hard origin/main && git push --force-with-lease origin develop`
in that case.

## Rule Of Thumb

- Rebase `develop` onto `origin/main` while the PR work is still unmerged.
- Fast-forward `develop` to `origin/main` after that work has been merged.
