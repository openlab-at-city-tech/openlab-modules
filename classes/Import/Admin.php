<?php
/**
 * Admin methods for module import.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Import;

use OpenLab\Modules\Schema;
use OpenLab\Modules\Editor;

/**
 * Admin class.
 */
class Admin {
	const STEP_UPLOAD = 0;

	const STEP_SETTINGS = 1;

	const STEP_IMPORT = 2;

	/**
	 * Import ID.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Initializes the class.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_submenu' ] );
		add_action( 'wp_ajax_openlab-import-export-import', [ $this, 'stream_import' ] );
	}

	/**
	 * Adds the 'Module Import' item to the 'Modules' menu.
	 *
	 * @return void
	 */
	public function add_submenu() {
		$parent = 'edit.php?post_type=' . Schema::get_module_post_type();
		add_submenu_page(
			$parent,
			__( 'Module Import', 'openlab-modules' ),
			__( 'Module Import', 'openlab-modules' ),
			'manage_options',
			Schema::get_module_post_type() . '-import',
			[ $this, 'render_import_page' ]
		);
	}

	/**
	 * Renders the Module Import admin page.
	 *
	 * @return void
	 */
	public function render_import_page() {
		$step = filter_input( INPUT_GET, 'step', FILTER_VALIDATE_INT );
		if ( ! $step ) {
			$step = static::STEP_UPLOAD;
		}

		$blocks_asset_file = Editor::get_blocks_asset_file();

		wp_enqueue_script(
			'openlab-modules-import',
			OPENLAB_MODULES_PLUGIN_URL . '/build/import.js',
			[ 'wp-i18n', 'jquery' ],
			$blocks_asset_file['version'],
			true
		);

		$max_upload_size   = wp_max_upload_size();
		$max_upload_size_h = ( ceil( $max_upload_size / ( 1000 * 10 ) ) / 100 ) . ' MB';

		// phpcs:ignore WordPress.Security.NonceVerification
		$import_id = isset( $_POST['import_id'] ) && is_numeric( $_POST['import_id'] ) ? intval( $_POST['import_id'] ) : 0;
		$url_args  = [
			'action' => 'openlab-import-export-import',
			'id'     => $import_id,
		];

		$script_data = [
			'url'           => add_query_arg( urlencode_deep( $url_args ), admin_url( 'admin-ajax.php' ) ),
			'maxUploadSize' => $max_upload_size,
			'strings'       => [
				// translators: %s is the max upload size.
				'errorSize' => sprintf( __( 'File too large. Max upload size is %s.', 'openlab-modules' ), $max_upload_size_h ),
			],
		];

		wp_add_inline_script(
			'openlab-modules-import',
			'var openlabModulesImport = ' . wp_json_encode( $script_data ) . ';',
			'before'
		);

		?>

		<div class="wrap nosubsub">
			<h1><?php esc_html_e( 'Import Module', 'openlab-modules' ); ?></h1>

			<?php settings_errors(); ?>

			<p><?php esc_html_e( 'Use this tool to import a previously exported OpenLab Module Archive.', 'openlab-modules' ); ?></p>

			<p><?php echo wp_kses_post( __( '<strong>Before you begin:</strong> The Module Archive contains a readme file with information about plugins you may want to activate before performing the import, as well as other information about the import. Please read through the file before beginning the import.', 'openlab-modules' ) ); ?></p>

			<?php
			switch ( $step ) {
				case static::STEP_UPLOAD:
					$this->render_upload_step();
					break;
				case static::STEP_SETTINGS:
					$this->render_settings_step();
					break;
				case static::STEP_IMPORT:
					$this->render_import_step();
					break;
			}
			?>
		</div>

		<?php
	}

	/**
	 * Renders the upload step.
	 *
	 * @return void
	 */
	public function render_upload_step() {
		?>
		<p><strong><?php esc_html_e( 'Step 1: Choose and upload your Module Export file (.zip).', 'openlab-modules' ); ?></strong></p>

		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( self::get_url( 1 ) ); ?>">
			<input type="hidden" name="action" value="import-upload" />
			<?php wp_nonce_field( 'openlab-modules-import-upload' ); ?>

			<label class="screen-reader-text" for="importzip"><?php esc_html_e( 'Import zip file', 'openlab-modules' ); ?></label>
			<input type="file" id="importzip" name="importzip" />
			<div id="ol-import-error" class="ol-import-error"></div>

			<?php submit_button( __( 'Upload Module Export File', 'openlab-modules' ), 'primary', 'upload-submit' ); ?>
		</form>
		<?php
	}

