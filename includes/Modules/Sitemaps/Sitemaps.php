<?php
namespace MMSEO\Modules\Sitemaps;

use MMSEO\Modules\Module;
use MMSEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * XML Sitemap module.
 *
 * Provides standard sitemap URLs:
 *   /sitemap_index.xml
 *   /{type}-sitemap.xml
 *   /{type}-sitemap{N}.xml
 *   /mmseo-sitemap.xsl  (XSL stylesheet)
 *
 * Disables core WP sitemaps and 301-redirects /wp-sitemap.xml.
 * Results are transient-cached with stamp-based invalidation.
 */
class Sitemaps extends Module {

	/**
	 * Transient cache TTL: 6 hours.
	 */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Option name for the cache invalidation stamp.
	 */
	private const STAMP_OPTION = 'mmseo_sitemap_stamp';

	/**
	 * Return the module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'sitemaps';
	}

	/**
	 * Register all hooks for the sitemaps module.
	 *
	 * @return void
	 */
	public function register(): void {
		// Bail if frontend is suspended (another SEO plugin active).
		if ( Plugin::is_frontend_suspended() ) {
			return;
		}

		// Disable WordPress core sitemaps.
		add_filter( 'wp_sitemaps_enabled', '__return_false' );

		// Register rewrite rules on init.
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );

