<?php
/**
 * Admin methods for module import.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Import;

use OpenLab\Modules\Schema;
use OpenLab\Modules\Editor;
use OpenLab\Modules\Logger\ServerSentEventsLogger;

use WP_Error;

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
		add_action( 'wp_ajax_openlab-modules-import', [ $this, 'stream_import' ] );
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
		$url = '';

		$step = filter_input( INPUT_GET, 'step', FILTER_VALIDATE_INT );
		if ( ! $step ) {
			$step = static::STEP_UPLOAD;
		}

		if ( static::STEP_SETTINGS === $step ) {
			$upload = $this->handle_upload();

			if ( is_wp_error( $upload ) ) {
				$this->display_error( $upload );
				return;
			}

			$decompressor = new Decompressor( $this->id );
			$extract_path = $decompressor->extract();

			if ( is_wp_error( $extract_path ) ) {
				$this->display_error( $extract_path->get_error_message() );
				return;
			}

			update_post_meta( $this->id, 'extract_path', $extract_path );
		} elseif ( static::STEP_IMPORT === $step ) {
			$import_id = filter_input( INPUT_POST, 'import_id', FILTER_VALIDATE_INT );

			if ( ! $import_id ) {
				$error = new WP_Error(
					'openlab-modules-import',
					__( 'Invalid import ID.', 'openlab-modules' ),
					[ $this->id ]
				);
				$this->display_error( $error );
				return;
			}

			$this->id = $import_id;

			$url_args = [
				'action' => 'openlab-modules-import',
				'id'     => (string) $this->id,
			];

			$url = add_query_arg( urlencode_deep( $url_args ), admin_url( 'admin-ajax.php' ) );
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

		$script_data = [
			'url'           => $url,
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
			<input type="hidden" name="import_id" value="<?php echo esc_attr( (string) $this->id ); ?>" />

			<input type="hidden" name="archive-has-attachments" value="1" />

			<?php wp_nonce_field( sprintf( 'module.import:%d', $this->id ) ); ?>
			<?php submit_button( __( 'Start Importing', 'openlab-modules' ) ); ?>
		</form>

		<?php
	}

	/**
	 * Renders the import step.
	 *
	 * @return void
	 */
	public function render_import_step() {
		?>

		<p><del><?php esc_html_e( 'Step 1: Choose and upload your Module Export file (.zip).', 'openlab-modules' ); ?></del></p>
		<p><del><?php esc_html_e( 'Step 2: Import the Module Export file', 'openlab-modules' ); ?></del></p>
		<p id="import-status-message"><strong><?php esc_html_e( 'Step 3: Now importing.', 'openlab-modules' ); ?></strong></p>

		<input type="hidden" name="import_id" value="<?php echo esc_attr( (string) $this->id ); ?>" />

		<input type="hidden" name="archive-has-attachments" value="1" />

		<table id="import-log" class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'openlab-modules' ); ?></th>
					<th><?php esc_html_e( 'Message', 'openlab-modules' ); ?></th>
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>

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
	 * Handles archive upload.
	 *
	 * @return \WP_Error|bool
	 */
	protected function handle_upload() {
		check_admin_referer( 'openlab-modules-import-upload' );

		$uploader = new ArchiveUpload( 'importzip' );
		$id       = $uploader->handle();

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$this->id = $id;

		return true;
	}

	/**
	 * Run an import, and send an event-stream response.
	 *
	 * @return void
	 */
	public function stream_import() {
		// Turn off PHP output compression.
		// phpcs:disable
		$previous = error_reporting( error_reporting() ^ E_WARNING );
		ini_set( 'output_buffering', 'off' );
		ini_set( 'zlib.output_compression', false );
		error_reporting( $previous );
		// phpcs:enable

		if ( $GLOBALS['is_nginx'] ) {
			// Setting this header instructs Nginx to disable fastcgi_buffering
			// and disable gzip for this request.
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
		}

		// Start the event stream.
		header( 'Content-Type: text/event-stream' );

		// phpcs:ignore WordPress.Security.NonceVerification
		$this->id = isset( $_REQUEST['id'] ) && is_numeric( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

		if ( ! $this->id ) {
			// Tell the browser to stop reconnecting.
			status_header( 204 );
			exit;
		}

		// 2KB padding for IE
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n";

		// Time to run the import!
		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		$extract_path = get_post_meta( $this->id, 'extract_path', true );
		if ( ! is_string( $extract_path ) ) {
			// Tell the browser to stop reconnecting.
			status_header( 204 );
			exit;
		}

		// Skip processing author data.
		add_filter( 'wxr_importer.pre_process.user', '__return_null' );

		$importer = $this->get_importer( $this->id );
		$status   = $importer->import( $extract_path . '/wordpress.xml' );

		// Clean up.
		$decompressor = new Decompressor( $this->id );
		$decompressor->cleanup();
		$this->id = 0;

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
	 * @param int $archive_id Archive ID.
	 * @return Importer
	 */
	protected function get_importer( $archive_id ) {
		$extract_path    = get_post_meta( $archive_id, 'extract_path', true );
		$attachment_mode = get_post_meta( $archive_id, 'attachment_mode', true );

		$options = [
			'fetch_attachments'         => true,
			'prefill_existing_posts'    => false,
			'prefill_existing_commetns' => false,
			'prefill_existing_terms'    => false,
			'attachment_mode'           => $attachment_mode,
			'aggressive_url_search'     => true,
			'default_author'            => get_current_user_id(),
		];

		$importer = new Importer( $options, $extract_path );
		$logger   = new ServerSentEventsLogger();
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

	/**
	 * Display import process errors.
	 *
	 * @param WP_Error $error
	 * @return void
	 */
	protected function display_error( WP_Error $error ) {
		extract( [ 'error' => $error ], EXTR_SKIP );

		var_dump( $error );
	}
}
