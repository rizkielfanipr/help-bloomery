---
name: feature-delivery
description: "Help Bloomery's end-to-end delivery workflow: documentation from the docs/templates, a GitHub branch before any code change, implementation, then module docs, report, and a Pull Request. Activate whenever the user plans, requests, or starts a new feature, a change to an existing module, a remediation, or a bug fix that changes behaviour — including Indonesian requests such as 'buatkan fitur', 'planning fitur', 'ubah alur', 'perbaiki modul', 'buat PRD', 'implementasikan' — and when finishing such work ('sudah selesai', 'buatkan PR'). Do not use for pure questions, read-only audits, or production server operations."
metadata:
  author: help-bloomery
---

# Feature Delivery Workflow

Every new feature or behaviour change follows four stages in order: **Plan → Branch → Implement → Deliver**. The user has authorised this flow to run without asking at each step: creating documentation, creating the branch, committing, pushing the branch, and opening the Pull Request. Merging, approving deployments, force-pushing anything other than the agent's own PR branch, and touching production remain the user's decision.

Reply to the user in Bahasa Indonesia. Commit messages and PR titles follow the repository convention (English, `feat:` / `fix:` / `docs:` / `ci:` / `test:` / `ui:` prefixes).

## Documentation is the contract

- `docs/products/{domain}/README.md` describes how the module behaves **now**; a feature `prd.md` is the specification the code must implement. Code follows these documents: do not introduce behaviour, permissions, statuses, or menus that the PRD/README does not describe — update the document first (Stage 1), then the code.
- If the code and the documentation disagree, do not silently pick one. Tell the user what differs, then fix the code or the document in the same branch so they match again.
- Every PR that changes behaviour updates the module README in the same PR (CI checks links and the code map; reviewers check the content).

## Loading context without exploring the whole codebase

Exploring old code is the most expensive part of a session. Build context in this order and stop as soon as it is enough:

1. `docs/products/{domain}/README.md` — menus, permissions, workflow, statuses, rules, and the **Peta kode** table.
2. Only the files in **Peta kode** that the task touches. Use `rg` inside those paths, not across the repository.
3. `docs/architecture/data-model.md` or the Boost `database-schema` tool for tables — not migrations.
4. `docs/architecture/panels.md` / `rbac.md` for navigation and permissions; `config/permissions.php` for exact names.
5. Repository-wide search only when the steps above leave a concrete gap. When that happens, add what was missing to the module's **Peta kode** (or README) in the same branch so the next session does not search again.

Read standards (`docs/standards/*`) and long PRDs by section (`§`), not end to end.

## Stage 1 — Plan (documentation first)

1. Identify the domain from the Back Office menu group (see `docs/architecture/panels.md`) and read `docs/products/{domain}/README.md`, which describes how the module works today, following the context order above.
2. Look for an existing feature folder in `docs/products/{domain}/`.
   - **Exists** → update its `prd.md` instead of creating a second document. Add new requirements, mark changed ones, and record the change in the PRD's status table. Never keep two canonical documents for one need.
   - **New** → `cp -R docs/templates/feature docs/products/{domain}/{feature}`, rename `prd-template.md` → `prd.md` (and `implementation-plan`/`implementation-prompt`/`rollout` only when needed), delete unused template files, fill every placeholder and the front matter (`status: draft`).
   - A fix to existing behaviour with several findings → use `docs/templates/remediation/`.
3. Base the PRD on an audit of the current code (models, Actions, Policies, permissions, ESB contracts). Do not invent owners, dates, or statuses; sections that do not apply say `Not applicable — {reason}`.
4. List open decisions in the PRD and ask the user only the ones that block implementation. Wait for the user's go-ahead before writing application code when the PRD is new or its scope changed materially.
5. Small bug fixes that do not change documented behaviour may skip the PRD; they still update the module README if any described behaviour changes.

## Stage 2 — Branch (before any code change)

```bash
git status --short                      # must be clean; stop and ask if it is not
git switch main
git pull --ff-only origin main
git switch -c {type}/{short-kebab-slug}  # feature/, fix/, docs/, ci/, refactor/
```

- Never commit on `main`. One concern per branch; do not mix unrelated changes (move them to their own branch).
- Prefer branching from the latest `main` over stacking on another open PR. If stacking is unavoidable and the base PR is squash-merged first, rebase with `git rebase --onto origin/main {old-base-commit} {branch}` and push with `--force-with-lease`.
- The documentation from Stage 1 lives on this branch too, so the PR carries docs and code together.
- Set the PRD front matter `status: in-development`.

## Stage 3 — Implement

- Follow `CLAUDE.md`/`AGENTS.md` rules (Actions for mutations, Policies, `php artisan make:*`, tests for every change, Pint).
- Keep the PRD as the requirement source. If the implementation must deviate, note the deviation and its reason (it goes in the report).
- New permissions go in `config/permissions.php`; deploy runs `permissions:sync`, but roles must be granted through the UI — mention this in the PR.

## Stage 4 — Deliver

1. **Verify**
   - Targeted tests: `php artisan test --compact {files}`.
   - Wide changes: run the Feature suite in CI batches (`SHARD_INDEX=n SHARD_TOTAL=4 bash ops/ci/feature-shard.sh` for n = 1..4) — a single full run can exhaust memory.
   - `vendor/bin/pint --dirty --format agent`, `bash ops/ci/check-doc-links.sh`, `bash ops/ci/check-code-map.sh`, `git diff --check`.
2. **Update documentation in the same branch**
   - `docs/products/{domain}/README.md`: menus/tiles and permissions, workflow and statuses, rules, schedules, data, related PRD status, and **Peta kode** (add new folders/files; remove ones that no longer exist). This README must match the code after the PR.
   - PRD: `status: implemented` and `updated_at` when the feature is complete in this PR.
   - Report: create `docs/products/{domain}/{feature}/reports/implementation-report.md` from `docs/templates/feature/implementation-report-template.md` when the work had phases, deviations, data migrations, or decisions worth keeping. Skip it for small changes.
   - `docs/architecture/*` when panels, RBAC, scheduler, integrations, or models changed (regenerate `data-model.md`; do not hand-edit parts of it).
   - Delete the `implementation-prompt.md` once the feature is complete.
3. **Commit and push** with a conventional message, ending with the attribution line required by the session. `git push -u origin {branch}`.
4. **Open the Pull Request** to `main` using `.github/pull_request_template.md`, written in Bahasa Indonesia, with every section filled (Perubahan, Alasan, Modul terdampak, Migration, Queue/scheduler, Integrasi eksternal, Verifikasi with real results, Uji staging steps).
   - If `gh` is available: `gh pr create --base main --head {branch} --title "..." --body-file {file}`.
   - Otherwise: save the body to the scratchpad and give the user the compare link `https://github.com/rizkielfanipr/help-bloomery/compare/main...{branch}?expand=1` plus the body file path.
5. **Hand over**: tell the user what the PR contains, what was verified, deploy notes (migrations, env, permissions to grant), and anything left open. Do not merge or approve deployments.

## After merge (when the user says it is merged)

- `git switch main && git pull --ff-only origin main`, then delete the merged local branch with `git branch -D {branch}` (squash merges make `-d` refuse).
- If staging/production testing is needed, give the user the steps; never approve the production environment yourself.
