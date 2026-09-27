<?php
namespace MMSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts analyzable content from raw post content (Gutenberg blocks + Divi shortcodes).
 *
 * Pure function — no DB calls, no caching.
 */
class ContentExtractor {

	/**
	 * Extract structured data from raw post content.
	 *
	 * @param string $raw_content Raw post_content from the database.
	 * @return array{
	 *   text: string,
	 *   html: string,
	 *   headings: string[],
	 *   images: array<int, array{src: string, alt: string}>,
	 *   links: array<int, array{href: string, internal: bool}>,
	 *   first_paragraph: string,
	 *   word_count: int
	 * }
	 */
	public static function extract( string $raw_content ): array {
		// -----------------------------------------------------------------------
		// Step A: Strip Gutenberg block comment delimiters, keep inner HTML.
		// -----------------------------------------------------------------------
		$html = preg_replace( '/<!--\s*\/?wp:[^\-]*?-->/s', '', $raw_content ) ?? $raw_content;

		// -----------------------------------------------------------------------
		// Step B: Harvest Divi shortcode attributes before stripping.
		// -----------------------------------------------------------------------
		$extra_headings = [];
		$extra_images   = [];

		// Harvest title= from et_pb_blurb and et_pb_slide.
		preg_match_all(
			'/\[et_pb_(?:blurb|slide)[^\]]*\btitle="([^"]+)"/',
			$html,
			$blurb_matches
		);
		foreach ( $blurb_matches[1] as $t ) {
			$extra_headings[] = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			// Append as <h3> so headings parser picks them up.
			$html .= '<h3>' . esc_html( $t ) . '</h3>';
		}

		// Harvest src= and alt= from each et_pb_image tag (attribute order varies).
		preg_match_all( '/\[et_pb_image\b([^\]]*)\]/', $html, $image_tags );
		foreach ( $image_tags[1] as $attrs ) {
			if ( ! preg_match( '/\bsrc="([^"]+)"/', $attrs, $src_m ) ) {
				continue;
			}
			$alt = preg_match( '/\balt="([^"]*)"/', $attrs, $alt_m ) ? $alt_m[1] : '';
			$extra_images[] = [
				'src' => esc_url_raw( $src_m[1] ),
				'alt' => sanitize_text_field( $alt ),
			];
		}

		// Harvest button_text= as plain text.
		preg_match_all( '/\[et_pb_[^\]]*\bbutton_text="([^"]+)"/', $html, $btn_matches );
		// Append button texts as plain-text spans so they count in word count.
		foreach ( $btn_matches[1] as $btn ) {
			$html .= '<span>' . esc_html( $btn ) . '</span>';
		}

		// -----------------------------------------------------------------------
		// Step C: Strip [et_pb_*] / [/et_pb_*] wrapper tags, keep inner content.
		// -----------------------------------------------------------------------
		$html = preg_replace( '/\[\/et_pb_[^\]]+\]/', "\n", $html ) ?? $html;
		$html = preg_replace( '/\[et_pb_[^\]]+\]/', "\n", $html ) ?? $html;

		// -----------------------------------------------------------------------
		// Step D: Strip remaining unknown shortcode wrappers, keep inner content.
		// -----------------------------------------------------------------------
		$html = preg_replace( '/\[\/[a-zA-Z0-9_-]+\]/', ' ', $html ) ?? $html;
		$html = preg_replace( '/\[[a-zA-Z0-9_-]+(?:[^\]]*?)?\]/', ' ', $html ) ?? $html;

		// -----------------------------------------------------------------------
		// Step E: Parse the resulting HTML.
		// -----------------------------------------------------------------------

		// Headings (h2–h6).
		$headings = $extra_headings;
		preg_match_all(
			'/<h([2-6])[^>]*>(.*?)<\/h\1>/is',
			$html,
			$heading_matches
		);
		foreach ( $heading_matches[2] as $h ) {
			$headings[] = trim( wp_strip_all_tags( $h ) );
		}
		$headings = array_values( array_filter( $headings ) );

		// Images — parse <img> tags.
		$images = $extra_images;
		preg_match_all( '/<img[^>]+>/i', $html, $img_tags );
		foreach ( $img_tags[0] as $img_tag ) {
			$src = '';
			$alt = '';
			if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/', $img_tag, $m ) ) {
				$src = esc_url_raw( $m[1] );
			}
			if ( preg_match( '/\balt=["\']([^"\']*)["\']/', $img_tag, $m ) ) {
				$alt = sanitize_text_field( $m[1] );
			}
			if ( '' !== $src ) {
				$images[] = [ 'src' => $src, 'alt' => $alt ];
			}
		}
		// Deduplicate images by src.
		$seen_srcs  = [];
		$unique_imgs = [];
		foreach ( $images as $img ) {
			if ( ! isset( $seen_srcs[ $img['src'] ] ) ) {
				$seen_srcs[ $img['src'] ] = true;
				$unique_imgs[]            = $img;
			}
		}
		$images = $unique_imgs;

