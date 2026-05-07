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
		public static function get_field( $form_or_id, $field_id ) {}
		public static function update_entry_field( $entry_id, $input_id, $value, $item_index = '' ) {}
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
	const PUBLIC_DECISION_UPDATE_VALUE = 'gf_email_approval_update_value';
	const NOTIFICATION_CONFIRMATION_TITLE = 'approval_confirmation_title';
	const NOTIFICATION_APPROVE_CONFIRMATION_TEXT = 'approval_approve_confirmation_text';
	const NOTIFICATION_REJECT_CONFIRMATION_TEXT = 'approval_reject_confirmation_text';
	const NOTIFICATION_APPROVE_BUTTON_LABEL = 'approval_approve_button_label';
	const NOTIFICATION_REJECT_BUTTON_LABEL = 'approval_reject_button_label';
	const NOTIFICATION_APPROVED_RESULT_TEXT = 'approval_approved_result_text';
	const NOTIFICATION_REJECTED_RESULT_TEXT = 'approval_rejected_result_text';
	const NOTIFICATION_UPDATE_MODE = 'approval_update_mode';
	const NOTIFICATION_DECISION_UPDATE_FIELD = 'approval_decision_update_field';
	const NOTIFICATION_APPROVED_TEXT_VALUE = 'approval_approved_text_value';
	const NOTIFICATION_REJECTED_TEXT_VALUE = 'approval_rejected_text_value';
	const NOTIFICATION_APPROVED_CHOICE_VALUE = 'approval_approved_choice_value';
	const NOTIFICATION_REJECTED_CHOICE_VALUE = 'approval_rejected_choice_value';
	const NOTIFICATION_APPROVED_CHOICE_VALUES = 'approval_approved_choice_values';
	const NOTIFICATION_REJECTED_CHOICE_VALUES = 'approval_rejected_choice_values';
	const PLUGIN_SETTING_PAGE_BACKGROUND_COLOR = 'approval_page_background_color';
	const PLUGIN_SETTING_CARD_BACKGROUND_COLOR = 'approval_page_card_background_color';
	const PLUGIN_SETTING_TEXT_COLOR = 'approval_page_text_color';
	const PLUGIN_SETTING_TITLE_COLOR = 'approval_page_title_color';
	const PLUGIN_SETTING_APPROVE_BUTTON_COLOR = 'approval_page_approve_button_color';
	const PLUGIN_SETTING_REJECT_BUTTON_COLOR = 'approval_page_reject_button_color';
	const PLUGIN_SETTING_BUTTON_TEXT_COLOR = 'approval_page_button_text_color';
	const PLUGIN_SETTING_CARD_MAX_WIDTH = 'approval_page_card_max_width';
	const PLUGIN_SETTING_CARD_PADDING = 'approval_page_card_padding';
	const PLUGIN_SETTING_CARD_BORDER_RADIUS = 'approval_page_card_border_radius';
	const UPDATE_MODE_AUTOMATIC = 'automatic';
	const UPDATE_MODE_MANUAL = 'manual';

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
		add_action( 'gform_update_status', array( $this, 'handle_entry_status_change' ), 10, 3 );
		add_action( 'gform_delete_entry', array( $this, 'handle_entry_delete' ) );
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_appearance_builder_assets' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'print_decision_update_settings_script' ) );
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
	 * Registers plugin-level appearance settings for the public approval pages.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function plugin_settings_fields() {
		$defaults = $this->get_public_page_theme_defaults();

		return array(
			array(
				'title'       => esc_html__( 'Approval Page Appearance', 'gf-email-approvals' ),
				'fields'      => array(
					array(
						'name'          => self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR,
						'label'         => esc_html__( 'Page background color', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'medium gf-email-approvals-color-control',
						'default_value' => $defaults[ self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR,
						'label'         => esc_html__( 'Card background color', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'medium gf-email-approvals-color-control',
						'default_value' => $defaults[ self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_TEXT_COLOR,
						'label'         => esc_html__( 'Body text color', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'medium gf-email-approvals-color-control',
						'default_value' => $defaults[ self::PLUGIN_SETTING_TEXT_COLOR ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_TITLE_COLOR,
						'label'         => esc_html__( 'Title color', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'medium gf-email-approvals-color-control',
						'default_value' => $defaults[ self::PLUGIN_SETTING_TITLE_COLOR ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR,
						'label'         => esc_html__( 'Approve button color', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'medium gf-email-approvals-color-control',
						'default_value' => $defaults[ self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_REJECT_BUTTON_COLOR,
						'label'         => esc_html__( 'Reject button color', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'medium gf-email-approvals-color-control',
						'default_value' => $defaults[ self::PLUGIN_SETTING_REJECT_BUTTON_COLOR ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_BUTTON_TEXT_COLOR,
						'label'         => esc_html__( 'Button text color', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'medium gf-email-approvals-color-control',
						'default_value' => $defaults[ self::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_CARD_MAX_WIDTH,
						'label'         => esc_html__( 'Card max width (320px to 960px)', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => (string) $defaults[ self::PLUGIN_SETTING_CARD_MAX_WIDTH ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_CARD_PADDING,
						'label'         => esc_html__( 'Card padding (16px to 80px)', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => (string) $defaults[ self::PLUGIN_SETTING_CARD_PADDING ],
					),
					array(
						'name'          => self::PLUGIN_SETTING_CARD_BORDER_RADIUS,
						'label'         => esc_html__( 'Card radius (0px to 40px)', 'gf-email-approvals' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => (string) $defaults[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ],
					),
					array(
						'name'        => 'approval_page_preview',
						'label'       => esc_html__( 'Live preview', 'gf-email-approvals' ),
						'type'        => 'approval_page_preview',
					),
				),
			),
		);
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
		$defaults                  = $this->get_notification_page_defaults();
		$decision_settings         = $this->get_notification_decision_update_settings( $notification );
		$decision_defaults         = $this->get_notification_decision_update_defaults();
		$decision_field_choices    = $this->get_decision_update_field_choices( $form, (string) $decision_settings[ self::NOTIFICATION_UPDATE_MODE ] );
		$current_target_field      = $this->get_decision_update_field( $form, (string) $decision_settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ], (string) $decision_settings[ self::NOTIFICATION_UPDATE_MODE ] );
		$current_target_choices    = $current_target_field ? $this->get_supported_field_choice_options( $current_target_field ) : array();
		$current_single_choices    = array_merge(
			array(
				array(
					'value' => '',
					'label' => esc_html__( 'Leave unchanged', 'gf-email-approvals' ),
				),
			),
			$current_target_choices
		);

		$fields[] = array(
			'title'       => esc_html__( 'Approval Pages', 'gf-email-approvals' ),
			'description' => esc_html__( 'Customize the public confirmation and result pages used by Approval Request links. Gravity Forms merge tags are supported.', 'gf-email-approvals' ),
			'id'          => 'approval-pages',
			'dependency'  => array(
				'live'   => true,
				'fields' => array(
					array(
						'field'  => 'event',
						'values' => array( 'approval_request' ),
					),
				),
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
					'class'         => 'large merge-tag-support mt-position-right approval-page-copy-textarea',
					'default_value' => $defaults[ self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ],
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_APPROVED_RESULT_TEXT,
					'label'         => esc_html__( 'Approved result text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right approval-page-copy-textarea',
					'default_value' => $defaults[ self::NOTIFICATION_APPROVED_RESULT_TEXT ],
				),
				array(
					'type'          => 'text',
					'name'          => self::NOTIFICATION_APPROVE_BUTTON_LABEL,
					'label'         => esc_html__( 'Approve button label', 'gf-email-approvals' ),
					'class'         => 'medium merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_APPROVE_BUTTON_LABEL ],
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_REJECT_CONFIRMATION_TEXT,
					'label'         => esc_html__( 'Reject confirmation text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right approval-page-copy-textarea',
					'default_value' => $defaults[ self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ],
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_REJECTED_RESULT_TEXT,
					'label'         => esc_html__( 'Rejected result text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right approval-page-copy-textarea',
					'default_value' => $defaults[ self::NOTIFICATION_REJECTED_RESULT_TEXT ],
				),
				array(
					'type'          => 'text',
					'name'          => self::NOTIFICATION_REJECT_BUTTON_LABEL,
					'label'         => esc_html__( 'Reject button label', 'gf-email-approvals' ),
					'class'         => 'medium merge-tag-support mt-position-right',
					'default_value' => $defaults[ self::NOTIFICATION_REJECT_BUTTON_LABEL ],
				),
			),
		);

		$fields[] = array(
			'title'       => esc_html__( 'Approval actions', 'gf-email-approvals' ),
			'description' => esc_html__( 'Optionally update one supported entry field after the approver confirms their decision. You can either apply a predefined value automatically or let the approver choose the value on the confirmation page.', 'gf-email-approvals' ),
			'id'          => 'approval-actions',
			'dependency'  => array(
				'live'   => true,
				'fields' => array(
					array(
						'field'  => 'event',
						'values' => array( 'approval_request' ),
					),
				),
			),
			'fields'      => array(
				array(
					'type'          => 'select',
					'name'          => self::NOTIFICATION_UPDATE_MODE,
					'label'         => esc_html__( 'Update behavior', 'gf-email-approvals' ),
					'class'         => 'medium',
					'choices'       => $this->get_decision_update_mode_choices(),
					'default_value' => $decision_settings[ self::NOTIFICATION_UPDATE_MODE ],
					'description'   => esc_html__( 'Choose whether the value is applied automatically when the link is confirmed or selected by the approver on the confirmation page.', 'gf-email-approvals' ),
				),
				array(
					'type'        => 'select',
					'name'        => self::NOTIFICATION_DECISION_UPDATE_FIELD,
					'label'       => esc_html__( 'Field to update', 'gf-email-approvals' ),
					'class'       => 'medium',
					'choices'     => $decision_field_choices,
					'description' => esc_html__( 'Choose one supported field to update after approval or rejection.', 'gf-email-approvals' ),
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_APPROVED_TEXT_VALUE,
					'label'         => esc_html__( 'Approved value', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right',
					'default_value' => $decision_defaults[ self::NOTIFICATION_APPROVED_TEXT_VALUE ],
					'description'   => esc_html__( 'Used for text-like target fields. Merge tags are supported. Leave blank to keep the field unchanged.', 'gf-email-approvals' ),
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_REJECTED_TEXT_VALUE,
					'label'         => esc_html__( 'Rejected value', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right',
					'default_value' => $decision_defaults[ self::NOTIFICATION_REJECTED_TEXT_VALUE ],
					'description'   => esc_html__( 'Used for text-like target fields. Merge tags are supported. Leave blank to keep the field unchanged.', 'gf-email-approvals' ),
				),
				array(
					'type'        => 'select',
					'name'        => self::NOTIFICATION_APPROVED_CHOICE_VALUE,
					'label'       => esc_html__( 'Approved choice', 'gf-email-approvals' ),
					'class'       => 'medium',
					'choices'     => $current_single_choices,
					'description' => esc_html__( 'Used for drop down and multiple choice target fields. Leave unchanged to keep the current value.', 'gf-email-approvals' ),
				),
				array(
					'type'        => 'select',
					'name'        => self::NOTIFICATION_REJECTED_CHOICE_VALUE,
					'label'       => esc_html__( 'Rejected choice', 'gf-email-approvals' ),
					'class'       => 'medium',
					'choices'     => $current_single_choices,
					'description' => esc_html__( 'Used for drop down and multiple choice target fields. Leave unchanged to keep the current value.', 'gf-email-approvals' ),
				),
				array(
					'type'        => 'select',
					'name'        => self::NOTIFICATION_APPROVED_CHOICE_VALUES,
					'label'       => esc_html__( 'Approved choices', 'gf-email-approvals' ),
					'class'       => 'medium',
					'choices'     => $current_target_choices,
					'multiple'    => true,
					'description' => esc_html__( 'Used for checkbox and multi select target fields. Leave empty to keep the current value.', 'gf-email-approvals' ),
				),
				array(
					'type'        => 'select',
					'name'        => self::NOTIFICATION_REJECTED_CHOICE_VALUES,
					'label'       => esc_html__( 'Rejected choices', 'gf-email-approvals' ),
					'class'       => 'medium',
					'choices'     => $current_target_choices,
					'multiple'    => true,
					'description' => esc_html__( 'Used for checkbox and multi select target fields. Leave empty to keep the current value.', 'gf-email-approvals' ),
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
		$text_fields = array(
			self::NOTIFICATION_CONFIRMATION_TITLE,
			self::NOTIFICATION_APPROVE_BUTTON_LABEL,
			self::NOTIFICATION_REJECT_BUTTON_LABEL,
		);

		foreach ( $text_fields as $setting_name ) {
			$posted_value = $this->get_posted_notification_setting( $setting_name, null );

			if ( null === $posted_value ) {
				continue;
			}

			$value = sanitize_text_field( (string) $posted_value );

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
			$posted_value = $this->get_posted_notification_setting( $setting_name, null );

			if ( null === $posted_value ) {
				continue;
			}

			$value = sanitize_textarea_field( (string) $posted_value );

			if ( '' === $value ) {
				unset( $notification[ $setting_name ] );
				continue;
			}

			$notification[ $setting_name ] = $value;
		}

		$update_mode = sanitize_key( (string) $this->get_posted_notification_setting( self::NOTIFICATION_UPDATE_MODE, '' ) );

		if ( ! in_array( $update_mode, array( self::UPDATE_MODE_AUTOMATIC, self::UPDATE_MODE_MANUAL ), true ) ) {
			$update_mode = '';
			unset( $notification[ self::NOTIFICATION_UPDATE_MODE ] );
		} else {
			$notification[ self::NOTIFICATION_UPDATE_MODE ] = $update_mode;
		}

		$target_field_id = sanitize_text_field( (string) $this->get_posted_notification_setting( self::NOTIFICATION_DECISION_UPDATE_FIELD, '' ) );

		$current_target_field = '' !== $target_field_id && '' !== $update_mode ? $this->get_decision_update_field( $form, $target_field_id, $update_mode ) : null;

		if ( $current_target_field ) {
			$notification[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] = (string) $current_target_field->id;
		} else {
			unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] );
		}

		$current_target_kind = $current_target_field ? $this->get_decision_update_field_kind( $current_target_field ) : '';

		$text_update_fields = array(
			self::NOTIFICATION_APPROVED_TEXT_VALUE,
			self::NOTIFICATION_REJECTED_TEXT_VALUE,
		);

		if ( self::UPDATE_MODE_AUTOMATIC === $update_mode && 'text' === $current_target_kind ) {
			foreach ( $text_update_fields as $setting_name ) {
				$value = sanitize_textarea_field( (string) $this->get_posted_notification_setting( $setting_name, '' ) );

				if ( '' === $value ) {
					unset( $notification[ $setting_name ] );
					continue;
				}

				$notification[ $setting_name ] = $value;
			}
		} else {
			foreach ( $text_update_fields as $setting_name ) {
				unset( $notification[ $setting_name ] );
			}
		}

		$choice_value_map = $current_target_field ? $this->get_supported_field_choice_value_map( $current_target_field ) : array();

		$single_choice_fields = array(
			self::NOTIFICATION_APPROVED_CHOICE_VALUE,
			self::NOTIFICATION_REJECTED_CHOICE_VALUE,
		);

		if ( self::UPDATE_MODE_AUTOMATIC === $update_mode && 'single' === $current_target_kind ) {
			foreach ( $single_choice_fields as $setting_name ) {
				$value = sanitize_text_field( (string) $this->get_posted_notification_setting( $setting_name, '' ) );

				if ( '' === $value || ! isset( $choice_value_map[ $value ] ) ) {
					unset( $notification[ $setting_name ] );
					continue;
				}

				$notification[ $setting_name ] = $value;
			}
		} else {
			foreach ( $single_choice_fields as $setting_name ) {
				unset( $notification[ $setting_name ] );
			}
		}

		$multi_choice_fields = array(
			self::NOTIFICATION_APPROVED_CHOICE_VALUES,
			self::NOTIFICATION_REJECTED_CHOICE_VALUES,
		);

		if ( self::UPDATE_MODE_AUTOMATIC === $update_mode && 'multi' === $current_target_kind ) {
			foreach ( $multi_choice_fields as $setting_name ) {
				$raw_values = $this->get_posted_notification_setting( $setting_name, array() );

				if ( ! is_array( $raw_values ) ) {
					unset( $notification[ $setting_name ] );
					continue;
				}

				$values = array();

				foreach ( $raw_values as $raw_value ) {
					$value = sanitize_text_field( $raw_value );

					if ( '' === $value || ! isset( $choice_value_map[ $value ] ) ) {
						continue;
					}

					$values[] = $value;
				}

				$values = array_values( array_unique( $values ) );

				if ( empty( $values ) ) {
					unset( $notification[ $setting_name ] );
					continue;
				}

				$notification[ $setting_name ] = $values;
			}
		} else {
			foreach ( $multi_choice_fields as $setting_name ) {
				unset( $notification[ $setting_name ] );
			}
		}

		return $notification;
	}

	/**
	 * Returns a posted notification setting value from the Gravity Forms settings renderer.
	 *
	 * @param string $setting_name The setting name.
	 * @param mixed  $default      The default value if the setting was not posted.
	 *
	 * @return mixed
	 */
	private function get_posted_notification_setting( $setting_name, $default = null ) {
		$posted_values = array();

		if ( class_exists( 'GFNotification' ) && method_exists( 'GFNotification', 'get_settings_renderer' ) ) {
			$settings_renderer = GFNotification::get_settings_renderer();

			if ( is_object( $settings_renderer ) && method_exists( $settings_renderer, 'get_posted_values' ) ) {
				$posted_values = $settings_renderer->get_posted_values();
			}
		}

		if ( is_array( $posted_values ) && array_key_exists( $setting_name, $posted_values ) ) {
			return $posted_values[ $setting_name ];
		}

		if ( isset( $_POST[ $setting_name ] ) ) {
			return wp_unslash( $_POST[ $setting_name ] );
		}

		$prefixed_setting_name = '_gform_setting_' . $setting_name;

		if ( isset( $_POST[ $prefixed_setting_name ] ) ) {
			return wp_unslash( $_POST[ $prefixed_setting_name ] );
		}

		return $default;
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
	 * Prints the admin script used to toggle post-confirmation field update controls.
	 *
	 * @return void
	 */
	public function print_decision_update_settings_script() {
		if ( ! class_exists( 'GFForms' ) || ! method_exists( 'GFForms', 'is_gravity_page' ) || ! GFForms::is_gravity_page() ) {
			return;
		}

		$form_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$form    = ( $form_id && class_exists( 'GFAPI' ) ) ? GFAPI::get_form( $form_id ) : array();
		$form    = is_array( $form ) ? $form : array();

		?>
		<style type="text/css">
			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ); ?>,
			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_APPROVED_RESULT_TEXT ); ?>,
			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ); ?>,
			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_REJECTED_RESULT_TEXT ); ?> {
				box-sizing: border-box;
				float: left;
				width: calc(50% - 8px);
			}

			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ); ?>,
			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ); ?> {
				clear: left;
				margin-right: 16px;
			}

			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_APPROVE_BUTTON_LABEL ); ?>,
			#gform_setting_<?php echo esc_attr( self::NOTIFICATION_REJECT_BUTTON_LABEL ); ?> {
				clear: both;
				float: none;
				width: 100%;
			}

			@media screen and (max-width: 900px) {
				#gform_setting_<?php echo esc_attr( self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ); ?>,
				#gform_setting_<?php echo esc_attr( self::NOTIFICATION_APPROVED_RESULT_TEXT ); ?>,
				#gform_setting_<?php echo esc_attr( self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ); ?>,
				#gform_setting_<?php echo esc_attr( self::NOTIFICATION_REJECTED_RESULT_TEXT ); ?> {
					float: none;
					clear: both;
					width: 100%;
					margin-right: 0;
				}
			}

			textarea.approval-page-copy-textarea.large {
				height: 80px;
				min-height: 80px;
			}
		</style>
		<script type="text/javascript">
			(function($) {
				var fieldConfig = <?php echo wp_json_encode( $this->get_decision_update_field_config( $form ) ); ?>;
				var targetFieldChoices = {
					automatic: <?php echo wp_json_encode( $this->get_decision_update_field_choices( $form, self::UPDATE_MODE_AUTOMATIC, false ) ); ?>,
					manual: <?php echo wp_json_encode( $this->get_decision_update_field_choices( $form, self::UPDATE_MODE_MANUAL, false ) ); ?>
				};
				var fieldNames = {
					mode: '<?php echo esc_js( self::NOTIFICATION_UPDATE_MODE ); ?>',
					target: '<?php echo esc_js( self::NOTIFICATION_DECISION_UPDATE_FIELD ); ?>',
					approvedText: '<?php echo esc_js( self::NOTIFICATION_APPROVED_TEXT_VALUE ); ?>',
					rejectedText: '<?php echo esc_js( self::NOTIFICATION_REJECTED_TEXT_VALUE ); ?>',
					approvedChoice: '<?php echo esc_js( self::NOTIFICATION_APPROVED_CHOICE_VALUE ); ?>',
					rejectedChoice: '<?php echo esc_js( self::NOTIFICATION_REJECTED_CHOICE_VALUE ); ?>',
					approvedChoices: '<?php echo esc_js( self::NOTIFICATION_APPROVED_CHOICE_VALUES ); ?>',
					rejectedChoices: '<?php echo esc_js( self::NOTIFICATION_REJECTED_CHOICE_VALUES ); ?>'
				};

				function getFieldInput(name) {
					return $('#' + name);
				}

				function getFieldRow(name) {
					return getFieldInput(name).closest('.gform-settings-field');
				}

				function setSelectChoices(name, choices, multiple, emptyLabel) {
					var $input = getFieldInput(name);
					if (!$input.length) {
						return;
					}

					var selected = $input.val();
					if (multiple) {
						selected = Array.isArray(selected) ? selected : (selected ? [selected] : []);
					} else {
						selected = selected || '';
					}

					var html = '';
					if (!multiple) {
						html += '<option value="">' + $('<div />').text(emptyLabel || '<?php echo esc_js( __( 'Leave unchanged', 'gf-email-approvals' ) ); ?>').html() + '</option>';
					}

					choices.forEach(function(choice) {
						html += '<option value="' + $('<div />').text(choice.value).html() + '">' + $('<div />').text(choice.label).html() + '</option>';
					});

					$input.html(html);

					if (multiple) {
						var validValues = selected.filter(function(value) {
							return choices.some(function(choice) {
								return choice.value === value;
							});
						});

						$input.val(validValues);
					} else {
						var isValid = choices.some(function(choice) {
							return choice.value === selected;
						});

						$input.val(isValid ? selected : '');
					}
				}

				function toggleDecisionUpdateFields() {
					var updateMode = getFieldInput(fieldNames.mode).val() || '';
					var isAutomatic = updateMode === '<?php echo esc_js( self::UPDATE_MODE_AUTOMATIC ); ?>';
					var hasUpdateMode = updateMode !== '';
					var targetChoices = hasUpdateMode && targetFieldChoices[updateMode] ? targetFieldChoices[updateMode] : [];

					setSelectChoices(fieldNames.target, targetChoices, false, '<?php echo esc_js( __( 'Do not update any field', 'gf-email-approvals' ) ); ?>');

					var targetFieldId = hasUpdateMode ? (getFieldInput(fieldNames.target).val() || '') : '';
					var config = fieldConfig[targetFieldId] || null;
					var kind = config ? config.kind : '';
					var choices = config ? config.choices : [];

					setSelectChoices(fieldNames.approvedChoice, choices, false, '<?php echo esc_js( __( 'Leave unchanged', 'gf-email-approvals' ) ); ?>');
					setSelectChoices(fieldNames.rejectedChoice, choices, false, '<?php echo esc_js( __( 'Leave unchanged', 'gf-email-approvals' ) ); ?>');
					setSelectChoices(fieldNames.approvedChoices, choices, true);
					setSelectChoices(fieldNames.rejectedChoices, choices, true);

					getFieldRow(fieldNames.target).toggle(hasUpdateMode);
					getFieldRow(fieldNames.approvedText).toggle(isAutomatic && kind === 'text');
					getFieldRow(fieldNames.rejectedText).toggle(isAutomatic && kind === 'text');
					getFieldRow(fieldNames.approvedChoice).toggle(isAutomatic && kind === 'single');
					getFieldRow(fieldNames.rejectedChoice).toggle(isAutomatic && kind === 'single');
					getFieldRow(fieldNames.approvedChoices).toggle(isAutomatic && kind === 'multi');
					getFieldRow(fieldNames.rejectedChoices).toggle(isAutomatic && kind === 'multi');
				}

				$(function() {
					toggleDecisionUpdateFields();
				});

				$(document).on('change', '#' + fieldNames.mode, toggleDecisionUpdateFields);
				$(document).on('change', '#' + fieldNames.target, toggleDecisionUpdateFields);
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * Enqueues assets used by the approval appearance builder on the plugin settings page.
	 *
	 * @return void
	 */
	public function enqueue_appearance_builder_assets() {
		if ( ! $this->is_approval_plugin_settings_page() ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Returns whether the current admin request is the add-on settings page.
	 *
	 * @return bool
	 */
	private function is_approval_plugin_settings_page() {
		if ( ! is_admin() || ! class_exists( 'GFForms' ) || ! method_exists( 'GFForms', 'is_gravity_page' ) || ! GFForms::is_gravity_page() ) {
			return false;
		}

		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$subview = isset( $_GET['subview'] ) ? sanitize_key( wp_unslash( $_GET['subview'] ) ) : '';

		return 'gf_settings' === $page && $this->_slug === $subview;
	}

	/**
	 * Renders the live preview field used on the plugin settings screen.
	 *
	 * @param array $field The field configuration.
	 * @param bool  $echo  Whether to print the markup immediately.
	 *
	 * @return string|void
	 */
	public function settings_approval_page_preview( $field, $echo = true ) {
		unset( $field );

		$defaults       = $this->get_public_page_theme_defaults();
		$current_theme  = $this->get_public_page_theme_settings();
		$preview_config = array(
			'settings' => array(
				'pageBackground' => self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR,
				'cardBackground' => self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR,
				'textColor'      => self::PLUGIN_SETTING_TEXT_COLOR,
				'titleColor'     => self::PLUGIN_SETTING_TITLE_COLOR,
				'approveButton'  => self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR,
				'rejectButton'   => self::PLUGIN_SETTING_REJECT_BUTTON_COLOR,
				'buttonText'     => self::PLUGIN_SETTING_BUTTON_TEXT_COLOR,
				'cardWidth'      => self::PLUGIN_SETTING_CARD_MAX_WIDTH,
				'cardPadding'    => self::PLUGIN_SETTING_CARD_PADDING,
				'cardRadius'     => self::PLUGIN_SETTING_CARD_BORDER_RADIUS,
			),
			'defaults' => array(
				'pageBackground' => $defaults[ self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ],
				'cardBackground' => $defaults[ self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
				'textColor'      => $defaults[ self::PLUGIN_SETTING_TEXT_COLOR ],
				'titleColor'     => $defaults[ self::PLUGIN_SETTING_TITLE_COLOR ],
				'approveButton'  => $defaults[ self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ],
				'rejectButton'   => $defaults[ self::PLUGIN_SETTING_REJECT_BUTTON_COLOR ],
				'buttonText'     => $defaults[ self::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
				'cardWidth'      => $defaults[ self::PLUGIN_SETTING_CARD_MAX_WIDTH ],
				'cardPadding'    => $defaults[ self::PLUGIN_SETTING_CARD_PADDING ],
				'cardRadius'     => $defaults[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ],
			),
			'states'   => array(
				'approve' => array(
					'title'      => esc_html__( 'Confirm approval action', 'gf-email-approvals' ),
					'message'    => esc_html__( 'You are about to approve this entry.', 'gf-email-approvals' ),
					'button'     => esc_html__( 'Approve', 'gf-email-approvals' ),
					'variant'    => 'approve',
					'showButton' => true,
					'showField'  => true,
				),
				'reject'  => array(
					'title'      => esc_html__( 'Confirm approval action', 'gf-email-approvals' ),
					'message'    => esc_html__( 'You are about to reject this entry.', 'gf-email-approvals' ),
					'button'     => esc_html__( 'Reject', 'gf-email-approvals' ),
					'variant'    => 'reject',
					'showButton' => true,
					'showField'  => true,
				),
				'result'  => array(
					'title'      => esc_html__( 'Approval', 'gf-email-approvals' ),
					'message'    => esc_html__( 'The entry has been approved.', 'gf-email-approvals' ),
					'button'     => '',
					'variant'    => 'approve',
					'showButton' => false,
					'showField'  => false,
				),
			),
		);

		ob_start();

		static $assets_printed = false;

		if ( ! $assets_printed ) {
			$assets_printed = true;
			?>
			<style type="text/css">
				.gf-email-approvals-appearance-grid {
					display: flex;
					flex-wrap: wrap;
					gap: 16px;
					align-items: flex-start;
				}

				.gf-email-approvals-appearance-grid > .gform-settings-field {
					float: none;
					clear: none;
					margin: 0;
				}

				.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting {
					flex: 0 0 calc(33.333% - 11px);
					max-width: calc(33.333% - 11px);
					min-width: 0;
					position: relative;
				}

				.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--color {
					flex-basis: calc(50% - 8px);
					max-width: calc(50% - 8px);
				}

				.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--base-color {
					flex-basis: calc(25% - 12px);
					max-width: calc(25% - 12px);
				}

				.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--button-color {
					flex-basis: calc(33.333% - 11px);
					max-width: calc(33.333% - 11px);
				}

				.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-preview-row {
					flex: 0 0 100%;
					max-width: 100%;
				}

				.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-row-break {
					flex: 0 0 100%;
					max-width: 100%;
					height: 0;
					margin: 0;
					padding: 0;
				}

				.gf-email-approvals-appearance-setting .wp-picker-container,
				.gf-email-approvals-appearance-setting .wp-picker-input-wrap,
				.gf-email-approvals-appearance-setting .wp-picker-holder {
					max-width: 100%;
					box-sizing: border-box;
				}

				.gf-email-approvals-appearance-setting .wp-picker-container {
					display: flex;
					flex-wrap: wrap;
					align-items: stretch;
					gap: 8px;
					width: 100%;
					position: relative;
					z-index: 1;
				}

				.gf-email-approvals-appearance-setting .wp-picker-container.wp-picker-active {
					z-index: 20;
				}

				.gf-email-approvals-appearance-setting .wp-picker-container .wp-color-result.button {
					border: 1px solid #9092b2;
					display: inline-flex;
					align-items: stretch;
					min-height: 40px;
					padding: 0 0 0 35px;
					margin: 0;
					box-shadow: none;
					overflow: hidden;
				}

				.gf-email-approvals-appearance-setting .wp-picker-container.wp-picker-active .wp-color-result.button {
					flex: 0 0 auto;
				}

				.gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--base-color .wp-picker-container .wp-color-result.button,
				.gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--base-color .wp-picker-container.wp-picker-active .wp-color-result.button,
				.gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--base-color .wp-picker-input-wrap {
					flex: 1 1 calc(50% - 4px);
					max-width: calc(50% - 4px);
					min-width: 0;
				}

				.gf-email-approvals-appearance-setting .wp-picker-container .wp-color-result-text {
					display: inline-flex;
					align-items: center;
					align-self: stretch;
					font-size: 0.875rem;
					line-height: 1.2;
					padding: 0 0.75rem;
					margin: 0;
					background: #fff;
				}

				.gf-email-approvals-appearance-setting .wp-picker-holder {
					display: none;
					position: absolute;
					top: calc(100% + 8px);
					left: 0;
					z-index: 30;
					box-shadow: 0 12px 28px rgba(18, 25, 97, 0.12), 0 2px 4px rgba(18, 25, 97, 0.08);
				}

				.gf-email-approvals-appearance-setting .wp-picker-container.wp-picker-active .wp-picker-holder {
					display: block;
				}

				.gf-email-approvals-appearance-setting .wp-picker-input-wrap {
					display: flex;
					flex: 1 1 0;
					align-items: stretch;
					gap: 8px;
					min-width: 0;
					margin: 0;
				}

				.gf-email-approvals-appearance-setting .wp-picker-input-wrap label {
					flex: 1 1 auto;
					min-width: 0;
					margin: 0;
				}

				.gf-email-approvals-appearance-setting .wp-picker-input-wrap .button-small,
				.gf-email-approvals-appearance-setting .wp-picker-input-wrap .button-small:hover {
					display: none !important;
				}

				.gf-email-approvals-appearance-setting .wp-picker-input-wrap input[type="text"] {
					font-size: 1rem;
					width: 5.25rem !important;
					min-height: 40px;
					box-sizing: border-box;
				}

				.gf-email-approvals-appearance-setting .wp-picker-input-wrap input[type="text"] {
					width: 100% !important;
				}

				.gf-email-approvals-appearance-builder {
					--gf-email-approvals-page-bg: #f5f5f5;
					--gf-email-approvals-card-bg: #ffffff;
					--gf-email-approvals-text: #1d2327;
					--gf-email-approvals-title: #1d2327;
					--gf-email-approvals-approve: #2271b1;
					--gf-email-approvals-reject: #b32d2e;
					--gf-email-approvals-button-text: #ffffff;
					--gf-email-approvals-active-button: #2271b1;
					--gf-email-approvals-card-width: 640px;
					--gf-email-approvals-card-padding: 32px;
					--gf-email-approvals-card-radius: 12px;
					--gf-email-approvals-shadow: rgba(29, 35, 39, 0.12);
					max-width: 100%;
				}

				.gf-email-approvals-appearance-builder__note {
					margin: 0 0 16px;
					color: #50575e;
				}

				.gf-email-approvals-appearance-builder__toolbar {
					display: flex;
					gap: 8px;
					flex-wrap: wrap;
					margin: 0 0 16px;
				}

				.gf-email-approvals-appearance-builder__toolbar .button-secondary.is-active {
					background: #2271b1;
					border-color: #2271b1;
					color: #fff;
				}

				.gf-email-approvals-appearance-builder__canvas {
					border: 1px solid #dcdcde;
					border-radius: 12px;
					overflow: hidden;
					background: #fff;
				}

				.gf-email-approvals-appearance-builder__viewport {
					padding: 24px;
					background: var(--gf-email-approvals-page-bg);
				}

				.gf-email-approvals-appearance-builder__card {
					width: 100%;
					max-width: var(--gf-email-approvals-card-width);
					margin: 0 auto;
					padding: var(--gf-email-approvals-card-padding);
					border-radius: var(--gf-email-approvals-card-radius);
					background: var(--gf-email-approvals-card-bg);
					color: var(--gf-email-approvals-text);
					box-shadow: 0 10px 30px var(--gf-email-approvals-shadow);
					box-sizing: border-box;
				}

				.gf-email-approvals-appearance-builder__title {
					margin: 0 0 16px;
					font-size: 28px;
					line-height: 1.2;
					color: var(--gf-email-approvals-title);
				}

				.gf-email-approvals-appearance-builder__message {
					margin: 0;
					white-space: pre-line;
					color: var(--gf-email-approvals-text);
				}

				.gf-email-approvals-appearance-builder__field {
					margin: 28px 0 20px;
				}

				.gf-email-approvals-appearance-builder__field-label {
					display: block;
					font-weight: 600;
					line-height: 1.4;
					color: var(--gf-email-approvals-text);
					margin-bottom: 12px;
				}

				.gf-email-approvals-appearance-builder__input {
					display: block;
					width: 100%;
					max-width: 100%;
					padding: 12px 14px;
					border: 1px solid rgba(29, 35, 39, 0.16);
					border-radius: max(4px, calc(var(--gf-email-approvals-card-radius) * 0.5));
					background: var(--gf-email-approvals-card-bg);
					color: var(--gf-email-approvals-text);
					font: inherit;
					box-sizing: border-box;
				}

				.gf-email-approvals-appearance-builder__button {
					display: block;
					width: 100%;
					box-sizing: border-box;
					padding: 12px 18px;
					background: var(--gf-email-approvals-active-button);
					color: var(--gf-email-approvals-button-text);
					border: 0;
					border-radius: max(4px, calc(var(--gf-email-approvals-card-radius) * 0.5));
					cursor: default;
					font: inherit;
					font-weight: 600;
				}

				.gf-email-approvals-appearance-builder__button[hidden],
				.gf-email-approvals-appearance-builder__field[hidden] {
					display: none !important;
				}

				@media screen and (max-width: 782px) {
					.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting {
						flex-basis: calc(50% - 8px);
						max-width: calc(50% - 8px);
					}

					.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--base-color,
					.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--button-color {
						flex-basis: calc(50% - 8px);
						max-width: calc(50% - 8px);
					}

					.gf-email-approvals-appearance-builder__viewport {
						padding: 16px;
					}

					.gf-email-approvals-appearance-builder__card {
						padding: max(16px, calc(var(--gf-email-approvals-card-padding) - 8px));
					}
				}

				@media screen and (max-width: 540px) {
					.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting {
						flex-basis: 100%;
						max-width: 100%;
					}

					.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--base-color,
					.gf-email-approvals-appearance-grid > .gf-email-approvals-appearance-setting.gf-email-approvals-appearance-setting--button-color {
						flex-basis: 100%;
						max-width: 100%;
					}
				}
			</style>
			<script type="text/javascript">
				(function($) {
					function parseConfig($builder) {
						var raw = $builder.attr('data-config');

						if (!raw) {
							return null;
						}

						try {
							return JSON.parse(raw);
						} catch (error) {
							return null;
						}
					}

					function getScope($builder) {
						return $builder.closest('.gform-settings-panel, form, .wrap');
					}

					function findInput($builder, name) {
						var $scope = getScope($builder);
						var selectors = [
							'#' + name,
							'#_gaddon_setting_' + name,
							'[name="_gaddon_setting_' + name + '"]',
							'[id$="' + name + '"]',
							'[name$="' + name + '"]'
						];
						var $input = $();

						$.each(selectors, function(index, selector) {
							$input = $scope.find(selector).first();

							if ($input.length) {
								return false;
							}
						});

						return $input;
					}

					function getInputValue($builder, name) {
						var $input = findInput($builder, name);
						return $input.length ? $input.val() : '';
					}

					function getFieldRow($builder, name) {
						return findInput($builder, name).closest('.gform-settings-field');
					}

					function getColorSettingNames(config) {
						return [
							config.settings.pageBackground,
							config.settings.cardBackground,
							config.settings.textColor,
							config.settings.titleColor,
							config.settings.approveButton,
							config.settings.rejectButton,
							config.settings.buttonText
						];
					}

					function refreshAllBuilders() {
						$('.gf-email-approvals-appearance-builder').each(function() {
							refresh($(this));
						});
					}

					function initColorPickers($builder, config) {
						if (!$.fn.wpColorPicker) {
							return;
						}

						$.each(getColorSettingNames(config), function(index, name) {
							var $input = findInput($builder, name);

							if (!$input.length || $input.data('gfEmailApprovalsColorPickerReady')) {
								return;
							}

							$input.wpColorPicker({
								width: 300,
								change: function() {
									setTimeout(refreshAllBuilders, 0);
								},
								clear: function() {
									setTimeout(refreshAllBuilders, 0);
								}
							});

							$input.parents('.wp-picker-container').find('.wp-color-result').addClass('ed_button');

							$input.data('gfEmailApprovalsColorPickerReady', true);
						});
					}

					function applySettingsLayout($builder, config) {
						var names = [
							config.settings.pageBackground,
							config.settings.cardBackground,
							config.settings.textColor,
							config.settings.titleColor,
							config.settings.approveButton,
							config.settings.rejectButton,
							config.settings.buttonText,
							config.settings.cardWidth,
							config.settings.cardPadding,
							config.settings.cardRadius
						];
						var colorSettingNames = getColorSettingNames(config);
						var baseColorSettingNames = [
							config.settings.pageBackground,
							config.settings.cardBackground,
							config.settings.textColor,
							config.settings.titleColor
						];
						var buttonColorSettingNames = [
							config.settings.approveButton,
							config.settings.rejectButton,
							config.settings.buttonText
						];
						var cardSettingNames = [
							config.settings.cardWidth,
							config.settings.cardPadding,
							config.settings.cardRadius
						];
						var $rows = $();

						$.each(names, function(index, name) {
							var $row = getFieldRow($builder, name);

							if (!$row.length) {
								return;
							}

							$row
								.addClass('gf-email-approvals-appearance-setting gf-email-approvals-settings-field-wrapper gf-email-approvals-settings-field-wrapper-' + name)
								.attr('data-setting-name', name);

							if ($.inArray(name, colorSettingNames) !== -1) {
								$row.addClass('gf-email-approvals-appearance-setting--color');
							}

							if ($.inArray(name, baseColorSettingNames) !== -1) {
								$row.addClass('gf-email-approvals-appearance-setting--base-color');
							}

							if ($.inArray(name, buttonColorSettingNames) !== -1) {
								$row.addClass('gf-email-approvals-appearance-setting--button-color');
							}

							$rows = $rows.add($row);
						});

						var $previewRow = $builder.closest('.gform-settings-field');

						if ($previewRow.length) {
							$previewRow.addClass('gf-email-approvals-appearance-preview-row');
							$rows = $rows.add($previewRow);
						}

						if ($rows.length) {
							var $grid = $rows.first().parent();

							$grid.addClass('gf-email-approvals-appearance-grid');
							$grid.children('.gf-email-approvals-appearance-row-break').remove();

							var $firstButtonColorRow = getFieldRow($builder, buttonColorSettingNames[0]);

							if ($firstButtonColorRow.length) {
								$('<div class="gf-email-approvals-appearance-row-break" aria-hidden="true"></div>').insertBefore($firstButtonColorRow);
							}

							var $firstCardRow = getFieldRow($builder, cardSettingNames[0]);

							if ($firstCardRow.length) {
								$('<div class="gf-email-approvals-appearance-row-break" aria-hidden="true"></div>').insertBefore($firstCardRow);
							}
						}
					}

					function sanitizeColor(value, fallback) {
						value = (value || '').toString().trim();

						return /^#(?:[0-9a-fA-F]{3}){1,2}$/.test(value) ? value : fallback;
					}

					function sanitizeNumber(value, fallback, min, max) {
						var number = parseInt(value, 10);

						if (isNaN(number)) {
							number = fallback;
						}

						if (number < min) {
							number = min;
						}

						if (number > max) {
							number = max;
						}

						return number;
					}

					function hexToRgba(hex, alpha, fallback) {
						hex = sanitizeColor(hex, '');

						if (!hex) {
							return fallback;
						}

						var normalized = hex.replace('#', '');

						if (normalized.length === 3) {
							normalized = normalized.replace(/(.)/g, '$1$1');
						}

						var red = parseInt(normalized.substring(0, 2), 16);
						var green = parseInt(normalized.substring(2, 4), 16);
						var blue = parseInt(normalized.substring(4, 6), 16);

						if ([red, green, blue].some(isNaN)) {
							return fallback;
						}

						return 'rgba(' + red + ',' + green + ',' + blue + ',' + alpha + ')';
					}

					function applyTheme($builder, config) {
						var defaults = config.defaults || {};
						var settings = config.settings || {};
						var pageBackground = sanitizeColor(getInputValue($builder, settings.pageBackground), defaults.pageBackground);
						var cardBackground = sanitizeColor(getInputValue($builder, settings.cardBackground), defaults.cardBackground);
						var textColor = sanitizeColor(getInputValue($builder, settings.textColor), defaults.textColor);
						var titleColor = sanitizeColor(getInputValue($builder, settings.titleColor), defaults.titleColor);
						var approveButton = sanitizeColor(getInputValue($builder, settings.approveButton), defaults.approveButton);
						var rejectButton = sanitizeColor(getInputValue($builder, settings.rejectButton), defaults.rejectButton);
						var buttonText = sanitizeColor(getInputValue($builder, settings.buttonText), defaults.buttonText);
						var cardWidth = sanitizeNumber(getInputValue($builder, settings.cardWidth), defaults.cardWidth, 320, 960);
						var cardPadding = sanitizeNumber(getInputValue($builder, settings.cardPadding), defaults.cardPadding, 16, 80);
						var cardRadius = sanitizeNumber(getInputValue($builder, settings.cardRadius), defaults.cardRadius, 0, 40);

						$builder[0].style.setProperty('--gf-email-approvals-page-bg', pageBackground);
						$builder[0].style.setProperty('--gf-email-approvals-card-bg', cardBackground);
						$builder[0].style.setProperty('--gf-email-approvals-text', textColor);
						$builder[0].style.setProperty('--gf-email-approvals-title', titleColor);
						$builder[0].style.setProperty('--gf-email-approvals-approve', approveButton);
						$builder[0].style.setProperty('--gf-email-approvals-reject', rejectButton);
						$builder[0].style.setProperty('--gf-email-approvals-button-text', buttonText);
						$builder[0].style.setProperty('--gf-email-approvals-card-width', cardWidth + 'px');
						$builder[0].style.setProperty('--gf-email-approvals-card-padding', cardPadding + 'px');
						$builder[0].style.setProperty('--gf-email-approvals-card-radius', cardRadius + 'px');
						$builder[0].style.setProperty('--gf-email-approvals-shadow', hexToRgba(textColor, 0.12, 'rgba(29,35,39,0.12)'));
					}

					function applyState($builder, stateName) {
						var config = parseConfig($builder);

						if (!config || !config.states || !config.states[stateName]) {
							return;
						}

						var state = config.states[stateName];
						var variantColor = state.variant === 'reject'
							? getComputedStyle($builder[0]).getPropertyValue('--gf-email-approvals-reject')
							: getComputedStyle($builder[0]).getPropertyValue('--gf-email-approvals-approve');

						$builder.attr('data-preview-state', stateName);
						$builder[0].style.setProperty('--gf-email-approvals-active-button', variantColor);
						$builder.find('[data-preview-title]').text(state.title);
						$builder.find('[data-preview-message]').text(state.message);
						$builder.find('[data-preview-button]').text(state.button).prop('hidden', !state.showButton);
						$builder.find('[data-preview-field]').prop('hidden', !state.showField);
						$builder.find('[data-preview-state]').removeClass('is-active');
						$builder.find('[data-preview-state="' + stateName + '"]').addClass('is-active');
					}

					function refresh($builder) {
						var config = parseConfig($builder);

						if (!config) {
							return;
						}

						applySettingsLayout($builder, config);
						initColorPickers($builder, config);
						applyTheme($builder, config);
						applyState($builder, $builder.attr('data-preview-state') || 'approve');
					}

					$(document).on('click', '.gf-email-approvals-appearance-builder [data-preview-state]', function() {
						var $button = $(this);
						var $builder = $button.closest('.gf-email-approvals-appearance-builder');

						applyState($builder, $button.attr('data-preview-state'));
					});

					$(document).on('input change', '.gform-settings-panel input, .gform-settings-panel textarea, .gform-settings-panel select, form input, form textarea, form select', function() {
						refreshAllBuilders();
					});

					$(function() {
						refreshAllBuilders();
					});
				})(jQuery);
			</script>
			<?php
		}
		?>
		<div class="gf-email-approvals-appearance-builder" data-config="<?php echo esc_attr( wp_json_encode( $preview_config ) ); ?>" data-preview-state="approve" style="<?php echo esc_attr( $this->get_public_page_preview_style_variables( $current_theme ) ); ?>">
			<div class="gf-email-approvals-appearance-builder__toolbar">
				<button type="button" class="button button-secondary is-active" data-preview-state="approve"><?php esc_html_e( 'Approve', 'gf-email-approvals' ); ?></button>
				<button type="button" class="button button-secondary" data-preview-state="reject"><?php esc_html_e( 'Reject', 'gf-email-approvals' ); ?></button>
				<button type="button" class="button button-secondary" data-preview-state="result"><?php esc_html_e( 'Result', 'gf-email-approvals' ); ?></button>
			</div>
			<div class="gf-email-approvals-appearance-builder__canvas">
				<div class="gf-email-approvals-appearance-builder__viewport">
					<div class="gf-email-approvals-appearance-builder__card">
						<h3 class="gf-email-approvals-appearance-builder__title" data-preview-title><?php esc_html_e( 'Confirm approval action', 'gf-email-approvals' ); ?></h3>
						<p class="gf-email-approvals-appearance-builder__message" data-preview-message><?php esc_html_e( 'You are about to approve this entry.', 'gf-email-approvals' ); ?></p>
						<div class="gf-email-approvals-appearance-builder__field" data-preview-field>
							<label class="gf-email-approvals-appearance-builder__field-label"><?php esc_html_e( 'Optional response field', 'gf-email-approvals' ); ?></label>
							<input type="text" class="gf-email-approvals-appearance-builder__input" value="" placeholder="<?php echo esc_attr__( 'Comment', 'gf-email-approvals' ); ?>" readonly="readonly" />
						</div>
						<button type="button" class="gf-email-approvals-appearance-builder__button" data-preview-button disabled="disabled"><?php esc_html_e( 'Approve', 'gf-email-approvals' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php

		$output = ob_get_clean();

		if ( $echo ) {
			echo $output;
			return;
		}

		return $output;
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
	 * Invalidates approval tokens when an entry leaves the active pool.
	 *
	 * @param int    $entry_id        The entry id.
	 * @param string $property_value  The new status.
	 * @param string $previous_value  The previous status.
	 *
	 * @return void
	 */
	public function handle_entry_status_change( $entry_id, $property_value, $previous_value ) {
		unset( $previous_value );

		if ( 'trash' !== (string) $property_value ) {
			return;
		}

		GFEmailApprovalsTokenStore::invalidate_entry_tokens( $entry_id );
	}

	/**
	 * Removes approval tokens when an entry is permanently deleted.
	 *
	 * @param int $entry_id The entry id.
	 *
	 * @return void
	 */
	public function handle_entry_delete( $entry_id ) {
		GFEmailApprovalsTokenStore::delete_entry_tokens( $entry_id );
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
		$form    = class_exists( 'GFAPI' ) ? GFAPI::get_form( absint( $this->array_value( $entry, 'form_id' ) ) ) : array();
		$form    = is_array( $form ) ? $form : array();

		if ( empty( $approve['url'] ) || empty( $reject['url'] ) ) {
			$email['abort_email'] = true;
			$this->log_error( sprintf( '%s(): approval_request email aborted for entry %d because tokens could not be generated.', __METHOD__, $entry_id ) );

			return $email;
		}

		$replacements = array(
			'{approval_status}'         => $this->get_status_label( self::STATUS_PENDING ),
			'{approval_approve_url}'    => $approve['url'],
			'{approval_reject_url}'     => $reject['url'],
		);

		$email['subject'] = strtr( $this->replace_approval_action_button_merge_tags( $email['subject'], $approve, $reject, $message_format, $form, $entry ), $replacements );
		$email['message'] = strtr( $this->replace_approval_action_button_merge_tags( $email['message'], $approve, $reject, $message_format, $form, $entry ), $replacements );

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

		$form         = class_exists( 'GFAPI' ) ? GFAPI::get_form( absint( $record->form_id ) ) : array();
		$entry        = class_exists( 'GFAPI' ) ? GFAPI::get_entry( absint( $record->entry_id ) ) : array();
		$form         = is_array( $form ) ? $form : array();
		$entry        = is_array( $entry ) ? $entry : array();

		if ( empty( $form ) || ! $this->is_entry_publicly_actionable( $entry ) ) {
			$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
		}

		if ( $record->used_at || $record->invalidated_at || self::STATUS_PENDING !== $this->get_entry_status( absint( $record->entry_id ) ) ) {
			$this->render_public_message_page( esc_html__( 'This approval request has already been processed.', 'gf-email-approvals' ) );
		}

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
		$button          = $this->get_approval_action_button_markup(
			array(
				'url'   => $url,
				'label' => $label,
				'color' => $button_class,
			),
			$message_format
		);

		return array(
			'url'    => $url,
			'label'  => $label,
			'color'  => $button_class,
			'button' => $button,
		);
	}

	/**
	 * Replaces approval button merge tags, including the advanced custom-label syntax.
	 *
	 * @param string     $text           The text containing approval button merge tags.
	 * @param array      $approve        Approve action data.
	 * @param array      $reject         Reject action data.
	 * @param string     $message_format The notification format.
	 * @param array|bool $form           The current form.
	 * @param array|bool $entry          The current entry.
	 *
	 * @return string
	 */
	private function replace_approval_action_button_merge_tags( $text, $approve, $reject, $message_format, $form = array(), $entry = array() ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return (string) $text;
		}

		if ( false === strpos( $text, '{approval_approve_button' ) && false === strpos( $text, '{approval_reject_button' ) ) {
			return $text;
		}

		$action_map = array(
			'approval_approve_button' => $approve,
			'approval_reject_button'  => $reject,
		);

		$updated_text = preg_replace_callback(
			'/\{(approval_(?:approve|reject)_button)(?::([^}]*))?\}/',
			function ( $matches ) use ( $action_map, $message_format, $form, $entry ) {
				$tag_name = isset( $matches[1] ) ? (string) $matches[1] : '';

				if ( ! isset( $action_map[ $tag_name ] ) ) {
					return isset( $matches[0] ) ? (string) $matches[0] : '';
				}

				$custom_label = isset( $matches[2] ) ? (string) $matches[2] : '';

				return $this->get_approval_action_button_markup( $action_map[ $tag_name ], $message_format, $custom_label, $form, $entry );
			},
			$text
		);

		return is_string( $updated_text ) ? $updated_text : $text;
	}

	/**
	 * Returns the rendered approval button markup for one action.
	 *
	 * @param array      $action_data    Approval action data.
	 * @param string     $message_format The notification format.
	 * @param string     $custom_label   Optional custom label from the advanced merge tag.
	 * @param array|bool $form           The current form.
	 * @param array|bool $entry          The current entry.
	 *
	 * @return string
	 */
	private function get_approval_action_button_markup( $action_data, $message_format, $custom_label = '', $form = array(), $entry = array() ) {
		$url = (string) $this->array_value( $action_data, 'url' );

		if ( '' === $url ) {
			return '';
		}

		$label = (string) $this->array_value( $action_data, 'label' );

		if ( '' !== trim( $custom_label ) ) {
			$resolved_label = sanitize_text_field( $this->replace_merge_tags_in_text( $custom_label, $form, $entry ) );

			if ( '' !== trim( $resolved_label ) ) {
				$label = $resolved_label;
			}
		}

		if ( 'text' === $message_format ) {
			return sprintf( "%s: %s", $label, $url );
		}

		return sprintf(
			'<a href="%1$s" style="display:inline-block;padding:12px 18px;margin:0 12px 12px 0;background:%2$s;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;">%3$s</a>',
			esc_url( $url ),
			esc_attr( (string) $this->array_value( $action_data, 'color' ) ),
			esc_html( $label )
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
	 * Returns whether the entry can still be processed from a public approval link.
	 *
	 * @param array $entry The current entry.
	 *
	 * @return bool
	 */
	private function is_entry_publicly_actionable( $entry ) {
		return is_array( $entry ) && ! empty( $entry ) && 'active' === (string) $this->array_value( $entry, 'status' );
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

			if ( ! $fallback ) {
				$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
			}

			if ( ! class_exists( 'GFAPI' ) ) {
				$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
			}

			$fallback_entry = GFAPI::get_entry( absint( $fallback->entry_id ) );
			$fallback_form  = GFAPI::get_form( absint( $fallback->form_id ) );

			if ( is_wp_error( $fallback_entry ) || empty( $fallback_form ) || ! is_array( $fallback_entry ) || ! $this->is_entry_publicly_actionable( $fallback_entry ) ) {
				$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
			}

			$this->render_public_message_page( esc_html__( 'This approval request has already been processed.', 'gf-email-approvals' ) );
		}

		if ( ! class_exists( 'GFAPI' ) ) {
			$this->render_public_message_page( esc_html__( 'This approval link is invalid or expired.', 'gf-email-approvals' ) );
		}

		$entry = GFAPI::get_entry( absint( $record->entry_id ) );
		$form  = GFAPI::get_form( absint( $record->form_id ) );

		if ( is_wp_error( $entry ) || empty( $form ) || ! is_array( $entry ) || ! $this->is_entry_publicly_actionable( $entry ) ) {
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
				'notification'    => $notification,
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

		$update_result = $this->apply_configured_decision_updates( $entry, $form, $status, $context );

		if ( empty( $update_result['success'] ) ) {
			return array(
				'success' => false,
				'message' => isset( $update_result['message'] ) ? $update_result['message'] : esc_html__( 'The approval request could not be processed.', 'gf-email-approvals' ),
			);
		}

		$entry             = isset( $update_result['entry'] ) && is_array( $update_result['entry'] ) ? $update_result['entry'] : $entry;
		$context['changes'] = isset( $update_result['changes'] ) && is_array( $update_result['changes'] ) ? $update_result['changes'] : array();

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
		$lines  = array(
			sprintf( __( 'Approval status changed to %s.', 'gf-email-approvals' ), $this->get_status_label( $status ) ),
			sprintf( __( 'Source: %s', 'gf-email-approvals' ), $source ),
			sprintf( __( 'Actor: %s', 'gf-email-approvals' ), $actor ),
		);

		if ( ! empty( $context['recipient_email'] ) ) {
			$lines[] = sprintf( __( 'Recipient: %s', 'gf-email-approvals' ), (string) $context['recipient_email'] );
		}

		if ( ! empty( $context['ip'] ) ) {
			$lines[] = sprintf( __( 'IP: %s', 'gf-email-approvals' ), (string) $context['ip'] );
		}

		if ( ! empty( $context['notification'] ) && is_array( $context['notification'] ) ) {
			$notification_name = (string) $this->array_value( $context['notification'], 'name', esc_html__( 'Approval Request', 'gf-email-approvals' ) );
			$notification_id   = (string) $this->array_value( $context['notification'], 'id', '' );

			if ( '' !== $notification_id ) {
				$lines[] = sprintf( __( 'Notification: %1$s (ID: %2$s)', 'gf-email-approvals' ), $notification_name, $notification_id );
			} else {
				$lines[] = sprintf( __( 'Notification: %s', 'gf-email-approvals' ), $notification_name );
			}
		}

		$changes = isset( $context['changes'] ) && is_array( $context['changes'] ) ? $context['changes'] : array();

		foreach ( $changes as $change ) {
			if ( empty( $change['field'] ) || ! is_object( $change['field'] ) ) {
				continue;
			}

			$field    = $change['field'];
			$field_id = isset( $field->id ) ? (string) $field->id : '';
			$label    = isset( $change['label'] ) ? (string) $change['label'] : $this->get_field_admin_label( $field );
			$prefix   = isset( $change['type'] ) && 'message' === $change['type']
				? __( 'Message field updated: %1$s (%2$s)', 'gf-email-approvals' )
				: __( 'Field updated: %1$s (%2$s)', 'gf-email-approvals' );

			$lines[] = sprintf( $prefix, $label, $field_id );
			$lines[] = sprintf( __( 'Old value: %s', 'gf-email-approvals' ), $this->format_field_value_for_note( $field, isset( $change['old'] ) ? $change['old'] : '' ) );
			$lines[] = sprintf( __( 'New value: %s', 'gf-email-approvals' ), $this->format_field_value_for_note( $field, isset( $change['new'] ) ? $change['new'] : '' ) );
		}

		$note = implode( "\n", $lines );

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
		$settings          = $this->get_notification_page_settings( $notification, $form, $entry );
		$decision_settings = $this->get_notification_decision_update_settings( $notification );
		$theme             = $this->get_public_page_theme_settings();
		$update_mode       = (string) $decision_settings[ self::NOTIFICATION_UPDATE_MODE ];
		$is_approve        = self::STATUS_APPROVED === $record->action;
		$title             = $settings[ self::NOTIFICATION_CONFIRMATION_TITLE ];
		$message           = $is_approve
			? $settings[ self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ]
			: $settings[ self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ];
		$button_label      = $is_approve
			? $settings[ self::NOTIFICATION_APPROVE_BUTTON_LABEL ]
			: $settings[ self::NOTIFICATION_REJECT_BUTTON_LABEL ];
		$action_url = add_query_arg(
			array(
				self::QUERY_ACTION => self::PUBLIC_ACTION_CONFIRM,
				self::QUERY_TOKEN  => $token,
			),
			home_url( '/' )
		);
		$message_markup      = $this->get_public_message_markup( $message );
		$target_field        = $this->get_decision_update_field( $form, (string) $decision_settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ], $update_mode );
		$manual_update_html  = self::UPDATE_MODE_MANUAL === $update_mode && $target_field
			? $this->get_public_manual_update_field_markup( $target_field, $entry, $theme )
			: '';
		$button_style = $this->get_public_page_button_style( $is_approve ? self::STATUS_APPROVED : self::STATUS_REJECTED, $theme, true );

		$this->render_public_page(
			$title,
			sprintf(
				'%1$s<form method="post" action="%2$s"><input type="hidden" name="%3$s" value="%4$s" /><input type="hidden" name="%5$s" value="%6$s" />%7$s<p><button type="submit" style="%8$s">%9$s</button></p></form>',
				$message_markup,
				esc_url( $action_url ),
				esc_attr( self::QUERY_TOKEN ),
				esc_attr( $token ),
				esc_attr( self::QUERY_ACTION ),
				esc_attr( self::PUBLIC_ACTION_CONFIRM ),
				$manual_update_html,
				esc_attr( $button_style ),
				esc_html( $button_label )
			)
		);
	}

	/**
	 * Returns the public field markup used when the approver must choose the value manually.
	 *
	 * @param object $field The configured target field.
	 * @param array  $entry The current entry.
	 *
	 * @return string
	 */
	private function get_public_manual_update_field_markup( $field, $entry, $theme = null ) {
		$theme         = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();
		$input_type    = $field->get_input_type();
		$label         = $this->get_field_admin_label( $field );
		$current_value = $this->get_entry_field_value_for_update( $entry, $field );
		$control_html  = '';

		if ( 'textarea' === $input_type ) {
			$control_html = sprintf(
				'<textarea name="%1$s" id="%2$s" rows="4" placeholder="%3$s" style="%4$s">%5$s</textarea>',
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( $this->get_public_manual_field_placeholder( $field ) ),
				esc_attr( $this->get_public_page_input_style( 'textarea', $theme ) ),
				esc_textarea( is_array( $current_value ) ? '' : (string) $current_value )
			);
		} elseif ( in_array( $input_type, array( 'text', 'email', 'number', 'phone', 'website', 'date' ), true ) ) {
			$control_html = sprintf(
				'<input type="%1$s" name="%2$s" id="%3$s" value="%4$s" placeholder="%5$s" style="%6$s" />',
				esc_attr( $this->get_public_html_input_type( $input_type ) ),
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( is_array( $current_value ) ? '' : (string) $current_value ),
				esc_attr( $this->get_public_manual_field_placeholder( $field ) ),
				esc_attr( $this->get_public_page_input_style( 'input', $theme ) )
			);
		} elseif ( 'select' === $input_type ) {
			$options = array(
				sprintf(
					'<option value=""%1$s>%2$s</option>',
					'' === (string) $current_value ? ' selected="selected"' : '',
					esc_html__( 'Choose a value', 'gf-email-approvals' )
				),
			);

			foreach ( $this->get_supported_field_choice_options( $field ) as $choice ) {
				$options[] = sprintf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $choice['value'] ),
					(string) $current_value === (string) $choice['value'] ? ' selected="selected"' : '',
					esc_html( $choice['label'] )
				);
			}

			$control_html = sprintf(
				'<select name="%1$s" id="%2$s" style="%3$s">%4$s</select>',
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( $this->get_public_page_input_style( 'select', $theme ) ),
				implode( '', $options )
			);
		} elseif ( 'multiselect' === $input_type ) {
			$selected_values = is_array( $current_value ) ? array_values( array_map( 'strval', $current_value ) ) : array();
			$options         = array();

			foreach ( $this->get_supported_field_choice_options( $field ) as $choice ) {
				$options[] = sprintf(
					'<option value="%1$s"%2$s>%3$s</option>',
					esc_attr( $choice['value'] ),
					in_array( (string) $choice['value'], $selected_values, true ) ? ' selected="selected"' : '',
					esc_html( $choice['label'] )
				);
			}

			$control_html = sprintf(
				'<select name="%1$s[]" id="%2$s" multiple="multiple" style="%3$s">%4$s</select>',
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
				esc_attr( $this->get_public_page_input_style( 'multiselect', $theme ) ),
				implode( '', $options )
			);
		} elseif ( 'radio' === $input_type ) {
			$choices = array();

			foreach ( $this->get_supported_field_choice_options( $field ) as $index => $choice ) {
				$choice_id = self::PUBLIC_DECISION_UPDATE_VALUE . '_' . $index;
				$choices[] = sprintf(
					'<label for="%1$s" style="%2$s"><input type="radio" name="%3$s" id="%1$s" value="%4$s"%5$s style="margin-top:3px;" /><span>%6$s</span></label>',
					esc_attr( $choice_id ),
					esc_attr( $this->get_public_page_choice_label_style( $theme ) ),
					esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
					esc_attr( $choice['value'] ),
					(string) $current_value === (string) $choice['value'] ? ' checked="checked"' : '',
					esc_html( $choice['label'] )
				);
			}

			$control_html = '<div style="margin-top:4px;">' . implode( '', $choices ) . '</div>';
		} elseif ( 'checkbox' === $input_type ) {
			$selected_values = is_array( $current_value ) ? array_values( array_map( 'strval', $current_value ) ) : array();
			$choices         = array();

			foreach ( $this->get_supported_field_choice_options( $field ) as $index => $choice ) {
				$choice_id = self::PUBLIC_DECISION_UPDATE_VALUE . '_' . $index;
				$choices[] = sprintf(
					'<label for="%1$s" style="%2$s"><input type="checkbox" name="%3$s[]" id="%1$s" value="%4$s"%5$s style="margin-top:3px;" /><span>%6$s</span></label>',
					esc_attr( $choice_id ),
					esc_attr( $this->get_public_page_choice_label_style( $theme ) ),
					esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ),
					esc_attr( $choice['value'] ),
					in_array( (string) $choice['value'], $selected_values, true ) ? ' checked="checked"' : '',
					esc_html( $choice['label'] )
				);
			}

			$control_html = '<div style="margin-top:4px;">' . implode( '', $choices ) . '</div>';
		}

		if ( '' === $control_html ) {
			return '';
		}

		$label_html = in_array( $input_type, array( 'radio', 'checkbox' ), true )
			? sprintf( '<div style="%1$s">%2$s</div>', esc_attr( $this->get_public_page_field_label_style( $theme ) ), esc_html( $label ) )
			: sprintf( '<label for="%1$s" style="%2$s">%3$s</label>', esc_attr( self::PUBLIC_DECISION_UPDATE_VALUE ), esc_attr( $this->get_public_page_field_label_style( $theme ) ), esc_html( $label ) );

		return sprintf(
			'<div style="margin:28px 0 20px 0;">%1$s%2$s</div>',
			$label_html,
			$control_html
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
			self::NOTIFICATION_APPROVE_BUTTON_LABEL      => __( 'Approve', 'gf-email-approvals' ),
			self::NOTIFICATION_REJECT_BUTTON_LABEL       => __( 'Reject', 'gf-email-approvals' ),
			self::NOTIFICATION_APPROVED_RESULT_TEXT      => __( 'The entry has been approved.', 'gf-email-approvals' ),
			self::NOTIFICATION_REJECTED_RESULT_TEXT      => __( 'The entry has been rejected.', 'gf-email-approvals' ),
		);
	}

	/**
	 * Returns the default settings used for post-confirmation field updates.
	 *
	 * @return array<string, mixed>
	 */
	private function get_notification_decision_update_defaults() {
		return array(
			self::NOTIFICATION_UPDATE_MODE            => '',
			self::NOTIFICATION_DECISION_UPDATE_FIELD   => '',
			self::NOTIFICATION_APPROVED_TEXT_VALUE     => '',
			self::NOTIFICATION_REJECTED_TEXT_VALUE     => '',
			self::NOTIFICATION_APPROVED_CHOICE_VALUE   => '',
			self::NOTIFICATION_REJECTED_CHOICE_VALUE   => '',
			self::NOTIFICATION_APPROVED_CHOICE_VALUES  => array(),
			self::NOTIFICATION_REJECTED_CHOICE_VALUES  => array(),
		);
	}

	/**
	 * Returns the supported update mode choices.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_decision_update_mode_choices() {
		return array(
			array(
				'value' => '',
				'label' => esc_html__( 'Do not update any field', 'gf-email-approvals' ),
			),
			array(
				'value' => self::UPDATE_MODE_AUTOMATIC,
				'label' => esc_html__( 'Set a predefined value automatically', 'gf-email-approvals' ),
			),
			array(
				'value' => self::UPDATE_MODE_MANUAL,
				'label' => esc_html__( 'Let the approver choose the value', 'gf-email-approvals' ),
			),
		);
	}

	/**
	 * Returns the effective decision update settings for a notification.
	 *
	 * @param array $notification The notification object.
	 *
	 * @return array<string, mixed>
	 */
	private function get_notification_decision_update_settings( $notification ) {
		$settings = $this->get_notification_decision_update_defaults();

		foreach ( $settings as $setting_name => $default_value ) {
			$value = $this->array_value( $notification, $setting_name, $default_value );

			if ( is_array( $default_value ) ) {
				if ( is_array( $value ) ) {
					$settings[ $setting_name ] = array_values(
						array_filter(
							array_map( 'strval', $value ),
							'strlen'
						)
					);
				}

				continue;
			}

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				$settings[ $setting_name ] = $value;
			}
		}

		if ( '' === $settings[ self::NOTIFICATION_UPDATE_MODE ] && '' !== (string) $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] ) {
			$settings[ self::NOTIFICATION_UPDATE_MODE ] = self::UPDATE_MODE_AUTOMATIC;
		}

		return $settings;
	}

	/**
	 * Returns the supported target field choices for post-confirmation field updates.
	 *
	 * @param array $form The current form object.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_decision_update_field_choices( $form, $update_mode = '', $include_empty = true ) {
		$choices = array();

		if ( $include_empty ) {
			$choices[] = array(
				'value' => '',
				'label' => esc_html__( 'Do not update any field', 'gf-email-approvals' ),
			);
		}

		$fields = $this->array_value( $form, 'fields' );

		if ( ! is_array( $fields ) ) {
			return $choices;
		}

		foreach ( $fields as $field ) {
			if ( ! $this->is_supported_decision_update_field( $field, $update_mode ) ) {
				continue;
			}

			$choices[] = array(
				'value' => (string) $field->id,
				'label' => $this->get_field_admin_label( $field ),
			);
		}

		return $choices;
	}

	/**
	 * Returns the decision update field metadata used by the admin script.
	 *
	 * @param array $form The current form object.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_decision_update_field_config( $form ) {
		$config = array();
		$fields = $this->array_value( $form, 'fields' );

		if ( ! is_array( $fields ) ) {
			return $config;
		}

		foreach ( $fields as $field ) {
			if ( ! $this->is_supported_decision_update_field( $field ) ) {
				continue;
			}

			$config[ (string) $field->id ] = array(
				'kind'    => $this->get_decision_update_field_kind( $field ),
				'choices' => $this->get_supported_field_choice_options( $field ),
			);
		}

		return $config;
	}

	/**
	 * Returns the target field configured for post-confirmation field updates.
	 *
	 * @param array  $form     The current form object.
	 * @param string $field_id The configured field id.
	 *
	 * @return object|null
	 */
	private function get_decision_update_field( $form, $field_id, $update_mode = '' ) {
		if ( '' === trim( $field_id ) || ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_field' ) ) {
			return null;
		}

		$field = GFAPI::get_field( $form, $field_id );

		return $this->is_supported_decision_update_field( $field, $update_mode ) ? $field : null;
	}

	/**
	 * Returns whether the field is supported as a post-confirmation update target.
	 *
	 * @param object|null $field The Gravity Forms field object.
	 *
	 * @return bool
	 */
	private function is_supported_decision_update_field( $field, $update_mode = '' ) {
		if ( ! is_object( $field ) || ! isset( $field->id ) || ! method_exists( $field, 'get_input_type' ) ) {
			return false;
		}

		if ( ! empty( $field->displayOnly ) ) {
			return false;
		}

		$input_type = $field->get_input_type();

		if ( self::UPDATE_MODE_MANUAL === $update_mode && 'hidden' === $input_type ) {
			return false;
		}

		return in_array( $input_type, array( 'text', 'textarea', 'email', 'number', 'phone', 'website', 'hidden', 'date', 'select', 'radio', 'checkbox', 'multiselect' ), true );
	}

	/**
	 * Returns the decision update kind for a field.
	 *
	 * @param object $field The Gravity Forms field object.
	 *
	 * @return string
	 */
	private function get_decision_update_field_kind( $field ) {
		$input_type = $field->get_input_type();

		if ( in_array( $input_type, array( 'select', 'radio' ), true ) ) {
			return 'single';
		}

		if ( in_array( $input_type, array( 'checkbox', 'multiselect' ), true ) ) {
			return 'multi';
		}

		return 'text';
	}

	/**
	 * Returns a display label for a Gravity Forms field.
	 *
	 * @param object $field The Gravity Forms field object.
	 *
	 * @return string
	 */
	private function get_field_admin_label( $field ) {
		if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'get_label' ) ) {
			return strip_tags( GFCommon::get_label( $field ) );
		}

		if ( isset( $field->adminLabel ) && '' !== (string) $field->adminLabel ) {
			return (string) $field->adminLabel;
		}

		return isset( $field->label ) ? (string) $field->label : sprintf( esc_html__( 'Field %d', 'gf-email-approvals' ), absint( $field->id ) );
	}

	/**
	 * Returns the choice options supported by a choice-based field.
	 *
	 * @param object $field The Gravity Forms field object.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_supported_field_choice_options( $field ) {
		$choices = isset( $field->choices ) && is_array( $field->choices ) ? $field->choices : array();
		$options = array();

		foreach ( $choices as $choice ) {
			$stored_value = $this->get_choice_storage_value( $choice );
			$choice_text  = isset( $choice['text'] ) ? (string) $choice['text'] : $stored_value;

			if ( '' === $stored_value && '' === $choice_text ) {
				continue;
			}

			$label = $choice_text;

			if ( isset( $choice['value'] ) && '' !== (string) $choice['value'] && (string) $choice['value'] !== $choice_text ) {
				$label = sprintf( __( '%1$s (%2$s)', 'gf-email-approvals' ), $choice_text, (string) $choice['value'] );
			}

			$options[] = array(
				'value' => $stored_value,
				'label' => $label,
			);
		}

		return $options;
	}

	/**
	 * Returns a value=>label map for a choice-based field.
	 *
	 * @param object $field The Gravity Forms field object.
	 *
	 * @return array<string, string>
	 */
	private function get_supported_field_choice_value_map( $field ) {
		$map = array();

		foreach ( $this->get_supported_field_choice_options( $field ) as $choice ) {
			$map[ $choice['value'] ] = $choice['label'];
		}

		return $map;
	}

	/**
	 * Returns the stored value for a choice.
	 *
	 * @param array $choice The Gravity Forms choice definition.
	 *
	 * @return string
	 */
	private function get_choice_storage_value( $choice ) {
		if ( is_array( $choice ) && array_key_exists( 'value', $choice ) && '' !== (string) $choice['value'] ) {
			return (string) $choice['value'];
		}

		return isset( $choice['text'] ) ? (string) $choice['text'] : '';
	}

	/**
	 * Applies any configured entry updates before the decision status notifications are sent.
	 *
	 * @param array  $entry   The current entry.
	 * @param array  $form    The current form.
	 * @param string $status  The target decision status.
	 * @param array  $context The execution context.
	 *
	 * @return array<string, mixed>
	 */
	private function apply_configured_decision_updates( $entry, $form, $status, $context ) {
		$notification = isset( $context['notification'] ) && is_array( $context['notification'] ) ? $context['notification'] : array();
		$settings     = $this->get_notification_decision_update_settings( $notification );
		$changes      = array();
		$entry_id     = absint( $this->array_value( $entry, 'id' ) );
		$update_mode  = (string) $settings[ self::NOTIFICATION_UPDATE_MODE ];
		$target_field = $this->get_decision_update_field( $form, (string) $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ], $update_mode );

		if ( '' === $update_mode ) {
			return array(
				'success' => true,
				'entry'   => $entry,
				'changes' => $changes,
			);
		}

		if ( ! $target_field ) {
			if ( '' !== (string) $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] ) {
				$this->log_error( __METHOD__ . '(): configured decision update target field is no longer available; skipping field update.' );
			}

			return array(
				'success' => true,
				'entry'   => $entry,
				'changes' => $changes,
			);
		}

		$new_value = self::UPDATE_MODE_MANUAL === $update_mode
			? $this->get_manual_decision_update_value_from_request( $target_field )
			: $this->get_configured_decision_update_value( $target_field, $settings, $status, $form, $entry );

		if ( null === $new_value ) {
			return array(
				'success' => true,
				'entry'   => $entry,
				'changes' => $changes,
			);
		}

		$old_value = $this->get_entry_field_value_for_update( $entry, $target_field );

		if ( $this->decision_update_values_equal( $target_field, $old_value, $new_value ) ) {
			return array(
				'success' => true,
				'entry'   => $entry,
				'changes' => $changes,
			);
		}

		$result = $this->persist_field_update( $entry_id, $entry, $form, $target_field, $new_value );

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		$entry     = $result['entry'];
		$changes[] = array(
			'type'  => 'target',
			'field' => $target_field,
			'label' => $this->get_field_admin_label( $target_field ),
			'old'   => $old_value,
			'new'   => $new_value,
		);

		return array(
			'success' => true,
			'entry'   => $entry,
			'changes' => $changes,
		);
	}

	/**
	 * Returns the configured decision update value for the current status.
	 *
	 * @param object $field    The target field object.
	 * @param array  $settings The notification settings.
	 * @param string $status   The target decision status.
	 * @param array  $form     The current form.
	 * @param array  $entry    The current entry.
	 *
	 * @return array|string|null
	 */
	private function get_configured_decision_update_value( $field, $settings, $status, $form, $entry ) {
		$kind = $this->get_decision_update_field_kind( $field );

		if ( 'text' === $kind ) {
			$setting_name = self::STATUS_APPROVED === $status ? self::NOTIFICATION_APPROVED_TEXT_VALUE : self::NOTIFICATION_REJECTED_TEXT_VALUE;
			$template     = isset( $settings[ $setting_name ] ) ? (string) $settings[ $setting_name ] : '';

			if ( '' === trim( $template ) ) {
				return null;
			}

			return $this->replace_merge_tags_in_text( $template, $form, $entry );
		}

		if ( 'single' === $kind ) {
			$setting_name = self::STATUS_APPROVED === $status ? self::NOTIFICATION_APPROVED_CHOICE_VALUE : self::NOTIFICATION_REJECTED_CHOICE_VALUE;
			$value        = isset( $settings[ $setting_name ] ) ? (string) $settings[ $setting_name ] : '';

			if ( '' === $value ) {
				return null;
			}

			$choices = $this->get_supported_field_choice_value_map( $field );

			if ( ! isset( $choices[ $value ] ) ) {
				$this->log_error( __METHOD__ . '(): configured single choice value is no longer valid for field ' . $field->id . '.' );
				return null;
			}

			return $value;
		}

		$setting_name = self::STATUS_APPROVED === $status ? self::NOTIFICATION_APPROVED_CHOICE_VALUES : self::NOTIFICATION_REJECTED_CHOICE_VALUES;
		$values       = isset( $settings[ $setting_name ] ) ? $settings[ $setting_name ] : array();
		$values       = is_array( $values ) ? array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) ) : array();

		if ( empty( $values ) ) {
			return null;
		}

		$choices = $this->get_supported_field_choice_value_map( $field );
		$values  = array_values(
			array_filter(
				array_unique( $values ),
				static function( $value ) use ( $choices ) {
					return isset( $choices[ $value ] );
				}
			)
		);

		if ( empty( $values ) ) {
			$this->log_error( __METHOD__ . '(): configured multi-choice values are no longer valid for field ' . $field->id . '.' );
			return null;
		}

		return $values;
	}

	/**
	 * Returns the manual value submitted on the public confirmation page.
	 *
	 * @param object $field The configured target field.
	 *
	 * @return array|string|null
	 */
	private function get_manual_decision_update_value_from_request( $field ) {
		$input_type = $field->get_input_type();
		$input_name = self::PUBLIC_DECISION_UPDATE_VALUE;

		if ( in_array( $input_type, array( 'checkbox', 'multiselect' ), true ) ) {
			$raw_values = isset( $_POST[ $input_name ] ) ? wp_unslash( $_POST[ $input_name ] ) : array();

			if ( ! is_array( $raw_values ) ) {
				return array();
			}

			$choices = $this->get_supported_field_choice_value_map( $field );
			$values  = array();

			foreach ( $raw_values as $raw_value ) {
				$value = sanitize_text_field( $raw_value );

				if ( '' === $value || ! isset( $choices[ $value ] ) ) {
					continue;
				}

				$values[] = $value;
			}

			return array_values( array_unique( $values ) );
		}

		if ( ! isset( $_POST[ $input_name ] ) ) {
			return null;
		}

		$raw_value = wp_unslash( $_POST[ $input_name ] );

		if ( is_array( $raw_value ) ) {
			return null;
		}

		if ( 'textarea' === $input_type ) {
			return sanitize_textarea_field( $raw_value );
		}

		if ( 'email' === $input_type ) {
			return sanitize_email( $raw_value );
		}

		if ( 'website' === $input_type ) {
			return esc_url_raw( $raw_value );
		}

		if ( in_array( $input_type, array( 'select', 'radio' ), true ) ) {
			$value   = sanitize_text_field( $raw_value );
			$choices = $this->get_supported_field_choice_value_map( $field );

			if ( '' !== $value && ! isset( $choices[ $value ] ) ) {
				return null;
			}

			return $value;
		}

		return sanitize_text_field( $raw_value );
	}

	/**
	 * Returns the input type used on the public confirmation page.
	 *
	 * @param string $field_input_type The Gravity Forms input type.
	 *
	 * @return string
	 */
	private function get_public_html_input_type( $field_input_type ) {
		$map = array(
			'text'    => 'text',
			'email'   => 'email',
			'number'  => 'number',
			'phone'   => 'tel',
			'website' => 'url',
			'date'    => 'date',
		);

		return isset( $map[ $field_input_type ] ) ? $map[ $field_input_type ] : 'text';
	}

	/**
	 * Returns the placeholder used for a manual public field when available.
	 *
	 * @param object $field The Gravity Forms field object.
	 *
	 * @return string
	 */
	private function get_public_manual_field_placeholder( $field ) {
		return isset( $field->placeholder ) ? (string) $field->placeholder : '';
	}

	/**
	 * Persists an updated field value and refreshes the in-memory entry array.
	 *
	 * @param int    $entry_id The current entry id.
	 * @param array  $entry    The current entry.
	 * @param array  $form     The current form.
	 * @param object $field    The target field object.
	 * @param mixed  $value    The new field value.
	 *
	 * @return array<string, mixed>
	 */
	private function persist_field_update( $entry_id, $entry, $form, $field, $value ) {
		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'update_entry_field' ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'The approval request could not be processed.', 'gf-email-approvals' ),
			);
		}

		$input_type = $field->get_input_type();

		if ( 'checkbox' === $input_type ) {
			return $this->persist_checkbox_field_update( $entry_id, $entry, $field, is_array( $value ) ? $value : array() );
		}

		$storage_value = $value;

		if ( 'multiselect' === $input_type ) {
			$storage_value = is_array( $value ) && method_exists( $field, 'to_string' ) ? $field->to_string( $value ) : implode( ',', (array) $value );
		}

		$result = GFAPI::update_entry_field( $entry_id, (string) $field->id, $storage_value );

		if ( true !== $result ) {
			$this->log_error( __METHOD__ . '(): failed to update field ' . $field->id . ' for entry ' . $entry_id . '.' );

			return array(
				'success' => false,
				'message' => esc_html__( 'The approval request could not be processed.', 'gf-email-approvals' ),
			);
		}

		$entry[ (string) $field->id ] = $storage_value;

		return array(
			'success' => true,
			'entry'   => $entry,
		);
	}

	/**
	 * Persists an updated checkbox field value.
	 *
	 * @param int    $entry_id         The current entry id.
	 * @param array  $entry            The current entry.
	 * @param object $field            The checkbox field object.
	 * @param array  $selected_values  The selected stored values.
	 *
	 * @return array<string, mixed>
	 */
	private function persist_checkbox_field_update( $entry_id, $entry, $field, $selected_values ) {
		$selected_values = array_values( array_unique( array_map( 'strval', $selected_values ) ) );
		$choice_map      = $this->get_checkbox_choice_input_map( $field );

		foreach ( $choice_map as $choice ) {
			$input_id = $choice['input_id'];
			$value    = in_array( $choice['value'], $selected_values, true ) ? $choice['value'] : '';
			$result   = GFAPI::update_entry_field( $entry_id, $input_id, $value );

			if ( true !== $result ) {
				$this->log_error( __METHOD__ . '(): failed to update checkbox input ' . $input_id . ' for entry ' . $entry_id . '.' );

				return array(
					'success' => false,
					'message' => esc_html__( 'The approval request could not be processed.', 'gf-email-approvals' ),
				);
			}

			$entry[ $input_id ] = $value;
		}

		$entry[ (string) $field->id ] = implode( ',', $selected_values );

		return array(
			'success' => true,
			'entry'   => $entry,
		);
	}

	/**
	 * Returns the checkbox choice map keyed by input id.
	 *
	 * @param object $field The checkbox field object.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_checkbox_choice_input_map( $field ) {
		$map    = array();
		$inputs = method_exists( $field, 'get_entry_inputs' ) ? $field->get_entry_inputs() : array();
		$inputs = is_array( $inputs ) ? array_values( $inputs ) : array();
		$choices = isset( $field->choices ) && is_array( $field->choices ) ? array_values( $field->choices ) : array();

		foreach ( $choices as $index => $choice ) {
			$input_id = isset( $inputs[ $index ]['id'] ) ? (string) $inputs[ $index ]['id'] : '';
			$value    = $this->get_choice_storage_value( $choice );
			$label    = isset( $choice['text'] ) ? (string) $choice['text'] : $value;

			if ( '' === $input_id || '' === $value ) {
				continue;
			}

			$map[] = array(
				'input_id' => $input_id,
				'value'    => $value,
				'label'    => $label,
			);
		}

		return $map;
	}

	/**
	 * Returns the current entry value for a supported field.
	 *
	 * @param array  $entry The current entry.
	 * @param object $field The target field object.
	 *
	 * @return mixed
	 */
	private function get_entry_field_value_for_update( $entry, $field ) {
		$input_type = $field->get_input_type();

		if ( 'checkbox' === $input_type ) {
			return $this->get_checkbox_selected_values_from_entry( $field, $entry );
		}

		$value = $this->array_value( $entry, (string) $field->id, '' );

		if ( 'multiselect' === $input_type ) {
			if ( method_exists( $field, 'to_array' ) ) {
				return $field->to_array( $value );
			}

			return '' === (string) $value ? array() : array_map( 'trim', explode( ',', (string) $value ) );
		}

		return is_array( $value ) ? $value : (string) $value;
	}

	/**
	 * Returns the currently selected stored values for a checkbox field.
	 *
	 * @param object $field The checkbox field object.
	 * @param array  $entry The current entry.
	 *
	 * @return array<int, string>
	 */
	private function get_checkbox_selected_values_from_entry( $field, $entry ) {
		$selected_values = array();

		foreach ( $this->get_checkbox_choice_input_map( $field ) as $choice ) {
			$current_value = (string) $this->array_value( $entry, $choice['input_id'], '' );

			if ( '' === $current_value ) {
				continue;
			}

			$selected_values[] = $choice['value'];
		}

		return $selected_values;
	}

	/**
	 * Returns whether two supported field values are equivalent.
	 *
	 * @param object $field     The target field object.
	 * @param mixed  $old_value The original value.
	 * @param mixed  $new_value The new value.
	 *
	 * @return bool
	 */
	private function decision_update_values_equal( $field, $old_value, $new_value ) {
		$input_type = $field->get_input_type();

		if ( in_array( $input_type, array( 'checkbox', 'multiselect' ), true ) ) {
			$old_values = is_array( $old_value ) ? array_values( array_map( 'strval', $old_value ) ) : array();
			$new_values = is_array( $new_value ) ? array_values( array_map( 'strval', $new_value ) ) : array();

			sort( $old_values );
			sort( $new_values );

			return $old_values === $new_values;
		}

		return (string) $old_value === (string) $new_value;
	}

	/**
	 * Formats a field value for audit note output.
	 *
	 * @param object $field The target field object.
	 * @param mixed  $value The raw field value.
	 *
	 * @return string
	 */
	private function format_field_value_for_note( $field, $value ) {
		$input_type = $field->get_input_type();

		if ( in_array( $input_type, array( 'select', 'radio' ), true ) ) {
			$choice_map = $this->get_supported_field_choice_value_map( $field );
			$value      = (string) $value;

			if ( '' === $value ) {
				return esc_html__( '(empty)', 'gf-email-approvals' );
			}

			return isset( $choice_map[ $value ] ) ? $choice_map[ $value ] : $value;
		}

		if ( 'checkbox' === $input_type ) {
			$choice_map = array();

			foreach ( $this->get_checkbox_choice_input_map( $field ) as $choice ) {
				$choice_map[ $choice['value'] ] = $choice['label'];
			}

			$values = is_array( $value ) ? $value : array();

			if ( empty( $values ) ) {
				return esc_html__( '(empty)', 'gf-email-approvals' );
			}

			$labels = array();

			foreach ( $values as $item ) {
				$item     = (string) $item;
				$labels[] = isset( $choice_map[ $item ] ) ? $choice_map[ $item ] : $item;
			}

			return implode( ', ', $labels );
		}

		if ( 'multiselect' === $input_type ) {
			$choice_map = $this->get_supported_field_choice_value_map( $field );
			$values     = is_array( $value ) ? $value : ( method_exists( $field, 'to_array' ) ? $field->to_array( $value ) : array() );

			if ( empty( $values ) ) {
				return esc_html__( '(empty)', 'gf-email-approvals' );
			}

			$labels = array();

			foreach ( $values as $item ) {
				$item     = (string) $item;
				$labels[] = isset( $choice_map[ $item ] ) ? $choice_map[ $item ] : $item;
			}

			return implode( ', ', $labels );
		}

		$value = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;

		return '' === trim( $value ) ? esc_html__( '(empty)', 'gf-email-approvals' ) : $value;
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
	 * Returns the default theme used on the public approval pages.
	 *
	 * @return array<string, int|string>
	 */
	private function get_public_page_theme_defaults() {
		return array(
			self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR => '#f5f5f5',
			self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR => '#ffffff',
			self::PLUGIN_SETTING_TEXT_COLOR            => '#1d2327',
			self::PLUGIN_SETTING_TITLE_COLOR           => '#1d2327',
			self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR  => '#2271b1',
			self::PLUGIN_SETTING_REJECT_BUTTON_COLOR   => '#b32d2e',
			self::PLUGIN_SETTING_BUTTON_TEXT_COLOR     => '#ffffff',
			self::PLUGIN_SETTING_CARD_MAX_WIDTH        => 640,
			self::PLUGIN_SETTING_CARD_PADDING          => 32,
			self::PLUGIN_SETTING_CARD_BORDER_RADIUS    => 12,
		);
	}

	/**
	 * Returns the sanitized theme used on the public approval pages.
	 *
	 * @return array<string, int|string>
	 */
	private function get_public_page_theme_settings() {
		$defaults = $this->get_public_page_theme_defaults();
		$settings = $defaults;

		$color_settings = array(
			self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR,
			self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR,
			self::PLUGIN_SETTING_TEXT_COLOR,
			self::PLUGIN_SETTING_TITLE_COLOR,
			self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR,
			self::PLUGIN_SETTING_REJECT_BUTTON_COLOR,
			self::PLUGIN_SETTING_BUTTON_TEXT_COLOR,
		);

		foreach ( $color_settings as $setting_name ) {
			$value = $this->get_plugin_setting( $setting_name );

			if ( ! is_string( $value ) ) {
				continue;
			}

			$sanitized = sanitize_hex_color( trim( $value ) );

			if ( $sanitized ) {
				$settings[ $setting_name ] = $sanitized;
			}
		}

		$settings[ self::PLUGIN_SETTING_CARD_MAX_WIDTH ] = $this->sanitize_public_page_dimension(
			$this->get_plugin_setting( self::PLUGIN_SETTING_CARD_MAX_WIDTH ),
			320,
			960,
			(int) $defaults[ self::PLUGIN_SETTING_CARD_MAX_WIDTH ]
		);

		$settings[ self::PLUGIN_SETTING_CARD_PADDING ] = $this->sanitize_public_page_dimension(
			$this->get_plugin_setting( self::PLUGIN_SETTING_CARD_PADDING ),
			16,
			80,
			(int) $defaults[ self::PLUGIN_SETTING_CARD_PADDING ]
		);

		$settings[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ] = $this->sanitize_public_page_dimension(
			$this->get_plugin_setting( self::PLUGIN_SETTING_CARD_BORDER_RADIUS ),
			0,
			40,
			(int) $defaults[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ]
		);

		return $settings;
	}

	/**
	 * Sanitizes a numeric theme dimension.
	 *
	 * @param mixed $value   Raw dimension value.
	 * @param int   $min     Minimum accepted value.
	 * @param int   $max     Maximum accepted value.
	 * @param int   $default Fallback value.
	 *
	 * @return int
	 */
	private function sanitize_public_page_dimension( $value, $min, $max, $default ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}

		if ( '' === $value || ! is_numeric( $value ) ) {
			return $default;
		}

		$value = (int) round( (float) $value );

		if ( $value < $min || $value > $max ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Converts a hex color to an rgba() string.
	 *
	 * @param string $hex_color Hex color value.
	 * @param float  $alpha     Alpha value between 0 and 1.
	 * @param string $fallback  Fallback rgba string.
	 *
	 * @return string
	 */
	private function hex_to_rgba( $hex_color, $alpha, $fallback ) {
		$hex_color = sanitize_hex_color( $hex_color );

		if ( ! $hex_color ) {
			return $fallback;
		}

		$hex_color = ltrim( $hex_color, '#' );

		if ( 3 === strlen( $hex_color ) ) {
			$hex_color = preg_replace( '/(.)/', '$1$1', $hex_color );
		}

		if ( 6 !== strlen( $hex_color ) ) {
			return $fallback;
		}

		$red   = hexdec( substr( $hex_color, 0, 2 ) );
		$green = hexdec( substr( $hex_color, 2, 2 ) );
		$blue  = hexdec( substr( $hex_color, 4, 2 ) );

		return sprintf( 'rgba(%1$d,%2$d,%3$d,%4$s)', $red, $green, $blue, rtrim( rtrim( sprintf( '%.2f', max( 0, min( 1, (float) $alpha ) ) ), '0' ), '.' ) );
	}

	/**
	 * Returns the inline CSS variables used by the settings preview.
	 *
	 * @param array $theme The current sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_preview_style_variables( $theme ) {
		return sprintf(
			'--gf-email-approvals-page-bg:%1$s;--gf-email-approvals-card-bg:%2$s;--gf-email-approvals-text:%3$s;--gf-email-approvals-title:%4$s;--gf-email-approvals-approve:%5$s;--gf-email-approvals-reject:%6$s;--gf-email-approvals-button-text:%7$s;--gf-email-approvals-card-width:%8$dpx;--gf-email-approvals-card-padding:%9$dpx;--gf-email-approvals-card-radius:%10$dpx;--gf-email-approvals-shadow:%11$s;',
			(string) $theme[ self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ],
			(string) $theme[ self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
			(string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ],
			(string) $theme[ self::PLUGIN_SETTING_TITLE_COLOR ],
			(string) $theme[ self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ],
			(string) $theme[ self::PLUGIN_SETTING_REJECT_BUTTON_COLOR ],
			(string) $theme[ self::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
			(int) $theme[ self::PLUGIN_SETTING_CARD_MAX_WIDTH ],
			(int) $theme[ self::PLUGIN_SETTING_CARD_PADDING ],
			(int) $theme[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ],
			$this->hex_to_rgba( (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ], 0.12, 'rgba(29,35,39,0.12)' )
		);
	}

	/**
	 * Returns the inline style used by public confirmation buttons.
	 *
	 * @param string     $status     The decision status.
	 * @param array|null $theme      Optional already-sanitized theme.
	 * @param bool       $full_width Whether the button should stretch to the card width.
	 *
	 * @return string
	 */
	private function get_public_page_button_style( $status, $theme = null, $full_width = true ) {
		$theme             = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();
		$radius            = max( 4, min( 18, (int) round( (int) $theme[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ] * 0.5 ) ) );
		$background        = self::STATUS_REJECTED === $status ? (string) $theme[ self::PLUGIN_SETTING_REJECT_BUTTON_COLOR ] : (string) $theme[ self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ];
		$width_declaration = $full_width ? 'width:100%;' : '';

		return sprintf(
			'display:block;%1$sbox-sizing:border-box;padding:12px 18px;background:%2$s;color:%3$s;border:0;border-radius:%4$dpx;cursor:pointer;font:inherit;font-weight:600;',
			$width_declaration,
			$background,
			(string) $theme[ self::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
			$radius
		);
	}

	/**
	 * Returns the inline style used by public manual input controls.
	 *
	 * @param string     $control_type Control type: input, textarea, select, multiselect.
	 * @param array|null $theme        Optional already-sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_input_style( $control_type, $theme = null ) {
		$theme        = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();
		$radius       = max( 4, min( 18, (int) round( (int) $theme[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ] * 0.5 ) ) );
		$border_color = $this->hex_to_rgba( (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ], 0.18, 'rgba(29,35,39,0.18)' );
		$style        = sprintf(
			'display:block;width:100%%;max-width:100%%;margin-top:12px;padding:12px 14px;border:1px solid %1$s;border-radius:%2$dpx;background:%3$s;color:%4$s;font:inherit;box-sizing:border-box;',
			$border_color,
			$radius,
			(string) $theme[ self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
			(string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ]
		);

		if ( 'textarea' === $control_type ) {
			return $style . 'min-height:120px;resize:vertical;';
		}

		if ( 'multiselect' === $control_type ) {
			return $style . 'min-height:140px;';
		}

		return $style;
	}

	/**
	 * Returns the inline style used by public manual field labels.
	 *
	 * @param array|null $theme Optional already-sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_field_label_style( $theme = null ) {
		$theme = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();

		return sprintf(
			'display:block;font-weight:600;line-height:1.4;color:%s;',
			(string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ]
		);
	}

	/**
	 * Returns the inline style used by radio and checkbox labels on public pages.
	 *
	 * @param array|null $theme Optional already-sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_choice_label_style( $theme = null ) {
		$theme = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();

		return sprintf(
			'display:flex;align-items:flex-start;gap:10px;margin-top:12px;font-weight:400;color:%s;',
			(string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ]
		);
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
		$theme              = $this->get_public_page_theme_settings();
		$responsive_padding = max( 16, (int) $theme[ self::PLUGIN_SETTING_CARD_PADDING ] - 8 );
		$allowed_html = array(
			'form'    => array(
				'method' => true,
				'action' => true,
			),
			'input'   => array(
				'type'  => true,
				'name'  => true,
				'value' => true,
				'id'    => true,
				'checked' => true,
				'placeholder' => true,
				'style' => true,
			),
			'textarea' => array(
				'name'        => true,
				'id'          => true,
				'rows'        => true,
				'placeholder' => true,
				'style'       => true,
			),
			'button'  => array(
				'type'  => true,
				'style' => true,
			),
			'label'   => array(
				'for'   => true,
				'style' => true,
			),
			'select'  => array(
				'name'     => true,
				'id'       => true,
				'multiple' => true,
				'style'    => true,
			),
			'option'  => array(
				'value'    => true,
				'selected' => true,
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
		echo '<style>html{box-sizing:border-box;}*,*::before,*::after{box-sizing:inherit;}input,select,textarea{font:inherit;max-width:100%;}textarea{resize:vertical;}body.gf-email-approvals-public{margin:0;font-family:Segoe UI,Arial,sans-serif;background:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ] ) . ';color:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ] ) . ';}main.gf-email-approvals-public__main{width:100%;max-width:' . absint( $theme[ self::PLUGIN_SETTING_CARD_MAX_WIDTH ] ) . 'px;margin:8vh auto;padding:24px;box-sizing:border-box;}section.gf-email-approvals-public__card{width:100%;box-sizing:border-box;background:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ] ) . ';border-radius:' . absint( $theme[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ] ) . 'px;padding:' . absint( $theme[ self::PLUGIN_SETTING_CARD_PADDING ] ) . 'px;box-shadow:0 10px 30px ' . esc_html( $this->hex_to_rgba( (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ], 0.12, 'rgba(0,0,0,0.08)' ) ) . ';}h1.gf-email-approvals-public__title{margin-top:0;margin-bottom:16px;font-size:28px;line-height:1.2;color:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_TITLE_COLOR ] ) . ';}.gf-email-approvals-public__content p:first-child{margin-top:0;}.gf-email-approvals-public__content p:last-child{margin-bottom:0;}@media screen and (max-width:680px){main.gf-email-approvals-public__main{margin:0;padding:16px;}section.gf-email-approvals-public__card{padding:' . absint( $responsive_padding ) . 'px;}}</style>';
		echo '</head><body class="gf-email-approvals-public">';
		echo '<main class="gf-email-approvals-public__main">';
		echo '<section class="gf-email-approvals-public__card">';
		echo '<h1 class="gf-email-approvals-public__title">' . esc_html( $title ) . '</h1>';
		echo '<div class="gf-email-approvals-public__content">';
		echo wp_kses( $content, $allowed_html );
		echo '</div></section></main></body></html>';

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