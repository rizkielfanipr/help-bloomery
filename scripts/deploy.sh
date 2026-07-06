#!/usr/bin/env bash
# =============================================================================
# Help Bloomery — Server Deployment Script
#
# Called automatically by GitHub Actions after each push.
# Can also be run manually from the server for rollbacks.
#
# Usage:
#   bash ~/deploy/deploy.sh <branch>
#   bash ~/deploy/deploy.sh <branch> --rollback
#   bash ~/deploy/deploy.sh <branch> --rollback <N>   (default N=1)
#
# Branches: main | staging
# =============================================================================
set -euo pipefail

# ── Arguments ─────────────────────────────────────────────────────────────────
BRANCH="${1:?[deploy] ERROR: branch argument required. Usage: deploy.sh <main|staging>}"
ACTION="${2:---deploy}"
ROLLBACK_STEPS="${3:-1}"

# ── Branch → paths ────────────────────────────────────────────────────────────
case "$BRANCH" in
    main)    LIVE_DIR="$HOME/public_html/helpbloomery" ;;
    staging) LIVE_DIR="$HOME/public_html/stg.helpbloomery" ;;
    *)       echo "[deploy] ERROR: Unknown branch '$BRANCH'. Expected: main | staging" >&2; exit 1 ;;
esac

DEPLOY_ROOT="$HOME/deploy"
BRANCH_DIR="$DEPLOY_ROOT/$BRANCH"          # ~/deploy/main  or  ~/deploy/staging
RELEASES_DIR="$BRANCH_DIR/releases"        # ~/deploy/main/releases/
SHARED_DIR="$BRANCH_DIR/shared"            # ~/deploy/main/shared/
CURRENT_LINK="$BRANCH_DIR/current"         # ~/deploy/main/current  (symlink)
INCOMING_ZIP="$DEPLOY_ROOT/incoming/$BRANCH/release.zip"
KEEP_RELEASES=5

# ── Helpers ───────────────────────────────────────────────────────────────────
log()  { echo "[$(date '+%H:%M:%S')] [deploy] $*"; }
fail() { echo "[deploy] FATAL: $*" >&2; exit 1; }

# Point LIVE_DIR at the current symlink.
# On first run LIVE_DIR is a real directory — we back it up and convert it.
activate_live() {
    if [ -d "$LIVE_DIR" ] && [ ! -L "$LIVE_DIR" ]; then
        local backup="${LIVE_DIR}.bak.$(date +%Y%m%d-%H%M%S)"
        log "Converting $LIVE_DIR to symlink — backup: $(basename "$backup")"
        mv "$LIVE_DIR" "$backup"
    fi
    # -sfn: force-replace symlink without following existing symlink target
    ln -sfn "$CURRENT_LINK" "$LIVE_DIR"
    log "Live: $LIVE_DIR → $(readlink -f "$LIVE_DIR")"
}

