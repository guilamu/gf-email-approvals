# Email Approvals for Gravity Forms

Send approval requests for Gravity Forms entries with secure email decision links and native admin status tracking.

## Configure Approval Emails

- Choose the `Approval Request`, `Approval Approved`, and `Approval Rejected` notification events directly in Gravity Forms.
- Generate approve and reject URLs or HTML buttons with dedicated merge tags inside notification bodies.
- Override button labels inline with advanced merge tags such as `{approval_approve_button:Approve this request}` or `{approval_reject_button:Reject this request}`.
- Customize the public confirmation and result page text directly on each `Approval Request` notification, with Gravity Forms merge tags and the native selector already available on the notification screen.
- Style the shared public approval card from `Forms → Settings → Email Approvals` with global colors, spacing, radius, and a live preview builder.
- Update one supported field after approval or rejection, either with a predefined automatic value or with a value chosen by the approver on the confirmation page.
- Match approval emails to each form workflow while keeping shared appearance controls in one plugin settings screen.

## Review Decisions in Gravity Forms

- Track Pending, Approved, and Rejected states in the entry list and in the entry detail sidebar.
- Change status from the entry detail panel or run bulk approve, reject, and reset actions from the entries table.
- Filter entries by approval status while staying inside the native Gravity Forms admin screens.

## Protect Approval Actions

- Confirm public approval actions before applying them to an entry.
- Invalidate older tokens when a request is resent or when one approver completes the decision.
- Record entry notes and logging-friendly events for auditability.

## Key Features

- **Gravity Forms Native:** Uses custom notification events, entry meta, list filters, and entry detail actions inside Gravity Forms.
- **Field Updates After Confirmation:** Can update one supported field per decision with either automatic values or approver-selected values, and records a detailed audit trail in the entry notes.
- **Global Approval Page Appearance:** Adds a shared appearance builder for the public approval confirmation and result pages, including colors, card width, padding, and radius with a live preview.
- **Decision Tokens:** Creates single-use approval links and invalidates older active tokens automatically.
- **Multilingual:** Works with forms, notifications, and approver emails in any language.
- **Translation-Ready:** User-facing strings use the `gf-email-approvals` text domain.
- **Secure:** Hashes tokens at rest, verifies admin actions with nonces, and checks `gravityforms_edit_entries` before status changes.
- **GitHub Updates:** Supports automatic updates from GitHub releases and a WordPress-style plugin details modal.

## Requirements

- Gravity Forms 2.7 or higher.
- At least one active `Approval Request` notification on each form that should enter the approval workflow.
- WordPress 6.5 or higher.
- PHP 7.4 or higher.
- Optional: Guilamu Bug Reporter for in-dashboard bug reporting.

## Installation

1. Upload the `gf-email-approvals` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Optionally open **Forms → Settings → Email Approvals** and configure the shared `Approval Page Appearance` settings.
4. Open **Forms → Your Form → Settings → Notifications** and create an `Approval Request` notification.
5. Optionally create `Approval Approved` and `Approval Rejected` notifications for post-decision follow-up emails.
6. Submit a test entry and confirm the approver receives working decision links.

## FAQ

### Does this replace standard Gravity Forms notifications?

No. It adds approval-specific events. Disable or adapt the default submission notifications if you do not want emails sent before approval.

### Can an approver use the same link twice?

No. After the first valid decision, the used token is marked as consumed and the remaining active tokens for the entry are invalidated.

### Can an administrator override a decision?

Yes. Users who can edit Gravity Forms entries can approve, reject, or reset status from the entry detail view and in bulk from the entry list.

### Does it support GitHub updates and bug reporting?

Yes. The plugin includes GitHub release updates, a WordPress-style details modal, and optional integration with Guilamu Bug Reporter.

### Can I customize the workflow with public hooks?

Not yet. The current MVP is configured through Gravity Forms notifications and native entry permissions rather than custom plugin hooks.

## Limitations

- The plugin currently supports a single approval state per entry, not sequential or multi-step approvals.
- Workflow-specific approval text and field update behavior still live on each `Approval Request` notification; the plugin settings page only controls the shared public page appearance.

## Troubleshooting

