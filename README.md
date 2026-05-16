# Email Approvals for Gravity Forms

Send approval requests for Gravity Forms entries with secure email decision links and native admin status tracking.

## Configure Approval Emails

- Choose the `Approval Request`, `Approval Approved`, and `Approval Rejected` notification events directly in Gravity Forms.
- Generate approve and reject URLs or HTML buttons with dedicated merge tags inside notification bodies.
- Override button labels inline with advanced merge tags such as `{approval_approve_button:Approve this request}` or `{approval_reject_button:Reject this request}`.
- Customize the public confirmation and result page text directly on each `Approval Request` notification, with Gravity Forms merge tags and the native selector already available on the notification screen.
- Style the shared public approval card from `Forms → Settings → Email Approvals` with global colors, spacing, radius, and a live preview builder.
- Update one or more supported fields after approval or rejection, either with predefined automatic values or with values chosen by the approver on the confirmation page using native Gravity Forms controls.
- Match approval emails to each form workflow while keeping shared appearance controls in one plugin settings screen.

## Review Decisions in Gravity Forms

- Track Pending, Approved, and Rejected states in the entry list and in the entry detail sidebar.
- Change status from the entry detail panel or run bulk approve, reject, and reset actions from the entries table.
- Filter entries by approval status while staying inside the native Gravity Forms admin screens.

## Protect Approval Actions

- Confirm public approval actions before applying them to an entry.
- Re-render the public approval page with inline field errors when required or invalid approver-selected values are submitted.
- Invalidate older tokens when a request is resent or when one approver completes the decision.
- Record entry notes and logging-friendly events for auditability.

## Key Features

- **Gravity Forms Native:** Uses custom notification events, entry meta, list filters, and entry detail actions inside Gravity Forms.
- **Field Updates After Confirmation:** Can update one or more supported fields per decision with either automatic values or approver-selected values, using type-aware Gravity Forms controls and native validation on the public approval page while recording a detailed audit trail in the entry notes.
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

### Do approval-page fields follow Gravity Forms validation rules?

Yes. Manual decision fields on the public approval page use Gravity Forms rendering and validation for the fields shown there, including required checks and choice integrity validation. Invalid submissions are re-rendered with inline field errors instead of applying the decision.

### Does it support GitHub updates and bug reporting?

Yes. The plugin includes GitHub release updates, a WordPress-style details modal, and optional integration with Guilamu Bug Reporter.

### Can I customize the workflow with public hooks?

Not yet. The current release is configured through Gravity Forms notifications and native entry permissions rather than custom plugin hooks.

## Limitations

- The plugin currently supports a single approval state per entry, not sequential or multi-step approvals.
- Workflow-specific approval text and field update behavior still live on each `Approval Request` notification; the plugin settings page only controls the shared public page appearance.

## Troubleshooting

1. If no approval email is sent, confirm the form has an active `Approval Request` notification and that the recipient resolves to a valid email address.
2. If a link says it is invalid or expired, send a fresh approval request; previous tokens are invalidated after a resend, reset, or successful decision.
3. If an admin cannot change status, confirm the user has the `gravityforms_edit_entries` capability.
4. If a GitHub update does not appear, clear transients and verify the latest GitHub release tag is newer than the plugin version header.
5. If a field update does not apply, confirm the chosen update behavior matches the selected field type, that choice-based fields still use valid configured choices, and that the approval link was generated after the latest field choice changes.

## Project Structure

```text
.
├── assets
│   ├── css
│   │   ├── admin-appearance-builder.css       # Appearance builder admin styles used on the plugin settings page
│   │   └── admin-notification-settings.css    # Notification editor layout styles for approval page copy and field updates
│   └── js
│       ├── admin-appearance-builder.js        # Appearance builder admin interactions, media picker, accordions, and live preview sync
│       └── admin-notification-settings.js     # Notification editor toggles for approval field update settings
├── gf-email-approvals.php                       # Main plugin bootstrap, GitHub updater wiring, and plugin row links
├── LICENSE                                      # GNU Affero General Public License v3.0 text
├── README.md                                    # Plugin documentation, support notes, and changelog
├── languages
│   ├── gf-email-approvals.pot                   # Translation template for future locale files
│   ├── gf-email-approvals-fr_FR.po             # French translation source catalog
│   └── gf-email-approvals-fr_FR.mo             # Compiled French translation loaded by WordPress
└── includes
    ├── class-gf-email-approvals-addon.php       # Gravity Forms add-on entrypoint and approval workflow orchestration
    ├── class-gf-email-approvals-appearance-settings-helper.php # Appearance settings rendering and preview configuration helpers
    ├── class-gf-email-approvals-public-page-presentation-helper.php # Public approval page theme and inline style calculation helpers
    ├── class-gf-email-approvals-token-store.php # Approval token storage and invalidation helpers
    ├── class-github-updater.php                 # GitHub release updater and plugin details modal
    └── Parsedown.php                            # Markdown parser used by the plugin details modal
```

## Changelog

### 1.0.0 - 2026-05-16

- **Initial Release:** Added a full email approval workflow for Gravity Forms entries with `Approval Request`, `Approval Approved`, and `Approval Rejected` notification events.
- **Initial Release:** Added secure single-use approve/reject links, token invalidation, native admin status management, bulk actions, audit notes, and approval status filters.
- **Initial Release:** Added notification-level approval page copy, advanced approval button merge tags, and a shared `Approval Page Appearance` builder with preview, logo, spacing, and color controls.
- **Initial Release:** Added automatic and approver-selected field updates, including multi-field public approval-page inputs rendered with native Gravity Forms controls and validated before a decision is applied.
- **Initial Release:** Added GitHub release updates, the plugin details modal, multilingual catalogs, and the optional Guilamu Bug Reporter integration.

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0). See the [LICENSE](LICENSE) file for details.

---

<p align="center">
    Made with love for the WordPress community
</p>