<?php
/**
 * Registry for linked Custom Post Types and taxonomies
 */

namespace HardG\CptTax;

use \Exception;

/**
 * Registry.
 */
class Registry {
	/**
	 * Registered links.
	 *
	 * @var array
	 */
	protected $links = [];

	/**
	 * Gets the instance of the registry.
	 *
	 * @return HardG\CPTTax\Registry
	 */
	protected static function get_instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Registers a linked CPT and taxonomy.
	 *
	 * @param string $key Unique key used to reference this link.
	 * @param string $post_type Post type name.
	 * @param string $taxonomy  Taxonomy name.
	 *
	 * @return boolean True on success. False if it already is registered.
	 */
	public static function register( $key, $post_type, $taxonomy ) {
		if ( ! function_exists( '\post_type_exists' ) ) {
			throw new Exception( 'WordPress must be installed to use this library.' );
		}

		if ( ! post_type_exists( $post_type ) ) {
			throw new Exception( sprintf( 'Post type does not exist: %s', $post_type ) );
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new Exception( sprintf( 'Taxonomy does not exist: %s', $taxonomy ) );
		}

		$registry = self::get_instance();

		if ( ! empty( $registry->links[ $key ] ) ) {
			return false;
		}

		$registry->links[ $key ] = new CptTaxLink( $post_type, $taxonomy );

		return true;
	}

	/**
	 * Gets the post-type/taxonomy link corresponding to a key.
	 *
	 * @param string $key Unique key.
	 * @return \HardG\CptTax\CptTax
	 */
	public static function get_link( $key ) {
		$registry = self::get_instance();
		return $registry->get_link_by_key( $key );
	}

	/**
	 * Gets the post-type/taxonomy link corresponding to a key.
	 *
	 * @param string $key Unique key.
	 * @return \HardG\CptTax\CptTax
	 */
	public function get_link_by_key( $key ) {
		if ( empty( $this->links[ $key ] ) ) {
			throw Exception( sprintf( 'CPT-Taxonomy link with the following key has not been registered: %s', $key ) );
		}

		return $this->links[ $key ];
	}

	/**
	 * Gets the term ID corresponding to a post ID.
	 *
	 * @param $key     Unique key for the post-type/taxonomy link.
	 * @param $post_id ID of the post.
	 * @return int
	 */
	public static function get_term_id_for_post_id( $key, $post_id ) {
		$link = self::get_link( $key );
		return $link->get_term_id_for_post_id( $post_id );
	}

	/**
	 * Gets the post ID corresponding to a term ID.
	 *
	 * @param $key     Unique key for the post-type/taxonomy link.
	 * @param $term_id ID of the term.
	 * @return int
	 */
	public static function get_post_id_for_term_id( $key, $post_id ) {
		$link = self::get_link( $key );
		return $link->get_post_id_for_term_id( $post_id );
	}
}
