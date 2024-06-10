<?php
/**
 * Sharing block template.
 *
 * @package openlab-modules
 */

// Only show to users who are logged in.
if ( ! is_user_logged_in() ) {
	return;
}

wp_enqueue_style( 'openlab-modules-frontend' );
wp_enqueue_script( 'openlab-modules-frontend' );

$block_unique_id = 'clone-module-' . uniqid();

// @todo This should be an attribute of the block.
$module_id = 0;
if ( \OpenLab\Modules\Schema::get_module_post_type() === get_post_type() ) {
	$is_module = true;
	$module_id = get_queried_object_id();
} else {
	$is_module  = false;
	$module_ids = \OpenLab\Modules\Module::get_module_ids_of_page( get_queried_object_id() );
	if ( $module_ids ) {
		$module_id = $module_ids[0];
	}
}

if ( ! $module_id ) {
	return;
}

// Don't show if sharing is disabled.
$module = \OpenLab\Modules\Module::get_instance( $module_id );
if ( ! $module || ! $module->is_sharing_enabled() ) {
	return;
}

?>

<div id="clone-module-container-<?php echo esc_attr( $block_unique_id ); ?>" class="clone-module-container" data-uniqid="<?php echo esc_attr( $block_unique_id ); ?>" data-module-id="<?php echo esc_attr( (string) $module_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'openlab-module-clone' ) ); ?>"></div>
