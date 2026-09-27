<?php
namespace MMSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Template variable replacer. Supports the %%var%% placeholder syntax.
 */
class Replacer {

	/**
	 * Replace all %%var%% placeholders in $template using $context.
	 *
	 * Context keys:
	 *   'post'  => WP_Post
	 *   'term'  => WP_Term
	 *
	 * @param string               $template Raw template string.
	 * @param array<string, mixed> $context  Optional context data.
	 * @return string Processed string.
	 */
	public static function replace( string $template, array $context = [] ): string {
		/** @var \WP_Post|null $post */
		$post = $context['post'] ?? null;
		/** @var \WP_Term|null $term */
		$term = $context['term'] ?? null;

		$sep      = Options::get( 'separator', '|' );
		$sitename = get_bloginfo( 'name' );

		$replacements = [];

		// %%sitename%%
		$replacements['%%sitename%%'] = $sitename;

		// %%tagline%%
		$replacements['%%tagline%%'] = get_bloginfo( 'description' );

		// %%sep%%
		$replacements['%%sep%%'] = $sep;

		// %%currentyear%%
		$replacements['%%currentyear%%'] = date_i18n( 'Y' );

		// %%currentdate%%
		$replacements['%%currentdate%%'] = date_i18n( get_option( 'date_format' ) );

		// %%searchphrase%%
		$replacements['%%searchphrase%%'] = get_search_query();

		// Post-dependent replacements.
		if ( $post instanceof \WP_Post ) {
			// %%title%%
			$replacements['%%title%%'] = get_the_title( $post );

			// %%excerpt%%
			$excerpt = $post->post_excerpt;
			if ( '' === $excerpt ) {
				$excerpt = wp_trim_words(
					wp_strip_all_tags( $post->post_content ),
					30, // ~156 chars equivalent
					''
				);
				$excerpt = mb_substr( $excerpt, 0, 156 );
			}
			$replacements['%%excerpt%%'] = $excerpt;

			// %%category%% — primary or first category.
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) ) {
				$primary_id   = (int) Meta::get_post( $post->ID, 'primary_category' );
				$primary_cat  = null;
				if ( $primary_id ) {
					foreach ( $categories as $cat ) {
						if ( (int) $cat->term_id === $primary_id ) {
							$primary_cat = $cat;
							break;
						}
					}
				}
				$replacements['%%category%%'] = $primary_cat
					? $primary_cat->name
					: $categories[0]->name;
			} else {
				$replacements['%%category%%'] = '';
			}

			// %%date%% — post date.
			$replacements['%%date%%'] = date_i18n(
				get_option( 'date_format' ),
				get_post_timestamp( $post )
			);

			// %%author%% — post author display name.
			$replacements['%%author%%'] = get_the_author_meta( 'display_name', (int) $post->post_author );

			// %%pt_single%% / %%pt_plural%%
			$pt_obj = get_post_type_object( $post->post_type );
			if ( $pt_obj ) {
				$replacements['%%pt_single%%'] = $pt_obj->labels->singular_name ?? '';
				$replacements['%%pt_plural%%'] = $pt_obj->labels->name ?? '';
			} else {
				$replacements['%%pt_single%%'] = '';
				$replacements['%%pt_plural%%'] = '';
			}
		} else {
			// Fallbacks when no post.
			$replacements['%%title%%']     = '';
			$replacements['%%excerpt%%']   = '';
			$replacements['%%category%%']  = '';
			$replacements['%%date%%']      = '';
			$replacements['%%author%%']    = '';
			$replacements['%%pt_single%%'] = '';
			$replacements['%%pt_plural%%'] = '';
		}

		// Term-dependent replacements.
		if ( $term instanceof \WP_Term ) {
			$replacements['%%term_title%%']       = $term->name;
			$replacements['%%term_description%%'] = $term->description;
			// Also fill %%title%% for term context.
			if ( '' === $replacements['%%title%%'] ) {
				$replacements['%%title%%'] = $term->name;
			}
		} else {
			$replacements['%%term_title%%']       = '';
			$replacements['%%term_description%%'] = '';
		}

		// %%archive_title%%
		$replacements['%%archive_title%%'] = self::build_archive_title();

		// %%page%% — "Page X of Y" for paginated archives.
		$paged = (int) get_query_var( 'paged' );
		if ( $paged > 1 ) {
			// max_num_pages from current query.
			global $wp_query;
			$max = isset( $wp_query ) ? (int) $wp_query->max_num_pages : 0;
			if ( $max > 1 ) {
				/* translators: 1: current page number, 2: total page count */
				$replacements['%%page%%'] = sprintf(
					__( 'Page %1$d of %2$d', 'mm-seo' ),
					$paged,
					$max
				);
			} else {
				$replacements['%%page%%'] = '';
			}
		} else {
			$replacements['%%page%%'] = '';
		}

		// %%pagenumber%%
		$replacements['%%pagenumber%%'] = $paged > 0 ? (string) $paged : '';

		// Perform replacements.
		$result = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$template
		);

		// Remove any unknown %%vars%%.
		$result = preg_replace( '/%%[a-zA-Z0-9_]+%%/', '', $result ) ?? $result;

		// Collapse multiple spaces.
		$result = preg_replace( '/\s{2,}/', ' ', $result ) ?? $result;

		// Trim leading/trailing separator + spaces.
		$sep_escaped = preg_quote( $sep, '/' );
		$result      = preg_replace(
			'/^\s*' . $sep_escaped . '\s*|\s*' . $sep_escaped . '\s*$/',
			'',
			trim( $result )
		) ?? trim( $result );
		$result      = trim( $result );

		/**
		 * Filter the final replaced template string.
		 *
		 * @param string               $result  Processed string.
		 * @param string               $template Original template.
		 * @param array<string, mixed> $context  Context data.
		 */
		return (string) apply_filters( 'mmseo_replaced_template', $result, $template, $context );
	}

	/**
	 * Build an archive title without the type prefix (e.g. "Category: " stripped).
	 */
	private static function build_archive_title(): string {
		if ( is_post_type_archive() ) {
			$title = post_type_archive_title( '', false );
			return is_string( $title ) ? $title : '';
		}

		if ( function_exists( 'get_the_archive_title' ) ) {
			$title = get_the_archive_title();
			// Strip "Category: ", "Tag: ", etc. prefix added by core.
			$title = preg_replace( '/^[^:]+:\s*/', '', $title ) ?? $title;
			return $title;
		}

		return '';
	}
}
