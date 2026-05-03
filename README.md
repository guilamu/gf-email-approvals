# Email Approvals for Gravity Forms

Send approval requests for Gravity Forms entries with secure email decision links and native admin status tracking.

## Configure Approval Emails

- Choose the `Approval Request`, `Approval Approved`, and `Approval Rejected` notification events directly in Gravity Forms.
- Generate approve and reject URLs or HTML buttons with dedicated merge tags inside notification bodies.
- Match approval emails to each form workflow without adding a separate plugin settings screen.

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
3. Open **Forms → Your Form → Settings → Notifications** and create an `Approval Request` notification.
4. Optionally create `Approval Approved` and `Approval Rejected` notifications for post-decision follow-up emails.
5. Submit a test entry and confirm the approver receives working decision links.

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

## Known Issues

- Keeping the default `Form Submission` notifications active can send business emails before approval.
- Resetting an entry back to Pending does not resend the `Approval Request` notification automatically.

## Limitations

- The plugin currently supports a single approval state per entry, not sequential or multi-step approvals.
- It does not add a dedicated form settings UI; configuration happens through Gravity Forms notifications.
- Approval decisions apply to the whole entry, not to individual fields or partial submissions.

## Troubleshooting

1. If no approval email is sent, confirm the form has an active `Approval Request` notification and that the recipient resolves to a valid email address.
2. If a link says it is invalid or expired, send a fresh approval request; previous tokens are invalidated after a resend, reset, or successful decision.
3. If an admin cannot change status, confirm the user has the `gravityforms_edit_entries` capability.
4. If a GitHub update does not appear, clear transients and verify the latest GitHub release tag is newer than the plugin version header.

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

### 0.1.0 - 2026-05-02

- **New:** Added the initial email approval workflow for Gravity Forms entries.
- **New:** Added GitHub release auto-updates, the plugin details modal, and Guilamu Bug Reporter integration.
- **Improved:** Added native Gravity Forms entry detail actions, list filters, status columns, and bulk actions.
- **Security:** Added one-time hashed decision tokens, nonce-protected admin actions, and capability checks for status changes.

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0). See the [LICENSE](LICENSE) file for details.

---

<p align="center">
    Made with love for the WordPress community
</p>