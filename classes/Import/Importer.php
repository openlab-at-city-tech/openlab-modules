<?php
/**
 * Main importer class.
 *
 * Based on WordPress Importer Redux.
 *
 * @see https://github.com/humanmade/WordPress-Importer
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules\Import;

use WP_Error;
use XMLReader;
use OpenLab\Modules\Logger\Logger;
use OpenLab\Modules\Module;

/**
 * Importer class.
 */
class Importer {
	/**
	 * Maximum supported WXR version
	 */
	const MAX_WXR_VERSION = '1.2';

	/**
	 * Regular expression for checking if a post references an attachment
	 *
	 * Note: This is a quick, weak check just to exclude text-only posts. More
	 * vigorous checking is done later to verify.
	 */
	const REGEX_HAS_ATTACHMENT_REFS = '!
		(
			# Match anything with an image or attachment class
			class=[\'"].*?\b(wp-image-\d+|attachment-[\w\-]+)\b
		|
			# Match anything that looks like an upload URL
			src=[\'"][^\'"]*(
				[0-9]{4}/[0-9]{2}/[^\'"]+\.(jpg|jpeg|png|gif)
			|
				content/uploads[^\'"]+
			)[\'"]
		)!ix';

	/**
	 * Version of WXR we're importing.
	 *
	 * Defaults to 1.0 for compatibility. Typically overridden by a
	 * `<wp:wxr_version>` tag at the start of the file.
	 *
	 * @var string
	 */
	protected $version = '1.0';

	/**
	 * Import directory path.
	 *
	 * @var string
	 */
	protected $path = '';

	/**
	 * Import options.
	 *
	 * @var array<string, bool|int|null|string>
	 */
	protected $options = [];

	/**
	 * Base URL.
	 *
	 * @var string
	 */
	protected $base_url = '';

	/**
	 * Site ID.
	 *
	 * @var int
	 */
	protected $site_id = 0;

	/**
	 * Processed posts.
	 *
	 * @var array<int, int>
	 */
	protected $processed_posts = array();

	/**
	 * Mapping of old IDs to new IDs.
	 *
	 * @var array<string, array<int|bool>>
	 */
	protected $mapping = array();

	/**
	 * Items that need remapping.
	 *
	 * @var array<string, array<int, bool>>
	 */
	protected $requires_remapping = array();

	/**
	 * Items that exist.
	 *
	 * @var array<string, array<int|bool>>
	 */
	protected $exists = array();

	/**
	 * User slug override.
	 *
	 * @var array<string, string>
	 */
	protected $user_slug_override = array();

	/**
	 * URL remap.
	 *
	 * @var array<string, string>
	 */
	protected $url_remap = array();

	/**
	 * Featured images.
	 *
	 * @var array<int, int>
	 */
	protected $featured_images = array();

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @param array<string, bool|int|null|string> $options {
	 *     Options for the importer.
	 *
	 *     @type bool      $prefill_existing_posts    Should we prefill `post_exists` calls? Default true.
	 *     @type bool      $prefill_existing_comments Should we prefill `comment_exists` calls? Default true.
	 *     @type bool      $prefill_existing_terms    Should we prefill `term_exists` calls? Default true.
	 *     @type bool      $update_attachment_guids   Should attachment GUIDs be updated to the new URL?
	 *                                                Default false.
	 *     @type bool      $fetch_attachments         Whether to fetch attachments from the remote server.
	 *                                                Default false.
	 *     @type bool      $aggressive_url_search     Whether to aggressively replace old URLs. Default false.
	 *     @type int|null  $default_author            User ID to use if author is missing or invalid.
	 *                                                Default null.
	 *     @type string    $attachment_mode          Attachment mode. Default 'remote'.
	 * }
	 * @param string                              $path Path to the import directory.
	 */
	public function __construct( $options = [], $path = '' ) {
		// Initialize some important variables.
		$empty_types = [
			'post'    => [],
			'comment' => [],
			'term'    => [],
			'user'    => [],
		];

		$this->mapping              = $empty_types;
		$this->mapping['user_slug'] = [];
		$this->mapping['term_id']   = [];

		$this->requires_remapping = $empty_types;
		$this->exists             = $empty_types;

		$this->path    = $path;
		$this->options = wp_parse_args(
			$options,
			[
				'prefill_existing_posts'    => true,
				'prefill_existing_comments' => true,
				'prefill_existing_terms'    => true,
				'update_attachment_guids'   => false,
				'attachment_mode'           => 'remote',
				'fetch_attachments'         => false,
				'aggressive_url_search'     => false,
				'default_author'            => null,
			]
		);
	}

	/**
	 * Set logger instance.
	 *
	 * @param Logger $logger Logger instance.
	 * @return void
	 */
	public function set_logger( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get a stream reader for the file.
	 *
	 * @param string $file Path to the XML file.
	 * @return XMLReader|WP_Error Reader instance on success, error otherwise.
	 */
	protected function get_reader( $file ) {
		$reader = new XMLReader();
		$status = $reader->open( $file );

		if ( ! $status ) {
			return new WP_Error( 'wxr_importer.cannot_parse', __( 'Could not open the file for parsing', 'openlab-modules' ) );
		}

		return $reader;
	}

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the WXR file for importing.
	 * @return array|WP_Error Array of authors on success, error otherwise.
	 */
	public function parse_authors( $file ) {
		// Let's run the actual importer now, woot.
		$reader = $this->get_reader( $file );
		if ( is_wp_error( $reader ) ) {
			return $reader;
		}

		// Set the version to compatibility mode first.
		$this->version = '1.0';

		// Start parsing!
		$authors = array();
		while ( $reader->read() ) {
			// Only deal with element opens.
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( XMLReader::ELEMENT !== $reader->nodeType ) {
				continue;
			}

			switch ( $reader->name ) {
				case 'wp:wxr_version':
					// Upgrade to the correct version.
					$this->version = $reader->readString();

					if ( version_compare( $this->version, self::MAX_WXR_VERSION, '>' ) ) {
						$this->logger->warning(
							sprintf(
								// translators: %1$s is the WXR version, %2$s is the importer version.
								__( 'This WXR file (version %1$s) is newer than the importer (version %2$s) and may not be supported. Please consider updating.', 'openlab-modules' ),
								$this->version,
								self::MAX_WXR_VERSION
							)
						);
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'wp:author':
					$node = $reader->expand();

					if ( $node ) {
						$parsed    = $this->parse_author_node( $node );
						$authors[] = $parsed;
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;
			}
		}

		return $authors;
	}

	/**
	 * The main controller for the actual import stage.
	 *
	 * @param string $file Path to the WXR file for importing.
	 * @return mixed|WP_Error Array of authors on success, error otherwise.
	 */
	public function import( $file ) {
		add_filter( 'import_post_meta_key', array( $this, 'is_valid_meta_key' ) );
		add_filter( 'http_request_timeout', array( &$this, 'bump_request_timeout' ) );

		$result = $this->import_start( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Let's run the actual importer now, woot.
		$reader = $this->get_reader( $file );
		if ( is_wp_error( $reader ) ) {
			return $reader;
		}

		// Set the version to compatibility mode first.
		$this->version = '1.0';

		// Reset other variables.
		$this->base_url = '';
		$this->site_id  = 0;

		// Start parsing!
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		while ( $reader->read() ) {
			// Only deal with element opens.
			if ( XMLReader::ELEMENT !== $reader->nodeType ) {
				continue;
			}

			switch ( $reader->name ) {
				case 'wp:wxr_version':
					// Upgrade to the correct version.
					$this->version = $reader->readString();

					if ( version_compare( $this->version, self::MAX_WXR_VERSION, '>' ) ) {
						$this->logger->warning(
							sprintf(
								// translators: %1$s is the WXR version, %2$s is the importer version.
								__( 'This WXR file (version %1$s) is newer than the importer (version %2$s) and may not be supported. Please consider updating.', 'openlab-modules' ),
								$this->version,
								self::MAX_WXR_VERSION
							)
						);
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'wp:site_id':
					$this->site_id = (int) $reader->readString();

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'wp:base_blog_url':
					$this->base_url = $reader->readString();

					// Check if path needs update for legacy file-serving.
					$this->path = $this->normalize_path( $this->path );

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'item':
					$node = $reader->expand();

					if ( $node ) {
						$parsed = $this->parse_post_node( $node );

						if ( ! is_wp_error( $parsed ) ) {
							$this->process_post( $parsed['data'], $parsed['meta'], $parsed['comments'], $parsed['terms'] );
						}
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'wp:author':
					$node = $reader->expand();

					if ( $node ) {
						$parsed = $this->parse_author_node( $node );
						$status = $this->process_author( $parsed['data'], $parsed['meta'] );
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'wp:category':
					$node = $reader->expand();

					if ( $node ) {
						$parsed = $this->parse_term_node( $node, 'category' );
						$status = $this->process_term( $parsed['data'], $parsed['meta'] );
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'wp:tag':
					$node = $reader->expand();

					if ( $node ) {
						$parsed = $this->parse_term_node( $node, 'tag' );
						$status = $this->process_term( $parsed['data'], $parsed['meta'] );
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				case 'wp:term':
					$node = $reader->expand();

					if ( $node ) {
						$parsed = $this->parse_term_node( $node );
						$status = $this->process_term( $parsed['data'], $parsed['meta'] );
					}

					// Handled everything in this node, move on to the next.
					$reader->next();
					break;

				default:
					// Skip this node, probably handled by something already.
					break;
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		// Now that we've done the main processing, do any required
		// post-processing and remapping.
		$this->post_process();

		$this->copy_files();

		if ( $this->options['aggressive_url_search'] ) {
			$this->replace_attachment_urls_in_content();
		}

		$this->remap_featured_images();

		$this->import_end();
	}

	/**
	 * Log an error instance to the logger.
	 *
	 * @param WP_Error $error Error instance to log.
	 * @return void
	 */
	protected function log_error( WP_Error $error ) {
		$this->logger->warning( $error->get_error_message() );

		// Log the data as debug info too.
		$data = $error->get_error_data();
		if ( ! empty( $data ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$this->logger->debug( var_export( $data, true ) );
		}
	}

	/**
	 * Parses the WXR file and prepares us for the task of processing parsed data.
	 *
	 * @param string $file Path to the WXR file for importing.
	 * @return void|WP_Error
	 */
	protected function import_start( $file ) {
		if ( ! is_file( $file ) ) {
			return new WP_Error( 'wxr_importer.file_missing', __( 'The file does not exist, please try again.', 'openlab-modules' ) );
		}

		// Suspend bunches of stuff in WP core.
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		// Prefill exists calls if told to.
		if ( $this->options['prefill_existing_posts'] ) {
			$this->prefill_existing_posts();
		}
		if ( $this->options['prefill_existing_comments'] ) {
			$this->prefill_existing_comments();
		}
		if ( $this->options['prefill_existing_terms'] ) {
			$this->prefill_existing_terms();
		}

		/**
		 * Begin the import.
		 *
		 * Fires before the import process has begun. If you need to suspend
		 * caching or heavy processing on hooks, do so here.
		 */
		do_action( 'import_start' );
	}

	/**
	 * Performs post-import cleanup of files and the cache.
	 *
	 * @return void
	 */
	protected function import_end() {
		// Re-enable stuff in core.
		wp_cache_flush();
		foreach ( get_taxonomies() as $tax ) {
			delete_option( "{$tax}_children" );
			_get_term_hierarchy( $tax );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );

		/**
		 * Complete the import.
		 *
		 * Fires after the import process has finished. If you need to update
		 * your cache or re-enable processing, do so here.
		 */
		do_action( 'import_end' );
	}

	/**
	 * Set the user mapping.
	 *
	 * @param array $mapping List of map arrays (containing `old_slug`, `old_id`, `new_id`).
	 * @return void
	 */
	public function set_user_mapping( $mapping ) {
		foreach ( $mapping as $map ) {
			if ( empty( $map['old_slug'] ) || empty( $map['old_id'] ) || empty( $map['new_id'] ) ) {
				$this->logger->warning( __( 'Invalid author mapping', 'openlab-modules' ) );
				// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
				$this->logger->debug( var_export( $map, true ) );
				continue;
			}

			$old_slug = $map['old_slug'];
			$old_id   = $map['old_id'];
			$new_id   = $map['new_id'];

			$this->mapping['user'][ $old_id ]        = $new_id;
			$this->mapping['user_slug'][ $old_slug ] = $new_id;
		}
	}

	/**
	 * Set the user slug overrides.
	 *
	 * Allows overriding the slug in the import with a custom/renamed version.
	 *
	 * @param string[] $overrides Map of old slug to new slug.
	 * @return void
	 */
	public function set_user_slug_overrides( $overrides ) {
		foreach ( $overrides as $original => $renamed ) {
			$this->user_slug_override[ $original ] = $renamed;
		}
	}

	/**
	 * Parse a post node into post data.
	 *
	 * @param \DOMNode $node Parent node of post data (typically `item`).
	 * @return array|WP_Error Post data array on success, error otherwise.
	 */
	protected function parse_post_node( $node ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$data     = array();
		$meta     = array();
		$comments = array();
		$terms    = array();

		foreach ( $node->childNodes as $child ) {
			// We only care about child elements.
			if ( XML_ELEMENT_NODE !== $child->nodeType || ! ( $child instanceof \DOMElement ) ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:post_type':
					$data['post_type'] = $child->textContent;
					break;

				case 'title':
					$data['post_title'] = $child->textContent;
					break;

				case 'guid':
					$data['guid'] = $child->textContent;
					break;

				case 'dc:creator':
					$data['post_author'] = $child->textContent;
					break;

				case 'content:encoded':
					$data['post_content'] = $child->textContent;
					break;

				case 'excerpt:encoded':
					$data['post_excerpt'] = $child->textContent;
					break;

				case 'wp:post_id':
					$data['post_id'] = $child->textContent;
					break;

				case 'wp:post_date':
					$data['post_date'] = $child->textContent;
					break;

				case 'wp:post_date_gmt':
					$data['post_date_gmt'] = $child->textContent;
					break;

				case 'wp:comment_status':
					$data['comment_status'] = $child->textContent;
					break;

				case 'wp:ping_status':
					$data['ping_status'] = $child->textContent;
					break;

				case 'wp:post_name':
					$data['post_name'] = $child->textContent;
					break;

				case 'wp:status':
					$data['post_status'] = $child->textContent;

					if ( 'auto-draft' === $data['post_status'] ) {
						// Bail now.
						return new WP_Error(
							'wxr_importer.post.cannot_import_draft',
							__( 'Cannot import auto-draft posts', 'openlab-modules' ),
							$data
						);
					}
					break;

				case 'wp:post_parent':
					$data['post_parent'] = $child->textContent;
					break;

				case 'wp:menu_order':
					$data['menu_order'] = $child->textContent;
					break;

				case 'wp:post_password':
					$data['post_password'] = $child->textContent;
					break;

				case 'wp:is_sticky':
					$data['is_sticky'] = $child->textContent;
					break;

				case 'wp:attachment_url':
					$data['attachment_url'] = $child->textContent;
					break;

				case 'wp:postmeta':
					$meta_item = $this->parse_meta_node( $child );
					if ( ! empty( $meta_item ) ) {
						$meta[] = $meta_item;
					}
					break;

				case 'wp:comment':
					$comment_item = $this->parse_comment_node( $child );
					if ( ! empty( $comment_item ) ) {
						$comments[] = $comment_item;
					}
					break;

				case 'category':
					$term_item = $this->parse_category_node( $child );
					if ( ! empty( $term_item ) ) {
						$terms[] = $term_item;
					}
					break;
			}
		}

		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return compact( 'data', 'meta', 'comments', 'terms' );
	}

	/**
	 * Create new posts based on import information
	 *
	 * Posts marked as having a parent which doesn't exist will become top level items.
	 * Doesn't create a new post if: the post type doesn't exist, the given post ID
	 * is already noted as imported or a post with the same title and date already exists.
	 * Note that new/updated terms, comments and meta are imported for the last of the above.
	 *
	 * @param array $data     Post data.
	 * @param array $meta     Meta data.
	 * @param array $comments Comments on the post.
	 * @param array $terms    Terms on the post.
	 * @return bool|void
	 */
	protected function process_post( $data, $meta, $comments, $terms ) {
		/**
		 * Pre-process post data.
		 *
		 * @param array $data Post data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 * @param array $comments Comments on the post.
		 * @param array $terms Terms on the post.
		 */
		$data = apply_filters( 'wxr_importer.pre_process.post', $data, $meta, $comments, $terms );
		if ( empty( $data ) ) {
			return false;
		}

		$original_id = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;

		$parent_id = isset( $data['post_parent'] ) ? (int) $data['post_parent'] : 0;

		// Have we already processed this?
		if ( isset( $this->mapping['post'][ $original_id ] ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $data['post_type'] );

		// Is this type even valid?
		if ( ! $post_type_object ) {
			$this->logger->warning(
				sprintf(
					// translators: %1$s is the post title, %2$s is the post type.
					__( 'Failed to import "%1$s": Invalid post type %2$s', 'openlab-modules' ),
					$data['post_title'],
					$data['post_type']
				)
			);
			return false;
		}

		$post_exists = $this->post_exists( $data );
		if ( $post_exists ) {
			$this->logger->info(
				sprintf(
					// translators: %1$s is the post type, %2$s is the post title.
					__( '%1$s "%2$s" already exists.', 'openlab-modules' ),
					$post_type_object->labels->singular_name,
					$data['post_title']
				)
			);

			/**
			 * Post processing already imported.
			 *
			 * @param array $data Raw data imported for the post.
			 */
			do_action( 'wxr_importer.process_already_imported.post', $data );

			// Even though this post already exists, new comments might need importing.
			$this->process_comments( $comments, $original_id, $data, (bool) $post_exists );

			return false;
		}

		// Map the parent post, or mark it as one we need to fix.
		$requires_remapping = false;
		if ( $parent_id ) {
			if ( isset( $this->mapping['post'][ $parent_id ] ) ) {
				$data['post_parent'] = $this->mapping['post'][ $parent_id ];
			} else {
				$meta[]             = array(
					'key'   => '_wxr_import_parent',
					'value' => $parent_id,
				);
				$requires_remapping = true;

				$data['post_parent'] = 0;
			}
		}

		// Map the author, or mark it as one we need to fix.
		$author = sanitize_user( $data['post_author'], true );
		if ( empty( $author ) ) {
			// Missing or invalid author, use default if available.
			$data['post_author'] = (int) $this->options['default_author'];
		} elseif ( isset( $this->mapping['user_slug'][ $author ] ) ) {
			$data['post_author'] = (int) $this->mapping['user_slug'][ $author ];
		} else {
			$data['post_author'] = (int) get_current_user_id();
		}

		// Does the post look like it contains attachment images?
		if ( preg_match( self::REGEX_HAS_ATTACHMENT_REFS, $data['post_content'] ) ) {
			$meta[]             = array(
				'key'   => '_wxr_import_has_attachment_refs',
				'value' => true,
			);
			$requires_remapping = true;
		}

		// Whitelist to just the keys we allow.
		$postdata = array();

		$allowed = array(
			'post_author'    => true,
			'post_date'      => true,
			'post_date_gmt'  => true,
			'post_content'   => true,
			'post_excerpt'   => true,
			'post_title'     => true,
			'post_status'    => true,
			'post_name'      => true,
			'comment_status' => true,
			'ping_status'    => true,
			'guid'           => true,
			'post_parent'    => true,
			'menu_order'     => true,
			'post_type'      => true,
			'post_password'  => true,
		);
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$postdata[ $key ] = $data[ $key ];
		}

		$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $data );

		if ( 'attachment' === $postdata['post_type'] ) {
			if ( ! $this->options['fetch_attachments'] ) {
				$this->logger->notice(
					sprintf(
						// translators: %s is the attachment title.
						__( 'Skipping attachment "%s", fetching attachments disabled', 'openlab-modules' ),
						$data['post_title']
					)
				);
				/**
				 * Post processing skipped.
				 *
				 * @param array $data Raw data imported for the post.
				 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
				 */
				do_action( 'wxr_importer.process_skipped.post', $data, $meta );
				return false;
			}
			$remote_url = ! empty( $data['attachment_url'] ) ? $data['attachment_url'] : $data['guid'];
			$post_id    = $this->process_attachment( $postdata, $meta, $remote_url );
		} else {
			$post_id = wp_insert_post( $postdata, true );
			do_action( 'wp_import_insert_post', $post_id, $original_id, $postdata, $data );
		}

		if ( is_wp_error( $post_id ) ) {
			$this->logger->error(
				sprintf(
					// translators: %1$s is the post title, %2$s is the post type.
					__( 'Failed to import "%1$s" (%2$s)', 'openlab-modules' ),
					$data['post_title'],
					$post_type_object->labels->singular_name
				)
			);
			$this->logger->debug( $post_id->get_error_message() );

			/**
			 * Post processing failed.
			 *
			 * @param WP_Error $post_id Error object.
			 * @param array $data Raw data imported for the post.
			 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
			 * @param array $comments Raw comment data, already processed by {@see process_comments}.
			 * @param array $terms Raw term data, already processed.
			 */
			do_action( 'wxr_importer.process_failed.post', $post_id, $data, $meta, $comments, $terms );
			return false;
		}

		// Ensure stickiness is handled correctly too.
		if ( '1' === $data['is_sticky'] ) {
			stick_post( $post_id );
		}

		// map pre-import ID to local ID.
		$this->mapping['post'][ $original_id ] = (int) $post_id;

		add_post_meta( $post_id, 'import_id', $original_id, true );

		if ( $requires_remapping ) {
			$this->requires_remapping['post'][ $post_id ] = true;
		}
		$this->mark_post_exists( $data, $post_id );

		$this->logger->info(
			sprintf(
				// translators: %1$s is the post title, %2$s is the post type.
				__( 'Imported "%1$s" (%2$s)', 'openlab-modules' ),
				$data['post_title'],
				$post_type_object->labels->singular_name
			)
		);
		$this->logger->debug(
			sprintf(
				// translators: %1$d is the original post ID, %2$d is the new post ID.
				__( 'Post %1$d remapped to %2$d', 'openlab-modules' ),
				$original_id,
				$post_id
			)
		);

		// Handle the terms too.
		$terms = apply_filters( 'wp_import_post_terms', $terms, $post_id, $data );

		if ( ! empty( $terms ) ) {
			$term_ids = array();
			foreach ( $terms as $term ) {
				$taxonomy = $term['taxonomy'];
				$key      = sha1( $taxonomy . ':' . $term['slug'] );

				if ( isset( $this->mapping['term'][ $key ] ) ) {
					$term_ids[ $taxonomy ][] = (int) $this->mapping['term'][ $key ];
				} else {
					$meta[]             = array(
						'key'   => '_wxr_import_term',
						'value' => $term,
					);
					$requires_remapping = true;
				}
			}

			foreach ( $term_ids as $tax => $ids ) {
				$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
				do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $data );
			}
		}

		$this->process_comments( $comments, $post_id, $data );
		$this->process_post_meta( $meta, $post_id, $data );

		/**
		 * Post processing completed.
		 *
		 * @param int $post_id New post ID.
		 * @param array $data Raw data imported for the post.
		 * @param array $meta Raw meta data, already processed by {@see process_post_meta}.
		 * @param array $comments Raw comment data, already processed by {@see process_comments}.
		 * @param array $terms Raw term data, already processed.
		 */
		do_action( 'wxr_importer.processed.post', $post_id, $data, $meta, $comments, $terms );

		return true;
	}

	/**
	 * Attempt to download a remote file attachment
	 *
	 * @param string $url  URL of item to fetch.
	 * @param array  $post Attachment details.
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise.
	 */
	protected function fetch_remote_file( $url, $post ) {
		// extract the file name and extension from the url.
		$file_name = basename( $url );

		// get placeholder file in the upload dir with a unique, sanitized filename.
		$upload = wp_upload_bits( $file_name, null, '', $post['upload_date'] );
		if ( $upload['error'] ) {
			return new WP_Error( 'upload_dir_error', $upload['error'] );
		}

		// fetch the remote url and write it to the placeholder file.
		$response = wp_remote_get(
			$url,
			array(
				'stream'   => true,
				'filename' => $upload['file'],
			)
		);

		// request failed.
		if ( is_wp_error( $response ) ) {
			wp_delete_file( $upload['file'] );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// make sure the fetch was successful.
		if ( 200 !== $code ) {
			wp_delete_file( $upload['file'] );
			return new WP_Error(
				'import_file_error',
				sprintf(
					// translators: %1$d is the HTTP response code, %2$s is the response message, %3$s is the URL.
					__( 'Remote server returned %1$d %2$s for %3$s', 'openlab-modules' ),
					$code,
					get_status_header_desc( $code ),
					$url
				)
			);
		}

		$filesize = filesize( $upload['file'] );
		$headers  = wp_remote_retrieve_headers( $response );

		if ( isset( $headers['content-length'] ) && $filesize !== (int) $headers['content-length'] ) {
			wp_delete_file( $upload['file'] );
			return new WP_Error( 'import_file_error', __( 'Remote file is incorrect size', 'openlab-modules' ) );
		}

		if ( 0 === $filesize ) {
			wp_delete_file( $upload['file'] );
			return new WP_Error( 'import_file_error', __( 'Zero size file downloaded', 'openlab-modules' ) );
		}

		$max_size = (int) $this->max_attachment_size();
		if ( ! empty( $max_size ) && $filesize > $max_size ) {
			wp_delete_file( $upload['file'] );

			// translators: %s is the max size.
			$message = sprintf( __( 'Remote file is too large, limit is %s', 'openlab-modules' ), size_format( $max_size ) );

			return new WP_Error( 'import_file_error', $message );
		}

		// As a precaution, allow WP to generate thumbnails. This may overwrite imported items.
		wp_create_image_subsizes( $upload['file'], $post['import_id'] );

		return $upload;
	}


	/**
	 * If fetching attachments is enabled then attempt to create a new attachment
	 *
	 * @param array  $post       Attachment post details from WXR.
	 * @param array  $meta       Attachment meta details from WXR.
	 * @param string $remote_url URL to fetch attachment from.
	 * @return int|WP_Error Post ID on success, WP_Error otherwise
	 */
	protected function process_attachment( $post, $meta, $remote_url ) {
		// try to use _wp_attached file for upload folder placement to ensure the same location as the export site.
		// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload().
		$post['upload_date'] = $post['post_date'];
		foreach ( $meta as $meta_item ) {
			if ( '_wp_attachment_metadata' === $meta_item['key'] ) {
				$post['metadata'] = maybe_unserialize( $meta_item['value'] );
				continue;
			}

			if ( '_wp_attached_file' !== $meta_item['key'] ) {
				continue;
			}

			if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta_item['value'], $matches ) ) {
				$post['upload_date'] = $matches[0];
			}
		}

		// if the URL is absolute, but does not contain address, then upload it assuming base_site_url.
		if ( preg_match( '|^/[\w\W]+$|', $remote_url ) ) {
			$remote_url = rtrim( $this->base_url, '/' ) . $remote_url;
		}

		if ( 'remote' === $this->options['attachment_mode'] ) {
			$upload = $this->fetch_remote_file( $remote_url, $post );
		} else {
			$upload = $this->copy_local_file( $remote_url );
		}

		if ( is_wp_error( $upload ) ) {
			return $upload;
		}

		$info = wp_check_filetype( $upload['file'] );

		$post['post_mime_type'] = $info['type'];

		// WP really likes using the GUID for display. Allow updating it.
		// See https://core.trac.wordpress.org/ticket/33386.
		if ( $this->options['update_attachment_guids'] ) {
			$post['guid'] = $upload['url'];
		}

		// As per wp-admin/includes/upload.php.
		$post_id = wp_insert_attachment( $post, $upload['file'] );
		if ( ! $post_id ) {
			return $post_id;
		}

		// Generate metadata for PDFs. This creates PDF thubmnails.
		if ( 'application/pdf' === $info['type'] ) {
			$post['metadata'] = wp_generate_attachment_metadata( $post_id, $upload['file'] );
		}

		if ( ! empty( $post['metadata'] ) ) {
			/*
			 * Thumbnail filenames may have had suffixes appended.
			 *
			 * See wp_unique_filename and https://core.trac.wordpress.org/ticket/42437
			 */
			if ( ! empty( $upload['sizes'] ) ) {
				foreach ( $upload['sizes'] as $size => $thumb ) {
					if ( ! empty( $post['metadata']['sizes'][ $size ] ) ) {
						$post['metadata']['sizes'][ $size ]['original_file'] = $post['metadata']['sizes'][ $size ]['file'];
						$post['metadata']['sizes'][ $size ]['file']          = basename( $thumb['file'] );
					}
				}
			} elseif ( 'remote' === $this->options['attachment_mode'] ) {
				if ( ! empty( $post['metadata']['sizes'] ) ) {
					$file_dirname = dirname( $remote_url );
					foreach ( $post['metadata']['sizes'] as $size => $thumb ) {
						$remote_thumb_url = trailingslashit( $file_dirname ) . $thumb['file'];
						$fetched_thumb    = $this->fetch_remote_file( $remote_thumb_url, $post );

						if ( ! is_wp_error( $fetched_thumb ) ) {
							$post['metadata']['sizes'][ $size ]['original_file'] = $remote_thumb_url;
							$post['metadata']['sizes'][ $size ]['file']          = basename( $fetched_thumb['file'] );
						}
					}
				}
			}

			wp_update_attachment_metadata( $post_id, $post['metadata'] );
		}

		// Map this image URL later if we need to.
		$this->url_remap[ $remote_url ] = $upload['url'];

		// If we have a HTTPS URL, ensure the HTTP URL gets replaced too.
		if ( substr( $remote_url, 0, 8 ) === 'https://' ) {
			$insecure_url                     = 'http' . substr( $remote_url, 5 );
			$this->url_remap[ $insecure_url ] = $upload['url'];
		}

		// Add additional image sizes for remapping.
		if ( is_string( $info['type'] ) && preg_match( '!^image/!', $info['type'] ) && ! empty( $post['metadata']['sizes'] ) ) {
			$name = basename( $upload['url'] );

			foreach ( $post['metadata']['sizes'] as $size => $data ) {

				$original_filename = isset( $data['original_file'] ) ? $data['original_file'] : $data['file'];

				$remote = str_replace( basename( $remote_url ), basename( $original_filename ), $remote_url );
				$local  = str_replace( $name, $data['file'], $upload['url'] );

				$this->url_remap[ $remote ] = $local;
			}
		}

		return $post_id;
	}

	/**
	 * Parse a meta node into meta data.
	 *
	 * @param \DOMElement $node Parent node of meta data (typically `wp:postmeta` or `wp:commentmeta`).
	 * @return array|null Meta data array on success, or null on error.
	 */
	protected function parse_meta_node( $node ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		foreach ( $node->childNodes as $child ) {
			// We only care about child elements.
			if ( XML_ELEMENT_NODE !== $child->nodeType || ! ( $child instanceof \DOMElement ) ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:meta_key':
					$key = $child->textContent;
					break;

				case 'wp:meta_value':
					$value = $child->textContent;
					break;
			}
		}

		if ( empty( $key ) || empty( $value ) ) {
			return null;
		}

		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return compact( 'key', 'value' );
	}

	/**
	 * Process and import post meta items.
	 *
	 * @param array $meta    List of meta data arrays.
	 * @param int   $post_id Post to associate with.
	 * @param array $post    Post data.
	 * @return bool True on success, false otherwise.
	 */
	protected function process_post_meta( $meta, $post_id, $post ) {
		if ( empty( $meta ) ) {
			return true;
		}

		foreach ( $meta as $meta_item ) {
			/**
			 * Pre-process post meta data.
			 *
			 * @param array $meta_item Meta data. (Return empty to skip.)
			 * @param int $post_id Post the meta is attached to.
			 */
			$meta_item = apply_filters( 'wxr_importer.pre_process.post_meta', $meta_item, $post_id );
			if ( empty( $meta_item ) ) {
				return false;
			}

			$key   = apply_filters( 'import_post_meta_key', $meta_item['key'], $post_id, $post );
			$value = false;

			if ( '_edit_last' === $key ) {
				$value = intval( $meta_item['value'] );
				if ( ! isset( $this->mapping['user'][ $value ] ) ) {
					// Skip!
					continue;
				}

				$value = $this->mapping['user'][ $value ];
			}

			if ( $key ) {
				// Export gets meta straight from the DB so could have a serialized string.
				if ( ! $value ) {
					$value = maybe_unserialize( $meta_item['value'] );
				}

				add_post_meta( $post_id, $key, $value );
				do_action( 'import_post_meta', $post_id, $key, $value );

				// If the post has a featured image, take note of this in case of remap.
				if ( '_thumbnail_id' === $key && is_numeric( $value ) ) {
					$this->featured_images[ $post_id ] = (int) $value;
				}
			}
		}

		return true;
	}

	/**
	 * Parse a comment node into comment data.
	 *
	 * @param \DOMElement $node Parent node of comment data (typically `wp:comment`).
	 * @return array Comment data array.
	 */
	protected function parse_comment_node( $node ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$data = array(
			'commentmeta' => array(),
		);

		foreach ( $node->childNodes as $child ) {
			// We only care about child elements.
			if ( XML_ELEMENT_NODE !== $child->nodeType || ! ( $child instanceof \DOMElement ) ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:comment_id':
					$data['comment_id'] = $child->textContent;
					break;
				case 'wp:comment_author':
					$data['comment_author'] = $child->textContent;
					break;

				case 'wp:comment_author_email':
					$data['comment_author_email'] = $child->textContent;
					break;

				case 'wp:comment_author_IP':
					$data['comment_author_IP'] = $child->textContent;
					break;

				case 'wp:comment_author_url':
					$data['comment_author_url'] = $child->textContent;
					break;

				case 'wp:comment_user_id':
					$data['comment_user_id'] = $child->textContent;
					break;

				case 'wp:comment_date':
					$data['comment_date'] = $child->textContent;
					break;

				case 'wp:comment_date_gmt':
					$data['comment_date_gmt'] = $child->textContent;
					break;

				case 'wp:comment_content':
					$data['comment_content'] = $child->textContent;
					break;

				case 'wp:comment_approved':
					$data['comment_approved'] = $child->textContent;
					break;

				case 'wp:comment_type':
					$data['comment_type'] = $child->textContent;
					break;

				case 'wp:comment_parent':
					$data['comment_parent'] = $child->textContent;
					break;

				case 'wp:commentmeta':
					$meta_item = $this->parse_meta_node( $child );
					if ( ! empty( $meta_item ) ) {
						$data['commentmeta'][] = $meta_item;
					}
					break;
			}
		}

		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $data;
	}

	/**
	 * Process and import comment data.
	 *
	 * @param array $comments    List of comment data arrays.
	 * @param int   $post_id     Post to associate with.
	 * @param array $post        Post data.
	 * @param bool  $post_exists Whether the post already exists.
	 * @return int|WP_Error Number of comments imported on success, error otherwise.
	 */
	protected function process_comments( $comments, $post_id, $post, $post_exists = false ) {

		$comments = apply_filters( 'wp_import_post_comments', $comments, $post_id, $post );
		if ( empty( $comments ) ) {
			return 0;
		}

		$num_comments = 0;

		// Sort by ID to avoid excessive remapping later.
		usort( $comments, array( $this, 'sort_comments_by_id' ) );

		foreach ( $comments as $key => $comment ) {
			/**
			 * Pre-process comment data
			 *
			 * @param array $comment Comment data. (Return empty to skip.)
			 * @param int $post_id Post the comment is attached to.
			 */
			$comment = apply_filters( 'wxr_importer.pre_process.comment', $comment, $post_id );
			if ( empty( $comment ) ) {
				$error = new WP_Error(
					'wxr_importer.comment.cannot_import',
					__( 'Cannot import comment', 'openlab-modules' ),
					$comment
				);

				return $error;
			}

			$original_id = isset( $comment['comment_id'] ) ? (int) $comment['comment_id'] : 0;
			$parent_id   = isset( $comment['comment_parent'] ) ? (int) $comment['comment_parent'] : 0;
			$author_id   = isset( $comment['comment_user_id'] ) ? (int) $comment['comment_user_id'] : 0;

			// if this is a new post we can skip the comment_exists() check.
			// TODO: Check comment_exists for performance.
			if ( $post_exists ) {
				$existing = $this->comment_exists( $comment );
				if ( $existing ) {

					/**
					 * Comment processing already imported.
					 *
					 * @param array $comment Raw data imported for the comment.
					 */
					do_action( 'wxr_importer.process_already_imported.comment', $comment );

					$this->mapping['comment'][ $original_id ] = $existing;
					continue;
				}
			}

			// Remove meta from the main array.
			$meta = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();
			unset( $comment['commentmeta'] );

			// Map the parent comment, or mark it as one we need to fix.
			$requires_remapping = false;
			if ( $parent_id ) {
				if ( isset( $this->mapping['comment'][ $parent_id ] ) ) {
					$comment['comment_parent'] = $this->mapping['comment'][ $parent_id ];
				} else {
					// Prepare for remapping later.
					$meta[]             = array(
						'key'   => '_wxr_import_parent',
						'value' => $parent_id,
					);
					$requires_remapping = true;

					// Wipe the parent for now.
					$comment['comment_parent'] = 0;
				}
			}

			// Map the author, or mark it as one we need to fix.
			if ( $author_id ) {
				if ( isset( $this->mapping['user'][ $author_id ] ) ) {
					$comment['user_id'] = $this->mapping['user'][ $author_id ];
				} else {
					// Prepare for remapping later.
					$meta[]             = array(
						'key'   => '_wxr_import_user',
						'value' => $author_id,
					);
					$requires_remapping = true;

					// Wipe the user for now.
					$comment['user_id'] = 0;
				}
			}

			// Run standard core filters.
			$comment['comment_post_ID'] = $post_id;
			$comment                    = wp_filter_comment( $comment );

			// wp_insert_comment expects slashed data.
			$comment_id = wp_insert_comment( wp_slash( $comment ) );

			if ( ! $comment_id ) {
				$this->logger->error(
					sprintf(
						// translators: %1$s is the comment content, %2$s is the post title.
						__( 'Failed to import comment "%1$s" for post "%2$s"', 'openlab-modules' ),
						$comment['comment_content'],
						$post['post_title']
					)
				);
				continue;
			}

			$this->mapping['comment'][ $original_id ] = $comment_id;
			if ( $requires_remapping ) {
				$this->requires_remapping['comment'][ $comment_id ] = true;
			}
			$this->mark_comment_exists( $comment, $comment_id );

			/**
			 * Comment has been imported.
			 *
			 * @param int $comment_id New comment ID
			 * @param array $comment Comment inserted (`comment_id` item refers to the original ID)
			 * @param int $post_id Post parent of the comment
			 * @param array $post Post data
			 */
			do_action( 'wp_import_insert_comment', $comment_id, $comment, $post_id, $post );

			// Process the meta items.
			foreach ( $meta as $meta_item ) {
				$value = maybe_unserialize( $meta_item['value'] );
				add_comment_meta( $comment_id, wp_slash( $meta_item['key'] ), wp_slash( $value ) );
			}

			/**
			 * Post processing completed.
			 *
			 * @param int   $comment_id New post ID.
			 * @param array $comment    Raw data imported for the comment.
			 * @param array $meta       Raw meta data, already processed by process_post_meta.
			 * @param int   $post_id    Parent post ID.
			 */
			do_action( 'wxr_importer.processed.comment', $comment_id, $comment, $meta, $post_id );

			++$num_comments;
		}

		return $num_comments;
	}

	/**
	 * Parse a category node into category data.
	 *
	 * @param \DOMElement $node Parent node of category data (typically `wp:category`).
	 * @return array|null Category data array on success, or null on error.
	 */
	protected function parse_category_node( $node ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$data = array(
			// Default taxonomy to "category", since this is a `<category>` tag.
			'taxonomy' => 'category',
		);
		$meta = array();

		if ( $node->hasAttribute( 'domain' ) ) {
			$data['taxonomy'] = $node->getAttribute( 'domain' );
		}

		if ( $node->hasAttribute( 'nicename' ) ) {
			$data['slug'] = $node->getAttribute( 'nicename' );
		}

		$data['name'] = $node->textContent;

		if ( empty( $data['slug'] ) ) {
			return null;
		}

		// Just for extra compatibility.
		if ( 'tag' === $data['taxonomy'] ) {
			$data['taxonomy'] = 'post_tag';
		}

		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $data;
	}

	/**
	 * Callback for `usort` to sort comments by ID
	 *
	 * @param array $a Comment data for the first comment.
	 * @param array $b Comment data for the second comment.
	 * @return int
	 */
	public static function sort_comments_by_id( $a, $b ) {
		if ( empty( $a['comment_id'] ) ) {
			return 1;
		}

		if ( empty( $b['comment_id'] ) ) {
			return -1;
		}

		return $a['comment_id'] - $b['comment_id'];
	}

	/**
	 * Parse author node
	 *
	 * @param \DOMNode $node Parent node of author data (typically `wp:author`).
	 * @return array
	 */
	protected function parse_author_node( $node ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$data = array();
		$meta = array();
		foreach ( $node->childNodes as $child ) {
			// We only care about child elements.
			if ( XML_ELEMENT_NODE !== $child->nodeType || ! ( $child instanceof \DOMElement ) ) {
				continue;
			}

			switch ( $child->tagName ) {
				case 'wp:author_login':
					$data['user_login'] = $child->textContent;
					break;

				case 'wp:author_id':
					$data['ID'] = $child->textContent;
					break;

				case 'wp:author_email':
					$data['user_email'] = $child->textContent;
					break;

				case 'wp:author_display_name':
					$data['display_name'] = $child->textContent;
					break;

				case 'wp:author_first_name':
					$data['first_name'] = $child->textContent;
					break;

				case 'wp:author_last_name':
					$data['last_name'] = $child->textContent;
					break;
			}
		}

		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return compact( 'data', 'meta' );
	}

	/**
	 * Process author data.
	 *
	 * @param array $data Data from the author node.
	 * @param array $meta Meta data from the author node.
	 * @return bool
	 */
	protected function process_author( $data, $meta ) {
		/**
		 * Pre-process user data.
		 *
		 * @param array $data User data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 */
		$data = apply_filters( 'wxr_importer.pre_process.user', $data, $meta );
		if ( empty( $data ) ) {
			return false;
		}

		// Have we already handled this user?
		$original_id   = isset( $data['ID'] ) ? $data['ID'] : 0;
		$original_slug = $data['user_login'];

		if ( isset( $this->mapping['user'][ $original_id ] ) ) {
			$existing = $this->mapping['user'][ $original_id ];

			// Note the slug mapping if we need to too.
			if ( ! isset( $this->mapping['user_slug'][ $original_slug ] ) ) {
				$this->mapping['user_slug'][ $original_slug ] = $existing;
			}

			return false;
		}

		if ( isset( $this->mapping['user_slug'][ $original_slug ] ) ) {
			$existing = $this->mapping['user_slug'][ $original_slug ];

			// Ensure we note the mapping too.
			$this->mapping['user'][ $original_id ] = $existing;

			return false;
		}

		// Allow overriding the user's slug.
		$login = $original_slug;
		if ( isset( $this->user_slug_override[ $login ] ) ) {
			$login = $this->user_slug_override[ $login ];
		}

		$userdata = array(
			'user_login' => sanitize_user( $login, true ),
			'user_pass'  => wp_generate_password(),
		);

		$allowed = array(
			'user_email'   => true,
			'display_name' => true,
			'first_name'   => true,
			'last_name'    => true,
		);
		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$userdata[ $key ] = $data[ $key ];
		}

		$user_id = wp_insert_user( wp_slash( $userdata ) );
		if ( is_wp_error( $user_id ) ) {
			$this->logger->error(
				sprintf(
					// translators: %s is the user login.
					__( 'Failed to import user "%s"', 'openlab-modules' ),
					$userdata['user_login']
				)
			);
			$this->logger->debug( $user_id->get_error_message() );

			/**
			 * User processing failed.
			 *
			 * @param WP_Error $user_id Error object.
			 * @param array $userdata Raw data imported for the user.
			 */
			do_action( 'wxr_importer.process_failed.user', $user_id, $userdata );
			return false;
		}

		if ( $original_id ) {
			$this->mapping['user'][ $original_id ] = $user_id;
		}
		$this->mapping['user_slug'][ $original_slug ] = $user_id;

		$this->logger->info(
			sprintf(
				// translators: %s is the user login.
				__( 'Imported user "%s"', 'openlab-modules' ),
				$userdata['user_login']
			)
		);
		$this->logger->debug(
			sprintf(
				// Translators: %1$d is the original user ID, %2$d is the new user ID.
				__( 'User %1$d remapped to %2$d', 'openlab-modules' ),
				$original_id,
				$user_id
			)
		);

		/**
		 * User processing completed.
		 *
		 * @param int $user_id New user ID.
		 * @param array $userdata Raw data imported for the user.
		 */
		do_action( 'wxr_importer.processed.user', $user_id, $userdata );

		return true;
	}

	/**
	 * Parse term node.
	 *
	 * @param \DOMNode $node Parent node of term data (typically `wp:category` or `wp:tag`).
	 * @param string   $type Type of term (category or tag).
	 * @return array Term data and meta data.
	 */
	protected function parse_term_node( \DOMNode $node, $type = 'term' ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$data = [];
		$meta = [];

		if ( 'category' === $type ) {
			$data['taxonomy'] = 'category';

			foreach ( $node->childNodes as $child ) {
				if ( XML_ELEMENT_NODE !== $child->nodeType ) {
					continue;
				}

				$data_key          = preg_replace( '#^wp:#', '', $child->nodeName );
				$data[ $data_key ] = $child->textContent;
			}
		} elseif ( 'tag' === $type ) {
			$data['taxonomy'] = 'post_tag';

			foreach ( $node->childNodes as $child ) {
				if ( XML_ELEMENT_NODE !== $child->nodeType ) {
					continue;
				}

				$data_key          = preg_replace( '#^wp:#', '', $child->nodeName );
				$data[ $data_key ] = $child->textContent;
			}
		} else {
			foreach ( $node->childNodes as $child ) {
				if ( XML_ELEMENT_NODE !== $child->nodeType ) {
					continue;
				}

				if ( 'wp:termmeta' === $child->nodeName ) {
					$meta_key   = '';
					$meta_value = '';

					foreach ( $child->childNodes as $meta_child ) {
						if ( XML_ELEMENT_NODE !== $meta_child->nodeType ) {
							continue;
						}

						if ( 'wp:meta_key' === $meta_child->nodeName ) {
							$meta_key = $meta_child->textContent;
						} elseif ( 'wp:meta_value' === $meta_child->nodeName ) {
							$meta_value = $meta_child->textContent;
						}
					}

					if ( $meta_key ) {
						$meta[] = [
							'key'   => $meta_key,
							'value' => $meta_value,
						];
					}
				} else {
					$data_key          = preg_replace( '#^wp:#', '', $child->nodeName );
					$data[ $data_key ] = $child->textContent;
				}
			}
		}

		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return [
			'data' => $data,
			'meta' => $meta,
		];
	}

	/**
	 * Process term data.
	 *
	 * @param array $data Term data.
	 * @param array $meta Meta data.
	 * @return bool
	 */
	protected function process_term( $data, $meta ) {
		/**
		 * Pre-process term data.
		 *
		 * @param array $data Term data. (Return empty to skip.)
		 * @param array $meta Meta data.
		 */
		$data = apply_filters( 'wxr_importer.pre_process.term', $data, $meta );
		if ( empty( $data ) ) {
			return false;
		}

		$original_id = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$parent_id   = isset( $data['parent'] ) ? (int) $data['parent'] : 0;

		$mapping_key = sha1( $data['taxonomy'] . ':' . $data['slug'] );
		$existing    = $this->term_exists( $data );
		if ( $existing ) {
			/**
			 * Term processing already imported.
			 *
			 * @param array $data Raw data imported for the term.
			 */
			do_action( 'wxr_importer.process_already_imported.term', $data );

			$this->mapping['term'][ $mapping_key ]    = $existing;
			$this->mapping['term_id'][ $original_id ] = $existing;
			return false;
		}

		// WP really likes to repeat itself in export files.
		if ( isset( $this->mapping['term'][ $mapping_key ] ) ) {
			return false;
		}

		$termdata = array();
		$allowed  = array(
			'slug'        => true,
			'description' => true,
		);

		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}

			$termdata[ $key ] = $data[ $key ];
		}

		$result = wp_insert_term( $data['name'], $data['taxonomy'], $termdata );
		if ( is_wp_error( $result ) ) {
			$this->logger->warning(
				sprintf(
					// translators: %1$s is the taxonomy name, %2$s is the term name.
					__( 'Failed to import %1$s %2$s', 'openlab-modules' ),
					$data['taxonomy'],
					$data['name']
				)
			);
			$this->logger->debug( $result->get_error_message() );
			do_action( 'wp_import_insert_term_failed', $result, $data );

			/**
			 * Term processing failed.
			 *
			 * @param WP_Error $result Error object.
			 * @param array $data Raw data imported for the term.
			 * @param array $meta Meta data supplied for the term.
			 */
			do_action( 'wxr_importer.process_failed.term', $result, $data, $meta );
			return false;
		}

		$term_id = $result['term_id'];

		$this->mapping['term'][ $mapping_key ]    = $term_id;
		$this->mapping['term_id'][ $original_id ] = $term_id;

		update_term_meta( $term_id, 'import_id', $original_id );

		if ( ! empty( $meta ) ) {
			foreach ( $meta as $_m ) {
				update_term_meta( $term_id, $_m['key'], $_m['value'] );
			}
		}

		$this->logger->info(
			sprintf(
				// translators: %1$s is the term name, %2$s is the taxonomy name.
				__( 'Imported "%1$s" (%2$s)', 'openlab-modules' ),
				$data['name'],
				$data['taxonomy']
			)
		);

		$this->logger->debug(
			sprintf(
				// translators: %1$d is the original term ID, %2$d is the new term ID.
				__( 'Term %1$d remapped to %2$d', 'openlab-modules' ),
				$original_id,
				$term_id
			)
		);

		do_action( 'wp_import_insert_term', $term_id, $data );

		/**
		 * Term processing completed.
		 *
		 * @param int $term_id New term ID.
		 * @param array $data Raw data imported for the term.
		 */
		do_action( 'wxr_importer.processed.term', $term_id, $data );

		return true;
	}

	/**
	 * Copy attachent file to uploads directory.
	 *
	 * @param string $url URL of item to fetch.
	 * @return array|WP_Error Local file location details on success, WP_Error otherwise
	 */
	protected function copy_local_file( $url ) {
		$wp_upload_dir = wp_upload_dir();

		// extract the file name and extension from the url.
		$name = basename( $url );

		// Get local file details.
		$extract_path     = str_replace( $this->base_url, $this->path, $url );
		$destination_path = str_replace( $this->path . '/files', $wp_upload_dir['basedir'], $extract_path );

		// Use WP_Filesystem to copy.
		$filesystem_is_initialized = WP_Filesystem();
		if ( ! $filesystem_is_initialized ) {
			$message = __( 'Could not initialize filesystem', 'openlab-modules' );
			return new WP_Error( 'filesystem_error', $message );
		}

		global $wp_filesystem;
		$success = $wp_filesystem->copy( $extract_path, $destination_path, true );
		if ( ! $success ) {
			return new WP_Error( 'copy_failed', __( 'Failed to copy file to destination.', 'openlab-modules' ) );
		}

		// Return value should mimic the return value of wp_upload_bits.
		$upload = array(
			'file' => $destination_path,
			'url'  => str_replace( $wp_upload_dir['basedir'], $wp_upload_dir['baseurl'], $destination_path ),
			'type' => wp_check_filetype( $name, null ),
		);

		return $upload;
	}

	/**
	 * Perform post-related processes that must be completed after import is complete.
	 *
	 * @return void
	 */
	protected function post_process() {
		// Time to tackle any left-over bits.
		if ( ! empty( $this->requires_remapping['post'] ) ) {
			$this->post_process_posts( $this->requires_remapping['post'] );
		}

		if ( ! empty( $this->requires_remapping['comment'] ) ) {
			$this->post_process_comments( $this->requires_remapping['comment'] );
		}

		$this->post_process_cpt_tax_map();
		$this->post_process_block_attributes();
		$this->post_process_post_meta();
	}

	/**
	 * Post-process posts to update parent and author IDs.
	 *
	 * @param array<int, mixed> $todo List of post IDs to process.
	 * @return void
	 */
	protected function post_process_posts( $todo ) {
		foreach ( $todo as $post_id => $_ ) {
			$this->logger->debug(
				sprintf(
					// Note: title intentionally not used to skip extra processing
					// for when debug logging is off.
					// translators: %d is the post ID.
					__( 'Running post-processing for post %d', 'openlab-modules' ),
					$post_id
				)
			);

			$data = array();

			$parent_id = get_post_meta( $post_id, '_wxr_import_parent', true );
			if ( ! empty( $parent_id ) && is_scalar( $parent_id ) ) {
				// Have we imported the parent now?
				if ( isset( $this->mapping['post'][ $parent_id ] ) ) {
					$data['post_parent'] = (int) $this->mapping['post'][ $parent_id ];
				} else {
					$this->logger->warning(
						sprintf(
							// translators: %1$s is the post title, %2$d is the post ID.
							__( 'Could not find the post parent for "%1$s" (post #%2$d)', 'openlab-modules' ),
							get_the_title( $post_id ),
							$post_id
						)
					);
					$this->logger->debug(
						sprintf(
							// translators: %1$d is the post ID, %2$d is the parent ID.
							__( 'Post %1$d was imported with parent %2$d, but could not be found', 'openlab-modules' ),
							$post_id,
							$parent_id
						)
					);
				}
			}

			$author_slug = get_post_meta( $post_id, '_wxr_import_user_slug', true );
			if ( ! empty( $author_slug ) && is_string( $author_slug ) ) {
				// Have we imported the user now?
				if ( isset( $this->mapping['user_slug'][ $author_slug ] ) ) {
					$data['post_author'] = (int) $this->mapping['user_slug'][ $author_slug ];
				} else {
					$this->logger->warning(
						sprintf(
							// translators: %1$s is the post title, %2$d is the post ID.
							__( 'Could not find the author for "%1$s" (post #%2$d)', 'openlab-modules' ),
							get_the_title( $post_id ),
							$post_id
						)
					);
					$this->logger->debug(
						sprintf(
							// translators: %1$d is the post ID, %2$s is the author slug.
							__( 'Post %1$d was imported with author "%2$s", but could not be found', 'openlab-modules' ),
							$post_id,
							$author_slug
						)
					);
				}
			}

			$has_attachments = get_post_meta( $post_id, '_wxr_import_has_attachment_refs', true );
			if ( ! empty( $has_attachments ) ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$content = $post->post_content;

					// Replace all the URLs we've got.
					$new_content = str_replace( array_keys( $this->url_remap ), $this->url_remap, $content );
					if ( $new_content !== $content ) {
						$data['post_content'] = $new_content;
					}
				}
			}

			// Do we have updates to make?
			if ( empty( $data ) ) {
				$this->logger->debug(
					sprintf(
						// translators: %d is the post ID.
						__( 'Post %d was marked for post-processing, but none was required.', 'openlab-modules' ),
						$post_id
					)
				);
				continue;
			}

			// Run the update.
			$data['ID'] = $post_id;
			$result     = wp_update_post( $data, true );
			if ( is_wp_error( $result ) ) {
				$this->logger->warning(
					sprintf(
						// translators: %1$s is the post title, %2$d is the post ID.
						__( 'Could not update "%1$s" (post #%2$d) with mapped data', 'openlab-modules' ),
						get_the_title( $post_id ),
						$post_id
					)
				);
				$this->logger->debug( $result->get_error_message() );
				continue;
			}

			// Clear out our temporary meta keys.
			delete_post_meta( $post_id, '_wxr_import_parent' );
			delete_post_meta( $post_id, '_wxr_import_user_slug' );
			delete_post_meta( $post_id, '_wxr_import_has_attachment_refs' );
		}
	}

	/**
	 * Post-process comments to update parent and author IDs.
	 *
	 * @param array<int, mixed> $todo List of comment IDs to process.
	 * @return void
	 */
	protected function post_process_comments( $todo ) {
		foreach ( $todo as $comment_id => $_ ) {
			$data = array();

			$parent_id = get_comment_meta( $comment_id, '_wxr_import_parent', true );
			if ( ! empty( $parent_id ) && is_scalar( $parent_id ) ) {
				// Have we imported the parent now?
				if ( isset( $this->mapping['comment'][ $parent_id ] ) ) {
					$data['comment_parent'] = $this->mapping['comment'][ $parent_id ];
				} else {
					$this->logger->warning(
						sprintf(
							// translators: %d is the comment ID.
							__( 'Could not find the comment parent for comment #%d', 'openlab-modules' ),
							$comment_id
						)
					);
					$this->logger->debug(
						sprintf(
							// translators: %1$d is the comment ID, %2$d is the parent ID.
							__( 'Comment %1$d was imported with parent %2$d, but could not be found', 'openlab-modules' ),
							$comment_id,
							$parent_id
						)
					);
				}
			}

			$author_id = get_comment_meta( $comment_id, '_wxr_import_user', true );
			if ( ! empty( $author_id ) && is_scalar( $author_id ) ) {
				// Have we imported the user now?
				if ( isset( $this->mapping['user'][ $author_id ] ) ) {
					$data['user_id'] = $this->mapping['user'][ $author_id ];
				} else {
					$this->logger->warning(
						sprintf(
							// translators: %d is the comment ID.
							__( 'Could not find the author for comment #%d', 'openlab-modules' ),
							$comment_id
						)
					);
					$this->logger->debug(
						sprintf(
							// translators: %1$d is the comment ID, %2$d is the author ID.
							__( 'Comment %1$d was imported with author %2$d, but could not be found', 'openlab-modules' ),
							$comment_id,
							$author_id
						)
					);
				}
			}

			// Do we have updates to make?
			if ( empty( $data ) ) {
				continue;
			}

			// Run the update.
			$data['comment_ID'] = $comment_id;
			$result             = wp_update_comment( wp_slash( $data ) );
			if ( empty( $result ) ) {
				$this->logger->warning(
					sprintf(
						// translators: %d is the comment ID.
						__( 'Could not update comment #%d with mapped data', 'openlab-modules' ),
						$comment_id
					)
				);
				continue;
			}

			// Clear out our temporary meta keys.
			delete_comment_meta( $comment_id, '_wxr_import_parent' );
			delete_comment_meta( $comment_id, '_wxr_import_user' );
		}
	}

	/**
	 * Gets new_id => old_id map based on 'import_id' postmeta.
	 *
	 * @return array<int, int>
	 */
	protected function get_post_id_map() {
		static $mapping;

		if ( isset( $mapping ) ) {
			return $mapping;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'import_id'" );

		$mapping = [];
		foreach ( $found as $item ) {
			$new_post_id = (int) $item->post_id;
			$old_post_id = (int) $item->meta_value;

			$mapping[ $old_post_id ] = $new_post_id;
		}

		return $mapping;
	}

	/**
	 * Gets old_term_id => new_term_id map based on 'import_id' termmeta.
	 *
	 * @return array<int, int>
	 */
	protected function get_term_id_map() {
		static $mapping;

		if ( isset( $mapping ) ) {
			return $mapping;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_results(
			"SELECT tm.term_id, tm.meta_value
			 FROM {$wpdb->termmeta} tm
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id
			 WHERE tm.meta_key = 'import_id'"
		);

		$mapping = [];
		foreach ( $found as $item ) {
			$new_term_id = (int) $item->term_id;
			$old_term_id = (int) $item->meta_value;

			$mapping[ $old_term_id ] = $new_term_id;
		}

		return $mapping;
	}

	/**
	 * Run processing related to block attributes that must run after all items are imported.
	 *
	 * @return void
	 */
	protected function post_process_block_attributes() {
		$mapping = $this->get_post_id_map();

		foreach ( $mapping as $old_post_id => $new_post_id ) {
			$post = get_post( $new_post_id );
			if ( ! $post ) {
				continue;
			}

			// Block attribute for module-navigation can be found with string manipulation - no block parsing required.
			$new_post_content = preg_replace_callback(
				'#<!-- wp:openlab-modules/module-navigation\s+\{"moduleId":\s*"?(\d+)"?\}\s*/-->#',
				function ( $matches ) use ( $mapping ) {
					$old_module_id = (int) $matches[1];
					$new_module_id = $mapping[ $old_module_id ] ?? null;

					if ( ! $new_module_id ) {
						return $matches[0];
					}

					return sprintf( '<!-- wp:openlab-modules/module-navigation {"moduleId":"%s"} /-->', $new_module_id );
				},
				$post->post_content
			);

			if ( $new_post_content !== $post->post_content && is_string( $new_post_content ) ) {
				wp_update_post(
					[
						'ID'           => (int) $new_post_id,
						'post_content' => $new_post_content,
					]
				);
			}
		}
	}

	/**
	 * Run processing related to postmeta that must run after all items are imported.
	 *
	 * @return void
	 */
	protected function post_process_post_meta() {
		global $wpdb;

		$mapping = $this->get_post_id_map();

		// First, loop through and change the inserted_navigation keys to avoid dupes.
		foreach ( $mapping as $old_post_id => $new_post_id ) {
			$post_module_ids = Module::get_module_ids_of_page( $new_post_id );
			foreach ( $post_module_ids as $new_post_module_id ) {
				$old_post_module_id = array_search( $new_post_module_id, $mapping, true );
				if ( $old_post_module_id ) {
					$old_meta_key = 'openlab_modules_inserted_navigation_' . (string) $old_post_module_id;
					$new_meta_key = 'openlab_modules_inserted_navigation_' . (string) $new_post_module_id;

					$old_meta_value = get_post_meta( $new_post_id, $old_meta_key, true );
					if ( $old_meta_value ) {
						delete_post_meta( $new_post_id, $old_meta_key );
						update_post_meta( $new_post_id, $new_meta_key, '1' );
					}
				}
			}
		}

		// Next, map module page IDs. Saving 'module_page_ids' will trigger Schema's navigation insertion.
		foreach ( $mapping as $old_post_id => $new_post_id ) {
			$module_page_ids_raw = get_post_meta( $new_post_id, 'module_page_ids', true );
			if ( $module_page_ids_raw && is_string( $module_page_ids_raw ) ) {
				$module_page_ids = json_decode( $module_page_ids_raw, true );

				if ( is_array( $module_page_ids ) ) {
					$new_module_page_ids = [];
					foreach ( $module_page_ids as $old_module_page_id ) {
						$new_module_page_id = $mapping[ $old_module_page_id ] ?? null;
						if ( $new_module_page_id ) {
							$new_module_page_ids[] = $new_module_page_id;
						}
					}

					update_post_meta( $new_post_id, 'module_page_ids', wp_json_encode( $new_module_page_ids ) );
				}
			}
		}

		// Delete all 'import_id' keys, so we don't interfere with future imports.
		// phpcs:ignore
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'import_id'
			)
		);

		// phpcs:ignore
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
				'import_id'
			)
		);
	}

	/**
	 * Run processing related to custom post type and taxonomy mapping that must run after all items are imported.
	 *
	 * @return void
	 */
	protected function post_process_cpt_tax_map() {
		$post_map = $this->get_post_id_map();
		$term_map = $this->get_term_id_map();

		foreach ( $post_map as $old_post_id => $new_post_id ) {
			// Update post_meta: 'term_id' → new term ID.
			$old_term_id_raw = get_post_meta( $new_post_id, 'term_id', true );
			$old_term_id     = is_numeric( $old_term_id_raw ) ? (int) $old_term_id_raw : 0;
			if ( $old_term_id && isset( $term_map[ $old_term_id ] ) ) {
				update_post_meta( $new_post_id, 'term_id', $term_map[ $old_term_id ] );
			}
		}

		foreach ( $term_map as $old_term_id => $new_term_id ) {
			// Update term_meta: 'post_id' → new post ID.
			$old_post_id_raw = get_term_meta( $new_term_id, 'post_id', true );
			$old_post_id     = is_numeric( $old_post_id_raw ) ? (int) $old_post_id_raw : 0;
			if ( $old_post_id && isset( $post_map[ $old_post_id ] ) ) {
				update_term_meta( $new_term_id, 'post_id', $post_map[ $old_post_id ] );
			}
		}
	}

	/**
	 * Copy files from extract path to destination path.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	protected function copy_files() {
		$filesystem_is_initialized = WP_Filesystem();
		if ( ! $filesystem_is_initialized ) {
			return new WP_Error( 'filesystem_error', __( 'Could not initialize filesystem', 'openlab-modules' ) );
		}

		global $wp_filesystem;

		$source_files_root = trailingslashit( $this->path ) . 'files';
		$upload_dir        = wp_upload_dir();
		$dest_files_root   = trailingslashit( $upload_dir['basedir'] );

		if ( ! $wp_filesystem->is_dir( $source_files_root ) ) {
			return new WP_Error(
				'missing_files_dir',
				// translators: %s is the source files directory.
				sprintf( __( 'Source files directory not found: %s', 'openlab-modules' ), $source_files_root )
			);
		}

		// Recursively scan source files dir.
		$all_files = $this->recursive_list_files( $source_files_root );

		foreach ( $all_files as $source_file ) {
			// Relative path from files root, e.g., '2025/05/foo.jpg'.
			$relative_path = ltrim( str_replace( $source_files_root, '', $source_file ), '/\\' );

			$destination_path = $dest_files_root . $relative_path;
			$destination_dir  = dirname( $destination_path );

			// Make sure destination directory exists.
			if ( ! $wp_filesystem->is_dir( $destination_dir ) ) {
				$wp_filesystem->mkdir( $destination_dir, FS_CHMOD_DIR, true );
			}

			// Copy the file.
			$success = $wp_filesystem->copy( $source_file, $destination_path, true );
			if ( ! $success ) {
				return new WP_Error(
					'copy_failed',
					// translators: %1$s is the source file, %2$s is the destination path.
					sprintf( __( 'Failed to copy %1$s to %2$s', 'openlab-modules' ), $source_file, $destination_path )
				);
			}
		}

		return true;
	}

	/**
	 * Recursively list all files in a directory.
	 *
	 * @param string $dir Directory to scan.
	 * @return array List of file paths.
	 */
	protected function recursive_list_files( $dir ) {
		global $wp_filesystem;

		$result = [];

		$entries = $wp_filesystem->dirlist( $dir );

		foreach ( $entries as $name => $entry ) {
			$full_path = trailingslashit( $dir ) . $name;

			if ( 'f' === $entry['type'] ) {
				$result[] = $full_path;
			} elseif ( 'd' === $entry['type'] ) {
				$result = array_merge( $result, $this->recursive_list_files( $full_path ) );
			}
		}

		return $result;
	}

	/**
	 * Use stored mapping information to update old attachment URLs
	 *
	 * @return void
	 */
	protected function replace_attachment_urls_in_content() {
		global $wpdb;
		// make sure we do the longest urls first, in case one is a substring of another.
		uksort( $this->url_remap, array( $this, 'cmpr_strlen' ) );

		foreach ( $this->url_remap as $from_url => $to_url ) {
			// remap urls in post_content.
			$query = $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $from_url, $to_url );

			// phpcs:disable WordPress.DB
			$wpdb->query( $query );

			// remap enclosure urls.
			$query = $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $from_url, $to_url );

			// phpcs:disable WordPress.DB
			$result = $wpdb->query( $query );
		}
	}

	/**
	 * Update _thumbnail_id meta to new, imported attachment IDs
	 *
	 * @return void
	 */
	protected function remap_featured_images() {
		// cycle through posts that have a featured image.
		foreach ( $this->featured_images as $post_id => $value ) {
			if ( isset( $this->processed_posts[ $value ] ) ) {
				$new_id = $this->processed_posts[ $value ];

				// only update if there's a difference.
				if ( $new_id !== $value ) {
					update_post_meta( $post_id, '_thumbnail_id', $new_id );
				}
			}
		}
	}

	/**
	 * Decide if the given meta key maps to information we will want to import.
	 *
	 * @param string $key The meta key to check.
	 * @return string|bool The key if we do want to import, false if not.
	 */
	public function is_valid_meta_key( $key ) {
		// skip attachment metadata since we'll regenerate it from scratch.
		// skip _edit_lock as not relevant for import.
		if ( in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ), true ) ) {
			return false;
		}

		return $key;
	}

	/**
	 * Decide what the maximum file size for downloaded attachments is.
	 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
	 *
	 * @return int Maximum attachment file size to import
	 */
	protected function max_attachment_size() {
		return apply_filters( 'import_attachment_size_limit', 0 );
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import.
	 *
	 * @access protected
	 *
	 * @return int 60
	 */
	public function bump_request_timeout() {
		return 60;
	}

	/**
	 * Compare function for sorting by string length.
	 *
	 * @param string $a First string.
	 * @param string $b Second string.
	 * @return int Comparison result.
	 */
	protected function cmpr_strlen( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}

	/**
	 * Prefill existing post data.
	 *
	 * This preloads all GUIDs into memory, allowing us to avoid hitting the
	 * database when we need to check for existence. With larger imports, this
	 * becomes prohibitively slow to perform SELECT queries on each.
	 *
	 * By preloading all this data into memory, it's a constant-time lookup in
	 * PHP instead. However, this does use a lot more memory, so for sites doing
	 * small imports onto a large site, it may be a better tradeoff to use
	 * on-the-fly checking instead.
	 *
	 * @return void
	 */
	protected function prefill_existing_posts() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts}" );

		foreach ( $posts as $item ) {
			$this->exists['post'][ $item->guid ] = $item->ID;
		}
	}

	/**
	 * Does the post exist?
	 *
	 * @param array $data Post data to check against.
	 * @return int|bool Existing post ID if it exists, false otherwise.
	 */
	protected function post_exists( $data ) {
		// Constant-time lookup if we prefilled.
		$exists_key = $data['guid'];

		if ( $this->options['prefill_existing_posts'] ) {
			return isset( $this->exists['post'][ $exists_key ] ) ? $this->exists['post'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it.
		if ( isset( $this->exists['post'][ $exists_key ] ) ) {
			return $this->exists['post'][ $exists_key ];
		}

		// Still nothing, try post_exists, and cache it.
		$exists                              = post_exists( $data['post_title'], $data['post_content'], $data['post_date'] );
		$this->exists['post'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Mark the post as existing.
	 *
	 * @param array $data Post data to mark as existing.
	 * @param int   $post_id Post ID.
	 *
	 * @return void
	 */
	protected function mark_post_exists( $data, $post_id ) {
		$exists_key                          = $data['guid'];
		$this->exists['post'][ $exists_key ] = $post_id;
	}

	/**
	 * Prefill existing comment data.
	 *
	 * @see self::prefill_existing_posts() for justification of why this exists.
	 *
	 * @return void
	 */
	protected function prefill_existing_comments() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$posts = $wpdb->get_results( "SELECT comment_ID, comment_author, comment_date FROM {$wpdb->comments}" );

		foreach ( $posts as $item ) {
			$exists_key                             = sha1( $item->comment_author . ':' . $item->comment_date );
			$this->exists['comment'][ $exists_key ] = $item->comment_ID;
		}
	}

	/**
	 * Does the comment exist?
	 *
	 * @param array $data Comment data to check against.
	 * @return int|bool Existing comment ID if it exists, false otherwise.
	 */
	protected function comment_exists( $data ) {
		$exists_key = sha1( $data['comment_author'] . ':' . $data['comment_date'] );

		// Constant-time lookup if we prefilled.
		if ( $this->options['prefill_existing_comments'] ) {
			return isset( $this->exists['comment'][ $exists_key ] ) ? $this->exists['comment'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it.
		if ( isset( $this->exists['comment'][ $exists_key ] ) ) {
			return $this->exists['comment'][ $exists_key ];
		}

		// Still nothing, try comment_exists, and cache it.
		$exists = comment_exists( $data['comment_author'], $data['comment_date'] );
		if ( $exists && is_numeric( $exists ) ) {
			$exists = (int) $exists;
		} else {
			$exists = false;
		}

		$this->exists['comment'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Mark the comment as existing.
	 *
	 * @param array $data Comment data to mark as existing.
	 * @param int   $comment_id Comment ID.
	 *
	 * @return void
	 */
	protected function mark_comment_exists( $data, $comment_id ) {
		$exists_key                             = sha1( $data['comment_author'] . ':' . $data['comment_date'] );
		$this->exists['comment'][ $exists_key ] = $comment_id;
	}

	/**
	 * Prefill existing term data.
	 *
	 * @see self::prefill_existing_posts() for justification of why this exists.
	 *
	 * @return void
	 */
	protected function prefill_existing_terms() {
		global $wpdb;
		$query  = "SELECT t.term_id, tt.taxonomy, t.slug FROM {$wpdb->terms} AS t";
		$query .= " JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id";

		// phpcs:disable WordPress.DB
		$terms = $wpdb->get_results( $query );

		foreach ( $terms as $item ) {
			$exists_key                          = sha1( $item->taxonomy . ':' . $item->slug );
			$this->exists['term'][ $exists_key ] = $item->term_id;
		}
	}

	/**
	 * Does the term exist?
	 *
	 * @param array $data Term data to check against.
	 * @return int|bool Existing term ID if it exists, false otherwise.
	 */
	protected function term_exists( $data ) {
		$exists_key = sha1( $data['taxonomy'] . ':' . $data['slug'] );

		// Constant-time lookup if we prefilled.
		if ( $this->options['prefill_existing_terms'] ) {
			return isset( $this->exists['term'][ $exists_key ] ) ? $this->exists['term'][ $exists_key ] : false;
		}

		// No prefilling, but might have already handled it.
		if ( isset( $this->exists['term'][ $exists_key ] ) ) {
			return $this->exists['term'][ $exists_key ];
		}

		// Still nothing, try comment_exists, and cache it.
		$exists = term_exists( $data['slug'], $data['taxonomy'] );
		if ( is_array( $exists ) ) {
			$exists = (int) $exists['term_id'];
		} elseif ( ! $exists ) {
			$exists = false;
		}

		$this->exists['term'][ $exists_key ] = $exists;

		return $exists;
	}

	/**
	 * Mark the term as existing.
	 *
	 * @param array $data Term data to mark as existing.
	 * @param int   $term_id Term ID.
	 * @return void
	 */
	protected function mark_term_exists( $data, $term_id ) {
		$exists_key                          = sha1( $data['taxonomy'] . ':' . $data['slug'] );
		$this->exists['term'][ $exists_key ] = $term_id;
	}

	/**
	 * Normalize uploads file path for legacy MS.
	 *
	 * @param string $path Extracted archvie path.
	 * @return string $path
	 */
	protected function normalize_path( $path ) {
		$upload_blogs_dir = '/wp-content/blogs.dir/';

		// Check for legacy file-serving paths.
		if ( is_dir( $path . $upload_blogs_dir ) ) {
			$path = $path . $upload_blogs_dir . $this->site_id;
		}

		return $path;
	}
}
