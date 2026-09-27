<?php
namespace MMSEO\Modules\Sitemaps;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for sitemap providers.
 *
 * Each provider declares which sitemap slugs it handles and supplies
 * the index entries plus per-page URL sets.
 */
abstract class Provider {

	/**
	 * Number of URLs per sitemap page.
	 * Filterable via the 'mmseo_sitemap_per_page' filter.
	 */
	const PER_PAGE = 1000;

	/**
	 * Return the sitemap slugs this provider handles.
	 *
	 * Example: ['post', 'page', 'portfolio']
	 *
	 * @return string[]
	 */
	abstract public function types(): array;

	/**
	 * Return the entries for the sitemap index.
	 *
	 * Each entry: ['loc' => string, 'lastmod' => string|null]
	 *
	 * @return array<int, array{loc: string, lastmod: string|null}>
	 */
	abstract public function index_entries(): array;

	/**
	 * Return the URL entries for a specific sitemap page.
	 *
	 * Each entry: [
	 *   'loc'     => string,
	 *   'lastmod' => string|null,
	 *   'images'  => [['src' => string, 'title' => string], ...]
	 * ]
	 *
	 * @param string $type Sitemap slug (e.g. 'post').
	 * @param int    $page 1-based page number.
	 * @return array<int, array{loc: string, lastmod: string|null, images: array}>
	 */
	abstract public function page( string $type, int $page ): array;

	/**
	 * Return the effective per-page limit (respecting the filter).
	 *
	 * @return int
	 */
	protected function per_page(): int {
		return (int) apply_filters( 'mmseo_sitemap_per_page', self::PER_PAGE );
	}
}
