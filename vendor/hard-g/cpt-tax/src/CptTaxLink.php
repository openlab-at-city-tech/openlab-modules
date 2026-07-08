<?php
/**
 * Utility for linking CPT items to taxonomy items.
 */

namespace HardG\CptTax;

/**
 * Utility class for linking each post of a CPT to a corresponding taxonomy item.
 */
class CptTaxLink {
	/**
	 * Post type name.
	 *
	 * @var string
	 */
	protected $post_type;

	/**
	 * Taxonomy name.
	 *
	 * @var string
	 */
	protected $taxonomy;

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type name.
	 * @param string $taxonomy  Taxonomy name.
	 */
	public function __construct( $post_type, $taxonomy ) {
		$this->post_type = $post_type;
		$this->taxonomy  = $taxonomy;

		add_action( 'save_post', [ $this, 'maybe_create_tax_term' ], 15, 3 );
		add_action( 'before_delete_post', [ $this, 'maybe_delete_tax_term' ] );
	}

	/**
	 * Gets the name of the post type associated with this link.
	 *
	 * @return string
	 */
	public function get_post_type() {
		return $this->post_type;
	}

	/**
	 * Gets the name of the taxonomy associated with this link.
	 *
	 * @return string
	 */
	public function get_taxonomy() {
		return $this->taxonomy;
	}

	/**
	 * 'save_post' callback that creates a tax term, if necessary.
	 *
	 * @param int      $post_id ID of the post being edited.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  True if this is an update, false if it's a new post.
	 * @return null
	 */
	public function maybe_create_tax_term( $post_id, $post, $update ) {
		if ( $this->post_type !== $post->post_type ) {
			return;
		}

		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$create = false;
		if ( $update ) {
			$term_id = $this->get_term_id_for_post_id( $post_id );
			if ( $term_id ) {
				$term = get_term( $term_id, $this->taxonomy );
				if ( $term->slug !== $post->post_name || $term->name !== $post->post_title ) {
					wp_update_term(
						$term->term_id,
						$this->taxonomy,
						[
							'name' => $post->post_title,
							'slug' => $post->post_name,
						]
					);
				}
			} else {
				$create = true;
			}
		} else {
			$create = true;
		}

		if ( $create ) {
			// In case of empties.
			$term_name = ! empty( $post->post_title ) ? $post->post_title : sprintf( 'Term corresponding to post %s', $post->ID );

			$term = wp_insert_term(
				$term_name,
				$this->taxonomy,
				[
					'slug' => $post->post_name,
				]
			);

			// This should never happen.
			if ( is_wp_error( $term ) ) {
				return $term;
			}

			$term_id = $term['term_id'];

			update_term_meta( $term_id, 'post_id', $post_id );
			update_post_meta( $post_id, 'term_id', $term_id );
		}
	}

	/**
	 * Callback that deletes a tax term on post deletion.
	 *
	 * @param int $post_id ID of the post being edited.
	 * @return null
	 */
	public function maybe_delete_tax_term( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || $this->post_type !== $post->post_type ) {
			return;
		}

		$term_id = $this->get_term_id_for_post_id( $post_id );
		if ( ! $term_id ) {
			return;
		}

		wp_delete_term( $term_id, $this->taxonomy );
	}

	/**
	 * Fetches the term ID associated with a post ID.
	 *
	 * @param int $post_id ID of the post.
	 * @return int
	 */
	public function get_term_id_for_post_id( $post_id ) {
		return (int) get_post_meta( $post_id, 'term_id', true );
	}

	/**
	 * Fetches the post ID associated with a term ID.
	 *
	 * @param int $term_id ID of the term.
	 * @return int
	 */
	public function get_post_id_for_term_id( $term_id ) {
		return (int) get_term_meta( $term_id, 'post_id', true );
	}
}
