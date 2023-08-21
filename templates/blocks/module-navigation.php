<?php
/**
 * Template for module-navigation block.
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

$module_post_status = get_post_status( $module_id );

$module_page_ids = $module->get_page_ids( 'publish' );

wp_enqueue_style( 'openlab-modules-frontend' );

?>

<?php if ( 'publish' === $module_post_status || current_user_can( 'edit_others_posts' ) ) : ?>
	<div class="wp-block-openlab-modules-module-navigation">
		<?php if ( 'publish' !== $module_post_status ) : ?>
			<p><strong><?php esc_html_e( 'The module associated with this navigation block is not published, and the navigation will not be visible to normal users.', 'openlab-modules' ); ?></strong></p>
		<?php endif; ?>

		<p class="openlab-modules-module-navigation-heading">
			<?php
			printf(
				// translators: Module link or title.
				esc_html__( 'Contents for Module: %s', 'openlab-modules' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $module->get_url() ),
					esc_html( $module->get_title() )
				)
			);
			?>
		</p>

		<ul class="openlab-modules-module-navigation-list">
			<?php
			$module_home_current_class = get_queried_object_id() === $module_id ? 'is-current' : '';
			?>
			<li class="<?php echo esc_attr( $module_home_current_class ); ?>">
				<a href="<?php the_permalink( $module_id ); ?>"><?php esc_html_e( 'Module Home', 'openlab-modules' ); ?></a>
			</li>

			<?php foreach ( $module_page_ids as $module_page_id ) : ?>
				<?php
				$module_page = get_post( $module_page_id );

				$is_current_class = get_queried_object_id() === $module_page_id ? 'is-current' : '';

				?>
				<li class="<?php echo esc_attr( $is_current_class ); ?>">
					<a href="<?php the_permalink( $module_page_id ); ?>"><?php echo esc_html( get_the_title( $module_page_id ) ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