		// Add custom query vars.
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );

		// Route sitemap requests (early template_redirect).
		add_action( 'template_redirect', [ $this, 'route' ], 1 );

		// Append Sitemap: line to robots.txt.
		add_filter( 'robots_txt', [ $this, 'robots_txt_sitemap' ], 10, 2 );

		// Cache invalidation hooks.
		add_action( 'save_post',     [ $this, 'bump_stamp' ] );
		add_action( 'deleted_post',  [ $this, 'bump_stamp' ] );
		add_action( 'edited_term',   [ $this, 'bump_stamp' ] );
		add_action( 'delete_term',   [ $this, 'bump_stamp' ] );
	}

	/**
	 * Register MM SEO sitemap rewrite rules.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		// sitemap_index.xml
		add_rewrite_rule(
			'^sitemap_index\.xml$',
			'index.php?mmseo_sitemap=index',
			'top'
		);

		// {type}-sitemap{page}.xml  (page is optional)
		add_rewrite_rule(
			'^([a-z0-9_-]+?)-sitemap([0-9]+)?\.xml$',
			'index.php?mmseo_sitemap=$matches[1]&mmseo_sitemap_page=$matches[2]',
			'top'
		);

		// XSL stylesheet
		add_rewrite_rule(
			'^mmseo-sitemap\.xsl$',
			'index.php?mmseo_sitemap_xsl=1',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'mmseo_sitemap';
		$vars[] = 'mmseo_sitemap_page';
		$vars[] = 'mmseo_sitemap_xsl';
		return $vars;
	}

	/**
	 * Route sitemap requests.
	 *
	 * Handles:
	 *   - /mmseo-sitemap.xsl  → serve XSL stylesheet
	 *   - /sitemap_index.xml  → serve sitemap index
	 *   - /{type}-sitemap.xml → serve URL set
	 *   - /wp-sitemap.xml     → 301 redirect to /sitemap_index.xml
	 *
	 * @return void
	 */
	public function route(): void {
		global $wp_query;

		// 301 redirect /wp-sitemap.xml → /sitemap_index.xml.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( preg_match( '#/wp-sitemap\.xml(?:\?.*)?$#', $request_uri ) ) {
			wp_redirect( home_url( '/sitemap_index.xml' ), 301 );
			exit;
		}

		// Serve XSL stylesheet.
		if ( get_query_var( 'mmseo_sitemap_xsl' ) ) {
			$this->serve_xsl();
			exit;
		}

		$sitemap_type = get_query_var( 'mmseo_sitemap' );
		if ( '' === $sitemap_type ) {
			return;
		}

		$page = max( 1, (int) get_query_var( 'mmseo_sitemap_page' ) );

		if ( 'index' === $sitemap_type ) {
			$xml = $this->get_cached( 'index', 0, function (): string {
				return $this->render_index();
			} );
			$this->send_xml( $xml );
			exit;
		}

		// Type-specific sitemap.
		$provider = $this->find_provider( $sitemap_type );

		if ( null === $provider ) {
			// Unknown sitemap type → 404.
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		$xml = $this->get_cached( $sitemap_type, $page, function () use ( $provider, $sitemap_type, $page ): string {
			return $this->render_urlset( $provider, $sitemap_type, $page );
		} );

		if ( '' === $xml ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		$this->send_xml( $xml );
		exit;
	}

	/**
	 * Append the Sitemap: line to robots.txt output.
	 *
	 * @param string $output  Current robots.txt content.
	 * @param bool   $public  Whether the site is set to be indexed publicly.
	 * @return string
	 */
	public function robots_txt_sitemap( string $output, bool $public ): string {
		if ( $public ) {
			$output .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n";
		}
		return $output;
	}

	/**
	 * Bump the sitemap cache invalidation stamp.
	 *
	 * Called on post save/delete and term edit/delete.
	 *
	 * @return void
	 */
	public function bump_stamp(): void {
		update_option( self::STAMP_OPTION, time(), false );
	}

	/**
	 * Flush the sitemap cache by bumping the stamp.
	 *
	 * Public static method — used by the admin flush button and WP-CLI.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		update_option( self::STAMP_OPTION, time(), false );
	}

	// -------------------------------------------------------------------------
	// Private implementation
	// -------------------------------------------------------------------------

	/**
	 * Get (or generate and cache) XML for a sitemap type + page.
	 *
	 * @param string   $type    Sitemap type or 'index'.
	 * @param int      $page    Page number (0 for index).
	 * @param callable $builder Callable that generates the XML string.
	 * @return string XML string.
	 */
	private function get_cached( string $type, int $page, callable $builder ): string {
		$stamp     = (int) get_option( self::STAMP_OPTION, 1 );
		$cache_key = 'mmseo_sm_' . md5( $type . '_' . $page . '_' . $stamp );
		// Transient keys are limited to 172 chars; md5 gives 32 + prefix 8 = 40.

		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_string( $cached ) ) {
			return $cached;
		}

		$xml = $builder();

		set_transient( $cache_key, $xml, self::CACHE_TTL );

		return $xml;
	}

	/**
	 * Render the sitemap index XML.
	 *
	 * @return string
	 */
	private function render_index(): string {
		$providers = $this->get_providers();
		$entries   = [];

		foreach ( $providers as $provider ) {
			$entries = array_merge( $entries, $provider->index_entries() );
		}

		return Renderer::index( $entries );
	}

	/**
	 * Render a URL-set XML for a specific provider type + page.
	 *
	 * @param Provider $provider Resolved provider.
	 * @param string   $type     Sitemap type slug.
	 * @param int      $page     Page number.
	 * @return string XML string or empty string when no URLs found.
	 */
	private function render_urlset( Provider $provider, string $type, int $page ): string {
		$urls = $provider->page( $type, $page );

		if ( empty( $urls ) ) {
			return '';
		}

		return Renderer::urlset( $urls );
	}

	/**
	 * Serve the XSL stylesheet from the assets directory.
	 *
	 * @return void
	 */
	private function serve_xsl(): void {
		$xsl_file = MMSEO_DIR . 'assets/xsl/sitemap.xsl';

		if ( ! file_exists( $xsl_file ) ) {
			status_header( 404 );
			return;
		}

		header( 'Content-Type: text/xsl; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		nocache_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		readfile( $xsl_file );
	}

	/**
	 * Send the sitemap XML response with the correct headers.
	 *
	 * @param string $xml XML content.
	 * @return void
	 */
	private function send_xml( string $xml ): void {
		status_header( 200 );
		header( 'Content-Type: text/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );
		nocache_headers();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $xml;
	}

	/**
	 * Return the registered sitemap providers.
	 *
	 * Providers are filterable via 'mmseo_sitemap_providers'.
	 *
	 * @return Provider[]
	 */
	private function get_providers(): array {
		$providers = [
			new PostsProvider(),
			new TermsProvider(),
			new AuthorsProvider(),
		];

		/**
		 * Filter the list of sitemap providers.
		 *
		 * @param Provider[] $providers Registered providers.
		 */
		return (array) apply_filters( 'mmseo_sitemap_providers', $providers );
	}

	/**
	 * Find the provider that handles a given sitemap type slug.
	 *
	 * @param string $type Sitemap type slug (e.g. 'post', 'category', 'author').
	 * @return Provider|null
	 */
	private function find_provider( string $type ): ?Provider {
		foreach ( $this->get_providers() as $provider ) {
			if ( in_array( $type, $provider->types(), true ) ) {
				return $provider;
			}
		}
		return null;
	}
}
