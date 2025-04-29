<?php
/**
 * Admin methods for module export.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Export;

use OpenLab\Modules\Module;
use OpenLab\Modules\Editor;

/**
 * Admin class.
 */
class Admin {
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

			<form method="post" id="export-site" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
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

				<input type="hidden" name="action" value="export-site" />

				<?php wp_nonce_field( 'ol-export-site' ); ?>

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
}
