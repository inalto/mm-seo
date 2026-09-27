<?php
namespace MMSEO\Modules;

use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * RSS Optimizer module.
 *
 * Prepends/appends custom snippets to RSS feed content and excerpts.
 * Snippets are stored in mmseo_settings as rss_before / rss_after.
 *
 * Supported placeholders:
 *   %%POSTLINK%%   → <a href="{permalink}">{post title}</a>
 *   %%BLOGLINK%%   → <a href="{home_url}">{blogname}</a>
 *   %%AUTHORLINK%% → <a href="{author posts URL}">{author display name}</a>
 *
 * Optional featured image at top of feed content when rss_featured_image is truthy.
 */
class RssOptimizer extends Module {

	public function id(): string {
		return 'rss';
	}

	public function register(): void {
		add_filter( 'the_content_feed', [ $this, 'filter_feed_content' ], 10, 2 );
		add_filter( 'the_excerpt_rss',  [ $this, 'filter_feed_excerpt' ], 10 );
	}

	// -------------------------------------------------------------------------
	// Filter callbacks
	// -------------------------------------------------------------------------

	/**
	 * Filter full feed content: optional featured image + prepend/append snippets.
	 *
	 * @param string $content   Post content for the feed.
	 * @param string $feed_type Feed type (rss, atom, etc.).
	 * @return string
	 */
	public function filter_feed_content( string $content, string $feed_type ): string {
		$before = $this->process_snippet( (string) Options::get( 'rss_before', '' ) );
		$after  = $this->process_snippet( (string) Options::get( 'rss_after', '' ) );

		// Optional featured image prepended to content.
		$image_html = '';
		if ( Options::get( 'rss_featured_image', false ) ) {
			$image_html = $this->get_featured_image_html();
		}

		return $image_html . $before . $content . $after;
	}

	/**
	 * Filter RSS excerpt: prepend/append snippets (no featured image in excerpt).
	 *
	 * @param string $excerpt Post excerpt for the feed.
	 * @return string
	 */
	public function filter_feed_excerpt( string $excerpt ): string {
		$before = $this->process_snippet( (string) Options::get( 'rss_before', '' ) );
		$after  = $this->process_snippet( (string) Options::get( 'rss_after', '' ) );

		return $before . $excerpt . $after;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Replace placeholders in a snippet and sanitize output.
	 *
	 * @param string $snippet Raw snippet HTML from settings.
	 * @return string Processed, sanitized snippet.
	 */
	private function process_snippet( string $snippet ): string {
		$snippet = trim( $snippet );
		if ( '' === $snippet ) {
			return '';
		}

		$post = get_post();

		// Build replacement values.
		$permalink   = $post ? (string) get_permalink( $post->ID ) : home_url();
		$post_title  = $post ? get_the_title( $post->ID ) : '';
		$home_url    = home_url();
		$blog_name   = get_option( 'blogname' );

		$post_link  = '<a href="' . esc_url( $permalink ) . '">' . esc_html( $post_title ) . '</a>';
		$blog_link  = '<a href="' . esc_url( $home_url ) . '">' . esc_html( (string) $blog_name ) . '</a>';

		// Author link.
		$author_link = '';
		if ( $post ) {
			$author_id   = (int) $post->post_author;
			$author_name = get_the_author_meta( 'display_name', $author_id );
			$author_url  = get_author_posts_url( $author_id );
			$author_link = '<a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a>';
		}

		$snippet = str_replace( '%%POSTLINK%%',   $post_link,   $snippet );
		$snippet = str_replace( '%%BLOGLINK%%',   $blog_link,   $snippet );
		$snippet = str_replace( '%%AUTHORLINK%%', $author_link, $snippet );

		return wp_kses_post( $snippet );
	}

	/**
	 * Build a featured image HTML string for the current post in the loop.
	 *
	 * @return string HTML <img> wrapped in a <p>, or empty string if no image.
	 */
	private function get_featured_image_html(): string {
		$post = get_post();
		if ( ! $post || ! has_post_thumbnail( $post->ID ) ) {
			return '';
		}

		$image = get_the_post_thumbnail( $post->ID, 'large' );
		if ( ! $image ) {
			return '';
		}

		return '<p>' . $image . '</p>';
	}
}
