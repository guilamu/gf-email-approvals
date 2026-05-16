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
	const NOTIFICATION_DECISION_UPDATE_FIELDS = 'approval_decision_update_fields';
	const NOTIFICATION_DECISION_UPDATE_MAPPINGS = 'approval_decision_update_mappings';
	const NOTIFICATION_APPROVED_TEXT_VALUE = 'approval_approved_text_value';
	const NOTIFICATION_REJECTED_TEXT_VALUE = 'approval_rejected_text_value';
	const NOTIFICATION_APPROVED_CHOICE_VALUE = 'approval_approved_choice_value';
	const NOTIFICATION_REJECTED_CHOICE_VALUE = 'approval_rejected_choice_value';
	const NOTIFICATION_APPROVED_CHOICE_VALUES = 'approval_approved_choice_values';
	const NOTIFICATION_REJECTED_CHOICE_VALUES = 'approval_rejected_choice_values';
	const NOTIFICATION_DECISION_UPDATE_FIELDS_FIELD = 'approval_decision_update_fields_builder';
	const NOTIFICATION_DECISION_UPDATE_MAPPINGS_FIELD = 'approval_decision_update_mappings_builder';
	const DECISION_VALUE_FIELD_NAME_APPROVED = 'approval_approved_value_control';
	const DECISION_VALUE_FIELD_NAME_REJECTED = 'approval_rejected_value_control';
	const DECISION_VALUE_VIRTUAL_FIELD_ID_APPROVED = 910000000;
	const DECISION_VALUE_VIRTUAL_FIELD_ID_REJECTED = 920000000;
	const DECISION_VALUE_VIRTUAL_FIELD_ID_MANUAL = 930000000;
	const PLUGIN_SETTING_PAGE_BACKGROUND_COLOR = 'approval_page_background_color';
	const PLUGIN_SETTING_CARD_BACKGROUND_COLOR = 'approval_page_card_background_color';
	const PLUGIN_SETTING_TEXT_COLOR = 'approval_page_text_color';
	const PLUGIN_SETTING_TITLE_COLOR = 'approval_page_title_color';
	const PLUGIN_SETTING_TITLE_ALIGNMENT = 'approval_page_title_alignment';
	const PLUGIN_SETTING_TITLE_FONT_SIZE = 'approval_page_title_font_size';
	const PLUGIN_SETTING_TITLE_FONT_SIZE_UNIT = 'approval_page_title_font_size_unit';
	const PLUGIN_SETTING_MESSAGE_ALIGNMENT = 'approval_page_message_alignment';
	const PLUGIN_SETTING_MESSAGE_FONT_SIZE = 'approval_page_message_font_size';
	const PLUGIN_SETTING_MESSAGE_FONT_SIZE_UNIT = 'approval_page_message_font_size_unit';
	const PLUGIN_SETTING_APPROVE_BUTTON_COLOR = 'approval_page_approve_button_color';
	const PLUGIN_SETTING_REJECT_BUTTON_COLOR = 'approval_page_reject_button_color';
	const PLUGIN_SETTING_BUTTON_TEXT_COLOR = 'approval_page_button_text_color';
	const PLUGIN_SETTING_CARD_MAX_WIDTH = 'approval_page_card_max_width';
	const PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT = 'approval_page_card_max_width_unit';
	const PLUGIN_SETTING_CARD_PADDING = 'approval_page_card_padding';
	const PLUGIN_SETTING_CARD_PADDING_UNIT = 'approval_page_card_padding_unit';
	const PLUGIN_SETTING_CARD_BORDER_RADIUS = 'approval_page_card_border_radius';
	const PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT = 'approval_page_card_border_radius_unit';
	const PLUGIN_SETTING_LOGO_IMAGE = 'approval_page_logo_image';
	const PLUGIN_SETTING_LOGO_ALIGNMENT = 'approval_page_logo_alignment';
	const PLUGIN_SETTING_LOGO_MAX_HEIGHT = 'approval_page_logo_max_height';
	const PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT = 'approval_page_logo_max_height_unit';
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
	 * Appearance settings helper.
	 *
	 * @var GFEmailApprovalsAppearanceSettingsHelper|null
	 */
	private $appearance_settings_helper = null;

	/**
	 * Public page presentation helper.
	 *
	 * @var GFEmailApprovalsPublicPagePresentationHelper|null
	 */
	private $public_page_presentation_helper = null;

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
	 * Returns the add-on icon used by Gravity Forms for this settings tab.
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return 'dashicons-email';
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_decision_update_settings_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_appearance_builder_assets' ) );
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
		return array(
			array(
				'title'       => esc_html__( 'Approval Page Settings', 'gf-email-approvals' ),
				'fields'      => array(
					array(
						'name' => 'approval_page_settings_accordions',
						'type' => 'approval_page_settings_accordions',
					),
				),
			),
			array(
				'title'       => esc_html__( 'Approval Page Preview', 'gf-email-approvals' ),
				'fields'      => array(
					array(
						'name'        => 'approval_page_preview',
						'label'       => '',
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
		$decision_settings         = $this->get_current_notification_decision_update_settings( $notification, $form );
		$manual_fields             = $this->get_notification_manual_decision_update_fields( $form, $decision_settings );
		$automatic_mappings        = $this->get_notification_decision_update_mappings( $form, $decision_settings );

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
					'default_value' => $this->get_notification_page_field_default_value( $notification, self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT, $defaults[ self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT ] ),
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_APPROVED_RESULT_TEXT,
					'label'         => esc_html__( 'Approved result text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right approval-page-copy-textarea',
					'default_value' => $this->get_notification_page_field_default_value( $notification, self::NOTIFICATION_APPROVED_RESULT_TEXT, $defaults[ self::NOTIFICATION_APPROVED_RESULT_TEXT ] ),
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
					'default_value' => $this->get_notification_page_field_default_value( $notification, self::NOTIFICATION_REJECT_CONFIRMATION_TEXT, $defaults[ self::NOTIFICATION_REJECT_CONFIRMATION_TEXT ] ),
				),
				array(
					'type'          => 'textarea',
					'name'          => self::NOTIFICATION_REJECTED_RESULT_TEXT,
					'label'         => esc_html__( 'Rejected result text', 'gf-email-approvals' ),
					'class'         => 'large merge-tag-support mt-position-right approval-page-copy-textarea',
					'default_value' => $this->get_notification_page_field_default_value( $notification, self::NOTIFICATION_REJECTED_RESULT_TEXT, $defaults[ self::NOTIFICATION_REJECTED_RESULT_TEXT ] ),
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
			'description' => esc_html__( 'Optionally update one or more supported entry fields after the approver confirms their decision. You can either apply predefined values automatically or let the approver choose one field value on the confirmation page.', 'gf-email-approvals' ),
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
					'type' => 'html',
					'name' => self::NOTIFICATION_DECISION_UPDATE_FIELDS_FIELD,
					'html' => $this->get_manual_decision_update_fields_setting_markup( $form, $manual_fields ),
				),
				array(
					'type'          => 'html',
					'name'          => self::NOTIFICATION_DECISION_UPDATE_MAPPINGS_FIELD,
					'html'          => $this->get_decision_update_mappings_setting_markup( $form, $automatic_mappings ),
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

			$value              = sanitize_textarea_field( (string) $posted_value );
			$empty_override_key = $this->get_notification_page_empty_override_key( $setting_name );

			if ( '' === $value ) {
				if ( '' !== $empty_override_key ) {
					$notification[ $empty_override_key ] = '1';
				}

				unset( $notification[ $setting_name ] );
				continue;
			}

			if ( '' !== $empty_override_key ) {
				unset( $notification[ $empty_override_key ] );
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

		$legacy_automatic_setting_names = array(
			self::NOTIFICATION_APPROVED_TEXT_VALUE,
			self::NOTIFICATION_REJECTED_TEXT_VALUE,
			self::NOTIFICATION_APPROVED_CHOICE_VALUE,
			self::NOTIFICATION_REJECTED_CHOICE_VALUE,
			self::NOTIFICATION_APPROVED_CHOICE_VALUES,
			self::NOTIFICATION_REJECTED_CHOICE_VALUES,
		);

		foreach ( $legacy_automatic_setting_names as $setting_name ) {
			unset( $notification[ $setting_name ] );
		}

		if ( self::UPDATE_MODE_MANUAL === $update_mode ) {
			$manual_fields = $this->sanitize_posted_manual_decision_update_fields( $form );

			if ( ! empty( $manual_fields ) ) {
				$notification[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] = $manual_fields;
			} else {
				unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] );
			}

			unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] );
			unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] );

			return $notification;
		}

		unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] );
		unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] );

		if ( self::UPDATE_MODE_AUTOMATIC === $update_mode ) {
			$mappings = $this->sanitize_posted_decision_update_mappings( $form );

			if ( ! empty( $mappings ) ) {
				$notification[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] = $mappings;
			} else {
				unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] );
			}

			return $notification;
		}

		unset( $notification[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] );

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
	 * Returns whether a notification setting was posted on the current request.
	 *
	 * @param string $setting_name The setting name.
	 *
	 * @return bool
	 */
	private function has_posted_notification_setting( $setting_name ) {
		$posted_values = array();

		if ( class_exists( 'GFNotification' ) && method_exists( 'GFNotification', 'get_settings_renderer' ) ) {
			$settings_renderer = GFNotification::get_settings_renderer();

			if ( is_object( $settings_renderer ) && method_exists( $settings_renderer, 'get_posted_values' ) ) {
				$posted_values = $settings_renderer->get_posted_values();
			}
		}

		if ( is_array( $posted_values ) && array_key_exists( $setting_name, $posted_values ) ) {
			return true;
		}

		if ( isset( $_POST[ $setting_name ] ) ) {
			return true;
		}

		return isset( $_POST[ '_gform_setting_' . $setting_name ] );
	}

	/**
	 * Returns a posted notification setting array.
	 *
	 * @param string $setting_name The setting name.
	 *
	 * @return array
	 */
	private function get_posted_notification_setting_array( $setting_name ) {
		$prefixed_setting_name = '_gform_setting_' . $setting_name;

		if ( isset( $_POST[ $prefixed_setting_name ] ) && is_array( $_POST[ $prefixed_setting_name ] ) ) {
			return wp_unslash( $_POST[ $prefixed_setting_name ] );
		}

		if ( isset( $_POST[ $setting_name ] ) && is_array( $_POST[ $setting_name ] ) ) {
			return wp_unslash( $_POST[ $setting_name ] );
		}

		$value = $this->get_posted_notification_setting( $setting_name, array() );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Returns the posted automatic decision update value for the selected target field.
	 *
	 * @param array  $form   The current form.
	 * @param object $field  The selected target field.
	 * @param string $status The target decision status.
	 *
	 * @return array|string|null
	 */
	private function get_posted_decision_update_value( $form, $field, $status, $row_key = 0 ) {
		if ( ! is_object( $field ) || ! class_exists( 'GFFormsModel' ) || ! method_exists( 'GFFormsModel', 'get_field_value' ) ) {
			return null;
		}

		$virtual_field = $this->get_decision_update_virtual_field( $field, $form, $status, $row_key );

		if ( ! $virtual_field ) {
			return null;
		}

		$submit_flag_name   = 'is_submit_' . absint( $virtual_field->formId );
		$submit_flag_exists = array_key_exists( $submit_flag_name, $_POST );
		$previous_submit    = $submit_flag_exists ? $_POST[ $submit_flag_name ] : null;

		$_POST[ $submit_flag_name ] = '1';
		$raw_value                  = GFFormsModel::get_field_value( $virtual_field, array(), true );

		if ( $submit_flag_exists ) {
			$_POST[ $submit_flag_name ] = $previous_submit;
		} else {
			unset( $_POST[ $submit_flag_name ] );
		}

		if ( 'multi' === $this->get_decision_update_field_kind( $field ) ) {
			if ( ! is_array( $raw_value ) ) {
				$raw_value = rgblank( $raw_value ) ? array() : array( $raw_value );
			}

			return array_values(
				array_filter(
					array_map( 'strval', $raw_value ),
					'strlen'
				)
			);
		}

		if ( method_exists( 'GFFormsModel', 'prepare_value' ) ) {
			$raw_value = GFFormsModel::prepare_value( $form, $virtual_field, $raw_value, 'input_' . $virtual_field->id, 0, array() );
		} elseif ( method_exists( $virtual_field, 'get_value_save_entry' ) ) {
			$raw_value = $virtual_field->get_value_save_entry( $raw_value, $form, 'input_' . $virtual_field->id, 0, array() );
		}

		if ( is_string( $raw_value ) ) {
			return $raw_value;
		}

		return is_scalar( $raw_value ) ? (string) $raw_value : null;
	}

	/**
	 * Creates a virtual Gravity Forms field used to render and read decision update inputs.
	 *
	 * @param object $field  The selected target field.
	 * @param array  $form   The current form.
	 * @param string $status The target decision status.
	 *
	 * @return object|null
	 */
	private function get_decision_update_virtual_field( $field, $form, $status, $row_key = 0, $preserve_validation = false ) {
		if ( ! is_object( $field ) || ! isset( $field->id ) ) {
			return null;
		}

		$virtual_field = clone $field;
		$original_id   = (string) $virtual_field->id;
		$virtual_id    = $this->get_decision_update_virtual_field_id( $status, $row_key );

		$virtual_field->id                 = $virtual_id;
		$virtual_field->formId             = absint( $this->array_value( $form, 'id' ) );
		$virtual_field->_is_entry_detail   = true;
		$virtual_field->isRequired         = $preserve_validation ? ! empty( $virtual_field->isRequired ) : false;
		$virtual_field->failed_validation  = false;
		$virtual_field->validation_message = '';
		$virtual_field->errorMessage       = '';

		if ( isset( $virtual_field->enableSelectAll ) ) {
			$virtual_field->enableSelectAll = false;
		}

		if ( isset( $virtual_field->choiceLimit ) ) {
			$virtual_field->choiceLimit = '';
		}

		if ( isset( $virtual_field->choiceLimitNumber ) ) {
			$virtual_field->choiceLimitNumber = '';
		}

		if ( isset( $virtual_field->choiceLimitMin ) ) {
			$virtual_field->choiceLimitMin = '';
		}

		if ( isset( $virtual_field->choiceLimitMax ) ) {
			$virtual_field->choiceLimitMax = '';
		}

		$this->remap_decision_update_virtual_field_inputs( $virtual_field, $original_id, (string) $virtual_id );

		return $virtual_field;
	}

	/**
	 * Updates multi-input ids on the virtual field so they match the virtual field id.
	 *
	 * @param object $field       The virtual field object.
	 * @param string $original_id The original field id.
	 * @param string $virtual_id  The virtual field id.
	 *
	 * @return void
	 */
	private function remap_decision_update_virtual_field_inputs( $field, $original_id, $virtual_id ) {
		if ( ! isset( $field->inputs ) || ! is_array( $field->inputs ) ) {
			return;
		}

		$inputs = array();

		foreach ( $field->inputs as $input ) {
			if ( isset( $input['id'] ) ) {
				$input_id = (string) $input['id'];

				if ( 0 === strpos( $input_id, $original_id . '.' ) ) {
					$input['id'] = $virtual_id . substr( $input_id, strlen( $original_id ) );
				} elseif ( $input_id === $original_id ) {
					$input['id'] = $virtual_id;
				}
			}

			$inputs[] = $input;
		}

		$field->inputs = $inputs;
	}

	/**
	 * Returns the virtual field id used to render a decision value input.
	 *
	 * @param string $status  The target decision status.
	 * @param int    $row_key The mapping row key.
	 *
	 * @return int
	 */
	private function get_decision_update_virtual_field_id( $status, $row_key = 0 ) {
		if ( self::STATUS_APPROVED === $status ) {
			$base_id = self::DECISION_VALUE_VIRTUAL_FIELD_ID_APPROVED;
		} elseif ( self::STATUS_REJECTED === $status ) {
			$base_id = self::DECISION_VALUE_VIRTUAL_FIELD_ID_REJECTED;
		} else {
			$base_id = self::DECISION_VALUE_VIRTUAL_FIELD_ID_MANUAL;
		}

		return $base_id + absint( $row_key );
	}

	/**
	 * Returns the native Gravity Forms field markup used for a decision update value.
	 *
	 * @param object     $field   The selected target field.
	 * @param array      $form    The current form.
	 * @param string     $status  The target decision status.
	 * @param int        $row_key The mapping row key.
	 * @param mixed|null $value   The current stored value.
	 *
	 * @return string
	 */
	private function get_decision_value_setting_markup( $field, $form, $status, $row_key = 0, $value = null ) {
		$virtual_field = $this->get_decision_update_virtual_field( $field, $form, $status, $row_key );

		if ( ! $virtual_field || ! method_exists( $virtual_field, 'get_field_input' ) ) {
			return '';
		}

		$input_type = $virtual_field->get_input_type();
		$virtual_id = $this->get_decision_update_virtual_field_id( $status, $row_key );

		if ( null === $value ) {
			$value = in_array( $input_type, array( 'checkbox', 'multiselect' ), true ) ? array() : '';
		} elseif ( 'multiselect' === $input_type ) {
			if ( is_string( $value ) && method_exists( $virtual_field, 'to_array' ) ) {
				$value = $virtual_field->to_array( $value );
			} elseif ( ! is_array( $value ) ) {
				$value = '' === (string) $value ? array() : array_map( 'trim', explode( ',', (string) $value ) );
			}
		} elseif ( 'checkbox' === $input_type ) {
			$value = is_array( $value ) ? $value : array();
		} elseif ( is_array( $value ) ) {
			$value = '';
		}

		if ( 'hidden' === $input_type ) {
			return sprintf(
				'<div class="gf-email-approvals-decision-value-field" data-input-type="hidden"><input type="text" name="input_%1$d" id="input_%1$d" class="regular-text" value="%2$s" /></div>',
				absint( $virtual_id ),
				esc_attr( is_array( $value ) ? '' : (string) $value )
			);
		}

		return sprintf(
			'<div class="gf-email-approvals-decision-value-field" data-input-type="%1$s">%2$s</div>',
			esc_attr( $input_type ),
			$virtual_field->get_field_input( $form, $value, array() )
		);
	}

	/**
	 * Returns the sanitized automatic mappings configured for a notification.
	 *
	 * @param array $form     The current form.
	 * @param array $settings The current notification settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_notification_decision_update_mappings( $form, $settings ) {
		$mappings = isset( $settings[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] ) && is_array( $settings[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] )
			? $settings[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ]
			: array();
		$sanitized = array();
		$used_ids  = array();

		foreach ( $mappings as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$field = $this->get_decision_update_field( $form, (string) $this->array_value( $mapping, 'field' ), self::UPDATE_MODE_AUTOMATIC );

			if ( ! $field ) {
				continue;
			}

			$field_id = (string) $field->id;

			if ( isset( $used_ids[ $field_id ] ) ) {
				continue;
			}

			$sanitized[] = array(
				'field'          => $field_id,
				'approved_value' => $this->sanitize_decision_update_mapping_value( $field, $this->array_value( $mapping, 'approved_value' ) ),
				'rejected_value' => $this->sanitize_decision_update_mapping_value( $field, $this->array_value( $mapping, 'rejected_value' ) ),
			);

			$used_ids[ $field_id ] = true;
		}

		return $sanitized;
	}

	/**
	 * Sanitizes the posted automatic decision update mappings.
	 *
	 * @param array $form The current form.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_posted_decision_update_mappings( $form ) {
		$posted_mappings = $this->get_posted_notification_setting_array( self::NOTIFICATION_DECISION_UPDATE_MAPPINGS );
		$sanitized       = array();
		$used_ids        = array();

		foreach ( $posted_mappings as $row_key => $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$field = $this->get_decision_update_field(
				$form,
				sanitize_text_field( (string) $this->array_value( $mapping, 'field', '' ) ),
				self::UPDATE_MODE_AUTOMATIC
			);

			if ( ! $field ) {
				continue;
			}

			$field_id = (string) $field->id;

			if ( isset( $used_ids[ $field_id ] ) ) {
				continue;
			}

			$sanitized[] = array(
				'field'          => $field_id,
				'approved_value' => $this->sanitize_decision_update_mapping_value( $field, $this->get_posted_decision_update_value( $form, $field, self::STATUS_APPROVED, $row_key ) ),
				'rejected_value' => $this->sanitize_decision_update_mapping_value( $field, $this->get_posted_decision_update_value( $form, $field, self::STATUS_REJECTED, $row_key ) ),
			);

			$used_ids[ $field_id ] = true;
		}

		return $sanitized;
	}

	/**
	 * Returns the sanitized manual field ids configured for a notification.
	 *
	 * @param array $form     The current form.
	 * @param array $settings The current notification settings.
	 *
	 * @return array<int, string>
	 */
	private function get_notification_manual_decision_update_fields( $form, $settings ) {
		$field_ids = isset( $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] ) && is_array( $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] )
			? $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ]
			: array();
		$legacy_field_id = (string) $this->array_value( $settings, self::NOTIFICATION_DECISION_UPDATE_FIELD, '' );
		$sanitized       = array();
		$used_ids        = array();

		if ( empty( $field_ids ) && '' !== $legacy_field_id ) {
			$field_ids[] = $legacy_field_id;
		}

		foreach ( $field_ids as $field_id ) {
			$field = $this->get_decision_update_field( $form, (string) $field_id, self::UPDATE_MODE_MANUAL );

			if ( ! $field ) {
				continue;
			}

			$field_id = (string) $field->id;

			if ( isset( $used_ids[ $field_id ] ) ) {
				continue;
			}

			$sanitized[]        = $field_id;
			$used_ids[ $field_id ] = true;
		}

		return $sanitized;
	}

	/**
	 * Sanitizes the posted manual decision update field ids.
	 *
	 * @param array $form The current form.
	 *
	 * @return array<int, string>
	 */
	private function sanitize_posted_manual_decision_update_fields( $form ) {
		$posted_field_ids = $this->get_posted_notification_setting_array( self::NOTIFICATION_DECISION_UPDATE_FIELDS );
		$sanitized        = array();
		$used_ids         = array();

		foreach ( $posted_field_ids as $field_id ) {
			$field = $this->get_decision_update_field(
				$form,
				sanitize_text_field( (string) $field_id ),
				self::UPDATE_MODE_MANUAL
			);

			if ( ! $field ) {
				continue;
			}

			$field_id = (string) $field->id;

			if ( isset( $used_ids[ $field_id ] ) ) {
				continue;
			}

			$sanitized[]         = $field_id;
			$used_ids[ $field_id ] = true;
		}

		return $sanitized;
	}

	/**
	 * Sanitizes a stored decision update mapping value for its field type.
	 *
	 * @param object $field The mapped field.
	 * @param mixed  $value The raw value.
	 *
	 * @return array|string
	 */
	private function sanitize_decision_update_mapping_value( $field, $value ) {
		$kind = $this->get_decision_update_field_kind( $field );

		if ( 'text' === $kind ) {
			if ( is_array( $value ) ) {
				return '';
			}

			return is_scalar( $value ) ? (string) $value : '';
		}

		$choices = $this->get_supported_field_choice_value_map( $field );

		if ( 'single' === $kind ) {
			$value = is_scalar( $value ) ? (string) $value : '';

			return '' !== $value && isset( $choices[ $value ] ) ? $value : '';
		}

		$values = is_array( $value ) ? $value : array();
		$values = array_values(
			array_filter(
				array_unique( array_map( 'strval', $values ) ),
				static function( $item ) use ( $choices ) {
					return '' !== $item && isset( $choices[ $item ] );
				}
			)
		);

		return $values;
	}

	/**
	 * Returns the automatic mappings builder markup.
	 *
	 * @param array $form     The current form.
	 * @param array $mappings The configured mappings.
	 *
	 * @return string
	 */
	private function get_decision_update_mappings_setting_markup( $form, $mappings ) {
		$rows_markup = '';

		foreach ( $mappings as $index => $mapping ) {
			$rows_markup .= $this->get_decision_update_mapping_row_markup( $form, $index + 1, $mapping );
		}

		$builder_classes = array( 'gf-email-approvals-mappings' );

		if ( '' !== $rows_markup ) {
			$builder_classes[] = 'gf-email-approvals-mappings--has-rows';
		}

		return sprintf(
			'<div class="%1$s" data-gf-email-approvals-mappings-builder><div class="gf-email-approvals-mappings__header" aria-hidden="true"><div class="gf-email-approvals-mappings__header-cell gf-email-approvals-mappings__header-cell--field">%2$s</div><div class="gf-email-approvals-mappings__header-cell">%3$s</div><div class="gf-email-approvals-mappings__header-cell">%4$s</div><div class="gf-email-approvals-mappings__header-cell gf-email-approvals-mappings__header-cell--actions"></div></div><div class="gf-email-approvals-mappings__rows" data-gf-email-approvals-mappings-rows>%5$s</div><button type="button" class="button button-secondary gf-email-approvals-mappings__add" data-gf-email-approvals-mappings-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span>%6$s</span></button></div>',
			esc_attr( implode( ' ', $builder_classes ) ),
			esc_html__( 'Field', 'gf-email-approvals' ),
			esc_html__( 'Approved value', 'gf-email-approvals' ),
			esc_html__( 'Rejected value', 'gf-email-approvals' ),
			$rows_markup,
			esc_html__( 'Add field', 'gf-email-approvals' )
		);
	}

	/**
	 * Returns the markup for one automatic mapping row.
	 *
	 * @param array $form    The current form.
	 * @param int   $row_key The mapping row key.
	 * @param array $mapping The mapping configuration.
	 *
	 * @return string
	 */
	private function get_decision_update_mapping_row_markup( $form, $row_key, $mapping ) {
		$selected_field_id = sanitize_text_field( (string) $this->array_value( $mapping, 'field', '' ) );
		$field             = $this->get_decision_update_field( $form, $selected_field_id, self::UPDATE_MODE_AUTOMATIC );
		$approved_markup   = $field ? $this->get_decision_value_setting_markup( $field, $form, self::STATUS_APPROVED, $row_key, $this->array_value( $mapping, 'approved_value' ) ) : '';
		$rejected_markup   = $field ? $this->get_decision_value_setting_markup( $field, $form, self::STATUS_REJECTED, $row_key, $this->array_value( $mapping, 'rejected_value' ) ) : '';

		return sprintf(
			'<div class="gf-email-approvals-mappings__row" data-gf-email-approvals-mapping-row data-row-key="%1$d"><div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--field"><label class="gf-email-approvals-mappings__mobile-label" for="%2$s">%3$s</label>%4$s</div><div class="gf-email-approvals-mappings__cell"><label class="gf-email-approvals-mappings__mobile-label">%5$s</label><div class="gf-email-approvals-mappings__slot" data-gf-email-approvals-mapping-slot="approved">%6$s</div></div><div class="gf-email-approvals-mappings__cell"><label class="gf-email-approvals-mappings__mobile-label">%7$s</label><div class="gf-email-approvals-mappings__slot" data-gf-email-approvals-mapping-slot="rejected">%8$s</div></div><div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--actions"><button type="button" class="gf-email-approvals-mappings__remove" data-gf-email-approvals-mapping-remove aria-label="%9$s"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">%9$s</span></button></div></div>',
			absint( $row_key ),
			esc_attr( sprintf( 'gf-email-approvals-mapping-field-%d', absint( $row_key ) ) ),
			esc_html__( 'Field', 'gf-email-approvals' ),
			$this->get_decision_update_mapping_field_select_markup( $form, $row_key, $selected_field_id ),
			esc_html__( 'Approved value', 'gf-email-approvals' ),
			$approved_markup,
			esc_html__( 'Rejected value', 'gf-email-approvals' ),
			$rejected_markup,
			esc_attr__( 'Remove field', 'gf-email-approvals' )
		);
	}

	/**
	 * Returns the field select markup for one automatic mapping row.
	 *
	 * @param array  $form              The current form.
	 * @param int    $row_key           The mapping row key.
	 * @param string $selected_field_id The selected field id.
	 *
	 * @return string
	 */
	private function get_decision_update_mapping_field_select_markup( $form, $row_key, $selected_field_id = '' ) {
		$options = array(
			sprintf( '<option value="">%s</option>', esc_html__( 'Select a field', 'gf-email-approvals' ) ),
		);

		foreach ( $this->get_decision_update_field_choices( $form, self::UPDATE_MODE_AUTOMATIC, false ) as $choice ) {
			$options[] = sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $choice['value'] ),
				(string) $selected_field_id === (string) $choice['value'] ? ' selected="selected"' : '',
				esc_html( $choice['label'] )
			);
		}

		return sprintf(
			'<select name="%1$s" id="%2$s" data-gf-email-approvals-mapping-field>%3$s</select>',
			esc_attr( sprintf( '_gform_setting_%s[%d][field]', self::NOTIFICATION_DECISION_UPDATE_MAPPINGS, absint( $row_key ) ) ),
			esc_attr( sprintf( 'gf-email-approvals-mapping-field-%d', absint( $row_key ) ) ),
			implode( '', $options )
		);
	}

	/**
	 * Returns the manual fields builder markup.
	 *
	 * @param array    $form      The current form.
	 * @param string[] $field_ids The configured manual field ids.
	 *
	 * @return string
	 */
	private function get_manual_decision_update_fields_setting_markup( $form, $field_ids ) {
		$rows_markup = '';

		foreach ( $field_ids as $index => $field_id ) {
			$rows_markup .= $this->get_manual_decision_update_field_row_markup( $form, $index + 1, $field_id );
		}

		$builder_classes = array( 'gf-email-approvals-mappings', 'gf-email-approvals-mappings--manual' );

		if ( '' !== $rows_markup ) {
			$builder_classes[] = 'gf-email-approvals-mappings--has-rows';
		}

		return sprintf(
			'<div class="%1$s" data-gf-email-approvals-manual-fields-builder><div class="gf-email-approvals-mappings__header" aria-hidden="true"><div class="gf-email-approvals-mappings__header-cell gf-email-approvals-mappings__header-cell--field">%2$s</div><div class="gf-email-approvals-mappings__header-cell gf-email-approvals-mappings__header-cell--actions"></div></div><div class="gf-email-approvals-mappings__rows" data-gf-email-approvals-manual-fields-rows>%3$s</div><button type="button" class="button button-secondary gf-email-approvals-mappings__add" data-gf-email-approvals-manual-fields-add><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span>%4$s</span></button></div>',
			esc_attr( implode( ' ', $builder_classes ) ),
			esc_html__( 'Field', 'gf-email-approvals' ),
			$rows_markup,
			esc_html__( 'Add field', 'gf-email-approvals' )
		);
	}

	/**
	 * Returns the markup for one manual field row.
	 *
	 * @param array  $form              The current form.
	 * @param int    $row_key           The builder row key.
	 * @param string $selected_field_id The selected field id.
	 *
	 * @return string
	 */
	private function get_manual_decision_update_field_row_markup( $form, $row_key, $selected_field_id = '' ) {
		return sprintf(
			'<div class="gf-email-approvals-mappings__row" data-gf-email-approvals-manual-field-row data-row-key="%1$d"><div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--field"><label class="gf-email-approvals-mappings__mobile-label" for="%2$s">%3$s</label>%4$s</div><div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--actions"><button type="button" class="gf-email-approvals-mappings__remove" data-gf-email-approvals-manual-field-remove aria-label="%5$s"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span class="screen-reader-text">%5$s</span></button></div></div>',
			absint( $row_key ),
			esc_attr( sprintf( 'gf-email-approvals-manual-field-%d', absint( $row_key ) ) ),
			esc_html__( 'Field', 'gf-email-approvals' ),
			$this->get_manual_decision_update_field_select_markup( $form, $row_key, $selected_field_id ),
			esc_attr__( 'Remove field', 'gf-email-approvals' )
		);
	}

	/**
	 * Returns the field select markup for one manual field row.
	 *
	 * @param array  $form              The current form.
	 * @param int    $row_key           The builder row key.
	 * @param string $selected_field_id The selected field id.
	 *
	 * @return string
	 */
	private function get_manual_decision_update_field_select_markup( $form, $row_key, $selected_field_id = '' ) {
		$options = array(
			sprintf( '<option value="">%s</option>', esc_html__( 'Select a field', 'gf-email-approvals' ) ),
		);

		foreach ( $this->get_decision_update_field_choices( $form, self::UPDATE_MODE_MANUAL, false ) as $choice ) {
			$options[] = sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $choice['value'] ),
				(string) $selected_field_id === (string) $choice['value'] ? ' selected="selected"' : '',
				esc_html( $choice['label'] )
			);
		}

		return sprintf(
			'<select name="%1$s" id="%2$s" data-gf-email-approvals-manual-field>%3$s</select>',
			esc_attr( sprintf( '_gform_setting_%s[%d]', self::NOTIFICATION_DECISION_UPDATE_FIELDS, absint( $row_key ) ) ),
			esc_attr( sprintf( 'gf-email-approvals-manual-field-%d', absint( $row_key ) ) ),
			implode( '', $options )
		);
	}

	/**
	 * Returns a cache-busting version string for a plugin asset.
	 *
	 * @param string $relative_path The asset path relative to the plugin root.
	 *
	 * @return int|string
	 */
	private function get_plugin_asset_version( $relative_path ) {
		$asset_path = GF_EMAIL_APPROVALS_PATH . ltrim( (string) $relative_path, '/\\' );

		if ( is_readable( $asset_path ) ) {
			$modified_time = filemtime( $asset_path );

			if ( false !== $modified_time ) {
				return $modified_time;
			}
		}

		return GF_EMAIL_APPROVALS_VERSION;
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
	public function enqueue_decision_update_settings_assets() {
		if ( ! class_exists( 'GFForms' ) || ! method_exists( 'GFForms', 'is_gravity_page' ) || ! GFForms::is_gravity_page() ) {
			return;
		}

		$form_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$form    = ( $form_id && class_exists( 'GFAPI' ) ) ? GFAPI::get_form( $form_id ) : array();
		$form    = is_array( $form ) ? $form : array();
		$style_version  = $this->get_plugin_asset_version( 'assets/css/admin-notification-settings.css' );
		$script_version = $this->get_plugin_asset_version( 'assets/js/admin-notification-settings.js' );

		wp_enqueue_style(
			'gf-email-approvals-admin-notification-settings',
			GF_EMAIL_APPROVALS_URL . 'assets/css/admin-notification-settings.css',
			array(),
			$style_version
		);

		wp_register_script(
			'gf-email-approvals-admin-notification-settings',
			GF_EMAIL_APPROVALS_URL . 'assets/js/admin-notification-settings.js',
			wp_script_is( 'gform_datepicker_init', 'registered' ) ? array( 'jquery', 'gform_datepicker_init' ) : array( 'jquery' ),
			$script_version,
			true
		);

		wp_add_inline_script(
			'gf-email-approvals-admin-notification-settings',
			'window.GFEmailApprovalsNotificationSettings = ' . wp_json_encode( $this->get_decision_update_settings_asset_config( $form ) ) . ';',
			'before'
		);

		wp_enqueue_script( 'gf-email-approvals-admin-notification-settings' );
	}

	/**
	 * Returns the localized data used by the notification settings admin script.
	 *
	 * @param array $form The current form.
	 *
	 * @return array<string, mixed>
	 */
	private function get_decision_update_settings_asset_config( $form ) {
		return array(
			'fieldConfig'        => $this->get_decision_update_field_config( $form ),
			'targetFieldChoices' => array(
				self::UPDATE_MODE_AUTOMATIC => $this->get_decision_update_field_choices( $form, self::UPDATE_MODE_AUTOMATIC, false ),
				self::UPDATE_MODE_MANUAL    => $this->get_decision_update_field_choices( $form, self::UPDATE_MODE_MANUAL, false ),
			),
			'visualSettingsUrl'  => add_query_arg(
				array(
					'page'    => 'gf_settings',
					'subview' => $this->_slug,
				),
				admin_url( 'admin.php' )
			),
			'fieldNames'         => array(
				'confirmationTitle' => self::NOTIFICATION_CONFIRMATION_TITLE,
				'mode'            => self::NOTIFICATION_UPDATE_MODE,
				'target'          => self::NOTIFICATION_DECISION_UPDATE_FIELD,
				'manualFields'    => self::NOTIFICATION_DECISION_UPDATE_FIELDS_FIELD,
				'manualFieldsSetting' => self::NOTIFICATION_DECISION_UPDATE_FIELDS,
				'mappings'        => self::NOTIFICATION_DECISION_UPDATE_MAPPINGS_FIELD,
				'mappingsSetting' => self::NOTIFICATION_DECISION_UPDATE_MAPPINGS,
			),
			'automaticMode'      => self::UPDATE_MODE_AUTOMATIC,
			'manualMode'         => self::UPDATE_MODE_MANUAL,
			'templateIds'        => array(
				'approved' => (string) $this->get_decision_update_virtual_field_id( self::STATUS_APPROVED, 0 ),
				'rejected' => (string) $this->get_decision_update_virtual_field_id( self::STATUS_REJECTED, 0 ),
			),
			'virtualIdBases'     => array(
				'approved' => self::DECISION_VALUE_VIRTUAL_FIELD_ID_APPROVED,
				'rejected' => self::DECISION_VALUE_VIRTUAL_FIELD_ID_REJECTED,
			),
			'strings'            => array(
				'doNotUpdateField'    => __( 'Do not update any field', 'gf-email-approvals' ),
				'selectField'         => __( 'Select a field', 'gf-email-approvals' ),
				'field'               => __( 'Field', 'gf-email-approvals' ),
				'approvedValue'       => __( 'Approved value', 'gf-email-approvals' ),
				'rejectedValue'       => __( 'Rejected value', 'gf-email-approvals' ),
				'addField'            => __( 'Add field', 'gf-email-approvals' ),
				'removeField'         => __( 'Remove field', 'gf-email-approvals' ),
				'visualSettingsTooltip' => __( 'Approval Page Visual Settings', 'gf-email-approvals' ),
			),
		);
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
		wp_enqueue_media();
		$style_version  = $this->get_plugin_asset_version( 'assets/css/admin-appearance-builder.css' );
		$script_version = $this->get_plugin_asset_version( 'assets/js/admin-appearance-builder.js' );

		wp_enqueue_style(
			'gf-email-approvals-admin-appearance-builder',
			GF_EMAIL_APPROVALS_URL . 'assets/css/admin-appearance-builder.css',
			array( 'wp-color-picker' ),
			$style_version
		);

		wp_register_script(
			'gf-email-approvals-admin-appearance-builder',
			GF_EMAIL_APPROVALS_URL . 'assets/js/admin-appearance-builder.js',
			array( 'jquery', 'wp-color-picker' ),
			$script_version,
			true
		);

		wp_add_inline_script(
			'gf-email-approvals-admin-appearance-builder',
			'window.GFEmailApprovalsAppearanceSettings = ' . wp_json_encode( $this->get_appearance_builder_asset_config() ) . ';',
			'before'
		);

		wp_enqueue_script( 'gf-email-approvals-admin-appearance-builder' );
	}

	/**
	 * Returns the localized settings used by the appearance builder admin script.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_appearance_builder_asset_config() {
		return $this->get_appearance_settings_helper()->get_appearance_builder_asset_config();
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
	 * Returns the appearance settings helper.
	 *
	 * @return GFEmailApprovalsAppearanceSettingsHelper
	 */
	private function get_appearance_settings_helper() {
		if ( null === $this->appearance_settings_helper ) {
			$this->appearance_settings_helper = new GFEmailApprovalsAppearanceSettingsHelper(
				array(
					'get_plugin_setting' => function( $name ) {
						return $this->get_plugin_setting( $name );
					},
					'single_setting_row' => function( $field ) {
						$this->single_setting_row( $field );
					},
					'get_public_page_theme_defaults' => function() {
						return $this->get_public_page_theme_defaults();
					},
					'get_public_page_theme_settings' => function() {
						return $this->get_public_page_theme_settings();
					},
					'get_public_page_preview_style_variables' => function( $theme ) {
						return $this->get_public_page_preview_style_variables( $theme );
					},
				)
			);
		}

		return $this->appearance_settings_helper;
	}

	/**
	 * Returns the public page presentation helper.
	 *
	 * @return GFEmailApprovalsPublicPagePresentationHelper
	 */
	private function get_public_page_presentation_helper() {
		if ( null === $this->public_page_presentation_helper ) {
			$this->public_page_presentation_helper = new GFEmailApprovalsPublicPagePresentationHelper(
				array(
					'get_plugin_setting' => function( $name ) {
						return $this->get_plugin_setting( $name );
					},
				)
			);
		}

		return $this->public_page_presentation_helper;
	}

	/**
	 * Renders the custom image uploader setting field.
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo  Whether to echo the output or return it.
	 *
	 * @return string|void
	 */
	public function settings_approval_image( $field, $echo = true ) {
		return $this->get_appearance_settings_helper()->settings_approval_image( $field, $echo );
	}

	/**
	 * Renders the custom alignment control field.
	 *
	 * @param array $field The field properties.
	 * @param bool  $echo  Whether to echo the output or return it.
	 *
	 * @return string|void
	 */
	public function settings_approval_alignment( $field, $echo = true ) {
		return $this->get_appearance_settings_helper()->settings_approval_alignment( $field, $echo );
	}

	/**
	 * Renders a dimension field with range slider, numeric input and unit selector.
	 *
	 * @param array $field The field configuration.
	 * @param bool  $echo  Whether to print the markup immediately.
	 *
	 * @return string|void
	 */
	public function settings_approval_dimension( $field, $echo = true ) {
		return $this->get_appearance_settings_helper()->settings_approval_dimension( $field, $echo );
	}

	/**
	 * Renders the approval page settings inside collapsible accordions.
	 *
	 * @param array $field The field configuration.
	 * @param bool  $echo  Whether to print the markup immediately.
	 *
	 * @return string|void
	 */
	public function settings_approval_page_settings_accordions( $field, $echo = true ) {
		return $this->get_appearance_settings_helper()->settings_approval_page_settings_accordions( $field, $echo );
	}

	/**
	 * Renders the live preview widget for the approval page appearance.
	 *
	 * @param array $field The field configuration.
	 * @param bool  $echo  Whether to print the markup immediately.
	 *
	 * @return string|void
	 */
	public function settings_approval_page_preview( $field, $echo = true ) {
		return $this->get_appearance_settings_helper()->settings_approval_page_preview( $field, $echo );
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
	 * Returns the inline style used by approval action buttons in HTML emails.
	 *
	 * @param string $background_color The button background color.
	 *
	 * @return string
	 */
	private function get_approval_action_button_inline_style( $background_color ) {
		return sprintf(
			'display:inline-block;padding:8px 8px;background:%1$s;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;',
			(string) $background_color
		);
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
			'<a href="%1$s" style="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr( $this->get_approval_action_button_inline_style( (string) $this->array_value( $action_data, 'color' ) ) ),
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
		$validation   = $this->validate_public_manual_decision_update_submission( $form, $entry, $notification );

		if ( isset( $validation['is_valid'] ) && ! $validation['is_valid'] ) {
			$this->render_public_confirmation_page(
				$record,
				$token,
				$notification,
				$form,
				$entry,
				isset( $validation['manual_render_state'] ) && is_array( $validation['manual_render_state'] ) ? $validation['manual_render_state'] : array()
			);
		}

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
	 * Validates manual approval-page fields before the decision is processed.
	 *
	 * @param array $form         The current form.
	 * @param array $entry        The current entry.
	 * @param array $notification The current notification.
	 *
	 * @return array<string, mixed>
	 */
	private function validate_public_manual_decision_update_submission( $form, $entry, $notification ) {
		unset( $entry );

		$result   = array( 'is_valid' => true );
		$settings = $this->get_notification_decision_update_settings( $notification );

		if ( self::UPDATE_MODE_MANUAL !== (string) $settings[ self::NOTIFICATION_UPDATE_MODE ] ) {
			return $result;
		}

		if ( ! class_exists( 'GFFormDisplay' ) || ! method_exists( 'GFFormDisplay', 'validate_field' ) || ! class_exists( 'GFFormsModel' ) || ! method_exists( 'GFFormsModel', 'get_field_value' ) ) {
			return $result;
		}

		$field_ids = $this->get_notification_manual_decision_update_fields( $form, $settings );

		if ( empty( $field_ids ) ) {
			return $result;
		}

		$render_form          = $this->get_public_gravity_forms_themed_form( $form );
		$form_id              = absint( $this->array_value( $render_form, 'id' ) );
		$submit_flag_name     = 'is_submit_' . $form_id;
		$submit_flag_set      = array_key_exists( $submit_flag_name, $_POST );
		$previous_submit      = $submit_flag_set ? $_POST[ $submit_flag_name ] : null;
		$virtual_fields       = array();
		$display_values       = array();
		$has_validation_error = false;

		$_POST[ $submit_flag_name ] = '1';

		foreach ( $field_ids as $index => $field_id ) {
			$row_key      = $index + 1;
			$source_field = $this->get_decision_update_field( $form, (string) $field_id, self::UPDATE_MODE_MANUAL );

			if ( ! $source_field ) {
				continue;
			}

			$validation_field = $this->get_decision_update_virtual_field( $source_field, $render_form, 'manual', $row_key, true );

			if ( ! $validation_field ) {
				continue;
			}

			$validation_field->_is_entry_detail = false;
			$validation_result                  = $this->validate_public_manual_decision_update_field_submission( $validation_field, $render_form );
			$display_values[ $row_key ]        = array_key_exists( 'value', $validation_result ) ? $validation_result['value'] : null;

			$virtual_field = $this->get_decision_update_virtual_field( $source_field, $render_form, 'manual', $row_key, true );

			if ( $virtual_field ) {
				$virtual_field->failed_validation  = ! empty( $validation_field->failed_validation );
				$virtual_field->validation_message = (string) $validation_field->validation_message;
				$virtual_fields[ $row_key ]        = $virtual_field;
			}

			if ( ! empty( $validation_field->failed_validation ) ) {
				$has_validation_error = true;
			}
		}

		if ( $submit_flag_set ) {
			$_POST[ $submit_flag_name ] = $previous_submit;
		} else {
			unset( $_POST[ $submit_flag_name ] );
		}

		if ( ! $has_validation_error ) {
			return $result;
		}

		$result['is_valid']            = false;
		$result['manual_render_state'] = array(
			'virtual_fields' => $virtual_fields,
			'display_values' => $display_values,
		);

		return $result;
	}

	/**
	 * Validates one public manual decision update field submission.
	 *
	 * @param object $field The validation field clone.
	 * @param array  $form  The current render form.
	 *
	 * @return array<string, mixed>
	 */
	private function validate_public_manual_decision_update_field_submission( $field, $form ) {
		$result = array(
			'is_valid' => true,
			'value'    => null,
		);

		if ( ! is_object( $field ) || ! is_array( $form ) || ! class_exists( 'GFFormsModel' ) || ! method_exists( 'GFFormsModel', 'get_field_value' ) ) {
			return $result;
		}

		$field->failed_validation  = false;
		$field->validation_message = '';
		$result['value']           = GFFormsModel::get_field_value( $field, array(), true );

		if ( class_exists( 'GFFormDisplay' ) && method_exists( 'GFFormDisplay', 'validate_field' ) ) {
			GFFormDisplay::validate_field( $field, $form, 'form-submit' );
		}

		if ( ! empty( $field->isRequired ) && empty( $field->failed_validation ) && method_exists( $field, 'is_value_submission_empty' ) && $field->is_value_submission_empty( absint( $this->array_value( $form, 'id' ) ) ) ) {
			if ( method_exists( $field, 'set_required_error' ) ) {
				$field->set_required_error( $result['value'] );
			} else {
				$field->failed_validation  = true;
				$field->validation_message = esc_html__( 'This field is required.', 'gravityforms' );
			}
		}

		$result['is_valid'] = empty( $field->failed_validation );

		return $result;
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
	private function render_public_confirmation_page( $record, $token, $notification = array(), $form = array(), $entry = array(), $manual_render_state = array() ) {
		$settings          = $this->get_notification_page_settings( $notification, $form, $entry );
		$decision_settings = $this->get_notification_decision_update_settings( $notification );
		$theme             = $this->get_public_page_theme_settings();
		$update_mode       = (string) $decision_settings[ self::NOTIFICATION_UPDATE_MODE ];
		$manual_fields     = self::UPDATE_MODE_MANUAL === $update_mode
			? $this->get_notification_manual_decision_update_fields( $form, $decision_settings )
			: array();
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
		$public_form         = $this->get_public_gravity_forms_themed_form( $form );
		$manual_render_parts = self::UPDATE_MODE_MANUAL === $update_mode
			? $this->get_public_manual_update_fields_render_context( $public_form, $entry, $manual_fields, $manual_render_state )
			: array(
				'markup'        => '',
				'head'          => '',
				'footer'        => '',
				'hidden_inputs' => '',
			);
		$manual_update_html = isset( $manual_render_parts['markup'] ) ? (string) $manual_render_parts['markup'] : '';
		$manual_hidden_inputs = isset( $manual_render_parts['hidden_inputs'] ) ? (string) $manual_render_parts['hidden_inputs'] : '';
		$button_style       = $this->get_public_page_button_style( $is_approve ? self::STATUS_APPROVED : self::STATUS_REJECTED, $theme, true );
		$form_id            = absint( $this->array_value( $form, 'id' ) );
		$form_render_config = $this->get_public_gravity_forms_render_config( $public_form );
		$form_wrapper_open  = '';
		$form_wrapper_close = '';
		$form_attributes    = sprintf(
			' class="gf-email-approvals-public__form"%s',
			$form_id > 0 ? ' id="gform_' . $form_id . '"' : ''
		);

		if ( '' !== $manual_update_html ) {
			$form_wrapper_open  = sprintf(
				'<div class="%1$s" data-form-theme="%2$s" data-form-index="%3$s"%4$s>',
				esc_attr( $form_render_config['wrapper_classes'] ),
				esc_attr( $form_render_config['theme_slug'] ),
				esc_attr( (string) $this->array_value( $form, 'page_instance', 0 ) ),
				$form_id > 0 ? ' id="gform_wrapper_' . $form_id . '"' : ''
			);
			$form_wrapper_close = '</div>';
		}

		$this->render_public_page(
			$title,
			sprintf(
				'%1$s%10$s<form method="post" action="%2$s"%11$s><input type="hidden" name="%3$s" value="%4$s" /><input type="hidden" name="%5$s" value="%6$s" />%7$s%13$s<p><button type="submit" style="%8$s">%9$s</button></p></form>%12$s',
				$message_markup,
				esc_url( $action_url ),
				esc_attr( self::QUERY_TOKEN ),
				esc_attr( $token ),
				esc_attr( self::QUERY_ACTION ),
				esc_attr( self::PUBLIC_ACTION_CONFIRM ),
				$manual_update_html,
				esc_attr( $button_style ),
				esc_html( $button_label ),
				$form_wrapper_open,
				$form_attributes,
				$form_wrapper_close,
				$manual_hidden_inputs
			),
			isset( $manual_render_parts['head'] ) ? (string) $manual_render_parts['head'] : '',
			isset( $manual_render_parts['footer'] ) ? (string) $manual_render_parts['footer'] : '',
			'' !== $manual_update_html
		);
	}

	/**
	 * Returns the public native Gravity Forms field markup used for one manual field.
	 *
	 * @param object $field        The virtual field to render.
	 * @param array  $form         The render form containing the virtual fields.
	 * @param object $source_field The original configured field.
	 * @param array  $entry        The current entry.
	 *
	 * @return string
	 */
	private function get_public_manual_update_field_markup( $field, $form, $source_field, $entry, $display_value = null, $has_display_value = false ) {
		if ( ! is_object( $field ) || ! is_array( $form ) || ! class_exists( 'GFFormDisplay' ) || ! method_exists( 'GFFormDisplay', 'get_field' ) ) {
			return '';
		}

		$value = $has_display_value ? $display_value : $this->get_entry_field_value_for_update( $entry, $source_field );

		return GFFormDisplay::get_field( $field, $value, false, $form );
	}

	/**
	 * Returns the rendered manual fields and any required Gravity Forms assets.
	 *
	 * @param array    $form      The current form.
	 * @param array    $entry     The current entry.
	 * @param string[] $field_ids The configured manual field ids.
	 *
	 * @return array<string, string>
	 */
	private function get_public_manual_update_fields_render_context( $form, $entry, $field_ids, $render_state = array() ) {
		$context = array(
			'markup'        => '',
			'head'          => '',
			'footer'        => '',
			'hidden_inputs' => '',
		);

		if ( empty( $field_ids ) || ! class_exists( 'GFFormDisplay' ) ) {
			return $context;
		}

		$render_form           = $this->get_public_gravity_forms_themed_form( $form );
		$render_form['fields'] = array();
		$rendered_fields       = array();
		$virtual_fields        = isset( $render_state['virtual_fields'] ) && is_array( $render_state['virtual_fields'] ) ? $render_state['virtual_fields'] : array();
		$display_values        = isset( $render_state['display_values'] ) && is_array( $render_state['display_values'] ) ? $render_state['display_values'] : array();

		foreach ( $field_ids as $index => $field_id ) {
			$row_key      = $index + 1;
			$source_field = $this->get_decision_update_field( $form, (string) $field_id, self::UPDATE_MODE_MANUAL );

			if ( ! $source_field ) {
				continue;
			}

			$virtual_field = isset( $virtual_fields[ $row_key ] ) && is_object( $virtual_fields[ $row_key ] )
				? $virtual_fields[ $row_key ]
				: $this->get_decision_update_virtual_field( $source_field, $form, 'manual', $row_key, true );

			if ( ! $virtual_field ) {
				continue;
			}

			$render_form['fields'][] = $virtual_field;
			$has_display_value       = array_key_exists( $row_key, $display_values );
			$rendered_field          = $this->get_public_manual_update_field_markup( $virtual_field, $render_form, $source_field, $entry );

			if ( $has_display_value ) {
				$rendered_field = $this->get_public_manual_update_field_markup( $virtual_field, $render_form, $source_field, $entry, $display_values[ $row_key ], true );
			}

			if ( '' !== trim( $rendered_field ) ) {
				$rendered_fields[] = $rendered_field;
			}
		}

		if ( empty( $rendered_fields ) ) {
			return $context;
		}

		$form_render_config = $this->get_public_gravity_forms_render_config( $render_form );
		$context['head']   .= $this->get_public_gravity_forms_style_markup( $render_form );

		if ( method_exists( 'GFFormDisplay', 'register_form_init_scripts' ) ) {
			GFFormDisplay::register_form_init_scripts( $render_form );
		}

		if ( method_exists( 'GFFormDisplay', 'print_form_scripts' ) ) {
			ob_start();
			GFFormDisplay::print_form_scripts( $render_form, false );
			$context['head'] .= (string) ob_get_clean();
		}

		if ( method_exists( 'GFFormDisplay', 'get_form_init_scripts' ) ) {
			$context['footer'] .= GFFormDisplay::get_form_init_scripts( $render_form );
		}

		$render_form_id = absint( $this->array_value( $render_form, 'id' ) );

		if ( $render_form_id > 0 && method_exists( 'GFFormDisplay', 'post_render_script' ) && class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'get_inline_script_tag' ) ) {
			$context['footer'] .= GFCommon::get_inline_script_tag(
				'gform.initializeOnLoaded( function() {' . GFFormDisplay::post_render_script( $render_form_id, 1 ) . '} );'
			);
		}

		$context['markup'] = sprintf(
			'<div class="gf-email-approvals-public__gf-wrapper" style="margin:28px 0 20px 0;"><div class="gform_body gform-body"><div class="%1$s"%2$s>%3$s</div></div></div>',
			esc_attr( $form_render_config['field_list_classes'] ),
			$render_form_id > 0 ? ' id="gform_fields_' . $render_form_id . '"' : '',
			implode( '', $rendered_fields )
		);
		$context['hidden_inputs'] = $this->get_public_manual_update_hidden_inputs_markup( $render_form );

		return $context;
	}

	/**
	 * Returns the hidden Gravity Forms inputs required for standalone manual choice validation.
	 *
	 * @param array $form The current render form.
	 *
	 * @return string
	 */
	private function get_public_manual_update_hidden_inputs_markup( $form ) {
		$form_id = absint( $this->array_value( $form, 'id' ) );

		if ( $form_id <= 0 ) {
			return '';
		}

		$markup = sprintf(
			'<input type="hidden" class="gform_hidden" name="is_submit_%1$d" value="1" />',
			$form_id
		);

		if ( class_exists( 'GFFormDisplay' ) && method_exists( 'GFFormDisplay', 'get_state' ) ) {
			$markup .= sprintf(
				'<input type="hidden" class="gform_hidden" name="state_%1$d" value="%2$s" />',
				$form_id,
				esc_attr( GFFormDisplay::get_state( $form, array() ) )
			);
		}

		return $markup;
	}

	/**
	 * Returns the form data used to render standalone public Gravity Forms controls.
	 *
	 * @param array $form The current form.
	 *
	 * @return array
	 */
	private function get_public_gravity_forms_themed_form( $form ) {
		$themed_form          = is_array( $form ) ? $form : array();
		$themed_form['theme'] = $this->get_public_gravity_forms_theme_slug( $themed_form );

		return $themed_form;
	}

	/**
	 * Returns the Gravity Forms theme slug used on the standalone approval page.
	 *
	 * @param array $form The current render form.
	 *
	 * @return string
	 */
	private function get_public_gravity_forms_theme_slug( $form ) {
		$theme_slug = is_array( $form ) ? (string) $this->array_value( $form, 'theme', '' ) : '';

		if ( in_array( $theme_slug, array( 'orbital', 'gravity-theme', 'legacy' ), true ) ) {
			return $theme_slug;
		}

		return 'orbital';
	}

	/**
	 * Returns the Gravity Forms wrapper and field list classes for public standalone rendering.
	 *
	 * @param array $form The current render form.
	 *
	 * @return array<string, string>
	 */
	private function get_public_gravity_forms_render_config( $form ) {
		$theme_slug       = $this->get_public_gravity_forms_theme_slug( $form );
		$browser_class    = class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'get_browser_class' ) ? trim( (string) GFCommon::get_browser_class() ) : '';
		$field_list_class = class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'get_ul_classes' ) ? (string) GFCommon::get_ul_classes( $form ) : 'gform_fields top_label form_sublabel_below description_below validation_below';
		$wrapper_classes  = trim( $browser_class . ' gform_wrapper gravity-theme gform-theme--no-framework' );

		if ( 'orbital' === $theme_slug ) {
			$wrapper_classes = trim( $browser_class . ' gform_wrapper gform-theme gform-theme--foundation gform-theme--framework gform-theme--orbital' );
		} elseif ( 'legacy' === $theme_slug ) {
			$wrapper_classes = trim( $browser_class . ' gform_wrapper gform_legacy_markup_wrapper gform-theme--no-framework' );
		}

		return array(
			'theme_slug'         => '' !== $theme_slug ? $theme_slug : 'gravity-theme',
			'wrapper_classes'    => $wrapper_classes,
			'field_list_classes' => $field_list_class,
		);
	}

	/**
	 * Returns the Gravity Forms stylesheet markup required by the standalone public page.
	 *
	 * @param array $form The current render form.
	 *
	 * @return string
	 */
	private function get_public_gravity_forms_style_markup( $form ) {
		if ( ! is_array( $form ) || empty( $form['id'] ) ) {
			return '';
		}

		$this->register_public_gravity_forms_theme_styles();

		if ( function_exists( 'gf_do_action' ) ) {
			gf_do_action( array( 'gform_enqueue_scripts', absint( $this->array_value( $form, 'id' ) ) ), $form, false );
		} else {
			do_action( 'gform_enqueue_scripts', $form, false );
		}

		$theme_style_handles = $this->get_public_gravity_forms_theme_style_handles( $form );

		foreach ( $theme_style_handles as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
			}
		}

		global $wp_styles;

		$theme_link_markup = $this->get_public_gravity_forms_theme_stylesheet_link_markup( $form );
		$printed_handles   = array_keys( $this->get_public_gravity_forms_theme_stylesheet_urls( $form ) );

		if ( ! is_object( $wp_styles ) ) {
			return $theme_link_markup;
		}

		$style_handles = array_values(
			array_diff(
				array_unique(
					array_merge(
						$theme_style_handles,
						array_filter( $wp_styles->queue, array( $this, 'is_public_gravity_forms_style_handle' ) )
					)
				),
				$printed_handles
			)
		);

		if ( empty( $style_handles ) ) {
			return $theme_link_markup;
		}

		ob_start();
		wp_print_styles( $style_handles );

		return $theme_link_markup . (string) ob_get_clean();
	}

	/**
	 * Returns direct stylesheet urls for the standalone Gravity Forms theme.
	 *
	 * @param array $form The current render form.
	 *
	 * @return array<string, string>
	 */
	private function get_public_gravity_forms_theme_stylesheet_urls( $form ) {
		if ( ! class_exists( 'GFCommon' ) || ! class_exists( 'GFForms' ) ) {
			return array();
		}

		$theme_slug = $this->get_public_gravity_forms_theme_slug( $form );
		$base_url   = GFCommon::get_base_url() . '/assets/css/dist';
		$dev_min    = defined( 'GF_SCRIPT_DEBUG' ) && GF_SCRIPT_DEBUG ? '' : '.min';
		$urls       = array();

		if ( 'orbital' === $theme_slug ) {
			$urls = array(
				'gravity_forms_theme_reset'      => "{$base_url}/gravity-forms-theme-reset{$dev_min}.css",
				'gravity_forms_theme_foundation' => "{$base_url}/gravity-forms-theme-foundation{$dev_min}.css",
				'gravity_forms_theme_framework'  => "{$base_url}/gravity-forms-theme-framework{$dev_min}.css",
				'gravity_forms_orbital_theme'    => "{$base_url}/gravity-forms-orbital-theme{$dev_min}.css",
			);
		}

		return $urls;
	}

	/**
	 * Returns direct stylesheet link markup for the standalone Gravity Forms theme.
	 *
	 * @param array $form The current render form.
	 *
	 * @return string
	 */
	private function get_public_gravity_forms_theme_stylesheet_link_markup( $form ) {
		if ( ! class_exists( 'GFForms' ) ) {
			return '';
		}

		$markup = array();

		foreach ( $this->get_public_gravity_forms_theme_stylesheet_urls( $form ) as $handle => $url ) {
			$href     = add_query_arg( 'ver', GFForms::$version, $url );
			$markup[] = sprintf(
				'<link rel="stylesheet" id="%1$s-css" href="%2$s" media="all" />',
				esc_attr( $handle ),
				esc_url( $href )
			);
		}

		return implode( '', $markup );
	}

	/**
	 * Registers the Gravity Forms public theme styles used by the standalone approval page.
	 *
	 * @return void
	 */
	private function register_public_gravity_forms_theme_styles() {
		if ( ! class_exists( 'GFCommon' ) || ! class_exists( 'GFForms' ) ) {
			return;
		}

		$base_url = GFCommon::get_base_url();
		$version  = GFForms::$version;
		$dev_min  = defined( 'GF_SCRIPT_DEBUG' ) && GF_SCRIPT_DEBUG ? '' : '.min';

		if ( ! wp_style_is( 'gravity_forms_theme_reset', 'registered' ) ) {
			wp_register_style( 'gravity_forms_theme_reset', "{$base_url}/assets/css/dist/gravity-forms-theme-reset{$dev_min}.css", array(), $version );
		}

		if ( ! wp_style_is( 'gravity_forms_theme_foundation', 'registered' ) ) {
			wp_register_style( 'gravity_forms_theme_foundation', "{$base_url}/assets/css/dist/gravity-forms-theme-foundation{$dev_min}.css", array(), $version );
		}

		if ( ! wp_style_is( 'gravity_forms_theme_framework', 'registered' ) ) {
			wp_register_style(
				'gravity_forms_theme_framework',
				"{$base_url}/assets/css/dist/gravity-forms-theme-framework{$dev_min}.css",
				array( 'gravity_forms_theme_reset', 'gravity_forms_theme_foundation' ),
				$version
			);
		}

		if ( ! wp_style_is( 'gravity_forms_orbital_theme', 'registered' ) ) {
			wp_register_style(
				'gravity_forms_orbital_theme',
				"{$base_url}/assets/css/dist/gravity-forms-orbital-theme{$dev_min}.css",
				array( 'gravity_forms_theme_framework' ),
				$version
			);
		}
	}

	/**
	 * Returns the core Gravity Forms theme style handles needed on the standalone approval page.
	 *
	 * @param array $form The current render form.
	 *
	 * @return string[]
	 */
	private function get_public_gravity_forms_theme_style_handles( $form ) {
		if ( 'orbital' === $this->get_public_gravity_forms_theme_slug( $form ) ) {
			return array(
				'gravity_forms_theme_reset',
				'gravity_forms_theme_foundation',
				'gravity_forms_theme_framework',
				'gravity_forms_orbital_theme',
			);
		}

		return array();
	}

	/**
	 * Returns whether a queued style handle belongs to Gravity Forms public rendering.
	 *
	 * @param string $handle The queued stylesheet handle.
	 *
	 * @return bool
	 */
	private function is_public_gravity_forms_style_handle( $handle ) {
		return is_string( $handle )
			&& (
				'dashicons' === $handle
				|| 0 === strpos( $handle, 'gravity_forms_' )
				|| 0 === strpos( $handle, 'gforms_' )
				|| 0 === strpos( $handle, 'gform_' )
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
			self::NOTIFICATION_UPDATE_MODE             => '',
			self::NOTIFICATION_DECISION_UPDATE_FIELD   => '',
			self::NOTIFICATION_DECISION_UPDATE_FIELDS  => array(),
			self::NOTIFICATION_DECISION_UPDATE_MAPPINGS => array(),
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

			if ( self::NOTIFICATION_DECISION_UPDATE_MAPPINGS === $setting_name ) {
				if ( is_array( $value ) ) {
					foreach ( $value as $mapping ) {
						if ( ! is_array( $mapping ) ) {
							continue;
						}

						$settings[ $setting_name ][] = array(
							'field'          => sanitize_text_field( (string) $this->array_value( $mapping, 'field', '' ) ),
							'approved_value' => $this->array_value( $mapping, 'approved_value', '' ),
							'rejected_value' => $this->array_value( $mapping, 'rejected_value', '' ),
						);
					}
				}

				continue;
			}

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

		if ( empty( $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] ) && '' !== (string) $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] ) {
			$settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] = array( (string) $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] );
		}

		if ( '' === $settings[ self::NOTIFICATION_UPDATE_MODE ] ) {
			if ( ! empty( $settings[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] ) ) {
				$settings[ self::NOTIFICATION_UPDATE_MODE ] = self::UPDATE_MODE_AUTOMATIC;
			} elseif ( ! empty( $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ] ) || '' !== (string) $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] ) {
				$settings[ self::NOTIFICATION_UPDATE_MODE ] = self::UPDATE_MODE_MANUAL;
			}
		}

		return $settings;
	}

	/**
	 * Returns the effective decision update settings for the current screen render.
	 *
	 * The automatic mappings builder is custom HTML, so Gravity Forms does not
	 * automatically rehydrate it from posted values on the save response.
	 * Prefer the submitted values when available so the refreshed screen matches
	 * what was just saved.
	 *
	 * @param array $notification The notification object.
	 * @param array $form         The current form object.
	 *
	 * @return array<string, mixed>
	 */
	private function get_current_notification_decision_update_settings( $notification, $form ) {
		$settings = $this->get_notification_decision_update_settings( $notification );

		if (
			! $this->has_posted_notification_setting( self::NOTIFICATION_UPDATE_MODE )
			&& ! $this->has_posted_notification_setting( self::NOTIFICATION_DECISION_UPDATE_FIELDS )
			&& ! $this->has_posted_notification_setting( self::NOTIFICATION_DECISION_UPDATE_FIELD )
			&& ! $this->has_posted_notification_setting( self::NOTIFICATION_DECISION_UPDATE_MAPPINGS )
		) {
			return $settings;
		}

		$update_mode = sanitize_key( (string) $this->get_posted_notification_setting( self::NOTIFICATION_UPDATE_MODE, '' ) );

		if ( ! in_array( $update_mode, array( self::UPDATE_MODE_AUTOMATIC, self::UPDATE_MODE_MANUAL ), true ) ) {
			$settings[ self::NOTIFICATION_UPDATE_MODE ]              = '';
			$settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ]    = '';
			$settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ]   = array();
			$settings[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] = array();

			return $settings;
		}

		$settings[ self::NOTIFICATION_UPDATE_MODE ] = $update_mode;

		if ( self::UPDATE_MODE_MANUAL === $update_mode ) {
			$settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ]    = '';
			$settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ]   = $this->sanitize_posted_manual_decision_update_fields( $form );
			$settings[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] = array();

			return $settings;
		}

		$settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ]    = '';
		$settings[ self::NOTIFICATION_DECISION_UPDATE_FIELDS ]   = array();
		$settings[ self::NOTIFICATION_DECISION_UPDATE_MAPPINGS ] = $this->sanitize_posted_decision_update_mappings( $form );

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
				'kind'             => $this->get_decision_update_field_kind( $field ),
				'choices'          => $this->get_supported_field_choice_options( $field ),
				'approvedTemplate' => $this->get_decision_value_setting_markup( $field, $form, self::STATUS_APPROVED, 0 ),
				'rejectedTemplate' => $this->get_decision_value_setting_markup( $field, $form, self::STATUS_REJECTED, 0 ),
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

		if ( '' === $update_mode ) {
			return array(
				'success' => true,
				'entry'   => $entry,
				'changes' => $changes,
			);
		}

		if ( self::UPDATE_MODE_MANUAL === $update_mode ) {
			$manual_fields = $this->get_notification_manual_decision_update_fields( $form, $settings );

			if ( empty( $manual_fields ) ) {
				if ( '' !== (string) $settings[ self::NOTIFICATION_DECISION_UPDATE_FIELD ] ) {
					$this->log_error( __METHOD__ . '(): configured manual decision update target fields are no longer available; skipping field updates.' );
				}

				return array(
					'success' => true,
					'entry'   => $entry,
					'changes' => $changes,
				);
			}

			foreach ( $manual_fields as $index => $field_id ) {
				$target_field = $this->get_decision_update_field( $form, (string) $field_id, $update_mode );

				if ( ! $target_field ) {
					continue;
				}

				$new_value = $this->get_manual_decision_update_value_from_request( $form, $target_field, $index + 1 );

				if ( null === $new_value ) {
					continue;
				}

				$old_value = $this->get_entry_field_value_for_update( $entry, $target_field );

				if ( $this->decision_update_values_equal( $target_field, $old_value, $new_value ) ) {
					continue;
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
			}

			return array(
				'success' => true,
				'entry'   => $entry,
				'changes' => $changes,
			);
		}

		foreach ( $this->get_notification_decision_update_mappings( $form, $settings ) as $mapping ) {
			$target_field = $this->get_decision_update_field( $form, (string) $this->array_value( $mapping, 'field', '' ), self::UPDATE_MODE_AUTOMATIC );

			if ( ! $target_field ) {
				continue;
			}

			$new_value = $this->get_configured_decision_update_mapping_value( $target_field, $mapping, $status, $form, $entry );

			if ( null === $new_value ) {
				continue;
			}

			$old_value = $this->get_entry_field_value_for_update( $entry, $target_field );

			if ( $this->decision_update_values_equal( $target_field, $old_value, $new_value ) ) {
				continue;
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
		}

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
	private function get_configured_decision_update_mapping_value( $field, $mapping, $status, $form, $entry ) {
		$kind = $this->get_decision_update_field_kind( $field );
		$value = self::STATUS_APPROVED === $status ? $this->array_value( $mapping, 'approved_value' ) : $this->array_value( $mapping, 'rejected_value' );

		if ( 'text' === $kind ) {
			$template = is_scalar( $value ) ? (string) $value : '';

			if ( '' === trim( $template ) ) {
				return null;
			}

			return $this->replace_merge_tags_in_text( $template, $form, $entry );
		}

		if ( 'single' === $kind ) {
			$value = is_scalar( $value ) ? (string) $value : '';

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

		$values = is_array( $value ) ? array_values( array_filter( array_map( 'strval', $value ), 'strlen' ) ) : array();

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
	 * @param array  $form    The current form.
	 * @param object $field   The configured target field.
	 * @param int    $row_key The manual field row key.
	 *
	 * @return array|string|null
	 */
	private function get_manual_decision_update_value_from_request( $form, $field, $row_key = 0 ) {
		return $this->get_posted_decision_update_value( $form, $field, 'manual', $row_key );
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
				continue;
			}

			if ( $this->is_notification_page_field_overridden_as_empty( $notification, $setting_name ) ) {
				$settings[ $setting_name ] = '';
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
		return $this->get_public_page_presentation_helper()->get_public_page_theme_defaults();
	}

	/**
	 * Returns the sanitized theme used on the public approval pages.
	 *
	 * @return array<string, int|string>
	 */
	private function get_public_page_theme_settings() {
		return $this->get_public_page_presentation_helper()->get_public_page_theme_settings();
	}

	/**
	 * Sanitizes a numeric theme dimension.
	 *
	 * @param mixed     $value   Raw dimension value.
	 * @param int|float $min     Minimum accepted value.
	 * @param int|float $max     Maximum accepted value.
	 * @param int|float $default Fallback value.
	 *
	 * @return float
	 */
	private function sanitize_public_page_dimension( $value, $min, $max, $default ) {
		return $this->get_public_page_presentation_helper()->sanitize_public_page_dimension( $value, $min, $max, $default );
	}

	/**
	 * Sanitizes a CSS unit string against a whitelist.
	 *
	 * @param mixed    $value   Raw unit value.
	 * @param string[] $allowed Allowed unit strings.
	 * @param string   $default Fallback unit.
	 *
	 * @return string
	 */
	private function sanitize_public_page_unit( $value, $allowed, $default ) {
		return $this->get_public_page_presentation_helper()->sanitize_public_page_unit( $value, $allowed, $default );
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
		return $this->get_public_page_presentation_helper()->hex_to_rgba( $hex_color, $alpha, $fallback );
	}

	/**
	 * Returns the inline CSS variables used by the settings preview.
	 *
	 * @param array $theme The current sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_preview_style_variables( $theme ) {
		return $this->get_public_page_presentation_helper()->get_public_page_preview_style_variables( $theme );
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
		return $this->get_public_page_presentation_helper()->get_public_page_button_style( $status, $theme, $full_width );
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
		return $this->get_public_page_presentation_helper()->get_public_page_input_style( $control_type, $theme );
	}

	/**
	 * Returns the inline style used by public manual field labels.
	 *
	 * @param array|null $theme Optional already-sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_field_label_style( $theme = null ) {
		return $this->get_public_page_presentation_helper()->get_public_page_field_label_style( $theme );
	}

	/**
	 * Returns the inline style used by radio and checkbox labels on public pages.
	 *
	 * @param array|null $theme Optional already-sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_choice_label_style( $theme = null ) {
		return $this->get_public_page_presentation_helper()->get_public_page_choice_label_style( $theme );
	}

	/**
	 * Returns standalone fallback CSS for native Gravity Forms markup on the public approval page.
	 *
	 * @param array|null $theme Optional already-sanitized theme.
	 *
	 * @return string
	 */
	private function get_public_page_gravity_forms_fallback_css( $theme = null ) {
		$theme               = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();
		$text_color          = (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ];
		$card_background     = (string) $theme[ self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ];
		$focus_color         = (string) $theme[ self::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ];
		$border_color        = $this->hex_to_rgba( $text_color, 0.18, 'rgba(29,35,39,0.18)' );
		$muted_text_color    = $this->hex_to_rgba( $text_color, 0.66, 'rgba(29,35,39,0.66)' );
		$shadow_color        = $this->hex_to_rgba( $text_color, 0.05, 'rgba(29,35,39,0.05)' );
		$focus_shadow_color  = $this->hex_to_rgba( $focus_color, 0.18, 'rgba(34,113,177,0.18)' );
		$control_radius      = max( 10, (int) round( floatval( $theme[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ] ) * 16 ) );
		$control_radius_px   = $control_radius . 'px';

		return '.gf-email-approvals-public .gf-email-approvals-public__form{display:block;}'
			. '.gf-email-approvals-public .gf-email-approvals-public__gf-wrapper{width:100%;}'
			. '.gf-email-approvals-public .gform_body{width:100%;}'
			. '.gf-email-approvals-public .gform_fields{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:18px 16px;margin:0;padding:0;list-style:none;}'
			. '.gf-email-approvals-public .gfield{grid-column:1 / -1;min-width:0;margin:0;padding:0;border:0;}'
			. '.gf-email-approvals-public .gfield--width-third{grid-column:span 4;}'
			. '.gf-email-approvals-public .gfield--width-half{grid-column:span 6;}'
			. '.gf-email-approvals-public .gfield--width-two-thirds{grid-column:span 8;}'
			. '.gf-email-approvals-public .gfield--width-quarter{grid-column:span 3;}'
			. '.gf-email-approvals-public .gfield--width-three-quarter{grid-column:span 9;}'
			. '.gf-email-approvals-public .gfield_label{display:block;margin:0 0 8px;font-size:15px;font-weight:600;line-height:1.45;color:' . esc_html( $text_color ) . ';}'
			. '.gf-email-approvals-public .gfield_required{margin-left:4px;color:#c02b0a;font-weight:700;}'
			. '.gf-email-approvals-public .ginput_container{position:relative;}'
			. '.gf-email-approvals-public .ginput_container input:not([type=checkbox]):not([type=radio]):not([type=hidden]),.gf-email-approvals-public .ginput_container select,.gf-email-approvals-public .ginput_container textarea{display:block;width:100%;max-width:100%;min-height:50px;margin:0;padding:12px 14px;border:1px solid ' . esc_html( $border_color ) . ';border-radius:' . esc_html( $control_radius_px ) . ';background:' . esc_html( $card_background ) . ';color:' . esc_html( $text_color ) . ';font:inherit;line-height:1.5;box-shadow:0 1px 2px ' . esc_html( $shadow_color ) . ';transition:border-color .2s ease,box-shadow .2s ease,background-color .2s ease;box-sizing:border-box;-webkit-appearance:none;appearance:none;}'
			. '.gf-email-approvals-public .ginput_container textarea{min-height:132px;resize:vertical;}'
			. '.gf-email-approvals-public .ginput_container select{padding-right:48px;background-image:linear-gradient(45deg, transparent 50%, ' . esc_html( $text_color ) . ' 50%),linear-gradient(135deg, ' . esc_html( $text_color ) . ' 50%, transparent 50%);background-position:calc(100% - 20px) 50%,calc(100% - 14px) 50%;background-size:6px 6px,6px 6px;background-repeat:no-repeat;}'
			. '.gf-email-approvals-public .ginput_container select[multiple]{min-height:148px;padding-right:14px;background-image:none;}'
			. '.gf-email-approvals-public .ginput_container input:not([type=checkbox]):not([type=radio]):not([type=hidden]):focus,.gf-email-approvals-public .ginput_container select:focus,.gf-email-approvals-public .ginput_container textarea:focus{outline:none;border-color:' . esc_html( $focus_color ) . ';box-shadow:0 0 0 4px ' . esc_html( $focus_shadow_color ) . ';}'
			. '.gf-email-approvals-public .gfield.gfield_error .gfield_label,.gf-email-approvals-public .gfield.gfield_error legend,.gf-email-approvals-public .gfield.gfield_error .gfield_validation_message{color:#c02b0a;}'
			. '.gf-email-approvals-public .gfield.gfield_error input:not([type=checkbox]):not([type=radio]):not([type=hidden]),.gf-email-approvals-public .gfield.gfield_error select,.gf-email-approvals-public .gfield.gfield_error textarea{border-color:#c02b0a;box-shadow:0 0 0 1px rgba(192,43,10,0.08);}'
			. '.gf-email-approvals-public .gfield_validation_message{display:block;margin:8px 0 0;padding:10px 12px;border:1px solid rgba(192,43,10,0.18);border-radius:' . esc_html( $control_radius_px ) . ';background:#fff6f6;font-size:13px;line-height:1.45;color:#c02b0a;}'
			. '.gf-email-approvals-public .gfield_description{margin:8px 0 0;font-size:13px;line-height:1.5;color:' . esc_html( $muted_text_color ) . ';}'
			. '.gf-email-approvals-public .gfield_description:empty{display:none;}'
			. '.gf-email-approvals-public .large_admin,.gf-email-approvals-public .medium,.gf-email-approvals-public .small{width:100% !important;max-width:100%;}'
			. '.gf-email-approvals-public .gfield_radio,.gf-email-approvals-public .gfield_checkbox{display:grid;gap:10px;margin:0;padding:6px 0 0;list-style:none;}'
			. '.gf-email-approvals-public .gchoice{display:flex;align-items:flex-start;gap:10px;margin:0;}'
			. '.gf-email-approvals-public .gchoice label{margin:0;font-weight:500;line-height:1.5;color:' . esc_html( $text_color ) . ';}'
			. '.gf-email-approvals-public .gchoice input{width:18px;height:18px;margin:2px 0 0;accent-color:' . esc_html( $focus_color ) . ';}'
			. '.gf-email-approvals-public .screen-reader-text{position:absolute !important;width:1px !important;height:1px !important;padding:0 !important;margin:-1px !important;overflow:hidden !important;clip:rect(0,0,0,0) !important;white-space:nowrap !important;border:0 !important;}'
			. '.gf-email-approvals-public .gf-email-approvals-public__content form > p:last-child{margin:28px 0 0;}'
			. '@media screen and (max-width:900px){.gf-email-approvals-public .gform_fields{grid-template-columns:1fr;gap:16px;}.gf-email-approvals-public .gfield--width-third,.gf-email-approvals-public .gfield--width-half,.gf-email-approvals-public .gfield--width-two-thirds,.gf-email-approvals-public .gfield--width-quarter,.gf-email-approvals-public .gfield--width-three-quarter{grid-column:1 / -1;}}';
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
		if ( '' === trim( $message ) ) {
			return '';
		}

		return '<p class="gf-email-approvals-public__message">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Returns the textarea page copy fields that may intentionally render blank.
	 *
	 * @return array<int, string>
	 */
	private function get_blankable_notification_page_fields() {
		return array(
			self::NOTIFICATION_APPROVE_CONFIRMATION_TEXT,
			self::NOTIFICATION_REJECT_CONFIRMATION_TEXT,
			self::NOTIFICATION_APPROVED_RESULT_TEXT,
			self::NOTIFICATION_REJECTED_RESULT_TEXT,
		);
	}

	/**
	 * Returns the notification key used to remember an intentionally blank page copy field.
	 *
	 * @param string $setting_name The page copy setting name.
	 *
	 * @return string
	 */
	private function get_notification_page_empty_override_key( $setting_name ) {
		return in_array( $setting_name, $this->get_blankable_notification_page_fields(), true )
			? $setting_name . '_is_blank'
			: '';
	}

	/**
	 * Returns the field default used by the notification editor.
	 *
	 * @param array  $notification  The current notification.
	 * @param string $setting_name  The page copy setting name.
	 * @param string $default_value The default copy.
	 *
	 * @return string
	 */
	private function get_notification_page_field_default_value( $notification, $setting_name, $default_value ) {
		return $this->is_notification_page_field_overridden_as_empty( $notification, $setting_name ) ? '' : $default_value;
	}

	/**
	 * Returns whether the notification should render a page copy field as blank.
	 *
	 * @param array  $notification The current notification.
	 * @param string $setting_name The page copy setting name.
	 *
	 * @return bool
	 */
	private function is_notification_page_field_overridden_as_empty( $notification, $setting_name ) {
		$empty_override_key = $this->get_notification_page_empty_override_key( $setting_name );

		return '' !== $empty_override_key
			&& is_array( $notification )
			&& array_key_exists( $empty_override_key, $notification )
			&& '1' === (string) $notification[ $empty_override_key ];
	}

	/**
	 * Outputs the public approval page.
	 *
	 * @param string $title           Page title.
	 * @param string $content         Page content.
	 * @param string $head_markup     Extra trusted markup to output in the head.
	 * @param string $footer_markup   Extra trusted markup to output before </body>.
	 * @param bool   $trusted_content Whether the page content is trusted internal markup.
	 *
	 * @return void
	 */
	private function render_public_page( $title, $content, $head_markup = '', $footer_markup = '', $trusted_content = false ) {
		status_header( 200 );
		nocache_headers();
		$theme         = $this->get_public_page_theme_settings();
		$card_width    = floatval( $theme[ self::PLUGIN_SETTING_CARD_MAX_WIDTH ] ) . esc_attr( $theme[ self::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT ] );
		$card_padding  = floatval( $theme[ self::PLUGIN_SETTING_CARD_PADDING ] ) . esc_attr( $theme[ self::PLUGIN_SETTING_CARD_PADDING_UNIT ] );
		$card_radius   = floatval( $theme[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS ] ) . esc_attr( $theme[ self::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ] );

		/* Compute a smaller padding for mobile breakpoint */
		$padding_unit = $theme[ self::PLUGIN_SETTING_CARD_PADDING_UNIT ];
		if ( 'px' === $padding_unit ) {
			$responsive_padding = max( 16, (int) $theme[ self::PLUGIN_SETTING_CARD_PADDING ] - 8 ) . 'px';
		} else {
			$responsive_padding = ( floatval( $theme[ self::PLUGIN_SETTING_CARD_PADDING ] ) * 0.8 ) . esc_attr( $padding_unit );
		}

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
				'class' => true,
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
			'img'     => array(
				'src'   => true,
				'alt'   => true,
				'style' => true,
			),
		);

		$logo_html         = '';
		$logo_url          = (string) $theme[ self::PLUGIN_SETTING_LOGO_IMAGE ];
		$title_alignment   = (string) $theme[ self::PLUGIN_SETTING_TITLE_ALIGNMENT ];
		$title_font_size   = floatval( $theme[ self::PLUGIN_SETTING_TITLE_FONT_SIZE ] ) . $theme[ self::PLUGIN_SETTING_TITLE_FONT_SIZE_UNIT ];
		$message_alignment = (string) $theme[ self::PLUGIN_SETTING_MESSAGE_ALIGNMENT ];
		$message_font_size = floatval( $theme[ self::PLUGIN_SETTING_MESSAGE_FONT_SIZE ] ) . $theme[ self::PLUGIN_SETTING_MESSAGE_FONT_SIZE_UNIT ];
		if ( ! empty( $logo_url ) ) {
			$logo_align  = (string) $theme[ self::PLUGIN_SETTING_LOGO_ALIGNMENT ];
			$logo_height = floatval( $theme[ self::PLUGIN_SETTING_LOGO_MAX_HEIGHT ] ) . esc_attr( $theme[ self::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT ] );
			$logo_html   = sprintf(
				'<div style="margin-bottom:24px;text-align:%1$s;"><img src="%2$s" alt="Logo" style="max-width:100%%;max-height:%3$s;height:auto;display:inline-block;vertical-align:top;" /></div>',
				esc_attr( $logo_align ),
				esc_url( $logo_url ),
				esc_attr( $logo_height )
			);
		}

		echo '<!doctype html><html><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo $head_markup; // WPCS: XSS ok.
		echo '<style>html{box-sizing:border-box;}*,*::before,*::after{box-sizing:inherit;}input,select,textarea{font:inherit;max-width:100%;}textarea{resize:vertical;}body.gf-email-approvals-public{margin:0;font-family:Segoe UI,Arial,sans-serif;background:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ] ) . ';color:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ] ) . ';}main.gf-email-approvals-public__main{width:100%;max-width:' . esc_html( $card_width ) . ';margin:8vh auto;box-sizing:border-box;}section.gf-email-approvals-public__card{width:100%;box-sizing:border-box;background:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ] ) . ';border-radius:' . esc_html( $card_radius ) . ';padding:' . esc_html( $card_padding ) . ';box-shadow:0 10px 30px ' . esc_html( $this->hex_to_rgba( (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ], 0.12, 'rgba(0,0,0,0.08)' ) ) . ';}h1.gf-email-approvals-public__title{margin-top:0;margin-bottom:16px;font-size:' . esc_html( $title_font_size ) . ';line-height:1.2;text-align:' . esc_html( $title_alignment ) . ';color:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_TITLE_COLOR ] ) . ';}.gf-email-approvals-public__message{white-space:pre-line;color:' . esc_html( (string) $theme[ self::PLUGIN_SETTING_TEXT_COLOR ] ) . ';font-size:' . esc_html( $message_font_size ) . ';text-align:' . esc_html( $message_alignment ) . ';}.gf-email-approvals-public__content p:first-child{margin-top:0;}.gf-email-approvals-public__content p:last-child{margin-bottom:0;}' . $this->get_public_page_gravity_forms_fallback_css( $theme ) . '@media screen and (max-width:680px){main.gf-email-approvals-public__main{margin:0;}section.gf-email-approvals-public__card{padding:' . esc_html( $responsive_padding ) . ';}}</style>';
		echo '</head><body class="gf-email-approvals-public">';
		echo '<main class="gf-email-approvals-public__main">';
		echo $logo_html; // WPCS: XSS ok.
		echo '<section class="gf-email-approvals-public__card">';
		echo '<h1 class="gf-email-approvals-public__title">' . esc_html( $title ) . '</h1>';
		echo '<div class="gf-email-approvals-public__content">';
		if ( $trusted_content ) {
			echo $content; // WPCS: XSS ok.
		} else {
			echo wp_kses( $content, $allowed_html );
		}
		echo '</div></section></main>';
		echo $footer_markup; // WPCS: XSS ok.
		echo '</body></html>';

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