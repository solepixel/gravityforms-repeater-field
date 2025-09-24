<?php
/**
 * Repeater Setting Template
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field = $this->get_current_field();
$setting = $this->get_repeater_setting();
?>

<tr>
	<td class="repeater_setting_label">
		<label for="<?php echo esc_attr( $setting['name'] ); ?>">
			<?php echo esc_html( $setting['label'] ); ?>
		</label>
	</td>
	<td class="repeater_setting_input">
		<?php foreach ( $setting['choices'] as $choice ) : ?>
			<label for="<?php echo esc_attr( $choice['name'] ); ?>">
				<input
					type="checkbox"
					id="<?php echo esc_attr( $choice['name'] ); ?>"
					name="<?php echo esc_attr( $choice['name'] ); ?>"
					value="1"
					<?php checked( rgar( $field, $choice['name'] ), '1' ); ?>
				/>
				<?php echo esc_html( $choice['label'] ); ?>
			</label>
		<?php endforeach; ?>
		<div class="gf-repeater-setting-description">
			<?php _e( 'When enabled, users can add multiple instances of this section with Add/Remove controls.', 'gravityforms-repeater-field' ); ?>
		</div>
	</td>
</tr>
