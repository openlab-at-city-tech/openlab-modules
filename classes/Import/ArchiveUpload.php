<?php
/**
 * Import Upload handler.
 *
 * Based on `File_Upload_Upgrader`.
 *
 * @see https://developer.wordpress.org/reference/classes/file_upload_upgrader/.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Import;

use WP_Error;

/**
 * Class ArchiveUpload.
 */
class ArchiveUpload {

	/**
	 * Supported archive extensions
	 *
	 * @var array
	 */
	private $supported_archives = [
		'zip',
	];

	/**
	 * The full path to the file package.
	 *
	 * @var string
	 */
	public $package;

	/**
	 * The name of the file.
	 *
	 * @var string $filename
	 */
	public $filename;

	/**
	 * The ID of the attachment post for this file.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Name for the form.
	 *
	 * @var string
	 */
	public $form;

	/**
	 * Construct the uploader for a form.
	 *
	 * @param string $form The name of the form the file was uploaded from.
	 */
	public function __construct( $form ) {
		$this->form = $form;
	}

	/**
	 * Handle file upload.
	 *
	 * @return WP_Error|int
	 */
	public function handle() {
		// phpcs:disable WordPress.Security.NonceVerification

		if ( empty( $_FILES[ $this->form ] ) || ! is_array( $_FILES[ $this->form ] ) ) {
			return new WP_Error( 'import.upload.handle', 'No file was uploaded.' );
		}

		if ( empty( $_FILES[ $this->form ]['name'] ) ) {
			return new WP_Error( 'import.upload.handle', 'Please select an OpenLab site archive file.' );
		}

		$file_path_info = is_string( $_FILES[ $this->form ]['name'] ) ? pathinfo( sanitize_text_field( wp_unslash( $_FILES[ $this->form ]['name'] ) ) ) : [];
		_b( $file_path_info );

		if ( ! $this->is_archive( $file_path_info ) ) {
			return new WP_Error( 'import.upload.handle', 'Incorrect format. Please choose an OpenLab site archive file.' );
		}

		$overrides = [
			'test_form' => false,
			'test_type' => false,
		];

		// @phpstan-ignore-next-line
		$file = wp_handle_upload( $_FILES[ $this->form ], $overrides );
		_b( $file );

		if ( isset( $file['error'] ) ) {
			return new WP_Error( 'import.upload.handle', $file['error'] );
		}

		$this->filename = sanitize_text_field( wp_unslash( $_FILES[ $this->form ]['name'] ) );
		$this->package  = $file['file'];

		// Construct the object array.
		$attachment = [
			'post_title'     => $this->filename,
			'post_content'   => $file['url'],
			'post_mime_type' => $file['type'],
			'guid'           => $file['url'],
			'context'        => 'import',
			'post_status'    => 'private',
		];

		// Save the data.
		$this->id = wp_insert_attachment( $attachment, $file['file'] );

		// phpcs:enable WordPress.Security.NonceVerification

		return $this->id;
	}

	/**
	 * Check if uploaded file is archive and is supported.
	 *
	 * @param array<string> $path_parts Path parts of the file.
	 * @return bool
	 */
	public function is_archive( $path_parts ) {
		if ( ! isset( $path_parts['extension'] ) ) {
			return false;
		}

		return in_array( $path_parts['extension'], $this->supported_archives, true );
	}
}