	/**
	 * Renders the settings step.
	 *
	 * @return void
	 */
	public function render_settings_step() {
		?>

		<p><del><?php esc_html_e( 'Step 1: Choose and upload your Module Export file (.zip).', 'openlab-modules' ); ?></del></p>
		<p><strong><?php esc_html_e( 'Step 2: Import the Module Export file', 'openlab-modules' ); ?></strong></p>

		<form method="post" action="<?php echo esc_url( self::get_url( 2 ) ); ?>">
			<input type="hidden" name="import_id" value="<?php echo esc_attr( $this->id ); ?>" />

			<?php if ( $this->archive_has_attachments ) : ?>

				<input type="hidden" name="archive-has-attachments" value="1" />

			<?php else : ?>

				<p><?php esc_html_e( 'This archive file does not contain any media files. During the import process, the importer will attempt to copy media files from the original site.', 'openlab-import-export' ); ?></p>

				<p><?php _e( '<strong>Please note</strong>: the original site must be publicly accessible in order to import the media files. If the site is not public, before continuing, please change privacy settings to public on the original site or contact the site owner in order to complete the import process for media files.', 'openlab-import-export' ); ?></p>
			<?php endif; ?>

			<?php wp_nonce_field( sprintf( 'module.import:%d', $this->id ) ); ?>
			<?php submit_button( __( 'Start Importing', 'openlab-modules' ) ); ?>
		</form>

		<?php
	}

	/**
	 * Get URL for the importer.
	 *
	 * @param int $step Step number.
	 * @return string
	 */
	public static function get_url( $step = 0 ) {
		$query_args = [
			'post_type' => Schema::get_module_post_type(),
			'page'      => Schema::get_module_post_type() . '-import',
		];

		if ( $step ) {
			$query_args['step'] = (int) $step;
		}

		$path = add_query_arg( $query_args, 'edit.php' );

		return admin_url( $path );
	}

	/**
	 * Run an import, and send an event-stream response.
	 *
	 * @return void
	 */
	public function stream_import() {
		// Turn off PHP output compression
		$previous = error_reporting( error_reporting() ^ E_WARNING );
		ini_set( 'output_buffering', 'off' );
		ini_set( 'zlib.output_compression', false );
		error_reporting( $previous );

		if ( $GLOBALS['is_nginx'] ) {
			// Setting this header instructs Nginx to disable fastcgi_buffering
			// and disable gzip for this request.
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
		}

		// Start the event stream.
		header( 'Content-Type: text/event-stream' );

		$this->id = wp_unslash( (int) $_REQUEST['id'] );

		if ( ! isset( $this->id ) ) {
			// Tell the browser to stop reconnecting.
			status_header( 204 );
			exit;
		}

		// 2KB padding for IE
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n";

		// Time to run the import!
		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		$extract_path = get_post_meta( $this->id, 'extract_path', true );

		// Skip processing author data.
		add_filter( 'wxr_importer.pre_process.user', '__return_null' );

		$importer = $this->get_importer( $this->id );
		$status   = $importer->import( $extract_path . '/wordpress.xml' );

		// Clean up.
		$decompressor = new Decompressor( $this->id );
		$decompressor->cleanup();
		unset( $this->id );

		// Let the browser know we're done.
		$complete = [
			'action' => 'complete',
			'error'  => false,
		];

		if ( is_wp_error( $status ) ) {
			$complete['error'] = $status->get_error_message();
		}

		$this->emit_sse_message( $complete );
		exit;
	}

	/**
	 * Get the importer instance.
	 *
	 * @param string $archive_id Archive ID.
	 * @return Importer
	 */
	protected function get_importer( $archive_id ) {
		$extract_path    = get_post_meta( $archive_id, 'extract_path', true );
		$attachment_mode = get_post_meta( $archive_id, 'attachment_mode', true );
		$options = [
			'fetch_attachments'     => true,
			'attachment_mode'       => $attachment_mode,
			'aggressive_url_search' => true,
			'default_author'        => get_current_user_id(),
		];

		$importer = new Importer( $options, $extract_path );
		$logger   = new ServerSentEventsLogger;
		$importer->set_logger( $logger );

		return $importer;
	}

	/**
	 * Emit a Server-Sent Events message.
	 *
	 * @param mixed $data Data to be JSON-encoded and sent in the message.
	 */
	protected function emit_sse_message( $data ) {
		echo "event: message\n";
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";

		// Extra padding.
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n";

		flush();
	}
}
