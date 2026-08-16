# Git Workflow

This repo uses a simple local flow:

- `main` is the deploy branch.
- `develop` is the working branch.
- Pull requests from `develop` to `main` are usually squash-merged.

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

## After A PR Is Squash-Merged

Do not rebase the old `develop` branch after its PR has been squash-merged.
The individual local commits are already represented on `main` by one new
squash commit, but Git cannot automatically match those different SHAs. A
normal rebase can replay already-merged work and create false conflicts.

Instead, reset `develop` to the updated `main` before starting more work:

```bash
git checkout develop
git fetch origin
git reset --hard origin/main
git push --force-with-lease origin develop
```

After this, continue new work on `develop`.

## Rule Of Thumb

- Rebase `develop` onto `origin/main` while the PR work is still unmerged.
- Reset `develop` to `origin/main` after that work has been squash-merged.
