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

$module_page_ids = $module->get_page_ids();

wp_enqueue_style( 'openlab-modules-frontend' );

?>

<div class="wp-block-openlab-modules-module-navigation">
	<p class="openlab-modules-module-navigation-heading">
		<?php
		echo sprintf(
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
