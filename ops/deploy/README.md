# Atomic release deployment

These scripts implement the release contract documented in
`docs/operations/ci-cd/release-pipeline.md`.

## Files

- `build-artifact.sh`: builds one immutable Laravel release archive and checksum.
- `receive-release.sh`: validates the forced SSH command and streams an archive into `incoming`.
- `deploy-release.sh`: validates, extracts, migrates, atomically activates, checks, and retains a release.
- `rollback-release.sh`: atomically selects a retained release and verifies it.
- `health-check.sh`: verifies Laravel, Redis, the Vite manifest, and the environment `/up` URL.

## Required server layout

```text
/var/www/bloomery/<environment>/
├── current -> releases/<run>-<sha8>
├── incoming/
├── releases/
└── shared/
    ├── .env
    ├── health-url
    └── storage/
```

`health-url` contains exactly one environment-specific HTTPS `/up` URL. It is read on the server;
the workflow does not decide which hostname represents an environment.

The deployment scripts are installed under `/home/deploy/bin/bloomery-release` and remain owned by
`deploy`. The existing forced-command gateway must explicitly delegate only commands matching:

```text
release <staging|production> <positive-run-number> <40-char-sha> <sha256>
```

Do not replace the existing gateway until the staging bootstrap checklist has passed. Production
continues to use the legacy deployment command throughout Phase 2.

## Phase 2 activation order

1. Merge the repository changes while leaving `ATOMIC_STAGING_ENABLED` unset. A manual workflow run
   may build an artifact, but the staging deploy job remains skipped.
2. Create the staging `incoming`, `releases`, and `shared/storage` directories without changing the
   production layout.
3. Copy the current staging `.env` and storage data into `shared`; do not move or delete the legacy
   staging application.
4. Create `shared/health-url` with the verified staging `/up` URL.
5. Install these reviewed scripts in `/home/deploy/bin/bloomery-release` and add an explicit
   `release ...` delegation to the existing forced-command gateway. Keep its legacy deploy branch.
6. Create the first release, point staging Nginx to `staging/current/public`, run `nginx -t`, reload,
   then repeat the external health check. The first pre-cutover HTTP check still reaches the legacy
   Nginx root, so it is not sufficient on its own.
7. Set the staging Environment variable `ATOMIC_STAGING_ENABLED=true` only after the preceding checks
   pass.
8. Complete three staging releases and one rollback simulation before starting Phase 3.

Never set the corresponding production gate or change the production Nginx root during Phase 2.

## Phase 4 production activation order

The production workflow is disabled unless the repository variable
`ATOMIC_PRODUCTION_ENABLED=true` is present. The server independently rejects production uploads
until `/var/www/bloomery/production/shared/atomic-release-enabled` exists. Both gates must remain
disabled while production still uses the legacy document root.

1. Keep `ATOMIC_PRODUCTION_ENABLED` unset and remove or disable
   `PRODUCTION_APPROVAL_REHEARSAL_ENABLED` after the Phase 3 evidence is recorded.
2. Create the production release layout and copy the active production `.env` and storage into
   `shared` without modifying the legacy application.
3. Create an initial release representing the currently active production commit and verify it
   through a local-only Nginx preflight endpoint.
4. Back up the production Nginx configuration, change its root to `production/current/public`, run
   `nginx -t`, reload, and verify `/up` plus the required smoke checks.
5. Create the server marker `shared/atomic-release-enabled` only after the cutover is healthy.
6. Set `ATOMIC_PRODUCTION_ENABLED=true`. The next approved production job will stream the same
   artifact that already passed staging.
7. Retain the legacy production directory and Nginx backup until at least three atomic production
   releases and one rollback rehearsal are successful.

If cutover verification fails, restore the previous Nginx root immediately. Do not roll back a
database migration automatically.

## Local verification

```bash
bash -n ops/deploy/*.sh
bash ops/deploy/tests/contracts-test.sh
```

The contract test uses temporary directories only. It does not contact a server or database.
