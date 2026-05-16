<?php

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates the admin appearance settings rendering for approval pages.
 */
class GFEmailApprovalsAppearanceSettingsHelper {
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
	 * Returns the localized settings used by the appearance builder admin script.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_appearance_builder_asset_config() {
		return array(
			'strings' => array(
				'selectLogo'   => __( 'Select Logo', 'gf-email-approvals' ),
				'useThisImage' => __( 'Use this image', 'gf-email-approvals' ),
				'editColor'    => __( 'Edit color', 'gf-email-approvals' ),
			),
		);
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
		$name        = rgar( $field, 'name' );
		$saved_value = esc_url_raw( (string) $this->get_plugin_setting( $name ) );
		$input_id    = 'gf_setting_' . esc_attr( $name );
		$input_name  = '_gform_setting_' . esc_attr( $name );
		$has_image   = ! empty( $saved_value );

		ob_start();
		?>
		<div class="gf-email-approvals-image-upload <?php echo $has_image ? 'has-image' : ''; ?>">
			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $input_name ); ?>" value="<?php echo esc_attr( $saved_value ); ?>" />
			<img class="gf-email-approvals-image-upload__preview" src="<?php echo esc_attr( $saved_value ); ?>" alt="Logo Preview" />
			<div class="gf-email-approvals-image-upload__placeholder">
				<div class="gf-email-approvals-image-upload__placeholder-icon">
					<span class="dashicons dashicons-format-image"></span>
				</div>
				<div class="gf-email-approvals-image-upload__placeholder-text">
					<?php esc_html_e( 'Click to upload (PNG, SVG, WebP)', 'gf-email-approvals' ); ?>
				</div>
			</div>
			<button type="button" class="gf-email-approvals-image-upload__remove" aria-label="<?php esc_attr_e( 'Remove image', 'gf-email-approvals' ); ?>">
				<span class="dashicons dashicons-trash" style="font-size:14px;line-height:1;width:14px;height:14px;vertical-align:middle;margin-right:2px;"></span><?php esc_html_e( 'Remove', 'gf-email-approvals' ); ?>
			</button>
		</div>
		<?php
		$html = ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
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
		$name        = rgar( $field, 'name' );
		$saved_value = sanitize_key( (string) $this->get_plugin_setting( $name ) );

		if ( empty( $saved_value ) && isset( $field['default_value'] ) ) {
			$saved_value = $field['default_value'];
		}

		$input_name = '_gform_setting_' . esc_attr( $name );

		ob_start();
		?>
		<div class="gf-email-approvals-alignment">
			<label class="gf-email-approvals-alignment__option <?php echo 'left' === $saved_value ? 'active' : ''; ?>">
				<input type="radio" name="<?php echo esc_attr( $input_name ); ?>" value="left" <?php checked( $saved_value, 'left' ); ?> />
				<span class="dashicons dashicons-editor-alignleft gf-email-approvals-alignment__icon"></span>
			</label>
			<label class="gf-email-approvals-alignment__option <?php echo 'center' === $saved_value ? 'active' : ''; ?>">
				<input type="radio" name="<?php echo esc_attr( $input_name ); ?>" value="center" <?php checked( $saved_value, 'center' ); ?> />
				<span class="dashicons dashicons-editor-aligncenter gf-email-approvals-alignment__icon"></span>
			</label>
			<label class="gf-email-approvals-alignment__option <?php echo 'right' === $saved_value ? 'active' : ''; ?>">
				<input type="radio" name="<?php echo esc_attr( $input_name ); ?>" value="right" <?php checked( $saved_value, 'right' ); ?> />
				<span class="dashicons dashicons-editor-alignright gf-email-approvals-alignment__icon"></span>
			</label>
		</div>
		<?php
		$html = ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
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
		$name          = rgar( $field, 'name' );
		$unit_name     = rgar( $field, 'unit_name', $name . '_unit' );
		$units         = rgar( $field, 'units', array() );
		$default_value = rgar( $field, 'default_value', 0 );
		$default_unit  = rgar( $field, 'default_unit', 'px' );
		$range_hint    = rgar( $field, 'range_hint', '' );

