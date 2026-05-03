<?php

defined( 'ABSPATH' ) || exit;

if ( false ) {
	abstract class GFAddOn {
		public function __construct() {}
		public function init() {}
		protected function log_debug( $message ) {}
		protected function log_error( $message ) {}
	}

	class GFForms {
		public static function include_addon_framework() {}
	}

	class GFAPI {
		public static function send_notifications( $form, $entry, $event = 'form_submission', $data = array() ) {}
		public static function get_entry( $entry_id ) {}
		public static function get_form( $form_id ) {}
		public static function add_note( $entry_id, $user_id, $user_name, $note ) {}
		public static function count_entries( $form_ids, $search_criteria = array() ) {}
	}
}

if ( ! class_exists( 'GFForms' ) || ! class_exists( 'GFAddOn' ) ) {
	return;
}

call_user_func( array( 'GFForms', 'include_addon_framework' ) );

/**
 * Main add-on bootstrap for the email approvals MVP.
 */
class GFEmailApprovalsAddon extends GFAddOn {
	const META_STATUS = 'approval_status';
	const STATUS_PENDING = 'pending';
	const STATUS_APPROVED = 'approved';
	const STATUS_REJECTED = 'rejected';
	const QUERY_ACTION = 'gf_approval_action';
	const QUERY_TOKEN = 'gf_approval_token';
	const PUBLIC_ACTION_CONFIRM = 'confirm';
	const NONCE_FIELD = 'gf_email_approval_nonce';
	const NONCE_ACTION = 'gf_email_approval_action';
	const MANUAL_APPROVE_ACTION = 'gf_email_approval_manual_approve';
	const MANUAL_REJECT_ACTION = 'gf_email_approval_manual_reject';
	const MANUAL_RESET_ACTION = 'gf_email_approval_manual_reset';
	const NOTIFICATION_CONFIRMATION_TITLE = 'approval_confirmation_title';
	const NOTIFICATION_APPROVE_CONFIRMATION_TEXT = 'approval_approve_confirmation_text';
	const NOTIFICATION_REJECT_CONFIRMATION_TEXT = 'approval_reject_confirmation_text';
	const NOTIFICATION_CONFIRM_BUTTON_LABEL = 'approval_confirm_button_label';
	const NOTIFICATION_APPROVED_RESULT_TEXT = 'approval_approved_result_text';
	const NOTIFICATION_REJECTED_RESULT_TEXT = 'approval_rejected_result_text';

	protected $_version = GF_EMAIL_APPROVALS_VERSION;
	protected $_min_gravityforms_version = '2.7';
	protected $_slug = 'gf-email-approvals';
	protected $_path = '';
	protected $_full_path = GF_EMAIL_APPROVALS_FILE;
	protected $_title = 'Email Approvals for Gravity Forms';
	protected $_short_title = 'Email Approvals';
	protected $_capabilities = array( 'gravityforms_edit_entries' );
	protected $_capabilities_settings_page = array( 'gravityforms_edit_entries' );
	protected $_capabilities_form_settings = array( 'gravityforms_edit_entries' );
	protected $_capabilities_uninstall = array( 'gravityforms_uninstall' );

	/**
	 * @var self|null
	 */
	private static $_instance = null;

	/**
	 * Tracks entries whose old request tokens were invalidated during the current request.
	 *
	 * @var array<int, bool>
	 */
	private $invalidated_request_entries = array();

	/**
	 * Keeps the latest manual action feedback per entry detail page load.
	 *
	 * @var array<int, array>
	 */
	private $manual_action_feedback = array();

	/**
	 * Sets runtime-derived addon properties.
	 */
	public function __construct() {
		$this->_path = plugin_basename( GF_EMAIL_APPROVALS_FILE );

		parent::__construct();
	}

	/**
	 * Returns the add-on instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Registers the first MVP hooks.
	 */
	public function init() {
		parent::init();

		add_filter( 'gform_notification_events', array( $this, 'register_notification_events' ), 10, 2 );
		add_filter( 'gform_custom_merge_tags', array( $this, 'register_custom_merge_tags' ), 10, 4 );
		add_filter( 'gform_pre_send_email', array( $this, 'prepare_approval_request_email' ), 10, 4 );
		add_filter( 'gform_replace_merge_tags', array( $this, 'replace_common_merge_tags' ), 10, 7 );
		add_filter( 'gform_entry_meta', array( $this, 'register_entry_meta' ), 10, 2 );
		add_action( 'gform_after_submission', array( $this, 'handle_after_submission' ), 10, 2 );
	}

	/**
	 * Registers dashboard-only hooks.
	 *
	 * @return void
	 */
	public function init_admin() {
		parent::init_admin();

		add_filter( 'gform_notification_settings_fields', array( $this, 'register_notification_settings_fields' ), 10, 3 );
		add_filter( 'gform_pre_notification_save', array( $this, 'save_notification_page_settings' ), 10, 2 );
		add_filter( 'gform_entry_list_columns', array( $this, 'register_entry_list_columns' ), 10, 2 );
		add_filter( 'gform_entry_list_bulk_actions', array( $this, 'register_entry_list_bulk_actions' ), 10, 2 );
		add_filter( 'gform_entries_column_filter', array( $this, 'render_entry_list_column' ), 10, 5 );
		add_filter( 'gform_filter_links_entry_list', array( $this, 'register_entry_list_filter_links' ), 10, 4 );
		add_filter( 'gform_entry_detail_meta_boxes', array( $this, 'register_entry_detail_meta_boxes' ), 10, 3 );
		add_action( 'gform_entry_list_action', array( $this, 'handle_entry_list_action' ), 10, 3 );
	}

	/**
	 * Registers public-site hooks.
	 *
	 * @return void
	 */
	public function init_frontend() {
		parent::init_frontend();

		add_action( 'template_redirect', array( $this, 'maybe_render_public_action_page' ) );
	}

	/**
	 * Creates or updates plugin storage when the add-on version changes.
	 *
	 * @param string|false $previous_version Previously installed add-on version.
	 *
	 * @return void
	 */
	public function upgrade( $previous_version ) {
		unset( $previous_version );

		GFEmailApprovalsTokenStore::maybe_create_table();
	}

	/**
	 * Adds custom notification events for the approval workflow.
	 *
	 * @param array $events Existing events.
	 * @param array $form   The current form.
	 *
	 * @return array
	 */
	public function register_notification_events( $events, $form ) {
		unset( $form );

		$events['approval_request'] = esc_html__( 'Approval Request', 'gf-email-approvals' );
		$events['approval_approved'] = esc_html__( 'Approval Approved', 'gf-email-approvals' );
		$events['approval_rejected'] = esc_html__( 'Approval Rejected', 'gf-email-approvals' );

		return $events;
	}

