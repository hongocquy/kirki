<?php
/**
 * Repeater Customizer Control.
 *
 * @package     Kirki
 * @subpackage  Controls
 * @copyright   Copyright (c) 2016, Aristeides Stathopoulos
 * @license     http://opensource.org/licenses/https://opensource.org/licenses/MIT
 * @since       1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Kirki_Controls_Repeater_Control' ) ) {

	class Kirki_Controls_Repeater_Control extends Kirki_Customize_Control {

		public $type = 'repeater';

		public $fields = array();

		// will store a filtered version of value for advenced fields (like images..)
		protected $filtered_value = array();

		public $row_label = array();

		public function __construct( $manager, $id, $args = array() ) {

			$l10n = Kirki_l10n::get_strings();

			parent::__construct( $manager, $id, $args );

			// Set up defaults for row labels.
			$this->row_label = array(
				'type' => 'text',
				'value' => $l10n['row'],
				'field' => false,
			);
			// Validating args for row labels.
			if ( isset( $args['row_label'] ) && is_array( $args['row_label'] ) && ! empty( $args['row_label'] ) ) {
				// Validating row label type.
				if ( isset( $args['row_label']['type'] ) && ( 'text' === $args['row_label']['type'] || 'field' === $args['row_label']['type'] ) ) {
					$this->row_label['type'] = $args['row_label']['type'];
				}
				// Validating row label type.
				if ( isset( $args['row_label']['value'] ) && ! empty( $args['row_label']['value'] ) ) {
					$this->row_label['value'] = esc_attr( $args['row_label']['value'] );
				}
				// Validating row label field.
				if ( isset( $args['row_label']['field'] ) && ! empty( $args['row_label']['field'] ) && isset( $args['fields'][ esc_attr( $args['row_label']['field'] ) ] ) ) {
					$this->row_label['field'] = esc_attr( $args['row_label']['field'] );
				} else {
					// If from field is not set correctly, making sure standard is set as the type.
					$this->row_label['type'] = 'text';
				}
			}

			if ( empty( $this->button_label ) ) {
				$this->button_label = $l10n['add-new'].' '.$this->row_label['value'];
			}

			if ( empty( $args['fields'] ) || ! is_array( $args['fields'] ) ) {
				$args['fields'] = array();
			}

			// An array to store keys of fields that need to be filtered.
			$image_fields_to_filter = array();

			foreach ( $args['fields'] as $key => $value ) {
				if ( ! isset( $value['default'] ) ) {
					$args['fields'][ $key ]['default'] = '';
				}

				if ( ! isset( $value['label'] ) ) {
					$args['fields'][ $key ]['label'] = '';
				}
				$args['fields'][ $key ]['id'] = $key;

				// We check if the filed is an image or a cropped_image.
				if ( isset( $value['type'] ) && ( 'image' === $value['type'] || 'cropped_image' === $value["type"] ) ) {
					// We add it to the list of fields that need some extra filtering/processing.
					$image_fields_to_filter[ $key ] = true;
				}
			}

			$this->fields = $args['fields'];

			// Now we are going to filter the fields

			// First we create a copy of the value that would be used otherwise
			$this->filtered_value = $this->value();

			if ( is_array( $this->filtered_value ) && ! empty( $this->filtered_value ) ) {

				// We iterate over the list of fields
				foreach ( $this->filtered_value as &$filtered_value_field ) {

					if ( is_array( $filtered_value_field ) && ! empty( $filtered_value_field ) ) {

						// We iterate over the list of properties for this field
						foreach ( $filtered_value_field as $key => &$value ) {

							// We check if this field was marked as requiring extra filtering (in this case image,cropped_images)
							if ( array_key_exists ( $key , $image_fields_to_filter ) ) {

								// What follows was made this way to preserve backward compatibility
								// The repeater control use to store the URL for images instead of the attachment ID

								// We check if the value look like an ID (otherwise it's probably a URL so don't filter it)
								if ( is_numeric( $value ) ) {
									// "sanitize" the value
									$attachment_id = (int) $value;
									// Try to get the attachment_url
									$url = wp_get_attachment_url( $attachment_id );
									// If we got a URL
									if ( $url ) {
										// 'id' is needed for form hidden value, URL is needed to display the image
										$value = array (
											'id'  => $attachment_id,
											'url' => $url,
										);
									}
								}
							}
						}
					}
				}
			}
		}

		public function to_json() {
			parent::to_json();

			$fields = $this->fields;
			$i18n   = Kirki_l10n::get_strings();
			$default_image_button_labels = array(
				'default'     => $i18n['add-image'],
				'remove'      => $i18n['remove'],
				'change'      => $i18n['change-image'],
				'placeholder' => $i18n['no-image-selected'],
			);

			$this->json['fields'] = $fields;
			$this->json['buttonLabels'] = $default_image_button_labels;
			$this->json['row_label'] = $this->row_label;

			// if filtered_value has been set and is not empty we use it instead of the actual value
			if ( is_array( $this->filtered_value ) && ! empty( $this->filtered_value ) ) {
				$this->json['value'] = $this->filtered_value;
			}
		}

		public function enqueue() {
			wp_enqueue_script( 'kirki-repeater' );
		}

		public function render_content() { ?>
			<?php $l10n = Kirki_l10n::get_strings(); ?>
			<?php if ( '' != $this->tooltip ) : ?>
				<a href="#" class="tooltip hint--left" data-hint="<?php echo esc_html( $this->tooltip ); ?>"><span class='dashicons dashicons-info'></span></a>
			<?php endif; ?>
			<label>
				<?php if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $this->description ) ) : ?>
					<span class="description customize-control-description"><?php echo $this->description; ?></span>
				<?php endif; ?>
				<input type="hidden" <?php $this->input_attrs(); ?> value="" <?php echo $this->get_link(); ?> />
			</label>

			<ul class="repeater-fields"></ul>

			<?php if ( isset( $this->choices['limit'] ) ) : ?>
				<p class="limit"><?php printf( $l10n['limit-rows'], $this->choices['limit'] ); ?></p>
			<?php endif; ?>
			<button class="button-secondary repeater-add"><?php echo esc_html( $this->button_label ); ?></button>

			<?php

			$this->repeater_js_template();

		}

		public function repeater_js_template() {
			?>
			<script type="text/html" class="customize-control-repeater-content">
				<# var field; var index = data['index']; #>


				<li class="repeater-row minimized" data-row="{{{ index }}}">

					<div class="repeater-row-header">
						<span class="repeater-row-label"></span>
						<i class="dashicons dashicons-arrow-down repeater-minimize"></i>
					</div>
					<div class="repeater-row-content">
						<# for ( i in data ) { #>
							<# if ( ! data.hasOwnProperty( i ) ) continue; #>
							<# field = data[i]; #>
							<# if ( ! field.type ) continue; #>

							<div class="repeater-field repeater-field-{{{ field.type }}}">

								<# if ( field.type === 'text' || field.type === 'url' || field.type === 'email' || field.type === 'tel' || field.type === 'date' ) { #>

									<label>
										<# if ( field.label ) { #>
											<span class="customize-control-title">{{ field.label }}</span>
										<# } #>
										<# if ( field.description ) { #>
											<span class="description customize-control-description">{{ field.description }}</span>
										<# } #>
										<input type="{{field.type}}" name="" value="{{{ field.default }}}" data-field="{{{ field.id }}}">
									</label>

								<# } else if ( field.type === 'hidden' ) { #>

									<input type="hidden" data-field="{{{ field.id }}}" <# if ( field.default ) { #> value="{{{ field.default }}}" <# } #> />

								<# } else if ( field.type === 'checkbox' ) { #>

									<label>
										<input type="checkbox" value="true" data-field="{{{ field.id }}}" <# if ( field.default ) { #> checked="checked" <# } #> />
										<# if ( field.description ) { #>
											{{ field.description }}
										<# } #>
									</label>

								<# } else if ( field.type === 'select' ) { #>

									<label>
										<# if ( field.label ) { #>
											<span class="customize-control-title">{{ field.label }}</span>
										<# } #>
										<# if ( field.description ) { #>
											<span class="description customize-control-description">{{ field.description }}</span>
										<# } #>
										<select data-field="{{{ field.id }}}">
											<# for ( i in field.choices ) { #>
												<# if ( field.choices.hasOwnProperty( i ) ) { #>
													<option value="{{{ i }}}" <# if ( field.default == i ) { #> selected="selected" <# } #>>{{ field.choices[i] }}</option>
												<# } #>
											<# } #>
										</select>
									</label>

								<# } else if ( field.type === 'radio' ) { #>

									<label>
										<# if ( field.label ) { #>
											<span class="customize-control-title">{{ field.label }}</span>
										<# } #>
										<# if ( field.description ) { #>
											<span class="description customize-control-description">{{ field.description }}</span>
										<# } #>

										<# for ( i in field.choices ) { #>
											<# if ( field.choices.hasOwnProperty( i ) ) { #>
												<label>
													<input type="radio" name="{{{ field.id }}}" data-field="{{{ field.id }}}" value="{{{ i }}}" <# if ( field.default == i ) { #> checked="checked" <# } #>> {{ field.choices[i] }} <br/>
												</label>
											<# } #>
										<# } #>
									</label>

								<# } else if ( field.type == 'radio-image' ) { #>

									<label>
										<# if ( field.label ) { #>
											<span class="customize-control-title">{{ field.label }}</span>
										<# } #>
										<# if ( field.description ) { #>
											<span class="description customize-control-description">{{ field.description }}</span>
										<# } #>

										<# for ( i in field.choices ) { #>
											<# if ( field.choices.hasOwnProperty( i ) ) { #>
												<input type="radio" id="{{{ field.id }}}_{{ index }}_{{{ i }}}" name="{{{ field.id }}}{{ index }}" data-field="{{{ field.id }}}" value="{{{ i }}}" <# if ( field.default == i ) { #> checked="checked" <# } #>>
													<label for="{{{ field.id }}}_{{ index }}_{{{ i }}}">
														<img src="{{ field.choices[i] }}">
													</label>
												</input>
											<# } #>
										<# } #>
									</label>

								<# } else if ( field.type == 'textarea' ) { #>

									<# if ( field.label ) { #>
										<span class="customize-control-title">{{ field.label }}</span>
									<# } #>
									<# if ( field.description ) { #>
										<span class="description customize-control-description">{{ field.description }}</span>
									<# } #>
									<textarea rows="5" data-field="{{{ field.id }}}">{{ field.default }}</textarea>

								<# } else if ( field.type === 'image' || field.type === 'cropped_image' ) { #>

									<label>
										<# if ( field.label ) { #>
											<span class="customize-control-title">{{ field.label }}</span>
										<# } #>
										<# if ( field.description ) { #>
											<span class="description customize-control-description">{{ field.description }}</span>
										<# } #>
									</label>

									<figure class="kirki-image-attachment" data-placeholder="{{ data.buttonLabels.placeholder }}" >
										<# if ( field.default ) { #>
											<# if ( field.default.url ) { #>
												<img src="{{{ field.default.url }}}">
											<# } else { #>
												<img src="{{{ field.default }}}">
											<# } #>
										<# } else { #>
											{{ data.buttonLabels.placeholder }}
										<# } #>
									</figure>

									<div class="actions">
										<button type="button" class="button remove-button<# if ( ! field.default ) { #> hidden<# } #>">{{ data.buttonLabels.remove }}</button>
										<button type="button" class="button upload-button" data-label="{{{ data.buttonLabels.default }}}" data-alt-label="{{{ data.buttonLabels.change }}}" >
											<# if ( field.default ) { #>
												{{ data.buttonLabels.change }}
											<# } else { #>
												{{ data.buttonLabels.default }}
											<# } #>
										</button>
										<# if ( field.default.id ) { #>
											<input type="hidden" class="hidden-field" value="{{{ field.default.id }}}" data-field="{{{ field.id }}}" >
										<# } else { #>
											<input type="hidden" class="hidden-field" value="{{{ field.default }}}" data-field="{{{ field.id }}}" >
										<# } #>
									</div>

								<# } #>

							</div>
						<# } #>
						<button type="button" class="button-link repeater-row-remove">{{ data.buttonLabels.remove }}</button>
					</div>
				</li>
			</script>
			<?php
		}

	}

}
