<?php
/**
 * Template for module-navigation block.
 *
 * @package openlab-modules
 */

$ordered_ids = isset( $args['orderedIds'] ) ? $args['orderedIds'] : [];

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

?>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<?php if ( $ordered_modules ) : ?>
	<ul class="openlab-modules-module-list">
		<?php foreach ( $ordered_modules as $module ) : ?>
			<li>
				<h2><a href="<?php echo esc_url( $module->get_url() ); ?>"><?php echo esc_html( $module->get_title() ); ?></a></h2>

				<p class="module-description">
					<?php echo esc_html( $module->get_description() ); ?>
				</p>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php elseif ( current_user_can( 'edit_others_posts' ) ) : ?>
		<p><?php esc_html_e( 'This site has no modules to display.', 'openlab-modules' ); ?></p>
	<?php endif; ?>
</div>
