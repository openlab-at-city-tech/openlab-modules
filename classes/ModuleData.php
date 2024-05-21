<?php
/**
 * Module data object.
 *
 * @package openlab-modules
 */

namespace OpenLab\Modules;

/**
 * Module data object.
 *
 * This abstraction over Module can be used by different export/clone tools.
 */
class ModuleData {
	/**
	 * Data.
	 *
	 * @var array{
	 *     id: int,
	 *     title: string,
	 *     content: string,
	 *     description: string,
	 *     nav_title: string,
	 *     slug: string,
	 *     url: string,
	 *     enable_sharing: bool,
	 *     pages: array<array{id: int, title: string, slug: string, url: string, content: string}>
	 *     attachments: array<array{id: int, url: string, path: string, alt: string, title: string, content: string, excerpt: string, item_id: int}>
	 * }
	 */
	protected $data = [
		'id'             => 0,
		'title'          => '',
		'content'        => '',
		'description'    => '',
		'nav_title'      => '',
		'slug'           => '',
		'url'            => '',
		'enable_sharing' => false,
		'pages'          => [],
		'attachments'    => [],
	];

	/**
	 * Set module ID.
	 *
	 * @param int $module_id Module ID.
	 * @return void
	 */
	public function set_id( $module_id ) {
		$this->data['id'] = $module_id;
	}

	/**
	 * Get module ID.
	 *
	 * @return int
	 */
	public function get_module_id() {
		return $this->data['id'];
	}

	/**
	 * Set title.
	 *
	 * @param string $title Title.
	 * @return void
	 */
	public function set_title( $title ) {
		$this->data['title'] = $title;
	}

	/**
	 * Get title.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->data['title'];
	}

	/**
	 * Set content.
	 *
	 * @param string $content Content.
	 * @return void
	 */
	public function set_content( $content ) {
		$this->data['content'] = $content;
	}

	/**
	 * Get content.
	 *
	 * @return string
	 */
	public function get_content() {
		return $this->data['content'];
	}

	/**
	 * Set description.
	 *
	 * @param string $description Description.
	 * @return void
	 */
	public function set_description( $description ) {
		$this->data['description'] = $description;
	}

	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->data['description'];
	}

	/**
	 * Set nav title.
	 *
	 * @param string $nav_title Navigation title.
	 * @return void
	 */
	public function set_nav_title( $nav_title ) {
		$this->data['nav_title'] = $nav_title;
	}

	/**
	 * Get nav title.
	 *
	 * @return string
	 */
	public function get_nav_title() {
		return $this->data['nav_title'];
	}

	/**
	 * Set slug.
	 *
	 * @param string $slug Slug.
	 * @return void
	 */
	public function set_slug( $slug ) {
		$this->data['slug'] = $slug;
	}

	/**
	 * Get slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->data['slug'];
	}

	/**
	 * Set URL.
	 *
	 * @param string $url URL.
	 * @return void
	 */
	public function set_url( $url ) {
		$this->data['url'] = $url;
	}

	/**
	 * Get URL.
	 *
	 * @return string
	 */
	public function get_url() {
		return $this->data['url'];
	}

	/**
	 * Set enable sharing.
	 *
	 * @param bool $enable_sharing Enable sharing.
	 * @return void
	 */
	public function set_enable_sharing( $enable_sharing ) {
		$this->data['enable_sharing'] = $enable_sharing;
	}

	/**
	 * Get enable sharing.
	 *
	 * @return bool
	 */
	public function get_enable_sharing() {
		return $this->data['enable_sharing'];
	}

	/**
	 * Add a page.
	 *
	 * @param array{id: int, title: string, slug: string, url: string, content: string} $page Array of page data.
	 * @return void
	 */
	public function add_page( $page ) {
		$this->data['pages'][] = $page;
	}

	/**
	 * Get pages.
	 *
	 * @return array{id: int, title: string, slug: string, url: string, content: string}[] $pages Array of page data.
	 */
	public function get_pages() {
		return $this->data['pages'];
	}

	/**
	 * Add an attachment.
	 *
	 * @param array{id: int, url: string, path: string, alt: string, title: string, content: string, excerpt: string} $attachment Array of attachment data.
	 * @return void
	 */
	public function add_attachment( $attachment ) {
		$this->data['attachments'][] = $attachment;
	}

	/**
	 * Get attachments.
	 *
	 * @return array{id: int, url: string, path: string, alt: string, title: string, content: string, excerpt: string, item_id: int}[] $attachments Array of attachment data.
	 */
	public function get_attachments() {
		return $this->data['attachments'];
	}
}