	/**
	 * Adds Approval Request-specific copy settings to the notification editor.
	 *
	 * @param array $fields       Existing notification settings sections.
	 * @param array $notification The notification being edited.
	 * @param array $form         The current form.
	 *
	 * @return array
	 */
	public function register_notification_settings_fields( $fields, $notification, $form ) {
		unset( $notification, $form );

		$defaults = $this->get_notification_page_defaults();

		$fields[] = array(
			'title'       => esc_html__( 'Approval Pages', 'gf-email-approvals' ),
			'description' => esc_html__( 'Customize the public confirmation and result pages used by Approval Request links. Gravity Forms merge tags are supported.', 'gf-email-approvals' ),
			'dependency'  => array(
				'field'  => 'event',
				'values' => array( 'approval_request' ),
				'live'   => true,
			),
			'fields'      => array(
				array(
					'type'          => 'text',
					'name'          => self::NOTIFICATION_CONFIRMATION_TITLE,
					'label'         => esc_html__( 'Confirmation title', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_CONFIRMATION_TITLE ],
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT,
					'label'         => esc_html__( 'Approve confirmation text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ],
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_REJECT_CONFIRMATION_TEXT,
					'label'         => esc_html__( 'Reject confirmation text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ],
				),
				array(
					'type'          => 'text',
					'name'          => self::NOTIFICATION_CONFIRM_BUTTON_LABEL,
					'label'         => esc_html__( 'Confirm button label', 'gf-email-approvals' ),
					'class'         => 'medium merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_CONFIRM_BUTTON_LABEL ],
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_APPROVED_RESULT_TEXT,
					'label'         => esc_html__( 'Approved result text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_APPROVED_RESULT_TEXT ],
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_REJECTED_RESULT_TEXT,
					'label'         => esc_html__( 'Rejected result text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_REJECTED_RESULT_TEXT ],
				),
			),
		);

		return $fields;
	}

	/**
	 * Persists Approval Request-specific page copy settings on the notification.
	 *
	 * @param array $notification The notification object being saved.
	 * @param array $form         The current form.
	 *
	 * @return array
	 */
	public function save_notification_page_settings( $notification, $form ) {
		unset( $form );

		$text_fields = array(
			self::NOTIFICATION_CONFIRMATION_TITLE,
			self::NOTIFICATION_CONFIRM_BUTTON_LABEL,
		);

		foreach ( $text_fields as $setting_name ) {
			if ( ! isset( $_POST[ $setting_name ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_POST[ $setting_name ] ) );

			if ( '' === $value ) {
				unset( $notification[ $setting_name ] );
				continue;
			}

			$notification[ $setting_name ] = $value;
		}

		$textarea_fields = array(
			self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT,
			self::NOTIFICATION_REJECT_CONFIRMATION_TEXT,
			self::NOTIFICATION_APPROVED_RESULT_TEXT,
			self::NOTIFICATION_REJECTED_RESULT_TEXT,
		);

		foreach ( $textarea_fields as $setting_name ) {
			if ( ! isset( $_POST[ $setting_name ] ) ) {
				continue;
			}

			$value = sanitize_textarea_field( wp_unslash( $_POST[ $setting_name ] ) );

			if ( '' === $value ) {
				unset( $notification[ $setting_name ] );
				continue;
			}

			$notification[ $setting_name ] = $value;
		}

		return $notification;
	}

	/**
	 * Exposes the approval merge tags in Gravity Forms editors.
	 *
	 * @param array $merge_tags Existing merge tags.
	 * @param int   $form_id    The current form id.
	 * @param array $fields     The current form fields.
	 * @param mixed $element_id The current editor element.
	 *
	 * @return array
	 */
	public function register_custom_merge_tags( $merge_tags, $form_id, $fields, $element_id ) {
		unset( $form_id, $fields, $element_id );

		foreach ( $this->get_approval_merge_tags() as $approval_merge_tag ) {
			$merge_tags[] = $approval_merge_tag;
		}

		return $merge_tags;
	}

	/**
	 * Replaces merge tags that are available across all approval notifications.
	 *
	 * @param string     $text       The text being processed.
	 * @param array|bool $form       The current form.
	 * @param array|bool $entry      The current entry.
	 * @param bool       $url_encode Whether URLs should be encoded.
	 * @param bool       $esc_html   Whether HTML should be escaped.
	 * @param bool       $nl2br      Whether new lines should be converted.
	 * @param string     $format     The output format.
	 *
	 * @return string
	 */
	public function replace_common_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		unset( $form, $url_encode, $esc_html, $nl2br, $format );

		if ( false === strpos( $text, '{approval_status}' ) || empty( $entry ) ) {
			return $text;
		}

		$status = $this->get_status_label( $this->get_entry_status( absint( $this->array_value( $entry, 'id' ) ) ) );

		return str_replace( '{approval_status}', $status, $text );
	}

	/**
	 * Adds approval status as queryable entry meta.
	 *
	 * @param array $entry_meta Existing entry meta.
	 * @param int   $form_id    The current form id.
	 *
	 * @return array
	 */
	public function register_entry_meta( $entry_meta, $form_id ) {
		unset( $form_id );

		$entry_meta[ self::META_STATUS ] = array(
			'label'                      => esc_html__( 'Approval Status', 'gf-email-approvals' ),
			'is_numeric'                 => false,
			'update_entry_meta_callback' => array( $this, 'update_entry_status_meta' ),
			'is_default_column'          => false,
		);

		return $entry_meta;
	}

	/**
	 * Returns the current status when Gravity Forms refreshes entry meta.
	 *
	 * @param string $key  The current meta key.
	 * @param array  $lead The current entry.
	 * @param array  $form The current form.
	 *
	 * @return string
	 */
	public function update_entry_status_meta( $key, $lead, $form ) {
		unset( $key, $form );

		return (string) $this->get_entry_status( absint( $this->array_value( $lead, 'id' ) ) );
	}

	/**
	 * Adds the approval status column to the entry list.
	 *
	 * @param array $columns Existing columns.
	 * @param int   $form_id The current form id.
	 *
	 * @return array
	 */
	public function register_entry_list_columns( $columns, $form_id ) {
		unset( $form_id );

		if ( isset( $columns['column_selector'] ) ) {
			$column_selector = $columns['column_selector'];
			unset( $columns['column_selector'] );
			$columns[ self::META_STATUS ] = esc_html__( 'Approval Status', 'gf-email-approvals' );
			$columns['column_selector'] = $column_selector;

			return $columns;
		}

		$columns[ self::META_STATUS ] = esc_html__( 'Approval Status', 'gf-email-approvals' );

		return $columns;
	}

	/**
	 * Adds approval workflow actions to the native entry list bulk action menu.
	 *
	 * @param array $actions Existing bulk actions.
	 * @param int   $form_id The current form id.
	 *
	 * @return array
	 */
	public function register_entry_list_bulk_actions( $actions, $form_id ) {
		unset( $form_id );

		if ( ! $this->current_user_can_edit_entries() ) {
			return $actions;
		}

		$current_filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';

		if ( in_array( $current_filter, array( 'trash', 'spam' ), true ) ) {
			return $actions;
		}

		$approval_actions = array(
			self::MANUAL_APPROVE_ACTION => esc_html__( 'Approve', 'gf-email-approvals' ),
			self::MANUAL_REJECT_ACTION  => esc_html__( 'Reject', 'gf-email-approvals' ),
			self::MANUAL_RESET_ACTION   => esc_html__( 'Reset Status', 'gf-email-approvals' ),
		);

		$updated_actions = array();
		$inserted        = false;

		foreach ( $actions as $action => $label ) {
			$updated_actions[ $action ] = $label;

			if ( 'remove_star' === $action ) {
				$updated_actions = array_merge( $updated_actions, $approval_actions );
				$inserted        = true;
			}
		}

		if ( ! $inserted ) {
			$updated_actions = array_merge( $updated_actions, $approval_actions );
		}

		return $updated_actions;
	}

	/**
	 * Adds approval status links to the native entry list filter row.
	 *
	 * @param array $filter_links   Existing filter links.
	 * @param array $form           The current form.
	 * @param bool  $include_counts Whether counts should be calculated.
	 * @param array $counts         Core entry counts.
	 *
	 * @return array
	 */
	public function register_entry_list_filter_links( $filter_links, $form, $include_counts, $counts ) {
		unset( $counts );

		$form_id = absint( $this->array_value( $form, 'id' ) );

		if ( ! $form_id ) {
			return $filter_links;
		}

		$status_links = array();

		foreach ( array(
			self::STATUS_PENDING  => esc_html__( 'Pending', 'gf-email-approvals' ),
			self::STATUS_APPROVED => esc_html__( 'Approved', 'gf-email-approvals' ),
			self::STATUS_REJECTED => esc_html__( 'Rejected', 'gf-email-approvals' ),
		) as $status => $label ) {
			$status_links[] = array(
				'id'            => 'approval_' . $status,
				'field_filters' => array(
					array(
						'key'      => self::META_STATUS,
						'operator' => 'is',
						'value'    => $status,
					),
				),
				'count'         => $include_counts && class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'count_entries' )
					? absint( GFAPI::count_entries( $form_id, array(
						'status'        => 'active',
						'field_filters' => array(
							array(
								'key'      => self::META_STATUS,
								'operator' => 'is',
								'value'    => $status,
							),
						),
					) ) )
					: 0,
				'label'         => $label,
			);
		}

		$all_link = array();
		$other_links = $filter_links;

		if ( ! empty( $filter_links ) ) {
			$all_link = array( array_shift( $other_links ) );
		}

		return array_merge( $all_link, $status_links, $other_links );
	}

	/**
	 * Registers the approval panel as a native entry detail meta box.
	 *
	 * @param array $meta_boxes Existing meta boxes.
	 * @param array $entry      The current entry.
	 * @param array $form       The current form.
	 *
	 * @return array
	 */
	public function register_entry_detail_meta_boxes( $meta_boxes, $entry, $form ) {
		unset( $entry, $form );

		$meta_boxes['gf_email_approvals'] = array(
			'title'    => esc_html__( 'Approval', 'gf-email-approvals' ),
			'callback' => array( $this, 'render_entry_detail_sidebar' ),
			'context'  => 'side',
			'priority' => 'default',
		);

		return $meta_boxes;
	}

	/**
	 * Renders the approval status entry list column.
	 *
	 * @param string $value        The current value.
	 * @param int    $form_id      The current form id.
	 * @param string $field_id     The field or meta key.
	 * @param array  $entry        The current entry.
	 * @param string $query_string The current query string.
	 *
	 * @return string
	 */
	public function render_entry_list_column( $value, $form_id, $field_id, $entry, $query_string ) {
		unset( $form_id, $query_string );

		if ( self::META_STATUS !== (string) $field_id ) {
			return $value;
		}

		return $this->get_status_badge_html( $this->get_entry_status( absint( $this->array_value( $entry, 'id' ) ) ) );
	}

	/**
	 * Processes custom approval bulk actions from the native entry list.
	 *
	 * @param string $action   Action being processed.
	 * @param array  $entries  Entry ids selected in the list.
	 * @param int    $form_id  The current form id.
	 *
	 * @return void
	 */
	public function handle_entry_list_action( $action, $entries, $form_id ) {
		if ( ! in_array( $action, array( self::MANUAL_APPROVE_ACTION, self::MANUAL_REJECT_ACTION, self::MANUAL_RESET_ACTION ), true ) ) {
			return;
		}

		if ( ! $this->current_user_can_edit_entries() ) {
			$this->render_entry_list_action_notice( esc_html__( 'You are not allowed to process these entries.', 'gf-email-approvals' ), 'error' );

			return;
		}

		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_form' ) || ! method_exists( 'GFAPI', 'get_entry' ) ) {
			$this->render_entry_list_action_notice( esc_html__( 'The approval action is currently unavailable.', 'gf-email-approvals' ), 'error' );

			return;
		}

		$form = GFAPI::get_form( absint( $form_id ) );

		if ( is_wp_error( $form ) || ! is_array( $form ) ) {
			$this->render_entry_list_action_notice( esc_html__( 'The approval action is currently unavailable.', 'gf-email-approvals' ), 'error' );

			return;
		}

		$entry_ids = array_values( array_filter( array_unique( array_map( 'absint', is_array( $entries ) ? $entries : array() ) ) ) );

		if ( empty( $entry_ids ) ) {
			$this->render_entry_list_action_notice( esc_html__( 'Select at least one entry to update its approval status.', 'gf-email-approvals' ), 'error' );

			return;
		}

		$current_user = wp_get_current_user();
		$context      = array(
			'source'          => self::MANUAL_RESET_ACTION === $action ? 'bulk-reset' : 'bulk',
			'recipient_email' => (string) $current_user->user_email,
			'ip'              => $this->get_request_ip(),
			'actor_name'      => $current_user->exists() ? $current_user->user_login : esc_html__( 'Admin', 'gf-email-approvals' ),
		);
		$processed    = 0;
		$skipped      = 0;
		$missing      = 0;

		foreach ( $entry_ids as $entry_id ) {
			$entry = GFAPI::get_entry( $entry_id );

			if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
				$missing++;
				continue;
			}

			$result = self::MANUAL_RESET_ACTION === $action
				? $this->reset_entry_status( $entry, $form, $context )
				: $this->process_decision(
					$entry,
					$form,
					self::MANUAL_APPROVE_ACTION === $action ? self::STATUS_APPROVED : self::STATUS_REJECTED,
					$context
				);

			if ( ! empty( $result['success'] ) ) {
				$processed++;
			} else {
				$skipped++;
			}
		}

		$this->render_entry_list_action_notice(
			$this->get_entry_list_action_notice_message( $action, $processed, $skipped, $missing ),
			$processed > 0 ? 'success' : 'warning'
		);
	}

	/**
	 * Handles submitted entries.
	 *
	 * @param array $entry The submitted entry.
	 * @param array $form  The current form.
	 *
	 * @return void
	 */
	public function handle_after_submission( $entry, $form ) {
		if ( 'spam' === $this->array_value( $entry, 'status' ) ) {
			return;
		}

		if ( ! $this->has_active_notification_event( $form, 'approval_request' ) ) {
			$this->log_debug( __METHOD__ . '(): No active approval_request notification found, skipping approval workflow.' );
			return;
		}

		$this->set_entry_status( absint( $this->array_value( $entry, 'id' ) ), self::STATUS_PENDING );
		$this->log_debug( sprintf( '%s(): Entry %d set to pending and approval_request notifications will be sent.', __METHOD__, absint( $this->array_value( $entry, 'id' ) ) ) );

		if ( class_exists( 'GFAPI' ) ) {
			GFAPI::send_notifications( $form, $entry, 'approval_request' );
		}
	}

	/**
	 * Injects fresh approval links into approval request notifications.
	 *
	 * @param array $email          The email data.
	 * @param string $message_format The message format.
	 * @param array $notification   The notification object.
	 * @param array $entry          The current entry.
	 *
	 * @return array
	 */
	public function prepare_approval_request_email( $email, $message_format, $notification, $entry ) {
		if ( 'approval_request' !== $this->array_value( $notification, 'event' ) ) {
			return $email;
		}

		$entry_id = absint( $this->array_value( $entry, 'id' ) );

		if ( self::STATUS_PENDING !== $this->get_entry_status( $entry_id ) ) {
			$email['abort_email'] = true;
			$this->log_debug( sprintf( '%s(): approval_request email aborted for entry %d because it is no longer pending.', __METHOD__, $entry_id ) );

			return $email;
		}

		if ( ! isset( $this->invalidated_request_entries[ $entry_id ] ) ) {
			GFEmailApprovalsTokenStore::invalidate_entry_tokens( $entry_id );
			$this->invalidated_request_entries[ $entry_id ] = true;
		}

		$approve = $this->build_approval_action_data( $entry, $notification, $this->array_value( $email, 'to' ), self::STATUS_APPROVED, $message_format );
		$reject  = $this->build_approval_action_data( $entry, $notification, $this->array_value( $email, 'to' ), self::STATUS_REJECTED, $message_format );

		if ( empty( $approve['url'] ) || empty( $reject['url'] ) ) {
			$email['abort_email'] = true;
			$this->log_error( sprintf( '%s(): approval_request email aborted for entry %d because tokens could not be generated.', __METHOD__, $entry_id ) );

			return $email;
		}

		$replacements = array(
			'{approval_status}'         => $this->get_status_label( self::STATUS_PENDING ),
			'{approval_approve_url}'    => $approve['url'],
			'{approval_reject_url}'     => $reject['url'],
			'{approval_approve_button}' => $approve['button'],
			'{approval_reject_button}'  => $reject['button'],
		);

		$email['subject'] = strtr( $email['subject'], $replacements );
		$email['message'] = strtr( $email['message'], $replacements );

		if ( ! $this->message_contains_action_merge_tags( $notification ) ) {
			$email['message'] .= $this->get_default_action_markup( $approve, $reject, $message_format );
		}

		return $email;
	}

	/**
	 * Renders the current status and manual entry actions in the sidebar.
	 *
	 * @param array $form  The current form.
	 * @param array $entry The current entry.
	 *
	 * @return void
	 */
	public function render_entry_detail_sidebar( $args, $metabox = null ) {
		unset( $metabox );

		$form     = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : array();
		$entry    = isset( $args['entry'] ) && is_array( $args['entry'] ) ? $args['entry'] : array();
		$entry_id = absint( $this->array_value( $entry, 'id' ) );
		$feedback = $this->handle_manual_entry_action( $form, $entry );
		$status   = $this->get_entry_status( $entry_id );
		$can_edit = $this->current_user_can_edit_entries();

		echo '<div class="gf-email-approvals-panel">';

		if ( $feedback ) {
			$class = ! empty( $feedback['success'] ) ? 'notice-success' : 'notice-error';
			echo '<div class="notice inline ' . esc_attr( $class ) . '"><p>' . esc_html( $feedback['message'] ) . '</p></div>';
		}

		echo '<p style="margin:0;">';
		echo esc_html__( 'Current status:', 'gf-email-approvals' ) . ' ';
		echo wp_kses_post( $this->get_status_badge_html( $status ) );
		echo '</p>';

		if ( self::STATUS_PENDING === $status && $can_edit ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
			echo '<div class="actions" style="padding:16px 0 0;display:flex;align-items:flex-start;">';
			echo '<label class="hidden" for="gf_email_approval_action">' . esc_html__( 'Change status', 'gf-email-approvals' ) . '</label>';
			echo '<select name="gf_email_approval_action" id="gf_email_approval_action" style="-webkit-appearance:none;-moz-appearance:none;appearance:none;background:url(data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2220%22%20height%3D%2220%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Cpath%20d%3D%22M5%206l5%205%205-5%202%201-7%207-7-7%202-1z%22%20fill%3D%22%239092B2%22%2F%3E%3C%2Fsvg%3E) no-repeat right .6rem top 55%;background-color:#fff;background-size:16px 16px;flex:0 1 auto;width:auto;max-width:calc(100% - 85px);height:2.3125rem;min-height:2.3125rem;margin-right:10px;padding:0 2rem 0 .8125rem;line-height:1.6875rem;box-sizing:border-box;">';
			echo '<option value="" selected="selected">' . esc_html__( 'Change status', 'gf-email-approvals' ) . '</option>';
			echo '<option value="' . esc_attr( self::MANUAL_APPROVE_ACTION ) . '">' . esc_html__( 'Approve', 'gf-email-approvals' ) . '</option>';
			echo '<option value="' . esc_attr( self::MANUAL_REJECT_ACTION ) . '">' . esc_html__( 'Reject', 'gf-email-approvals' ) . '</option>';
			echo '</select>';
			echo '<input type="submit" class="button" value="' . esc_attr__( 'Apply', 'gf-email-approvals' ) . '" style="flex:0 0 75px;height:2.3125rem;min-height:0;line-height:19px;padding:8px 18px;box-sizing:border-box;" onclick="var action = jQuery(\'#gf_email_approval_action\').val(); if (!action) { return false; } jQuery(\'#action\').val(action);" />';
			echo '</div>';
		} elseif ( $can_edit ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
			echo '<p style="margin:16px 0 0;">';
			echo '<input type="submit" class="button" value="' . esc_attr__( 'Reset', 'gf-email-approvals' ) . '" onclick="jQuery(\'#action\').val(\'' . esc_js( self::MANUAL_RESET_ACTION ) . '\');" />';
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Handles the public confirmation endpoint.
	 *
	 * @return void
	 */
	public function maybe_render_public_action_page() {
		$action = isset( $_REQUEST[ self::QUERY_ACTION ] ) ? sanitize_key( wp_unslash( $_REQUEST[ self::QUERY_ACTION ] ) ) : '';

		if ( self::PUBLIC_ACTION_CONFIRM !== $action ) {
			return;
		}

		$token = isset( $_REQUEST[ self::QUERY_TOKEN ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ self::QUERY_TOKEN ] ) ) : '';

		if ( '' === $token ) {
			$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
		}

		if ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? wp_unslash( $_SERVER['REQUEST_METHOD'] ) : 'GET' ) ) {
			$this->handle_public_action_submission( $token );
		}

		$record = GFEmailApprovalsTokenStore::get_token_record( $token );

		if ( ! $record ) {
			$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
		}

		if ( $record->used_at || $record->invalidated_at || self::STATUS_PENDING !== $this->get_entry_status( absint( $record->entry_id ) ) ) {
			$this->render_public_message_page( esc_html__( 'This approval request has already been processed.', 'gf-email-approvals' ) );
		}

		$form         = class_exists( 'GFAPI' ) ? GFAPI::get_form( absint( $record->form_id ) ) : array();
		$entry        = class_exists( 'GFAPI' ) ? GFAPI::get_entry( absint( $record->entry_id ) ) : array();
		$form         = is_array( $form ) ? $form : array();
		$entry        = is_array( $entry ) ? $entry : array();
		$notification = $this->get_form_notification( $form, (string) $record->notification_id );

		$this->render_public_confirmation_page( $record, $token, $notification, $form, $entry );
	}

