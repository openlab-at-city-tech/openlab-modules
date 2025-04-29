<?php
/**
 * Admin methods for module export.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Export;

use OpenLab\Modules\Editor;
use OpenLab\Modules\Module;
use OpenLab\Modules\Schema;

/**
 * Admin class.
 */
class Admin {

	/**
	 * Initializes the class.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'admin_post_export-module', [ $this, 'handle' ] );
	}

	/**
	 * Adds the 'Module Export' to the 'Modules' menu.
	 *
	 * @return void
	 */
	public function add_submenu() {
		$parent = 'edit.php?post_type=' . Schema::get_module_post_type();
		add_submenu_page(
			$parent,
			__( 'Module Export', 'openlab-modules' ),
			__( 'Export Modules', 'openlab-modules' ),
			'manage_options',
			Schema::get_module_post_type() . '-export',
			[ __CLASS__, 'render_export_page' ]
		);
	}

	/**
	 * Renders the Export Modules admin page.
	 *
	 * @return void
	 */
	public static function render_export_page() {
		$blocks_asset_file = Editor::get_blocks_asset_file();

		wp_enqueue_script(
			'openlab-modules-export',
			OPENLAB_MODULES_PLUGIN_URL . '/build/export.js',
			[ 'wp-i18n' ],
			$blocks_asset_file['version'],
			true
		);

		$all_modules = Module::get();

		$module_data = [];
		foreach ( $all_modules as $module ) {
			$attribution_data = $module->get_raw_attribution_data();

			$module_id = $module->get_id();

			$module_data[] = [
				'id'          => $module_id,
				'title'       => $module->get_title(),
				'url'         => $module->get_url(),
				'author_name' => $attribution_data['user_name'],
			];
		}

		$script_data = [
			'modules' => $module_data,
		];

		wp_add_inline_script(
			'openlab-modules-export',
			sprintf(
				'var openlabModulesExport = %s;',
				wp_json_encode( $script_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
			),
			'before'
		);

		?>

		<div class="wrap nosubsub">
			<h1><?php esc_html_e( 'Export', 'openlab-modules' ); ?></h1>

			<?php settings_errors(); ?>

			<p><?php esc_html_e( 'Use this tool to create a Module Archive file (.zip) that will be downloaded to your computer and can be used with the Module Export Import plugin to import into another site.', 'openlab-modules' ); ?></p>

			<form method="post" id="export-module" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<h2><?php esc_html_e( 'Choose what to export', 'openlab-modules' ); ?></h2>

				<p><?php esc_html_e( 'Please choose the module you would like to export.', 'openlab-modules' ); ?></p>

				<label for="module-select"><?php esc_html_e( 'Modules', 'openlab-modules' ); ?></label>
				<select name="module-select" id="module-select">
					<option value="" disabled="disabled" selected="selected"><?php esc_html_e( 'Please Select', 'openlab-modules' ); ?></option>

					<?php
					foreach ( $all_modules as $module ) {
						?>
						<option value="<?php echo esc_attr( (string) $module->get_id() ); ?>"><?php echo esc_html( $module->get_title() ); ?></option>
						<?php
					}
					?>
				</select>

				<h2><?php esc_html_e( 'Readme file', 'openlab-modules' ); ?></h2>

				<p id="readme-description"><?php esc_html_e( 'A readme text file will be included with the exported archive file. It will include information on how this archive file can be imported into another site. You can also include your own custom text in the box below.', 'openlab-modules' ); ?></p>

				<label for="readme-additional-text" class="screen-reader-text"><?php esc_html_e( 'Additional text for readme file', 'openlab-modules' ); ?></label>

				<textarea class="widefat" name="readme-additional-text" id="readme-additional-text" aria-describedby="readme-description"></textarea>

				<h2><?php esc_html_e( 'Acknowledgements', 'openlab-modules' ); ?></h2>

				<p id="acknowledgements-description"><?php esc_html_e( 'The text below will be included in an acknowledgments block on the module home on any site that imports your module’s contents. You can edit the acknowledgments below, if necessary.', 'openlab-modules' ); ?></p>

				<label for="acknowledgements-text" class="screen-reader-text"><?php esc_html_e( 'Acknowledgments text', 'openlab-modules' ); ?></label>

				<textarea class="widefat" name="acknowledgements-text" id="acknowledgements-text" aria-describedby="acknowledgements-description"></textarea>

				<input type="hidden" name="action" value="export-module" />

				<?php wp_nonce_field( 'openlab-modules-export' ); ?>

				<br />

				<div class="archive-download-type-button">
					<?php
					submit_button(
						__( 'Download Archive File', 'openlab-modules' ),
						'primary large',
						'download-archive-file'
					);
					?>
				</div>
			</form>
		</div>

		<?php
	}

	/**
	 * Handles the export request.
	 *
	 * @return void
	 */
	public function handle() {
		check_admin_referer( 'openlab-modules-export' );

		$exporter = new Exporter( wp_get_upload_dir() );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$module_id_raw = isset( $_POST['module-select'] ) ? wp_unslash( $_POST['module-select'] ) : '';
		$module_id     = is_numeric( $module_id_raw ) ? (int) $module_id_raw : 0;

		$referer = wp_get_referer();
		if ( ! $referer ) {
			$referer = admin_url( 'edit.php?post_type=' . Schema::get_module_post_type() );
		}

		if ( empty( $module_id ) ) {
			add_settings_error(
				'failed_export',
				'failed_export',
				__( 'Please select a module to export.', 'openlab-modules' )
			);

			wp_safe_redirect( $referer );
			exit;
		}

		$exporter->set_module_id( $module_id );

		if ( ! empty( $_POST['readme-additional-text'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$additional_text_raw = wp_unslash( $_POST['readme-additional-text'] );
			$additional_text     = is_string( $additional_text_raw ) ? sanitize_textarea_field( $additional_text_raw ) : '';

			$exporter->add_readme_custom_text( $additional_text );
		}

		if ( ! empty( $_POST['acknowledgements-text'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$acknowledgements_text_raw = wp_unslash( $_POST['acknowledgements-text'] );
			$acknowledgements_text     = is_string( $acknowledgements_text_raw ) ? wp_kses_post( $acknowledgements_text_raw ) : '';

			$exporter->add_acknowledgements_text( $acknowledgements_text );
		}

		wp_ob_end_flush_all();
		$filename = $exporter->run();

		if ( is_wp_error( $filename ) ) {
			add_settings_error(
				'failed_export',
				'failed_export',
				$filename->get_error_message()
			);

			wp_safe_redirect( $referer );
			exit;
		}

		header( 'Content-type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . basename( $filename ) . '"' );
		header( 'Content-length: ' . filesize( $filename ) );
		readfile( $filename );

		// Remove file.
		unlink( $filename );

		exit;
	}
}
