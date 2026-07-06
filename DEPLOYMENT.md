# Deployment Guide — Help Bloomery

## Overview

Deployment uses GitHub Actions → SCP → SSH. On every push to `main` or `staging`, the workflow:

1. Builds the Laravel app and frontend assets on the CI runner
2. Packages everything into a single `release.zip`
3. Uploads the zip to the server via SCP
4. SSHs into the server and runs `scripts/deploy.sh`
5. The deploy script extracts the release, wires shared files, runs `php artisan optimize`, and atomically activates the new release via a symlink swap

FTP is gone entirely. The first deployment takes seconds (one zip upload) instead of hours.

---

## Folder Structure on Server

```
~/
├── deploy/
│   ├── incoming/
│   │   ├── main/           ← release.zip lands here (cleaned after extract)
│   │   └── staging/
│   ├── main/
│   │   ├── releases/
│   │   │   ├── 20260706-101530/   ← old release
│   │   │   └── 20260706-114500/   ← current release
│   │   ├── shared/
│   │   │   ├── .env               ← persistent .env (never overwritten)
│   │   │   └── storage/           ← persistent storage (uploads, logs, cache)
│   │   └── current -> releases/20260706-114500   ← active release symlink
│   ├── staging/
│   │   ├── releases/
│   │   ├── shared/
│   │   └── current -> releases/...
│   └── deploy.sh           ← uploaded fresh on every deploy
│
└── public_html/
    ├── helpbloomery -> ~/deploy/main/current        ← main symlink
    └── stg.helpbloomery -> ~/deploy/staging/current ← staging symlink
```

Each release directory contains the full extracted Laravel project. The `storage/` and `.env` inside each release are symlinks into `shared/` so they are never affected by deployments.

---

## GitHub Secrets

Add these in **GitHub → Repository → Settings → Secrets and variables → Actions**:

| Secret | Description | Example |
|---|---|---|
| `SSH_HOST` | Server IP or hostname | `103.x.x.x` |
| `SSH_PORT` | SSH port (Rumahweb cPanel uses 2222) | `2222` |
| `SSH_USER` | cPanel SSH username | `myusername` |
| `SSH_PRIVATE_KEY` | Full private key (Ed25519 recommended) | `-----BEGIN OPENSSH PRIVATE KEY-----...` |

### Generating an SSH key pair

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/helpbloomery_deploy
```

- Add the **public key** (`helpbloomery_deploy.pub`) to cPanel → SSH Access → Manage Keys → Import
- Add the **private key** (`helpbloomery_deploy`) as the `SSH_PRIVATE_KEY` secret in GitHub

---

## First Deployment (Migration from FTP)

The deploy script automatically handles the one-time migration from the existing FTP-deployed directory.

On the **first push**, the script detects that `~/public_html/helpbloomery` is a real directory (not yet a symlink) and:

1. Copies the existing `.env` into `~/deploy/main/shared/.env`
2. Copies the existing `storage/app/` and `storage/logs/` into `~/deploy/main/shared/storage/`
3. Renames the existing directory to `helpbloomery.bak.<timestamp>` as a safety net
4. Creates the `~/public_html/helpbloomery` symlink pointing to the new release

**Before your first push**, verify these on the server:

```bash
# Confirm the existing .env is correct
cat ~/public_html/helpbloomery/.env

# The script will auto-migrate, but you can do it manually too:
mkdir -p ~/deploy/main/shared
cp ~/public_html/helpbloomery/.env ~/deploy/main/shared/.env
```

---

## How Deployment Works Step by Step

```
GitHub Actions runner
└── checkout + build (PHP deps, npm, Vite)
└── create release.zip (vendor included, storage/logs excluded)
└── SSH: mkdir ~/deploy/incoming/main
└── SCP: release.zip → ~/deploy/incoming/main/
└── SCP: deploy.sh   → ~/deploy/deploy.sh
└── SSH: bash ~/deploy/deploy.sh main

Server: deploy.sh main
└── mkdir -p releases/, shared/
└── migrate .env and storage/ (first deploy only)
└── unzip release.zip → releases/20260706-114500/
└── rm release.zip
└── ln -sf shared/.env       → releases/.../  .env
└── ln -sf shared/storage    → releases/.../  storage
└── php artisan optimize
└── ln -snf releases/20260706-114500 current   ← atomic
└── ln -sfn ~/deploy/main/current public_html/helpbloomery
└── prune releases (keep 5)
```

---

## Rollback

SSH into the server and run the deploy script with `--rollback`:

```bash
# Roll back 1 step (to the previous release)
bash ~/deploy/deploy.sh main --rollback

# Roll back 2 steps
bash ~/deploy/deploy.sh main --rollback 2

# Same for staging
bash ~/deploy/deploy.sh staging --rollback
```

Rollback is instant — it only changes which symlink `current` points to. No files are extracted or moved.

### Listing available releases

```bash
ls -1dt ~/deploy/main/releases/*/
```

The top entry is the current release. Lower entries are candidates for rollback.

### Checking the active release

```bash
readlink -f ~/public_html/helpbloomery
# Output: /home/username/deploy/main/releases/20260706-114500
```

---

## Restoring a Specific Previous Release

If you need to activate a release by name (not just N steps back):

```bash
ln -snf ~/deploy/main/releases/20260706-101530 ~/deploy/main/current
```

The symlink swap is atomic. The site switches instantly.

---

## Shared Files — .env and Storage

These files live outside any release in `~/deploy/<branch>/shared/` and are symlinked into each release on deploy. They are **never modified or deleted** by the deploy process.

| Path | What it contains |
|---|---|
| `shared/.env` | Database credentials, app key, mail config, etc. |
| `shared/storage/app/` | User uploads, generated files |
| `shared/storage/logs/` | Laravel log files |
| `shared/storage/framework/` | Cache, sessions, compiled views (rebuilt by `optimize`) |

### Editing .env on the server

```bash
nano ~/deploy/main/shared/.env
# Then clear config cache:
cd ~/deploy/main/current && php artisan config:clear
```

---

## PHP CLI on Rumahweb cPanel

If `php artisan optimize` fails with a wrong PHP version, find the correct binary:

```bash
which php
php -v
# If it's not 8.4:
/opt/cpanel/ea-php84/root/usr/bin/php -v
```

Update line `php artisan optimize` in `scripts/deploy.sh` to use the full path.

---

## open_basedir Consideration

This deployment places releases in `~/deploy/` and symlinks them into `~/public_html/`. PHP must be allowed to follow these symlinks.

Most Rumahweb cPanel plans set `open_basedir` to `/home/username/`, which covers both `public_html/` and `deploy/` — so symlinks work transparently.

If you see PHP errors like `open_basedir restriction in effect`, contact Rumahweb support and request that `open_basedir` include your home directory (`/home/username/`).

---

## Concurrency

The workflow uses GitHub Actions concurrency groups per branch:

```yaml
concurrency:
  group: deploy-${{ github.ref_name }}
  cancel-in-progress: true
```

If you push twice quickly to `main`, the first deploy is cancelled and only the second runs. `main` and `staging` deploys can run in parallel because they use separate concurrency groups and separate inbox directories on the server.

---

## Keeping Releases Clean

The deploy script automatically deletes releases beyond the newest 5. To change this, edit `KEEP_RELEASES` at the top of `scripts/deploy.sh`:

```bash
KEEP_RELEASES=5
```

To manually delete all but the current release:

```bash
current=$(readlink ~/deploy/main/current)
ls -1dt ~/deploy/main/releases/*/ | grep -v "$current" | xargs rm -rf
```
