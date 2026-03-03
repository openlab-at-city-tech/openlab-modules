<?php
/**
 * Template for module-navigation block.
 *
 * @package openlab-modules
 */

$openlab_modules_module_id = (int) $args['moduleId'];

$openlab_modules_show_module_description = isset( $args['showModuleDescription'] ) ? (bool) $args['showModuleDescription'] : false;

$openlab_modules_list_style = isset( $args['listStyle'] ) ? $args['listStyle'] : 'unordered';

if ( ! $openlab_modules_module_id ) {
	return;
}

$openlab_modules_module = \OpenLab\Modules\Module::get_instance( $openlab_modules_module_id );
if ( ! $openlab_modules_module ) {
	return;
}

$openlab_modules_module_post_status = get_post_status( $openlab_modules_module_id );

$openlab_modules_module_page_ids = $openlab_modules_module->get_page_ids( 'publish' );

wp_enqueue_style( 'openlab-modules-frontend' );

?>

<?php if ( 'publish' === $openlab_modules_module_post_status || current_user_can( 'edit_others_posts' ) ) : ?>
	<?php /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
	<div <?php echo get_block_wrapper_attributes(); ?>>
		<?php if ( 'publish' !== $openlab_modules_module_post_status ) : ?>
			<p><strong><?php esc_html_e( 'The module associated with this navigation block is not published, and the navigation will not be visible to normal users.', 'openlab-modules' ); ?></strong></p>
		<?php endif; ?>

		<h2 class="openlab-modules-module-navigation-heading">
			<?php
			printf(
				// translators: Module link or title.
				esc_html__( 'MODULE: %s', 'openlab-modules' ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $openlab_modules_module->get_url() ),
					esc_html( $openlab_modules_module->get_title() )
				)
			);
			?>
		</h2>

		<?php if ( $openlab_modules_show_module_description && $openlab_modules_module->get_description() ) : ?>
			<p class="openlab-modules-module-description">
				<?php echo esc_html( $openlab_modules_module->get_description() ); ?>
			</p>
		<?php endif; ?>

		<?php if ( 'ordered' === $openlab_modules_list_style ) : ?>
			<ol class="openlab-modules-module-navigation-list">
				<?php
				$openlab_modules_module_home_current_class = get_queried_object_id() === $openlab_modules_module_id ? 'is-current' : '';
				?>
				<li class="<?php echo esc_attr( $openlab_modules_module_home_current_class ); ?>">
					<a href="<?php the_permalink( $openlab_modules_module_id ); ?>"><?php echo esc_html( $openlab_modules_module->get_nav_title() ); ?></a>
				</li>

				<?php foreach ( $openlab_modules_module_page_ids as $openlab_modules_module_page_id ) : ?>
					<?php
					$openlab_modules_module_page = get_post( $openlab_modules_module_page_id );

					$openlab_modules_is_current_class = get_queried_object_id() === $openlab_modules_module_page_id ? 'is-current' : '';

					?>
					<li class="<?php echo esc_attr( $openlab_modules_is_current_class ); ?>">
						<a href="<?php the_permalink( $openlab_modules_module_page_id ); ?>"><?php echo esc_html( get_the_title( $openlab_modules_module_page_id ) ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<ul class="openlab-modules-module-navigation-list">
				<?php
				$openlab_modules_module_home_current_class = get_queried_object_id() === $openlab_modules_module_id ? 'is-current' : '';
				?>
				<li class="<?php echo esc_attr( $openlab_modules_module_home_current_class ); ?>">
					<a href="<?php the_permalink( $openlab_modules_module_id ); ?>"><?php echo esc_html( $openlab_modules_module->get_nav_title() ); ?></a>
				</li>

				<?php foreach ( $openlab_modules_module_page_ids as $openlab_modules_module_page_id ) : ?>
					<?php
					$openlab_modules_module_page = get_post( $openlab_modules_module_page_id );

					$openlab_modules_is_current_class = get_queried_object_id() === $openlab_modules_module_page_id ? 'is-current' : '';

					?>
					<li class="<?php echo esc_attr( $openlab_modules_is_current_class ); ?>">
						<a href="<?php the_permalink( $openlab_modules_module_page_id ); ?>"><?php echo esc_html( get_the_title( $openlab_modules_module_page_id ) ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
<?php endif; ?>
