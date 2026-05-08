<?php

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates public page theme and presentation calculations.
 */
class GFEmailApprovalsPublicPagePresentationHelper {
	/**
	 * Internal callbacks used to bridge back to the add-on.
	 *
	 * @var array<string, callable>
	 */
	private $callbacks = array();

	/**
	 * @param array<string, callable> $callbacks Add-on bridge callbacks.
	 */
	public function __construct( $callbacks ) {
		$this->callbacks = $callbacks;
	}

	/**
	 * Returns the default theme used on the public approval pages.
	 *
	 * @return array<string, int|string>
	 */
	public function get_public_page_theme_defaults() {
		return array(
			GFEmailApprovalsAddon::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR   => '#f5f5f5',
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR   => '#ffffff',
			GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR              => '#1d2327',
			GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_COLOR             => '#1d2327',
			GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR    => '#2271b1',
			GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR     => '#b32d2e',
			GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR       => '#ffffff',
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH          => 60,
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT     => 'vw',
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING            => 2,
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT       => 'rem',
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS      => 0.75,
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT => 'rem',
			GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_IMAGE              => '',
			GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT          => 'center',
			GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT         => 3,
			GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT    => 'rem',
		);
	}

	/**
	 * Returns the sanitized theme used on the public approval pages.
	 *
	 * @return array<string, int|string>
	 */
	public function get_public_page_theme_settings() {
		$defaults = $this->get_public_page_theme_defaults();
		$settings = $defaults;

		$color_settings = array(
			GFEmailApprovalsAddon::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR,
			GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR,
			GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR,
			GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_COLOR,
			GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR,
			GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR,
			GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR,
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

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH ] = $this->sanitize_public_page_dimension(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH ),
			0,
			9999,
			$defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH ]
		);

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT ] = $this->sanitize_public_page_unit(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT ),
			array( 'vw', '%', 'px', 'rem' ),
			$defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT ]
		);

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING ] = $this->sanitize_public_page_dimension(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING ),
			0,
			9999,
			$defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING ]
		);

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT ] = $this->sanitize_public_page_unit(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT ),
			array( 'rem', 'em', 'px' ),
			$defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT ]
		);

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ] = $this->sanitize_public_page_dimension(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ),
			0,
			9999,
			$defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ]
		);

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ] = $this->sanitize_public_page_unit(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ),
			array( 'rem', 'px' ),
			$defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ]
		);

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_IMAGE ] = esc_url_raw( (string) $this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_IMAGE ) );

		$logo_alignment = sanitize_key( (string) $this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT ) );
		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT ] = in_array( $logo_alignment, array( 'left', 'center', 'right' ), true ) ? $logo_alignment : $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT ];

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT ] = $this->sanitize_public_page_dimension(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT ),
			0,
			9999,
			(float) $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT ]
		);

		$settings[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT ] = $this->sanitize_public_page_unit(
			$this->get_plugin_setting( GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT ),
			array( 'rem', 'px' ),
			$defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT ]
		);

		return $settings;
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
	public function sanitize_public_page_dimension( $value, $min, $max, $default ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
		}

		if ( '' === $value || ! is_numeric( $value ) ) {
			return (float) $default;
		}

		$value = (float) $value;

		if ( $value < $min || $value > $max ) {
			return (float) $default;
		}

		return $value;
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
	public function sanitize_public_page_unit( $value, $allowed, $default ) {
		if ( is_string( $value ) && in_array( $value, $allowed, true ) ) {
			return $value;
		}

		return $default;
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
	public function hex_to_rgba( $hex_color, $alpha, $fallback ) {
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
	 * @param array<string, mixed> $theme The current sanitized theme.
	 *
	 * @return string
	 */
	public function get_public_page_preview_style_variables( $theme ) {
		$card_width   = floatval( $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH ] ) . $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT ];
		$card_padding = floatval( $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING ] ) . $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT ];
		$card_radius  = floatval( $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ] ) . $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ];
		$logo_height  = floatval( $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT ] ) . $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT ];

		return sprintf(
			'--gf-email-approvals-page-bg:%1$s;--gf-email-approvals-card-bg:%2$s;--gf-email-approvals-text:%3$s;--gf-email-approvals-title:%4$s;--gf-email-approvals-approve:%5$s;--gf-email-approvals-reject:%6$s;--gf-email-approvals-button-text:%7$s;--gf-email-approvals-card-width:%8$s;--gf-email-approvals-card-padding:%9$s;--gf-email-approvals-card-radius:%10$s;--gf-email-approvals-shadow:%11$s;--gf-email-approvals-logo-align:%12$s;--gf-email-approvals-logo-height:%13$s;',
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ],
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ],
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_COLOR ],
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ],
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR ],
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
			$card_width,
			$card_padding,
			$card_radius,
			$this->hex_to_rgba( (string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ], 0.12, 'rgba(29,35,39,0.12)' ),
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT ],
			$logo_height
		);
	}

	/**
	 * Returns the inline style used by public confirmation buttons.
	 *
	 * @param string                 $status     The decision status.
	 * @param array<string, mixed>|null $theme   Optional already-sanitized theme.
	 * @param bool                   $full_width Whether the button should stretch to the card width.
	 *
	 * @return string
	 */
	public function get_public_page_button_style( $status, $theme = null, $full_width = true ) {
		$theme             = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();
		$radius_value      = floatval( $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ] ) * 0.5;
		$radius_unit       = $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ];
		$radius            = $radius_value . $radius_unit;
		$background        = GFEmailApprovalsAddon::STATUS_REJECTED === $status ? (string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR ] : (string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ];
		$width_declaration = $full_width ? 'width:100%;' : '';

		return sprintf(
			'display:block;%1$sbox-sizing:border-box;padding:12px 18px;background:%2$s;color:%3$s;border:0;border-radius:%4$s;cursor:pointer;font:inherit;font-weight:600;',
			$width_declaration,
			$background,
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
			$radius
		);
	}

	/**
	 * Returns the inline style used by public manual input controls.
	 *
	 * @param string                   $control_type Control type: input, textarea, select, multiselect.
	 * @param array<string, mixed>|null $theme       Optional already-sanitized theme.
	 *
	 * @return string
	 */
	public function get_public_page_input_style( $control_type, $theme = null ) {
		$theme        = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();
		$radius_value = floatval( $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ] ) * 0.5;
		$radius_unit  = $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ];
		$radius       = $radius_value . $radius_unit;
		$border_color = $this->hex_to_rgba( (string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ], 0.18, 'rgba(29,35,39,0.18)' );
		$style        = sprintf(
			'display:block;width:100%%;max-width:100%%;margin-top:12px;padding:12px 14px;border:1px solid %1$s;border-radius:%2$s;background:%3$s;color:%4$s;font:inherit;box-sizing:border-box;',
			$border_color,
			$radius,
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ]
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
	 * @param array<string, mixed>|null $theme Optional already-sanitized theme.
	 *
	 * @return string
	 */
	public function get_public_page_field_label_style( $theme = null ) {
		$theme = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();

		return sprintf(
			'display:block;font-weight:600;line-height:1.4;color:%s;',
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ]
		);
	}

	/**
	 * Returns the inline style used by radio and checkbox labels on public pages.
	 *
	 * @param array<string, mixed>|null $theme Optional already-sanitized theme.
	 *
	 * @return string
	 */
	public function get_public_page_choice_label_style( $theme = null ) {
		$theme = is_array( $theme ) ? $theme : $this->get_public_page_theme_settings();

		return sprintf(
			'display:flex;align-items:flex-start;gap:10px;margin-top:12px;font-weight:400;color:%s;',
			(string) $theme[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ]
		);
	}

	/**
	 * Returns a plugin setting value from the add-on.
	 *
	 * @param string $name Setting name.
	 *
	 * @return mixed
	 */
	private function get_plugin_setting( $name ) {
		return $this->call_callback( 'get_plugin_setting', array( $name ) );
	}

	/**
	 * Calls one of the helper bridge callbacks.
	 *
	 * @param string            $name Callback name.
	 * @param array<int, mixed> $args Callback arguments.
	 *
	 * @return mixed
	 */
	private function call_callback( $name, $args = array() ) {
		if ( ! isset( $this->callbacks[ $name ] ) ) {
			return null;
		}

		return call_user_func_array( $this->callbacks[ $name ], $args );
	}
}