	/**
	 * Enables Gravity Forms logging support for the add-on.
	 *
	 * @return bool
	 */
	public function supports_logging() {
		return true;
	}

	/**
	 * Prevents multisite network activation from advertising support we do not implement.
	 *
	 * @return bool
	 */
	public function requires_plugin() {
		return true;
	}

	/**
	 * Returns whether the form has at least one active notification for the given event.
	 *
	 * @param array  $form  The current form.
	 * @param string $event The event name.
	 *
	 * @return bool
	 */
	private function has_active_notification_event( $form, $event ) {
		$notifications = $this->array_value( $form, 'notifications' );

		if ( ! is_array( $notifications ) ) {
			return false;
		}

		foreach ( $notifications as $notification ) {
			if ( $event === $this->array_value( $notification, 'event' ) && $this->is_notification_active( $notification ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns whether the notification is active.
	 *
	 * @param array $notification The notification object.
	 *
	 * @return bool
	 */
	private function is_notification_active( $notification ) {
		$active = $this->array_value( $notification, 'isActive', null );

		return null === $active || true === $active || '1' === (string) $active || 1 === $active;
	}

	/**
	 * Generates the approval URL and button markup for one action.
	 *
	 * @param array  $entry           The current entry.
	 * @param array  $notification    The current notification.
	 * @param string $recipient_email The resolved recipient email.
	 * @param string $status          The target status.
	 * @param string $message_format  The notification format.
	 *
	 * @return array<string, string>
	 */
	private function build_approval_action_data( $entry, $notification, $recipient_email, $status, $message_format ) {
		$notification_id = (string) $this->array_value( $notification, 'id' );
		$token           = GFEmailApprovalsTokenStore::generate_token( $entry, $notification_id, $status, (string) $recipient_email );
		$url             = add_query_arg(
			array(
				self::QUERY_ACTION => self::PUBLIC_ACTION_CONFIRM,
				self::QUERY_TOKEN  => $token,
			),
			home_url( '/' )
		);
		$is_approved     = self::STATUS_APPROVED === $status;
		$label           = $is_approved ? esc_html__( 'Approve', 'gf-email-approvals' ) : esc_html__( 'Reject', 'gf-email-approvals' );
		$button_class    = $is_approved ? '#1f7a1f' : '#b42318';
		$button          = 'text' === $message_format
			? sprintf( "%s: %s", $label, $url )
			: sprintf(
				'<a href="%1$s" style="display:inline-block;padding:12px 18px;margin:0 12px 12px 0;background:%2$s;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;">%3$s</a>',
				esc_url( $url ),
				esc_attr( $button_class ),
				esc_html( $label )
			);

		return array(
			'url'    => $url,
			'button' => $button,
		);
	}

	/**
	 * Returns whether an approval-request notification already contains explicit action tags.
	 *
	 * @param array $notification The notification object.
	 *
	 * @return bool
	 */
	private function message_contains_action_merge_tags( $notification ) {
		$message = (string) $this->array_value( $notification, 'message' );

		return false !== strpos( $message, '{approval_approve_' ) || false !== strpos( $message, '{approval_reject_' );
	}

	/**
	 * Appends default action markup when the notification body has no explicit merge tags.
	 *
	 * @param array  $approve        Approve action data.
	 * @param array  $reject         Reject action data.
	 * @param string $message_format The message format.
	 *
	 * @return string
	 */
	private function get_default_action_markup( $approve, $reject, $message_format ) {
		if ( 'text' === $message_format ) {
			return "\n\n" . $approve['button'] . "\n" . $reject['button'];
		}

		return sprintf(
			'<div style="margin-top:24px;"><p style="margin:0 0 12px 0;font-weight:600;">%s</p>%s%s</div>',
			esc_html__( 'Choose an action:', 'gf-email-approvals' ),
			$approve['button'],
			$reject['button']
		);
	}

	/**
	 * Returns the current approval status for an entry.
	 *
	 * @param int $entry_id The entry id.
	 *
	 * @return string
	 */
	private function get_entry_status( $entry_id ) {
		$status = function_exists( 'gform_get_meta' ) ? gform_get_meta( $entry_id, self::META_STATUS ) : '';

		return is_string( $status ) ? $status : '';
	}

	/**
	 * Persists the approval status for an entry.
	 *
	 * @param int    $entry_id The entry id.
	 * @param string $status   The new status.
	 *
	 * @return void
	 */
	private function set_entry_status( $entry_id, $status ) {
		if ( function_exists( 'gform_update_meta' ) ) {
			gform_update_meta( $entry_id, self::META_STATUS, $status );
		}
	}

	/**
	 * Returns a human-readable status label.
	 *
	 * @param string $status The raw status.
	 *
	 * @return string
	 */
	private function get_status_label( $status ) {
		switch ( $status ) {
			case self::STATUS_PENDING:
				return esc_html__( 'Pending', 'gf-email-approvals' );
			case self::STATUS_APPROVED:
				return esc_html__( 'Approved', 'gf-email-approvals' );
			case self::STATUS_REJECTED:
				return esc_html__( 'Rejected', 'gf-email-approvals' );
			default:
				return esc_html__( 'Not requested', 'gf-email-approvals' );
		}
	}

	/**
	 * Returns a compact badge for the entry list and detail sidebar.
	 *
	 * @param string $status The raw status.
	 *
	 * @return string
	 */
	private function get_status_badge_html( $status ) {
		$map = array(
			self::STATUS_PENDING  => '#a15c00',
			self::STATUS_APPROVED => '#1f7a1f',
			self::STATUS_REJECTED => '#b42318',
		);
		$color = isset( $map[ $status ] ) ? $map[ $status ] : '#5b6672';

		return sprintf(
			'<span style="display:inline-block;padding:4px 8px;border-radius:999px;background:%1$s;color:#ffffff;font-size:12px;line-height:1.4;">%2$s</span>',
			esc_attr( $color ),
			esc_html( $this->get_status_label( $status ) )
		);
	}

	/**
	 * Handles admin-side manual approve/reject requests.
	 *
	 * @param array $form  The current form.
	 * @param array $entry The current entry.
	 *
	 * @return array|null
	 */
	private function handle_manual_entry_action( $form, $entry ) {
		$entry_id = absint( $this->array_value( $entry, 'id' ) );

		if ( isset( $this->manual_action_feedback[ $entry_id ] ) ) {
			return $this->manual_action_feedback[ $entry_id ];
		}

		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

		if ( ! in_array( $action, array( self::MANUAL_APPROVE_ACTION, self::MANUAL_REJECT_ACTION, self::MANUAL_RESET_ACTION ), true ) ) {
			return null;
		}

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			$this->manual_action_feedback[ $entry_id ] = array(
				'success' => false,
				'message' => esc_html__( 'The approval action could not be verified.', 'gf-email-approvals' ),
			);

			return $this->manual_action_feedback[ $entry_id ];
		}

		if ( ! $this->current_user_can_edit_entries() ) {
			$this->manual_action_feedback[ $entry_id ] = array(
				'success' => false,
				'message' => esc_html__( 'You are not allowed to process this entry.', 'gf-email-approvals' ),
			);

			return $this->manual_action_feedback[ $entry_id ];
		}

		$current_user = wp_get_current_user();

		if ( self::MANUAL_RESET_ACTION === $action ) {
			$this->manual_action_feedback[ $entry_id ] = $this->reset_entry_status(
				$entry,
				$form,
				array(
					'source'          => 'manual-reset',
					'recipient_email' => (string) $current_user->user_email,
					'ip'              => $this->get_request_ip(),
					'actor_name'      => $current_user->exists() ? $current_user->user_login : esc_html__( 'Admin', 'gf-email-approvals' ),
				)
			);

			return $this->manual_action_feedback[ $entry_id ];
		}

		$status = self::MANUAL_APPROVE_ACTION === $action ? self::STATUS_APPROVED : self::STATUS_REJECTED;

		$this->manual_action_feedback[ $entry_id ] = $this->process_decision(
			$entry,
			$form,
			$status,
			array(
				'source'          => 'manual',
				'recipient_email' => (string) $current_user->user_email,
				'ip'              => $this->get_request_ip(),
				'actor_name'      => $current_user->exists() ? $current_user->user_login : esc_html__( 'Admin', 'gf-email-approvals' ),
			)
		);

		return $this->manual_action_feedback[ $entry_id ];
	}

	/**
	 * Resets a processed entry back to the pending state.
	 *
	 * @param array $entry   The current entry.
	 * @param array $form    The current form.
	 * @param array $context Execution context.
	 *
	 * @return array
	 */
	private function reset_entry_status( $entry, $form, $context ) {
		$entry_id       = absint( $this->array_value( $entry, 'id' ) );
		$current_status = $this->get_entry_status( $entry_id );

		if ( self::STATUS_PENDING === $current_status ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'This entry is already pending approval.', 'gf-email-approvals' ),
			);
		}

		$this->set_entry_status( $entry_id, self::STATUS_PENDING );
		GFEmailApprovalsTokenStore::invalidate_entry_tokens( $entry_id );

		$this->maybe_add_entry_note( $entry_id, self::STATUS_PENDING, $context );
		$this->log_debug(
			sprintf(
				'%s(): entry_id=%d form_id=%d recipient=%s ip=%s',
				__METHOD__,
				$entry_id,
				absint( $this->array_value( $form, 'id' ) ),
				isset( $context['recipient_email'] ) ? $context['recipient_email'] : '',
				isset( $context['ip'] ) ? $context['ip'] : ''
			)
		);

		return array(
			'success' => true,
			'message' => esc_html__( 'The entry has been reset to pending.', 'gf-email-approvals' ),
		);
	}

	/**
	 * Returns whether the current user can edit Gravity Forms entries.
	 *
	 * @return bool
	 */
	private function current_user_can_edit_entries() {
		return class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'current_user_can_any' )
			? GFCommon::current_user_can_any( 'gravityforms_edit_entries' )
			: current_user_can( 'gravityforms_edit_entries' );
	}

