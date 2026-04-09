<?php
/**
 * Sharing block template.
 *
 * @package openlab-module-builder
 */

defined( 'ABSPATH' ) || exit;

// Only show to users who are logged in.
if ( ! is_user_logged_in() ) {
	return;
}

wp_enqueue_style( 'openlab-module-builder-frontend' );
wp_enqueue_script( 'openlab-module-builder-frontend' );

$openlab_modules_block_unique_id = 'clone-module-' . uniqid();

// @todo This should be an attribute of the block.
$openlab_modules_module_id = 0;
if ( \OpenLab\Modules\Schema::get_module_post_type() === get_post_type() ) {
	$openlab_modules_is_module = true;
	$openlab_modules_module_id = get_queried_object_id();
} else {
	$openlab_modules_is_module  = false;
	$openlab_modules_module_ids = \OpenLab\Modules\Module::get_module_ids_of_page( get_queried_object_id() );
	if ( $openlab_modules_module_ids ) {
		$openlab_modules_module_id = $openlab_modules_module_ids[0];
	}
}

if ( ! $openlab_modules_module_id ) {
	return;
}

// Don't show if sharing is disabled.
$openlab_modules_module = \OpenLab\Modules\Module::get_instance( $openlab_modules_module_id );
if ( ! $openlab_modules_module || ! $openlab_modules_module->is_sharing_enabled() ) {
	return;
}

?>

<div id="clone-module-container-<?php echo esc_attr( $openlab_modules_block_unique_id ); ?>" class="clone-module-container" data-uniqid="<?php echo esc_attr( $openlab_modules_block_unique_id ); ?>" data-module-id="<?php echo esc_attr( (string) $openlab_modules_module_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'openlab-module-clone' ) ); ?>"></div>
