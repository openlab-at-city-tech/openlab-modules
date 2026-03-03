<?php
/**
 * Template for module-navigation block.
 *
 * @package openlab-modules
 */

$openlab_modules_ordered_ids = isset( $args['orderedIds'] ) ? $args['orderedIds'] : [];

$openlab_modules_show_author      = isset( $args['showModuleAuthor'] ) ? $args['showModuleAuthor'] : false;
$openlab_modules_show_description = isset( $args['showModuleDescription'] ) ? $args['showModuleDescription'] : false;
$openlab_modules_show_image       = isset( $args['showModuleImage'] ) ? $args['showModuleImage'] : false;

$openlab_modules_card_background_color = isset( $args['cardBackgroundColor'] ) ? $args['cardBackgroundColor'] : '#f5f5f5';

if ( ! empty( $openlab_modules_ordered_ids ) ) {
	$openlab_modules_ordered_modules = [];
	foreach ( $openlab_modules_ordered_ids as $openlab_modules_module_id ) {
		$openlab_modules_module = \OpenLab\Modules\Module::get_instance( $openlab_modules_module_id );
		if ( $openlab_modules_module ) {
			$openlab_modules_ordered_modules[] = $openlab_modules_module;
		}
	}
} else {
	$openlab_modules_ordered_modules = \OpenLab\Modules\Module::get();
}

wp_enqueue_style( 'openlab-modules-frontend' );

$openlab_modules_additional_attributes = [];
if ( $openlab_modules_card_background_color ) {
	$openlab_modules_additional_attributes['class'] = 'has-card-background-color';
	$openlab_modules_additional_attributes['style'] = '--card-background-color: ' . esc_attr( $openlab_modules_card_background_color ) . ';';
}

$openlab_modules_block_wrapper_attributes = get_block_wrapper_attributes( $openlab_modules_additional_attributes );

?>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo $openlab_modules_block_wrapper_attributes; ?>>
	<?php if ( $openlab_modules_ordered_modules ) : ?>
	<div class="openlab-modules-module-list">
		<?php foreach ( $openlab_modules_ordered_modules as $openlab_modules_module ) : ?>
			<div class="module-list-item">
				<?php if ( $openlab_modules_show_image ) : ?>
				<div class="module-list-item-image">
					<?php if ( $openlab_modules_module->get_featured_image_url() ) : ?>
						<a href="<?php echo esc_url( $openlab_modules_module->get_url() ); ?>">
							<div class="image-ratio-box">
								<img alt="<?php echo esc_attr( $openlab_modules_module->get_title() ); ?>" src="<?php echo esc_url( $openlab_modules_module->get_featured_image_url() ); ?>" />
							</div>
						</a>
					<?php else : ?>
						<div class="image-ratio-box">&nbsp;</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="module-list-item-info">
					<h2><a href="<?php echo esc_url( $openlab_modules_module->get_url() ); ?>"><?php echo esc_html( $openlab_modules_module->get_title() ); ?></a></h2>

					<?php if ( $openlab_modules_show_author && $openlab_modules_module->get_author_name() ) : ?>
						<p class="module-author"><?php echo esc_html( $openlab_modules_module->get_author_name() ); ?></p>
					<?php endif; ?>

					<?php if ( $openlab_modules_show_description ) : ?>
						<p class="module-description">
							<?php echo esc_html( $openlab_modules_module->get_description() ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php elseif ( current_user_can( 'edit_others_posts' ) ) : ?>
		<p><?php esc_html_e( 'This site has no modules to display.', 'openlab-modules' ); ?></p>
	<?php endif; ?>
</div>