	/**
	 * Builds a concise notice for a completed entry list approval action.
	 *
	 * @param string $action    Action performed.
	 * @param int    $processed Number of updated entries.
	 * @param int    $skipped   Number of skipped entries.
	 * @param int    $missing   Number of entries that could not be loaded.
	 *
	 * @return string
	 */
	private function get_entry_list_action_notice_message( $action, $processed, $skipped, $missing ) {
		switch ( $action ) {
			case self::MANUAL_APPROVE_ACTION:
				$processed_message = sprintf(
					_n( '%d entry approved.', '%d entries approved.', $processed, 'gf-email-approvals' ),
					$processed
				);
				break;
			case self::MANUAL_REJECT_ACTION:
				$processed_message = sprintf(
					_n( '%d entry rejected.', '%d entries rejected.', $processed, 'gf-email-approvals' ),
					$processed
				);
				break;
			default:
				$processed_message = sprintf(
					_n( '%d entry reset to pending.', '%d entries reset to pending.', $processed, 'gf-email-approvals' ),
					$processed
				);
				break;
		}

		$parts = array();

		if ( $processed > 0 ) {
			$parts[] = $processed_message;
		}

		if ( $skipped > 0 ) {
			$parts[] = sprintf(
				_n( '%d entry skipped because its current status was not compatible.', '%d entries skipped because their current status was not compatible.', $skipped, 'gf-email-approvals' ),
				$skipped
			);
		}

		if ( $missing > 0 ) {
			$parts[] = sprintf(
				_n( '%d entry could not be loaded.', '%d entries could not be loaded.', $missing, 'gf-email-approvals' ),
				$missing
			);
		}

		if ( empty( $parts ) ) {
			return esc_html__( 'No entries were updated.', 'gf-email-approvals' );
		}

		return implode( ' ', $parts );
	}