		// Links — parse <a href> tags.
		$links      = [];
		$home_host  = wp_parse_url( home_url(), PHP_URL_HOST ) ?? '';
		preg_match_all( '/<a\s[^>]*\bhref=["\']([^"\']+)["\'][^>]*>/i', $html, $link_matches );
		foreach ( $link_matches[1] as $href ) {
			$parsed   = wp_parse_url( $href );
			$internal = false;
			if ( isset( $parsed['host'] ) ) {
				$internal = ( $parsed['host'] === $home_host );
			} elseif ( isset( $parsed['path'] ) && '/' === substr( $parsed['path'], 0, 1 ) ) {
				$internal = true;
			}
			$links[] = [
				'href'     => $href,
				'internal' => $internal,
			];
		}

		// First paragraph.
		$first_paragraph = '';
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $p_match ) ) {
			$p_text = trim( wp_strip_all_tags( $p_match[1] ) );
			if ( '' !== $p_text ) {
				$first_paragraph = $p_text;
			}
		}

		// Plain text.
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );

		// Fall back first_paragraph to first non-empty line.
		if ( '' === $first_paragraph && '' !== $text ) {
			$lines = preg_split( '/\n+/', $text ) ?: [];
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$first_paragraph = $line;
					break;
				}
			}
		}

		// Word count (unicode-safe for Italian accents).
		$word_count = '' !== $text
			? count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) ?: [] )
			: 0;

		return [
			'text'            => $text,
			'html'            => $html,
			'headings'        => $headings,
			'images'          => $images,
			'links'           => $links,
			'first_paragraph' => $first_paragraph,
			'word_count'      => $word_count,
		];
	}

	/**
	 * Shortcut: extract images only from raw content.
	 *
	 * @param string $raw Raw post_content.
	 * @return array<int, array{src: string, alt: string}>
	 */
	public static function extract_images( string $raw ): array {
		return self::extract( $raw )['images'];
	}

	/**
	 * Case- and accent-insensitive keyword match with Italian stem heuristic.
	 *
	 * @param string $haystack Text to search in.
	 * @param string $keyword  Keyword or phrase to find.
	 * @return bool
	 */
	public static function keyword_match( string $haystack, string $keyword ): bool {
		if ( '' === $keyword ) {
			return false;
		}

		$haystack_norm = mb_strtolower( remove_accents( $haystack ) );
		$keyword_norm  = mb_strtolower( remove_accents( $keyword ) );

		if ( str_contains( $haystack_norm, $keyword_norm ) ) {
			return true;
		}

		// Italian stem heuristic: for each keyword word >4 chars, try stripping final vowel.
		$words = preg_split( '/\s+/u', $keyword_norm, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$stemmed_kw = [];
		foreach ( $words as $word ) {
			if ( mb_strlen( $word ) > 4 ) {
				$stemmed_kw[] = rtrim( $word, 'aeiouàèéìòù' );
			} else {
				$stemmed_kw[] = $word;
			}
		}
		$stemmed_phrase = implode( ' ', $stemmed_kw );
		if ( $stemmed_phrase !== $keyword_norm && str_contains( $haystack_norm, $stemmed_phrase ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Count occurrences of a keyword in text (accent- and case-insensitive).
	 *
	 * @param string $text    Source text.
	 * @param string $keyword Keyword or phrase.
	 * @return int
	 */
	public static function keyword_occurrences( string $text, string $keyword ): int {
		if ( '' === $keyword || '' === $text ) {
			return 0;
		}

		$text_norm    = mb_strtolower( remove_accents( $text ) );
		$keyword_norm = mb_strtolower( remove_accents( $keyword ) );

		$count = substr_count( $text_norm, $keyword_norm );
		return max( 0, $count );
	}
}