1. If no approval email is sent, confirm the form has an active `Approval Request` notification and that the recipient resolves to a valid email address.
2. If a link says it is invalid or expired, send a fresh approval request; previous tokens are invalidated after a resend, reset, or successful decision.
3. If an admin cannot change status, confirm the user has the `gravityforms_edit_entries` capability.
4. If a GitHub update does not appear, clear transients and verify the latest GitHub release tag is newer than the plugin version header.
5. If a field update does not apply, confirm the chosen update behavior matches the selected field type and that choice-based fields still use valid configured choices.

## Project Structure

```text
.
├── gf-email-approvals.php                       # Main plugin bootstrap, GitHub updater wiring, and plugin row links
├── LICENSE                                      # GNU Affero General Public License v3.0 text
├── README.md                                    # Plugin documentation, support notes, and changelog
├── languages
│   ├── gf-email-approvals.pot                   # Translation template for future locale files
│   ├── gf-email-approvals-fr_FR.po             # French translation source catalog
│   └── gf-email-approvals-fr_FR.mo             # Compiled French translation loaded by WordPress
└── includes
    ├── class-gf-email-approvals-addon.php       # Gravity Forms add-on integration and approval workflow
    ├── class-gf-email-approvals-token-store.php # Approval token storage and invalidation helpers
    ├── class-github-updater.php                 # GitHub release updater and plugin details modal
    └── Parsedown.php                            # Markdown parser used by the plugin details modal
```

## Changelog

### 0.5.0 - 2026-05-07

- **New:** Added `Approval Page Appearance` under `Forms → Settings → Email Approvals` to style the shared public approval confirmation and result pages with color controls, card dimensions, and a live preview.

### 0.4.0 - 2026-05-03

- **Improved:** Public approval links are now invalidated when entries are trashed and removed when entries are permanently deleted, and trashed or missing entries can no longer be actioned from public approval pages.
- **Improved:** Approval Request notifications now expose separate Approve and Reject button labels, tighter Approval Pages copy fields, and a cleaner two-column layout for confirmation and result text.
- **Improved:** The `Approval Pages` and `Approval actions` sections in the notification editor now only appear for the `Approval Request` event.

### 0.3.2 - 2026-05-03

- **Improved:** Fixed the plugin details modal so the sidebar version now prefers the installed plugin version instead of a stale GitHub release tag.
- **Improved:** Aligned the modal download link and release metadata so they only point to GitHub when a newer release is actually available.

### 0.3.1 - 2026-05-03

- **Improved:** Fixed the plugin details modal sidebar so `Requires Gravity Forms` renders as formatted HTML instead of escaped literal tags.

### 0.3.0 - 2026-05-03

- **New:** Added automatic and approver-selected field updates for one supported entry field on each `Approval Request` notification.
- **New:** Added advanced approval button merge tags so each email can override the displayed Approve/Reject button text inline.
- **Improved:** Reworked the notification editor so post-confirmation field updates start with an explicit update behavior choice before the target field is selected.
- **Improved:** Refined the public confirmation page layout so manual update fields stay properly contained inside the confirmation card.
- **Improved:** Added detailed audit notes for approval decisions, including notification context, recipient, IP, and field diffs.

### 0.2.0 - 2026-05-03

- **New:** Added notification-level `Approval Pages` settings for public confirmation and result copy on `Approval Request` notifications.
- **New:** Added merge tag support to Approval Pages text fields using the native Gravity Forms merge tag selector.
- **Improved:** Scoped public approval page text to the originating notification by storing and resolving the source notification for each approval token.
- **Improved:** Refined the notification editor UX so Approval Pages settings only appear for the `Approval Request` event.

### 0.1.0 - 2026-05-02

- **New:** Added the initial email approval workflow for Gravity Forms entries.
- **New:** Added GitHub release auto-updates, the plugin details modal, and Guilamu Bug Reporter integration.
- **Improved:** Added native Gravity Forms entry detail actions, list filters, status columns, bulk actions, and configurable public approval page copy on `Approval Request` notifications.
- **Security:** Added one-time hashed decision tokens, nonce-protected admin actions, and capability checks for status changes.

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0). See the [LICENSE](LICENSE) file for details.

---

<p align="center">
    Made with love for the WordPress community
</p>