	/**
	 * Outputs an inline alert in the entry list action flow.
	 *
	 * @param string $message Notice message.
	 * @param string $type    Alert type.
	 *
	 * @return void
	 */
	private function render_entry_list_action_notice( $message, $type ) {
		$class = in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success';

		echo '<div id="message" class="alert ' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Handles the POST confirmation for public approval links.
	 *
	 * @param string $token The raw token.
	 *
	 * @return void
	 */
	private function handle_public_action_submission( $token ) {
		$record = GFEmailApprovalsTokenStore::get_token_record( $token, true );

		if ( ! $record ) {
			$fallback = GFEmailApprovalsTokenStore::get_token_record( $token, false );
			$message  = $fallback ? esc_html__( 'This approval request has already been processed.', 'gf-email-approvals' ) : esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' );

			$this->render_public_message_page( $message );
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
		}

		$entry = GFAPI::get_entry( absint( $record->entry_id ) );
		$form  = GFAPI::get_form( absint( $record->form_id ) );

		if ( is_wp_error( $entry ) || empty( $form ) ) {
			$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
		}

		$notification = $this->get_form_notification( is_array( $form ) ? $form : array(), (string) $record->notification_id );

		$result = $this->process_decision(
			$entry,
			$form,
			(string) $record->action,
			array(
				'source'          => 'email',
				'token_id'        => absint( $record->id ),
				'recipient_email' => (string) $record->recipient_email,
				'ip'              => $this->get_request_ip(),
			)
		);

		$message = $result['message'];

		if ( ! empty( $result['success'] ) ) {
			$settings = $this->get_notification_page_settings( $notification, $form, $entry );
			$message  = self::STATUS_APPROVED === (string) $record->action
				? $settings[ self::NOTIFICATION_APPROVED_RESULT_TEXT ]
				: $settings[ self::NOTIFICATION_REJECTED_RESULT_TEXT ];
		}

		$this->render_public_message_page( $message );
	}

	/**
	 * Applies the approved or rejected decision to the entry.
	 *
	 * @param array  $entry   The current entry.
	 * @param array  $form    The current form.
	 * @param string $status  The target status.
	 * @param array  $context Execution context.
	 *
	 * @return array
	 */
	private function process_decision( $entry, $form, $status, $context ) {
		$entry_id       = absint( $this->array_value( $entry, 'id' ) );
		$current_status = $this->get_entry_status( $entry_id );

		if ( self::STATUS_PENDING !== $current_status ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'This approval request has already been processed.', 'gf-email-approvals' ),
			);
		}

