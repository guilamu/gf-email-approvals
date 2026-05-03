# ONBOARDING

## Purpose

This file is for a future AI agent or developer resuming work on this plugin quickly.

The public/plugin-facing documentation lives in `README.md`.
This file is the working-context shortcut.

## Plugin Summary

`Email Approvals for Gravity Forms` adds an approval workflow to Gravity Forms entries.

Current behavior:
- Entries can move through `pending`, `approved`, and `rejected` states.
- Approval requests are sent through Gravity Forms notifications using custom events.
- Approvers receive one-time tokenized links by email.
- Admin users can approve, reject, or reset status from the entry detail screen and via bulk actions.
- The plugin also includes:
  - GitHub auto-update support
  - Guilamu Bug Reporter integration
  - translation support with `languages/`
  - AGPL-3.0 licensing

## Key Files

`gf-email-approvals.php`
Main plugin bootstrap. Handles plugin metadata, updater wiring, bug reporter registration, plugin row links, and textdomain loading.

`includes/class-gf-email-approvals-addon.php`
Main Gravity Forms add-on logic. Most behavior changes happen here.

`includes/class-gf-email-approvals-token-store.php`
Token table creation and token lifecycle.

`includes/class-github-updater.php`
GitHub release updater and WordPress plugin details modal.

`languages/gf-email-approvals.pot`
Translation template.

`languages/gf-email-approvals-fr_FR.po`
French translation source.

`languages/gf-email-approvals-fr_FR.mo`
Compiled French translation loaded by WordPress.

## Current Architecture Notes

- The plugin is a Gravity Forms `GFAddOn` implementation.
- The bootstrap uses `gform_loaded` and a dedicated bootstrap class.
- Capabilities are declared explicitly on the add-on class.
- Entry status is stored in Gravity Forms entry meta under `approval_status`.
- Token storage uses a custom database table.
- Token rows store hashes, not raw tokens.
- Public decision links go through a confirmation screen before the action is applied.
- Audit trail already exists in entry notes, including who acted, when the status changed, and the action source.
- The GitHub updater reads `README.md` for the plugin details modal.
- Translations are explicitly loaded with `load_plugin_textdomain()`.

## Validation Commands

Use these first after any PHP change:

```powershell
php -l "c:\Temp\Email Approvals for Gravity Forms\gf-email-approvals\gf-email-approvals.php"
php -l "c:\Temp\Email Approvals for Gravity Forms\gf-email-approvals\includes\class-gf-email-approvals-addon.php"
php -l "c:\Temp\Email Approvals for Gravity Forms\gf-email-approvals\includes\class-github-updater.php"
```

Useful reality check:
- verify the affected admin flow in Gravity Forms entry detail or entry list
- verify public approval links when touching token or confirmation logic
- verify the plugin details modal when touching updater/readme parsing

## Known Constraints

- This workspace is not a git repository.
- `wp` / WP-CLI was not available in this environment.
- `msgfmt` was not available in this environment.
- The current `.pot` and `.mo` files were generated manually during this session, not through a committed build script.
- Do not assume a dedicated plugin settings screen exists; most configuration is done through Gravity Forms notifications.

## Known Product Decisions

- Keep the plugin minimal and aligned with native Gravity Forms admin UX.
- Keep approval workflow single-step for now.
- Keep optional approver comments on the public confirmation page for a later slice, not the current one.
- Keep public documentation in `README.md` and operational resume notes here.
- License is now `AGPL-3.0`.
- Text domain is `gf-email-approvals`.

## When Editing

- If changing user-facing strings, update translations.
- If changing the updater modal content, remember it depends on `README.md`.
- If changing approval statuses or text labels, check:
  - entry detail UI
  - entry list column/filter/bulk actions
  - public confirmation/result pages
  - notification merge tags
  - `languages/*.po` and `.pot`

## Good Next Checks

If you resume work later, start here:
1. Read `README.md` for the current public-facing description.
2. Read `gf-email-approvals.php` for bootstrap/integration points.
3. Read `includes/class-gf-email-approvals-addon.php` for the active workflow.
4. Run the 3 `php -l` commands above before and after changes.