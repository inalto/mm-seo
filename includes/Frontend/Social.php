<?php
namespace MMSEO\Frontend;

use MMSEO\Meta;
use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs Open Graph and Twitter Card meta tags in wp_head.
 * Called by Head::output() when the social module is enabled.
 */
class Social {

	/**
	 * Output all OG and Twitter meta tags for the current page.
	 */
	public function output(): void {
		$tags = $this->build_tags();
		foreach ( $tags as $tag ) {
			echo "\t" . $tag . "\n";
		}
	}

	/**
	 * Build the full list of meta tag HTML strings.
	 *
	 * @return string[]
	 */
	private function build_tags(): array {
		$tags    = [];
		$social  = Options::social();
		$meta_tags = new MetaTags();

		// Resolve common values.
		$canonical   = $meta_tags->canonical();
		$seo_title   = apply_filters( 'mmseo_title', '' );
		$description = $meta_tags->description();
		$locale      = str_replace( '-', '_', get_locale() );
		$site_name   = $social['og_site_name'] ?? get_bloginfo( 'name' );

		// -----------------------------------------------------------------------
		// Open Graph base.
		// -----------------------------------------------------------------------
		$tags[] = $this->og_tag( 'og:locale', $locale );

		// og:type.
		$og_type = 'website';
		if ( is_singular( 'post' ) ) {
			$og_type = 'article';
		}
		$tags[] = $this->og_tag( 'og:type', $og_type );

		// og:title.
		$og_title = '';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$og_title = Meta::get_post( $post->ID, 'og_title' );
			}
		}
		if ( '' === $og_title ) {
			$og_title = $seo_title;
		}
		if ( '' !== $og_title ) {
			$tags[] = $this->og_tag( 'og:title', $og_title );
		}

		// og:description.
		$og_desc = '';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$og_desc = Meta::get_post( $post->ID, 'og_desc' );
			}
		}
		if ( '' === $og_desc ) {
			$og_desc = $description;
		}
		if ( '' !== $og_desc ) {
			$tags[] = $this->og_tag( 'og:description', $og_desc );
		}

		// og:url.
		if ( '' !== $canonical ) {
			$tags[] = $this->og_tag( 'og:url', $canonical );
		}

		// og:site_name.
		if ( '' !== $site_name ) {
			$tags[] = $this->og_tag( 'og:site_name', $site_name );
		}

		// og:image + width/height.
		$image_url = '';
		$image_id  = 0;
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$image_url = Meta::get_post( $post->ID, 'og_image' );
				$image_id  = (int) Meta::get_post( $post->ID, 'og_image_id' );

				// Fall back to featured image.
				if ( '' === $image_url ) {
					$thumb_id = (int) get_post_thumbnail_id( $post->ID );
					if ( $thumb_id > 0 ) {
						$image_id  = $thumb_id;
						$image_url = (string) wp_get_attachment_url( $thumb_id );
					}
				}
			}
		}

		// Fall back to default share image from social settings.
		if ( '' === $image_url && ! empty( $social['og_default_image'] ) ) {
			$image_url = $social['og_default_image'];
			$image_id  = (int) ( $social['og_default_image_id'] ?? 0 );
		}

		if ( '' !== $image_url ) {
			$tags[] = $this->og_tag( 'og:image', $image_url );

			// Dimensions when we have the attachment ID.
			if ( $image_id > 0 ) {
				$src = wp_get_attachment_image_src( $image_id, 'full' );
				if ( is_array( $src ) && isset( $src[1], $src[2] ) ) {
					$tags[] = $this->og_tag( 'og:image:width', (string) $src[1] );
					$tags[] = $this->og_tag( 'og:image:height', (string) $src[2] );
				}
			}
		}

		// article:published_time / article:modified_time.
		if ( is_singular( 'post' ) ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$published = get_post_time( 'c', true, $post );
				$modified  = get_post_modified_time( 'c', true, $post );
				if ( $published ) {
					$tags[] = $this->og_tag( 'article:published_time', $published );
				}
				if ( $modified ) {
					$tags[] = $this->og_tag( 'article:modified_time', $modified );
				}
			}
		}

		// -----------------------------------------------------------------------
		// Twitter Card.
		// -----------------------------------------------------------------------
		$tw_card = $social['twitter_card'] ?? 'summary_large_image';
		$tags[]  = $this->tw_tag( 'twitter:card', $tw_card );

		if ( ! empty( $social['twitter_site'] ) ) {
			$tags[] = $this->tw_tag( 'twitter:site', $social['twitter_site'] );
		}

		// twitter:title.
		$tw_title = '';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$tw_title = Meta::get_post( $post->ID, 'tw_title' );
			}
		}
		if ( '' === $tw_title ) {
			$tw_title = $og_title;
		}
		if ( '' !== $tw_title ) {
			$tags[] = $this->tw_tag( 'twitter:title', $tw_title );
		}

		// twitter:description.
		$tw_desc = '';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$tw_desc = Meta::get_post( $post->ID, 'tw_desc' );
			}
		}
		if ( '' === $tw_desc ) {
			$tw_desc = $og_desc;
		}
		if ( '' !== $tw_desc ) {
			$tags[] = $this->tw_tag( 'twitter:description', $tw_desc );
		}

		// twitter:image — custom TW image or fall back to OG image.
		$tw_image = '';
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$tw_image = Meta::get_post( $post->ID, 'tw_image' );
			}
		}
		if ( '' === $tw_image ) {
			$tw_image = $image_url;
		}
		if ( '' !== $tw_image ) {
			$tags[] = $this->tw_tag( 'twitter:image', $tw_image );
		}

		return array_filter( $tags );
	}

	/**
	 * Build an Open Graph <meta property> tag string.
	 *
	 * @param string $property Property name.
	 * @param string $content  Content value (unescaped).
	 * @return string
	 */
	private function og_tag( string $property, string $content ): string {
		if ( '' === $content ) {
			return '';
		}
		return sprintf(
			'<meta property="%s" content="%s" />',
			esc_attr( $property ),
			esc_attr( $content )
		);
	}

	/**
	 * Build a Twitter <meta name> tag string.
	 *
	 * @param string $name    Meta name.
	 * @param string $content Content value (unescaped).
	 * @return string
	 */
	private function tw_tag( string $name, string $content ): string {
		if ( '' === $content ) {
			return '';
		}
		return sprintf(
			'<meta name="%s" content="%s" />',
			esc_attr( $name ),
			esc_attr( $content )
		);
	}
}
