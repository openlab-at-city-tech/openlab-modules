<?php
/**
 * Template for module-attribution block.
 *
 * @package openlab-modules
 */

$module_id = (int) $args['moduleId'];

if ( ! $module_id ) {
	return;
}

$module = \OpenLab\Modules\Module::get_instance( $module_id );
if ( ! $module ) {
	return;
}

$attribution_text = $module->get_attribution_text();

wp_enqueue_style( 'openlab-modules-frontend' );

?>

<div class="wp-block-openlab-modules-module-attribution">
	<?php if ( $attribution_text ) : ?>
		<p class="openlab-modules-module-attribution-text"><?php echo wp_kses_post( $attribution_text ); ?></p>
	<?php endif; ?>
</div>
