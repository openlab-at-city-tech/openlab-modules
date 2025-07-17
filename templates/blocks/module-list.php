<?php
/**
 * Template for module-navigation block.
 *
 * @package openlab-modules
 */

$ordered_ids = isset( $args['orderedIds'] ) ? $args['orderedIds'] : [];

$show_author      = isset( $args['showModuleAuthor'] ) ? $args['showModuleAuthor'] : false;
$show_description = isset( $args['showModuleDescription'] ) ? $args['showModuleDescription'] : false;
$show_image       = isset( $args['showModuleImage'] ) ? $args['showModuleImage'] : false;

$card_background_color = isset( $args['cardBackgroundColor'] ) ? $args['cardBackgroundColor'] : '#f5f5f5';

if ( ! empty( $ordered_ids ) ) {
	$ordered_modules = [];
	foreach ( $ordered_ids as $module_id ) {
		$module = \OpenLab\Modules\Module::get_instance( $module_id );
		if ( $module ) {
			$ordered_modules[] = $module;
		}
	}
} else {
	$ordered_modules = \OpenLab\Modules\Module::get();
}

wp_enqueue_style( 'openlab-modules-frontend' );

$additional_attributes = [];
if ( $card_background_color ) {
	$additional_attributes['class'] = 'has-card-background-color';
	$additional_attributes['style'] = '--card-background-color: ' . esc_attr( $card_background_color ) . ';';
}

$block_wrapper_attributes = get_block_wrapper_attributes( $additional_attributes );

?>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo $block_wrapper_attributes; ?>>
	<?php if ( $ordered_modules ) : ?>
	<div class="openlab-modules-module-list">
		<?php foreach ( $ordered_modules as $module ) : ?>
			<div class="module-list-item">
				<?php if ( $show_image ) : ?>
				<div class="module-list-item-image">
					<a href="<?php echo esc_url( $module->get_url() ); ?>">
						<div class="image-ratio-box">
							<img alt="<?php echo esc_attr( $module->get_title() ); ?>" src="<?php echo esc_url( $module->get_featured_image_url() ); ?>" />
						</div>
					</a>
				</div>
				<?php endif; ?>

				<div class="module-list-item-info">
					<h2><a href="<?php echo esc_url( $module->get_url() ); ?>"><?php echo esc_html( $module->get_title() ); ?></a></h2>

					<?php if ( $show_author && $module->get_author_name() ) : ?>
						<p class="module-author"><?php echo esc_html( $module->get_author_name() ); ?></p>
					<?php endif; ?>

					<?php if ( $show_description ) : ?>
						<p class="module-description">
							<?php echo esc_html( $module->get_description() ); ?>
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