# =============================================================================
# ROLLBACK
# =============================================================================
do_rollback() {
    log "Rolling back $BRANCH by $ROLLBACK_STEPS step(s)..."

    [ -d "$RELEASES_DIR" ] || fail "No releases directory found at $RELEASES_DIR"

    local -a releases
    mapfile -t releases < <(ls -1dt "$RELEASES_DIR"/*/  2>/dev/null | sed 's|/$||')

    [ "${#releases[@]}" -gt 0 ] || fail "No releases found in $RELEASES_DIR"
    [ "${#releases[@]}" -gt "$ROLLBACK_STEPS" ] || \
        fail "Cannot roll back $ROLLBACK_STEPS step(s): only ${#releases[@]} release(s) available"

    local target="${releases[$ROLLBACK_STEPS]}"
    log "Target release: $(basename "$target")"

    ln -snf "$target" "$CURRENT_LINK"
    activate_live

    log "✓ Rolled back $BRANCH → $(basename "$target")"
    log "To undo this rollback: bash ~/deploy/deploy.sh $BRANCH --rollback 0  (re-deploy instead)"
}

# =============================================================================
# DEPLOY
# =============================================================================
do_deploy() {
    local TIMESTAMP
    TIMESTAMP=$(date +%Y%m%d-%H%M%S)
    local RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"

    log "Deploying $BRANCH @ $TIMESTAMP"

    # ── Bootstrap persistent directory structure ───────────────────────────
    mkdir -p "$RELEASES_DIR"
    mkdir -p "$SHARED_DIR/storage/app/public"
    mkdir -p "$SHARED_DIR/storage/framework/cache/data"
    mkdir -p "$SHARED_DIR/storage/framework/sessions"
    mkdir -p "$SHARED_DIR/storage/framework/views"
    mkdir -p "$SHARED_DIR/storage/logs"

    # ── First-deployment migration: .env ───────────────────────────────────
    # If shared/.env does not exist yet, copy it from the currently live
    # directory so the app keeps its existing configuration.
    if [ ! -f "$SHARED_DIR/.env" ]; then
        if [ -d "$LIVE_DIR" ] && [ ! -L "$LIVE_DIR" ] && [ -f "$LIVE_DIR/.env" ]; then
            cp "$LIVE_DIR/.env" "$SHARED_DIR/.env"
            log "Migrated .env → $SHARED_DIR/.env"
        else
            fail "$SHARED_DIR/.env not found. Create it manually:\n  cp <source>/.env $SHARED_DIR/.env"
        fi
    fi

    # ── First-deployment migration: storage ────────────────────────────────
    # Copy uploaded files and logs from the live directory into shared/storage
    # so they survive the directory→symlink conversion.
    if [ -d "$LIVE_DIR" ] && [ ! -L "$LIVE_DIR" ] && [ -d "$LIVE_DIR/storage" ]; then
        log "Migrating storage → $SHARED_DIR/storage (first deploy only)..."
        cp -rp "$LIVE_DIR/storage/app/."  "$SHARED_DIR/storage/app/"  2>/dev/null || true
        cp -rp "$LIVE_DIR/storage/logs/." "$SHARED_DIR/storage/logs/" 2>/dev/null || true
    fi

    # ── Validate incoming archive ──────────────────────────────────────────
    [ -f "$INCOMING_ZIP" ] || fail "release.zip not found at $INCOMING_ZIP"

    # ── Extract ───────────────────────────────────────────────────────────
    log "Extracting release.zip → $RELEASE_DIR"
    mkdir -p "$RELEASE_DIR"
    unzip -q "$INCOMING_ZIP" -d "$RELEASE_DIR"
    rm -f "$INCOMING_ZIP"

    # ── Wire shared .env ──────────────────────────────────────────────────
    ln -sf "$SHARED_DIR/.env" "$RELEASE_DIR/.env"

    # ── Wire shared storage ───────────────────────────────────────────────
    rm -rf "$RELEASE_DIR/storage"
    ln -sf "$SHARED_DIR/storage" "$RELEASE_DIR/storage"

    # ── Laravel post-deploy optimizations ────────────────────────────────
    log "Running php artisan optimize..."
    cd "$RELEASE_DIR"
    php artisan optimize

    # ── Atomic activation: update current symlink first ───────────────────
    # ln -snf is a single rename(2) syscall — atomic on Linux.
    log "Activating release..."
    ln -snf "$RELEASE_DIR" "$CURRENT_LINK"
    activate_live

    # ── Prune: keep only the N most recent releases ────────────────────────
    log "Pruning old releases (keeping $KEEP_RELEASES)..."
    ls -1dt "$RELEASES_DIR"/*/  2>/dev/null \
        | sed 's|/$||' \
        | tail -n +$((KEEP_RELEASES + 1)) \
        | xargs -r rm -rf

    log "✓ Deployed successfully: $BRANCH @ $TIMESTAMP"
}

# =============================================================================
# ENTRYPOINT
# =============================================================================
case "$ACTION" in
    --deploy)   do_deploy ;;
    --rollback) do_rollback ;;
    *)          fail "Unknown action '$ACTION'. Expected: --deploy | --rollback [N]" ;;
esac
