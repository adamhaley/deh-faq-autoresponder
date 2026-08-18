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

GitHub's "Rebase and merge" button replays each commit's *content* onto
`main` unchanged, but it rewrites committer metadata (date, committer
identity) in the process, which gives every replayed commit a new SHA. So
`develop` and `main` end up with identical content but different hashes — a
plain `git merge --ff-only` will refuse, since `develop` is no longer a
literal ancestor of `main`.

Reset `develop` to the updated `main` before starting more work:

```bash
git checkout develop
git fetch origin
git reset --hard origin/main
git push --force-with-lease origin develop
```

After this, continue new work on `develop`.

## Rule Of Thumb

- Rebase `develop` onto `origin/main` while the PR work is still unmerged.
- Reset `develop` to `origin/main` after that work has been rebase-merged.
