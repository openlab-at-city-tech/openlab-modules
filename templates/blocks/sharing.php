<?php
/**
 * Sharing block template.
 *
 * @package openlab-modules
 */

wp_enqueue_style( 'openlab-modules-frontend' );
wp_enqueue_script( 'openlab-modules-frontend' );

$block_unique_id = 'clone-module-' . uniqid();

?>

<div id="clone-module-container-<?php echo esc_attr( $block_unique_id ); ?>" class="clone-module-container" data-uniqid="<?php echo esc_attr( $block_unique_id ); ?>"></div>
