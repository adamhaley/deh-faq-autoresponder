# Git Workflow

This repo uses a simple local flow:

- `main` is the deploy branch.
- `develop` is the working branch.
- Pull requests from `develop` to `main` are **rebase-merged** (GitHub's
  "Rebase and merge" button), not squashed. Commits on `develop` should stay
  atomic, since each one lands on `main` unchanged rather than getting
  collapsed into one.

## Before A PR Is Merged

Use rebase when `develop` still contains unmerged work and `main` has moved
forward:

```bash
git checkout develop
git fetch origin
git rebase origin/main
git push --force-with-lease origin develop
```

This keeps the PR current with `main` without creating a merge commit.

## After A PR Is Rebase-Merged

Because rebase-merging replays `develop`'s commits onto `main` unchanged, the
SHAs match. `develop` just needs a fast-forward, no reset required:

```bash
git checkout develop
git fetch origin
git merge --ff-only origin/main
git push origin develop
```

After this, continue new work on `develop`.

## Rule Of Thumb

- Rebase `develop` onto `origin/main` while the PR work is still unmerged.
- Fast-forward `develop` to `origin/main` after that work has been
  rebase-merged.