		$this->set_entry_status( $entry_id, $status );

		if ( ! empty( $context['token_id'] ) ) {
			GFEmailApprovalsTokenStore::mark_token_used( absint( $context['token_id'] ), (string) $context['ip'] );
		}

		GFEmailApprovalsTokenStore::invalidate_entry_tokens( $entry_id, ! empty( $context['token_id'] ) ? absint( $context['token_id'] ) : 0 );

		$this->maybe_add_entry_note( $entry_id, $status, $context );
		$this->log_debug(
			sprintf(
				'%s(): entry_id=%d form_id=%d action=%s recipient=%s ip=%s',
				__METHOD__,
				$entry_id,
				absint( $this->array_value( $form, 'id' ) ),
				$status,
				isset( $context['recipient_email'] ) ? $context['recipient_email'] : '',
				isset( $context['ip'] ) ? $context['ip'] : ''
			)
		);

		if ( class_exists( 'GFAPI' ) ) {
			GFAPI::send_notifications( $form, $entry, 'approval_' . $status );
		}

		return array(
			'success' => true,
			'message' => self::STATUS_APPROVED === $status
				? esc_html__( 'The entry has been approved.', 'gf-email-approvals' )
				: esc_html__( 'The entry has been rejected.', 'gf-email-approvals' ),
		);
	}

	/**
	 * Adds a note to the entry for manual review later.
	 *
	 * @param int    $entry_id The entry id.
	 * @param string $status   The final status.
	 * @param array  $context  The execution context.
	 *
	 * @return void
	 */
	private function maybe_add_entry_note( $entry_id, $status, $context ) {
		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'add_note' ) ) {
			return;
		}

		$source = isset( $context['source'] ) ? (string) $context['source'] : 'system';
		$actor  = isset( $context['actor_name'] ) ? (string) $context['actor_name'] : ( isset( $context['recipient_email'] ) ? (string) $context['recipient_email'] : $source );
		$note   = sprintf( __( 'Approval status changed to %s via %s by %s.', 'gf-email-approvals' ), $status, $source, $actor );

		GFAPI::add_note( $entry_id, get_current_user_id(), 'Email Approvals', $note );
	}

	/**
	 * Renders the public confirmation screen.
	 *
	 * @param object $record The token record.
	 * @param string $token  The raw token.
	 *
	 * @return void
	 */
	private function render_public_confirmation_page( $record, $token, $notification = array(), $form = array(), $entry = array() ) {
		$settings   = $this->get_notification_page_settings( $notification, $form, $entry );
		$is_approve = self::STATUS_APPROVED === $record->action;
		$title      = $settings[ self::NOTIFICATION_CONFIRMATION_TITLE ];
		$message    = $is_approve
			? $settings[ self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ]
			: $settings[ self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ];
		$action_url = add_query_arg(
			array(
				self::QUERY_ACTION => self::PUBLIC_ACTION_CONFIRM,
				self::QUERY_TOKEN  => $token,
			),
			home_url( '/' )
		);
		$message_markup = $this->get_public_message_markup( $message );

		$this->render_public_page(
			$title,
			sprintf(
				'%1$s<form method="post" action="%2$s"><input type="hidden" name="%3$s" value="%4$s" /><input type="hidden" name="%5$s" value="%6$s" /><p><button type="submit" style="display:block;width:100%%;box-sizing:border-box;padding:12px 18px;background:#1d2327;color:#fff;border:0;border-radius:4px;cursor:pointer;">%7$s</button></p></form>',
				$message_markup,
				esc_url( $action_url ),
				esc_attr( self::QUERY_TOKEN ),
				esc_attr( $token ),
				esc_attr( self::QUERY_ACTION ),
				esc_attr( self::PUBLIC_ACTION_CONFIRM ),
				esc_html( $settings[ self::NOTIFICATION_CONFIRM_BUTTON_LABEL ] )
			)
		);
	}

	/**
	 * Renders a simple result page.
	 *
	 * @param string $message The message to render.
	 *
	 * @return void
	 */
	private function render_public_message_page( $message ) {
		$this->render_public_page( esc_html__( 'Approval', 'gf-email-approvals' ), $this->get_public_message_markup( $message ) );
	}

	/**
	 * Returns the default copy used on the public approval pages.
	 *
	 * @return array<string, string>
	 */
	private function get_notification_page_defaults() {
		return array(
			self::NOTIFICATION_CONFIRMATION_TITLE        => __( 'Confirm approval action', 'gf-email-approvals' ),
			self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT => __( 'You are about to approve this entry.', 'gf-email-approvals' ),
			self::NOTIFICATION_REJECT_CONFIRMATION_TEXT  => __( 'You are about to reject this entry.', 'gf-email-approvals' ),
			self::NOTIFICATION_CONFIRM_BUTTON_LABEL      => __( 'Confirm', 'gf-email-approvals' ),
			self::NOTIFICATION_APPROVED_RESULT_TEXT      => __( 'The entry has been approved.', 'gf-email-approvals' ),
			self::NOTIFICATION_REJECTED_RESULT_TEXT      => __( 'The entry has been rejected.', 'gf-email-approvals' ),
		);
	}

	/**
	 * Returns the approval-specific merge tags in Gravity Forms selector format.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_approval_merge_tags() {
		return array(
			array(
				'label' => esc_html__( 'Approval Status', 'gf-email-approvals' ),
				'tag'   => '{approval_status}',
			),
			array(
				'label' => esc_html__( 'Approval Approve URL', 'gf-email-approvals' ),
				'tag'   => '{approval_approve_url}',
			),
			array(
				'label' => esc_html__( 'Approval Reject URL', 'gf-email-approvals' ),
				'tag'   => '{approval_reject_url}',
			),
			array(
				'label' => esc_html__( 'Approval Approve Button', 'gf-email-approvals' ),
				'tag'   => '{approval_approve_button}',
			),
			array(
				'label' => esc_html__( 'Approval Reject Button', 'gf-email-approvals' ),
				'tag'   => '{approval_reject_button}',
			),
		);
	}

	/**
	 * Returns the effective page copy for a notification, with defaults as fallback.
	 *
	 * @param array $notification The notification object.
	 *
	 * @return array<string, string>
	 */
	private function get_notification_page_settings( $notification, $form = array(), $entry = array() ) {
		$settings = $this->get_notification_page_defaults();

		foreach ( $settings as $setting_name => $default_value ) {
			$value = $this->array_value( $notification, $setting_name );

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$settings[ $setting_name ] = $value;
			}
		}

		foreach ( $settings as $setting_name => $value ) {
			$settings[ $setting_name ] = $this->replace_merge_tags_in_text( $value, $form, $entry );
		}

		return $settings;
	}

	/**
	 * Resolves Gravity Forms merge tags in arbitrary text using the current form and entry.
	 *
	 * @param string     $text  The text to parse.
	 * @param array|bool $form  The current form.
	 * @param array|bool $entry The current entry.
	 *
	 * @return string
	 */
	private function replace_merge_tags_in_text( $text, $form, $entry ) {
		if ( ! is_string( $text ) || '' === $text || false === strpos( $text, '{' ) ) {
			return (string) $text;
		}

		if ( ! class_exists( 'GFCommon' ) || ! method_exists( 'GFCommon', 'replace_variables' ) ) {
			return $text;
		}

		return GFCommon::replace_variables(
			$text,
			! empty( $form ) ? $form : false,
			! empty( $entry ) ? $entry : false,
			false,
			false,
			false,
			'text'
		);
	}

	/**
	 * Finds a notification by id on the current form.
	 *
	 * @param array  $form            The form object.
	 * @param string $notification_id The notification id.
	 *
	 * @return array
	 */
	private function get_form_notification( $form, $notification_id ) {
		$notifications = $this->array_value( $form, 'notifications' );

		if ( ! is_array( $notifications ) || '' === $notification_id ) {
			return array();
		}

		if ( isset( $notifications[ $notification_id ] ) && is_array( $notifications[ $notification_id ] ) ) {
			return $notifications[ $notification_id ];
		}

		foreach ( $notifications as $notification ) {
			if ( ! is_array( $notification ) ) {
				continue;
			}

			if ( $notification_id === (string) $this->array_value( $notification, 'id' ) ) {
				return $notification;
			}
		}

		return array();
	}

	/**
	 * Formats plain notification copy for the public approval pages.
	 *
	 * @param string $message The message to render.
	 *
	 * @return string
	 */
	private function get_public_message_markup( $message ) {
		return '<p style="white-space:pre-line;">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Outputs the public approval page.
	 *
	 * @param string $title   Page title.
	 * @param string $content Page content.
	 *
	 * @return void
	 */
	private function render_public_page( $title, $content ) {
		status_header( 200 );
		nocache_headers();
		$allowed_html = array(
			'form'    => array(
				'method' => true,
				'action' => true,
			),
			'input'   => array(
				'type'  => true,
				'name'  => true,
				'value' => true,
			),
			'button'  => array(
				'type'  => true,
				'style' => true,
			),
			'p'       => array(
				'style' => true,
			),
			'div'     => array(
				'style' => true,
			),
			'a'       => array(
				'href'   => true,
				'style'  => true,
				'target' => true,
				'rel'    => true,
			),
			'span'    => array(
				'style' => true,
			),
			'strong'  => array(),
			'br'      => array(),
			'h1'      => array(
				'style' => true,
			),
			'section' => array(
				'style' => true,
			),
			'main'    => array(
				'style' => true,
			),
		);

		echo '<!doctype html><html><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo '</head><body style="margin:0;font-family:Segoe UI,Arial,sans-serif;background:#f5f5f5;color:#1d2327;">';
		echo '<main style="max-width:640px;margin:8vh auto;padding:24px;">';
		echo '<section style="background:#ffffff;border-radius:12px;padding:32px;box-shadow:0 10px 30px rgba(0,0,0,0.08);">';
		echo '<h1 style="margin-top:0;font-size:28px;">' . esc_html( $title ) . '</h1>';
		echo wp_kses( $content, $allowed_html );
		echo '</section></main></body></html>';

		exit;
	}

	/**
	 * Returns the request IP address when available.
	 *
	 * @return string
	 */
	private function get_request_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Returns an array value without depending on Gravity Forms helper functions.
	 *
	 * @param array|mixed $source  The source array.
	 * @param string      $key     The requested key.
	 * @param mixed       $default The fallback value.
	 *
	 * @return mixed
	 */
	private function array_value( $source, $key, $default = '' ) {
		return is_array( $source ) && array_key_exists( $key, $source ) ? $source[ $key ] : $default;
	}
}