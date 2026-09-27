<?php
namespace MMSEO\Frontend;

use MMSEO\Options;
use MMSEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates all MM SEO output in wp_head (priority 1).
 *
 * Emits the <!-- MM SEO --> block containing: meta description, canonical,
 * rel prev/next, Open Graph / Twitter cards, verification meta tags,
 * and JSON-LD schema. Also manages the wp_robots filter and removes
 * WordPress core's rel_canonical (replaced by ours).
 */
class Head {

	/** @var MetaTags */
	private MetaTags $meta_tags;

	/** @var Social */
	private Social $social;

	/** @var Title */
	private Title $title;

	/**
	 * Register all frontend hooks.
	 * Bails silently if frontend is suspended (another SEO plugin active).
	 */
	public function register(): void {
		if ( Plugin::is_frontend_suspended() ) {
			return;
		}

		// Instantiate helpers.
		$this->meta_tags = new MetaTags();
		$this->social    = new Social();
		$this->title     = new Title();

		// Title hooks (Title registers itself).
		$this->title->register();

		// Robots filter.
		add_filter( 'wp_robots', [ $this->meta_tags, 'robots' ] );

		// Replace core canonical with ours.
		remove_action( 'wp_head', 'rel_canonical' );

		// Main head output.
		add_action( 'wp_head', [ $this, 'output' ], 1 );
	}

	/**
	 * Output the MM SEO head block.
	 */
	public function output(): void {
		echo "\n<!-- MM SEO -->\n";

		// Meta description.
		$desc = $this->meta_tags->description();
		if ( '' !== $desc ) {
			printf(
				"\t<meta name=\"description\" content=\"%s\" />\n",
				esc_attr( $desc )
			);
		}

		// Canonical.
		$canonical = $this->meta_tags->canonical();
		if ( '' !== $canonical ) {
			printf(
				"\t<link rel=\"canonical\" href=\"%s\" />\n",
				esc_url( $canonical )
			);
		}

		// rel prev/next.
		$pn = $this->meta_tags->prev_next();
		if ( '' !== $pn['prev'] ) {
			printf( "\t<link rel=\"prev\" href=\"%s\" />\n", esc_url( $pn['prev'] ) );
		}
		if ( '' !== $pn['next'] ) {
			printf( "\t<link rel=\"next\" href=\"%s\" />\n", esc_url( $pn['next'] ) );
		}

		// Open Graph + Twitter (when social module enabled).
		if ( Options::module_enabled( 'social' ) ) {
			$this->social->output();
		}

		/**
		 * Extension point: modules/plugins hook here to add their own head output.
		 * The Verification module uses this to add Google/Bing/etc. meta tags.
		 */
		do_action( 'mmseo_head' );

		// JSON-LD Schema (when schema module enabled and class exists).
		if ( Options::module_enabled( 'schema' ) && class_exists( Schema::class ) ) {
			( new Schema() )->output();
		}

		echo "<!-- / MM SEO -->\n";
	}
}