		$saved_value = $this->get_plugin_setting( $name );
		$saved_unit  = $this->get_plugin_setting( $unit_name );

		if ( '' === $saved_value || null === $saved_value || false === $saved_value ) {
			$saved_value = $default_value;
		}

		if ( ! $saved_unit || ! isset( $units[ $saved_unit ] ) ) {
			$saved_unit  = $default_unit;
			$saved_value = $default_value;
		}

		$current_unit_config = $units[ $saved_unit ];

		ob_start();

		$input_id      = 'gf_setting_' . esc_attr( $name );
		$unit_input_id = 'gf_setting_' . esc_attr( $unit_name );
		?>
		<div class="gf-email-approvals-dimension" data-field-name="<?php echo esc_attr( $name ); ?>">
			<input
				type="range"
				class="gf-email-approvals-dimension__range"
				min="<?php echo esc_attr( $current_unit_config['min'] ); ?>"
				max="<?php echo esc_attr( $current_unit_config['max'] ); ?>"
				step="<?php echo esc_attr( $current_unit_config['step'] ); ?>"
				value="<?php echo esc_attr( $saved_value ); ?>"
				aria-hidden="true"
				tabindex="-1"
			/>
			<input
				type="number"
				id="<?php echo esc_attr( $input_id ); ?>"
				name="<?php echo esc_attr( '_gform_setting_' . $name ); ?>"
				class="gf-email-approvals-dimension__input"
				min="<?php echo esc_attr( $current_unit_config['min'] ); ?>"
				max="<?php echo esc_attr( $current_unit_config['max'] ); ?>"
				step="<?php echo esc_attr( $current_unit_config['step'] ); ?>"
				value="<?php echo esc_attr( $saved_value ); ?>"
			/>
			<select
				id="<?php echo esc_attr( $unit_input_id ); ?>"
				name="<?php echo esc_attr( '_gform_setting_' . $unit_name ); ?>"
				class="gf-email-approvals-dimension__unit"
			>
				<?php foreach ( $units as $unit_key => $unit_config ) : ?>
					<option
						value="<?php echo esc_attr( $unit_key ); ?>"
						data-min="<?php echo esc_attr( $unit_config['min'] ); ?>"
						data-max="<?php echo esc_attr( $unit_config['max'] ); ?>"
						data-step="<?php echo esc_attr( $unit_config['step'] ); ?>"
						data-default="<?php echo esc_attr( rgar( $field, 'default_value' ) ); ?>"
						<?php selected( $saved_unit, $unit_key ); ?>
					><?php echo esc_html( $unit_config['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php if ( $range_hint ) : ?>
			<span class="gf-email-approvals-dimension__hint"><?php echo esc_html( $range_hint ); ?></span>
		<?php endif; ?>
		<?php

		$output = ob_get_clean();

		if ( $echo ) {
			echo $output;
			return;
		}

		return $output;
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
		unset( $field );

		$defaults   = $this->get_public_page_theme_defaults();
		$accordions = $this->get_approval_page_setting_accordions( $defaults );
		$output     = $this->render_approval_page_setting_accordions( $accordions );

		if ( $echo ) {
			echo $output;
			return;
		}

		return $output;
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
		unset( $field );

		$current_theme  = $this->get_public_page_theme_settings();
		$preview_config = $this->get_approval_page_preview_config();
		$output         = $this->render_approval_page_preview( $preview_config, $current_theme );

		if ( $echo ) {
			echo $output;
			return;
		}

		return $output;
	}

	/**
	 * Renders the approval page setting accordions markup.
	 *
	 * @param array<int, array<string, mixed>> $accordions Accordion definitions.
	 *
	 * @return string
	 */
	private function render_approval_page_setting_accordions( $accordions ) {
		ob_start();
		?>
		<div class="gf-email-approvals-accordions">
			<?php foreach ( $accordions as $accordion ) : ?>
				<?php echo $this->render_approval_page_setting_accordion( $accordion ); ?>
			<?php endforeach; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Renders a single approval page setting accordion.
	 *
	 * @param array<string, mixed> $accordion Accordion definition.
	 *
	 * @return string
	 */
	private function render_approval_page_setting_accordion( $accordion ) {
		ob_start();
		?>
		<div
			class="gf-email-approvals-accordion<?php echo $accordion['open'] ? ' open fully-open' : ''; ?>"
			id="<?php echo esc_attr( $accordion['id'] ); ?>"
		>
			<button
				class="gf-email-approvals-accordion__header"
				type="button"
			>
				<span class="gf-email-approvals-accordion__title"><?php echo esc_html( $accordion['title'] ); ?></span>
				<span class="gf-email-approvals-accordion__icon">▼</span>
			</button>
			<div class="gf-email-approvals-accordion__content">
				<div class="gf-email-approvals-accordion__content-inner">
					<?php foreach ( $accordion['fields'] as $sub_field ) : ?>
						<?php $this->render_approval_page_setting_accordion_field( $sub_field ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Renders a single field inside an approval page settings accordion.
	 *
	 * @param array<string, mixed> $sub_field Field definition.
	 *
	 * @return void
	 */
	private function render_approval_page_setting_accordion_field( $sub_field ) {
		$field_type    = rgar( $sub_field, 'type' );
		$render_method = 'settings_' . $field_type;

		if ( 'text' !== $field_type && method_exists( $this, $render_method ) ) {
			$field_id    = 'gfea_wrap_' . esc_attr( rgar( $sub_field, 'name' ) ) . '_container';
			$field_label = rgar( $sub_field, 'label', '' );
			?>
			<div id="<?php echo esc_attr( $field_id ); ?>" class="gform-settings-field">
				<?php if ( $field_label ) : ?>
					<div class="gform-settings-field__header">
						<label class="gform-settings-label"><?php echo esc_html( $field_label ); ?></label>
					</div>
				<?php endif; ?>
				<?php $this->{$render_method}( $sub_field ); ?>
			</div>
			<?php

			return;
		}

		$this->render_single_setting_row( $sub_field );
	}

	/**
	 * Returns the accordion definitions used by the appearance settings screen.
	 *
	 * @param array<string, mixed> $defaults Default theme settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_approval_page_setting_accordions( $defaults ) {
		return array(
			array(
				'id'     => 'gf-email-approvals-accordion-colors',
				'title'  => esc_html__( 'Colors', 'gf-email-approvals' ),
				'fields' => $this->get_approval_page_color_fields( $defaults ),
				'open'   => true,
			),
			array(
				'id'     => 'gf-email-approvals-accordion-dimensions',
				'title'  => esc_html__( 'Dimensions', 'gf-email-approvals' ),
				'fields' => $this->get_approval_page_dimension_fields( $defaults ),
				'open'   => false,
			),
			array(
				'id'     => 'gf-email-approvals-accordion-text',
				'title'  => esc_html__( 'Text', 'gf-email-approvals' ),
				'fields' => $this->get_approval_page_text_fields( $defaults ),
				'open'   => false,
			),
			array(
				'id'     => 'gf-email-approvals-accordion-logo',
				'title'  => esc_html__( 'Logo', 'gf-email-approvals' ),
				'fields' => $this->get_approval_page_logo_fields( $defaults ),
				'open'   => false,
			),
		);
	}

	/**
	 * Returns the color field definitions used by the appearance settings accordion.
	 *
	 * @param array<string, mixed> $defaults Default theme settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_approval_page_color_fields( $defaults ) {
		return array(
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR,
				'label'         => esc_html__( 'Page background', 'gf-email-approvals' ),
				'type'          => 'text',
				'class'         => 'medium gf-email-approvals-color-control',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR,
				'label'         => esc_html__( 'Card background', 'gf-email-approvals' ),
				'type'          => 'text',
				'class'         => 'medium gf-email-approvals-color-control',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR,
				'label'         => esc_html__( 'Body text', 'gf-email-approvals' ),
				'type'          => 'text',
				'class'         => 'medium gf-email-approvals-color-control',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_COLOR,
				'label'         => esc_html__( 'Title', 'gf-email-approvals' ),
				'type'          => 'text',
				'class'         => 'medium gf-email-approvals-color-control',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_COLOR ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR,
				'label'         => esc_html__( 'Approve button', 'gf-email-approvals' ),
				'type'          => 'text',
				'class'         => 'medium gf-email-approvals-color-control',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR,
				'label'         => esc_html__( 'Reject button', 'gf-email-approvals' ),
				'type'          => 'text',
				'class'         => 'medium gf-email-approvals-color-control',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR,
				'label'         => esc_html__( 'Button text', 'gf-email-approvals' ),
				'type'          => 'text',
				'class'         => 'medium gf-email-approvals-color-control',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
			),
		);
	}

	/**
	 * Returns the dimension field definitions used by the appearance settings accordion.
	 *
	 * @param array<string, mixed> $defaults Default theme settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_approval_page_dimension_fields( $defaults ) {
		return array(
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH,
				'label'         => esc_html__( 'Card max width', 'gf-email-approvals' ),
				'type'          => 'approval_dimension',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH ],
				'unit_name'     => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT,
				'default_unit'  => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT ],
				'units'         => array(
					'vw'  => array( 'label' => 'vw',  'min' => 40,  'max' => 90,  'step' => 1 ),
					'%'   => array( 'label' => '%',   'min' => 40,  'max' => 100, 'step' => 1 ),
					'px'  => array( 'label' => 'px',  'min' => 400, 'max' => 960, 'step' => 1 ),
					'rem' => array( 'label' => 'rem', 'min' => 25,  'max' => 60,  'step' => 1 ),
				),
				'range_hint'    => esc_html__( '40 – 90 vw / 400 – 960 px', 'gf-email-approvals' ),
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING,
				'label'         => esc_html__( 'Card padding', 'gf-email-approvals' ),
				'type'          => 'approval_dimension',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING ],
				'unit_name'     => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT,
				'default_unit'  => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT ],
				'units'         => array(
					'rem' => array( 'label' => 'rem', 'min' => 1,  'max' => 5,  'step' => 0.25 ),
					'em'  => array( 'label' => 'em',  'min' => 1,  'max' => 5,  'step' => 0.25 ),
					'px'  => array( 'label' => 'px',  'min' => 8,  'max' => 80, 'step' => 1 ),
				),
				'range_hint'    => esc_html__( '1 – 5 rem / 8 – 80 px', 'gf-email-approvals' ),
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS,
				'label'         => esc_html__( 'Card radius', 'gf-email-approvals' ),
				'type'          => 'approval_dimension',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ],
				'unit_name'     => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT,
				'default_unit'  => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ],
				'units'         => array(
					'rem' => array( 'label' => 'rem', 'min' => 0,  'max' => 2,  'step' => 0.05 ),
					'px'  => array( 'label' => 'px',  'min' => 0,  'max' => 40, 'step' => 1 ),
				),
				'range_hint'    => esc_html__( '0 – 2 rem / 0 – 40 px', 'gf-email-approvals' ),
			),
		);
	}

	/**
	 * Returns the text field definitions used by the appearance settings accordion.
	 *
	 * @param array<string, mixed> $defaults Default theme settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_approval_page_text_fields( $defaults ) {
		return array(
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_ALIGNMENT,
				'label'         => esc_html__( 'Title alignment', 'gf-email-approvals' ),
				'type'          => 'approval_alignment',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_ALIGNMENT ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE,
				'label'         => esc_html__( 'Title size', 'gf-email-approvals' ),
				'type'          => 'approval_dimension',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE ],
				'unit_name'     => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE_UNIT,
				'default_unit'  => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE_UNIT ],
				'units'         => array(
					'rem' => array( 'label' => 'rem', 'min' => 1.25, 'max' => 3,  'step' => 0.05 ),
					'px'  => array( 'label' => 'px',  'min' => 20,   'max' => 48, 'step' => 1 ),
				),
				'range_hint'    => esc_html__( '1.25 – 3 rem / 20 – 48 px', 'gf-email-approvals' ),
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_ALIGNMENT,
				'label'         => esc_html__( 'Message alignment', 'gf-email-approvals' ),
				'type'          => 'approval_alignment',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_ALIGNMENT ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE,
				'label'         => esc_html__( 'Message size', 'gf-email-approvals' ),
				'type'          => 'approval_dimension',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE ],
				'unit_name'     => GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE_UNIT,
				'default_unit'  => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE_UNIT ],
				'units'         => array(
					'rem' => array( 'label' => 'rem', 'min' => 0.875, 'max' => 1.75, 'step' => 0.05 ),
					'px'  => array( 'label' => 'px',  'min' => 14,    'max' => 28,   'step' => 1 ),
				),
				'range_hint'    => esc_html__( '0.875 – 1.75 rem / 14 – 28 px', 'gf-email-approvals' ),
			),
		);
	}

	/**
	 * Returns the logo field definitions used by the appearance settings accordion.
	 *
	 * @param array<string, mixed> $defaults Default theme settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_approval_page_logo_fields( $defaults ) {
		return array(
			array(
				'name'  => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_IMAGE,
				'label' => esc_html__( 'Image file', 'gf-email-approvals' ),
				'type'  => 'approval_image',
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT,
				'label'         => esc_html__( 'Alignment', 'gf-email-approvals' ),
				'type'          => 'approval_alignment',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT ],
			),
			array(
				'name'          => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT,
				'label'         => esc_html__( 'Max height', 'gf-email-approvals' ),
				'type'          => 'approval_dimension',
				'default_value' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT ],
				'unit_name'     => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT,
				'default_unit'  => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT ],
				'units'         => array(
					'rem' => array( 'label' => 'rem', 'min' => 1,  'max' => 10, 'step' => 0.5 ),
					'px'  => array( 'label' => 'px',  'min' => 16, 'max' => 160, 'step' => 1 ),
				),
				'range_hint'    => esc_html__( '1 – 10 rem / 16 – 160 px', 'gf-email-approvals' ),
			),
		);
	}

	/**
	 * Renders the approval page appearance preview markup.
	 *
	 * @param array<string, array<string, mixed>> $preview_config Preview configuration.
	 * @param array<string, mixed>                $current_theme  Current theme settings.
	 *
	 * @return string
	 */
	private function render_approval_page_preview( $preview_config, $current_theme ) {
		ob_start();
		?>
		<div class="gf-email-approvals-appearance-builder" data-config="<?php echo esc_attr( wp_json_encode( $preview_config ) ); ?>" data-preview-state="approve" style="<?php echo esc_attr( $this->get_public_page_preview_style_variables( $current_theme ) ); ?>">
			<div class="gf-email-approvals-appearance-builder__toolbar">
				<button type="button" class="button button-secondary is-active" data-preview-state="approve"><?php esc_html_e( 'Approve', 'gf-email-approvals' ); ?></button>
				<button type="button" class="button button-secondary" data-preview-state="reject"><?php esc_html_e( 'Reject', 'gf-email-approvals' ); ?></button>
				<button type="button" class="button button-secondary" data-preview-state="result"><?php esc_html_e( 'Result', 'gf-email-approvals' ); ?></button>
			</div>
			<div class="gf-email-approvals-appearance-builder__canvas">
				<div class="gf-email-approvals-appearance-builder__viewport">
					<div class="gf-email-approvals-appearance-builder__logo" style="display: none;">
						<img class="gf-email-approvals-appearance-builder__logo-img" src="" alt="Logo" />
					</div>
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

		return ob_get_clean();
	}

	/**
	 * Returns the config used by the approval page appearance preview.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_approval_page_preview_config() {
		$defaults = $this->get_public_page_theme_defaults();

		return array(
			'settings' => $this->get_approval_page_preview_setting_names(),
			'defaults' => $this->get_approval_page_preview_defaults( $defaults ),
			'states'   => $this->get_approval_page_preview_states(),
		);
	}

	/**
	 * Returns the plugin setting names consumed by the appearance preview.
	 *
	 * @return array<string, string>
	 */
	private function get_approval_page_preview_setting_names() {
		return array(
			'pageBackground'    => GFEmailApprovalsAddon::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR,
			'cardBackground'    => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR,
			'textColor'         => GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR,
			'titleColor'        => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_COLOR,
			'titleAlignment'    => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_ALIGNMENT,
			'titleFontSize'     => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE,
			'titleFontSizeUnit' => GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE_UNIT,
			'messageAlignment'  => GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_ALIGNMENT,
			'messageFontSize'   => GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE,
			'messageFontUnit'   => GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE_UNIT,
			'approveButton'     => GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR,
			'rejectButton'      => GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR,
			'buttonText'        => GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR,
			'cardWidth'         => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH,
			'cardWidthUnit'     => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT,
			'cardPadding'       => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING,
			'cardPaddingUnit'   => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT,
			'cardRadius'        => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS,
			'cardRadiusUnit'    => GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT,
			'logoImage'         => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_IMAGE,
			'logoAlignment'     => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT,
			'logoMaxHeight'     => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT,
			'logoMaxHeightUnit' => GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT,
		);
	}

	/**
	 * Returns the default values used by the appearance preview.
	 *
	 * @param array<string, mixed> $defaults Default theme settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_approval_page_preview_defaults( $defaults ) {
		return array(
			'pageBackground'    => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_PAGE_BACKGROUND_COLOR ],
			'cardBackground'    => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BACKGROUND_COLOR ],
			'textColor'         => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TEXT_COLOR ],
			'titleColor'        => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_COLOR ],
			'titleAlignment'    => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_ALIGNMENT ],
			'titleFontSize'     => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE ],
			'titleFontSizeUnit' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_TITLE_FONT_SIZE_UNIT ],
			'messageAlignment'  => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_ALIGNMENT ],
			'messageFontSize'   => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE ],
			'messageFontUnit'   => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_MESSAGE_FONT_SIZE_UNIT ],
			'approveButton'     => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_APPROVE_BUTTON_COLOR ],
			'rejectButton'      => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_REJECT_BUTTON_COLOR ],
			'buttonText'        => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_BUTTON_TEXT_COLOR ],
			'cardWidth'         => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH ],
			'cardWidthUnit'     => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_MAX_WIDTH_UNIT ],
			'cardPadding'       => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING ],
			'cardPaddingUnit'   => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_PADDING_UNIT ],
			'cardRadius'        => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS ],
			'cardRadiusUnit'    => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_CARD_BORDER_RADIUS_UNIT ],
			'logoImage'         => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_IMAGE ],
			'logoAlignment'     => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_ALIGNMENT ],
			'logoMaxHeight'     => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT ],
			'logoMaxHeightUnit' => $defaults[ GFEmailApprovalsAddon::PLUGIN_SETTING_LOGO_MAX_HEIGHT_UNIT ],
		);
	}

	/**
	 * Returns the preview states rendered in the appearance builder.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_approval_page_preview_states() {
		return array(
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
	 * Renders a standard GF single setting row through the add-on.
	 *
	 * @param array<string, mixed> $field Field definition.
	 *
	 * @return void
	 */
	private function render_single_setting_row( $field ) {
		$this->call_callback( 'single_setting_row', array( $field ) );
	}

	/**
	 * Returns the public page theme defaults from the add-on.
	 *
	 * @return array<string, mixed>
	 */
	private function get_public_page_theme_defaults() {
		return $this->call_callback( 'get_public_page_theme_defaults' );
	}

	/**
	 * Returns the current public page theme settings from the add-on.
	 *
	 * @return array<string, mixed>
	 */
	private function get_public_page_theme_settings() {
		return $this->call_callback( 'get_public_page_theme_settings' );
	}

	/**
	 * Returns inline preview style variables from the add-on.
	 *
	 * @param array<string, mixed> $theme Theme settings.
	 *
	 * @return string
	 */
	private function get_public_page_preview_style_variables( $theme ) {
		return $this->call_callback( 'get_public_page_preview_style_variables', array( $theme ) );
	}

	/**
	 * Calls one of the helper bridge callbacks.
	 *
	 * @param string     $name Callback name.
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