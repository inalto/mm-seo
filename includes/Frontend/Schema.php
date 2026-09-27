<?php
namespace MMSEO\Frontend;

use MMSEO\Meta;
use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Generates the JSON-LD @graph schema block for the current page.
 *
 * Outputs a single <script type="application/ld+json"> tag containing
 * all applicable schema pieces: Organization/Person, WebSite, WebPage,
 * Article/etc., BreadcrumbList, and primary ImageObject.
 *
 * Called by Head::output() when the 'schema' module is enabled.
 */
class Schema {

	/**
	 * Build the graph and output the JSON-LD script tag.
	 *
	 * @return void
	 */
	public function output(): void {
		$graph = $this->build_graph();

		if ( empty( $graph ) ) {
			return;
		}

		/**
		 * Filter the complete schema @graph array before output.
		 *
		 * @param array $graph Array of schema pieces.
		 */
		$graph = (array) apply_filters( 'mmseo_schema_graph', $graph );

		if ( empty( $graph ) ) {
			return;
		}

		$schema = [
			'@context' => 'https://schema.org',
			'@graph'   => array_values( array_filter( $graph ) ),
		];

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			return;
		}

		echo "\t<script type=\"application/ld+json\">\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json;
		echo "\n\t</script>\n";
	}

	// -------------------------------------------------------------------------
	// Graph builder
	// -------------------------------------------------------------------------

	/**
	 * Build all schema pieces for the current page.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_graph(): array {
		$home_url = trailingslashit( home_url() );
		$context  = $this->build_context();
		$pieces   = [];

		// Organization / Person.
		$org_piece = $this->build_organization( $home_url );
		if ( ! empty( $org_piece ) ) {
			$pieces[] = $this->apply_piece_filter( 'organization', $org_piece, $context );
		}

		// WebSite.
		$website_piece = $this->build_website( $home_url );
		if ( ! empty( $website_piece ) ) {
			$pieces[] = $this->apply_piece_filter( 'website', $website_piece, $context );
		}

		// Primary ImageObject (for featured image, referenced by WebPage).
		$primary_image_id = $this->get_primary_image_id();
		$image_piece      = null;
		if ( $primary_image_id ) {
			$image_piece = $this->build_image_object( $primary_image_id, $home_url );
			if ( ! empty( $image_piece ) ) {
				$pieces[] = $this->apply_piece_filter( 'imageobject', $image_piece, $context );
			}
		}

		// WebPage.
		$webpage_piece = $this->build_webpage( $home_url, $context, $image_piece );
		if ( ! empty( $webpage_piece ) ) {
			$pieces[] = $this->apply_piece_filter( 'webpage', $webpage_piece, $context );
		}

		// Article / content piece (singular posts only).
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$article_piece = $this->build_article( $post, $home_url, $context );
				if ( ! empty( $article_piece ) ) {
					$pieces[] = $this->apply_piece_filter(
						strtolower( $article_piece['@type'] ?? 'article' ),
						$article_piece,
						$context
					);
				}
			}
		}

		// BreadcrumbList (when breadcrumbs module enabled and class exists).
		if (
			Options::module_enabled( 'breadcrumbs' )
			&& class_exists( Breadcrumbs::class )
		) {
			$breadcrumb_piece = $this->build_breadcrumb_list( $home_url );
			if ( ! empty( $breadcrumb_piece ) ) {
				$pieces[] = $this->apply_piece_filter( 'breadcrumblist', $breadcrumb_piece, $context );
			}
		}

		return $pieces;
	}

	// -------------------------------------------------------------------------
	// Schema piece builders
	// -------------------------------------------------------------------------

	/**
	 * Build the Organization or Person schema piece.
	 *
	 * @param string $home_url Site home URL.
	 * @return array<string, mixed>
	 */
	private function build_organization( string $home_url ): array {
		$site_type = Options::get( 'site_type', 'org' );
		$type      = ( 'person' === $site_type ) ? 'Person' : 'Organization';

		$org_name = Options::get( 'org_name', '' );
		if ( '' === $org_name ) {
			$org_name = get_bloginfo( 'name' );
		}

		$piece = [
			'@type' => $type,
			'@id'   => $home_url . '#organization',
			'name'  => $org_name,
			'url'   => $home_url,
		];

		// Logo.
		$logo_id = (int) Options::get( 'org_logo', 0 );
		if ( $logo_id > 0 ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( $logo_url ) {
				$logo_meta = wp_get_attachment_metadata( $logo_id );
				$piece['logo'] = [
					'@type'  => 'ImageObject',
					'url'    => $logo_url,
					'width'  => $logo_meta['width'] ?? null,
					'height' => $logo_meta['height'] ?? null,
				];
				$piece['logo'] = array_filter( $piece['logo'] );
			}
		}

		// sameAs social profiles.
		$social_profiles = Options::get( 'social_profiles', [] );
		$same_as         = array_values( array_filter( (array) $social_profiles ) );
		if ( ! empty( $same_as ) ) {
			$piece['sameAs'] = $same_as;
		}

		return $piece;
	}

	/**
	 * Build the WebSite schema piece.
	 *
	 * @param string $home_url Site home URL.
	 * @return array<string, mixed>
	 */
	private function build_website( string $home_url ): array {
		$piece = [
			'@type'       => 'WebSite',
			'@id'         => $home_url . '#website',
			'url'         => $home_url,
			'name'        => get_bloginfo( 'name' ),
			'publisher'   => [ '@id' => $home_url . '#organization' ],
			'inLanguage'  => get_locale(),
			'potentialAction' => [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				],
				'query-input' => 'required name=search_term_string',
			],
		];

		return $piece;
	}

	/**
	 * Build the WebPage schema piece.
	 *
	 * @param string                   $home_url    Site home URL.
	 * @param array<string, mixed>     $context     Page context data.
	 * @param array<string, mixed>|null $image_piece Primary image piece (already built).
	 * @return array<string, mixed>
	 */
	private function build_webpage( string $home_url, array $context, ?array $image_piece ): array {
		$canonical = $context['canonical'] ?? '';
		$url       = '' !== $canonical ? $canonical : trailingslashit( home_url() );

		// Determine WebPage @type.
		$wp_type = 'WebPage';
		if ( is_search() ) {
			$wp_type = 'SearchResultsPage';
		} elseif ( is_archive() || is_home() ) {
			$wp_type = 'CollectionPage';
		}

		$piece = [
			'@type'      => $wp_type,
			'@id'        => $url . '#webpage',
			'url'        => $url,
			'name'       => wp_get_document_title(),
			'isPartOf'   => [ '@id' => $home_url . '#website' ],
			'inLanguage' => get_locale(),
		];

		// Primary image reference.
		if ( ! empty( $image_piece['@id'] ) ) {
			$piece['primaryImageOfPage'] = [ '@id' => $image_piece['@id'] ];
		}

		return $piece;
	}

	/**
	 * Build the Article (or sub-type) schema piece for singular posts.
	 *
	 * @param \WP_Post             $post     Current post.
	 * @param string               $home_url Site home URL.
	 * @param array<string, mixed> $context  Page context data.
	 * @return array<string, mixed>|null Null when schema type is 'None'.
	 */
	private function build_article( \WP_Post $post, string $home_url, array $context ): ?array {
		// Determine schema type.
		$schema_type = Meta::get_post( $post->ID, 'schema_type' );
		if ( '' === $schema_type ) {
			$pt_settings = Options::title_for( 'post_types', $post->post_type );
			$schema_type = $pt_settings['schema_type'] ?? 'Article';
		}

		// Bail when explicitly set to None.
		if ( 'None' === $schema_type || '' === $schema_type ) {
			return null;
		}

		$canonical = $context['canonical'] ?? get_permalink( $post );
		$url       = '' !== $canonical ? $canonical : get_permalink( $post );

		$published = $post->post_date_gmt && '0000-00-00 00:00:00' !== $post->post_date_gmt
			? gmdate( \DateTime::ATOM, strtotime( $post->post_date_gmt . ' GMT' ) )
			: gmdate( \DateTime::ATOM, strtotime( $post->post_date ) );

		$modified = $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt
			? gmdate( \DateTime::ATOM, strtotime( $post->post_modified_gmt . ' GMT' ) )
			: gmdate( \DateTime::ATOM, strtotime( $post->post_modified ) );

		$piece = [
			'@type'            => $schema_type,
			'@id'              => $url . '#article',
			'headline'         => get_the_title( $post ),
			'url'              => $url,
			'datePublished'    => $published,
			'dateModified'     => $modified,
			'mainEntityOfPage' => [ '@id' => $url . '#webpage' ],
			'publisher'        => [ '@id' => $home_url . '#organization' ],
		];

		// Author.
		$author_name = get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( '' !== $author_name ) {
			$piece['author'] = [
				'@type' => 'Person',
				'name'  => $author_name,
			];
		}

		// Featured image.
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$img_url = wp_get_attachment_image_url( (int) $thumb_id, 'full' );
			if ( $img_url ) {
				$piece['image'] = $img_url;
			}
		}

		return $piece;
	}

	/**
	 * Build the BreadcrumbList schema piece from the Breadcrumbs trail.
	 *
	 * @param string $home_url Site home URL.
	 * @return array<string, mixed>|null Null when trail is empty or only one item.
	 */
	private function build_breadcrumb_list( string $home_url ): ?array {
		// Call Breadcrumbs::get_trail() statically.
		if ( ! method_exists( Breadcrumbs::class, 'get_trail' ) ) {
			return null;
		}

		$trail = Breadcrumbs::get_trail();

		if ( empty( $trail ) ) {
			return null;
		}

		$items    = [];
		$position = 1;
		$total    = count( $trail );

		foreach ( $trail as $crumb ) {
			$item = [
				'@type'    => 'ListItem',
				'position' => $position,
				'name'     => $crumb['label'],
			];

			// Per Google spec: last item should NOT have an item URL.
			if ( $position < $total && ! empty( $crumb['url'] ) ) {
				$item['item'] = $crumb['url'];
			}

			$items[]   = $item;
			$position++;
		}

		if ( empty( $items ) ) {
			return null;
		}

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => trailingslashit( home_url() ) . '#breadcrumb',
			'itemListElement' => $items,
		];
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Apply the per-piece filter.
	 *
	 * Filter name: mmseo_schema_piece_{type_lowercase}
	 *
	 * @param string               $type    Piece type (lowercase, e.g. 'organization').
	 * @param array<string, mixed> $piece   Schema piece.
	 * @param array<string, mixed> $context Page context data.
	 * @return array<string, mixed>
	 */
	private function apply_piece_filter( string $type, array $piece, array $context ): array {
		/**
		 * Filter an individual schema piece before it is added to the graph.
		 *
		 * @param array<string, mixed> $piece   Schema piece array.
		 * @param array<string, mixed> $context Page context data.
		 */
		return (array) apply_filters( 'mmseo_schema_piece_' . $type, $piece, $context );
	}

	/**
	 * Build the page context array (canonical URL, post object, etc.).
	 *
	 * @return array<string, mixed>
	 */
	private function build_context(): array {
		$context = [];

		// Get canonical from MetaTags.
		if ( class_exists( MetaTags::class ) ) {
			$context['canonical'] = ( new MetaTags() )->canonical();
		} else {
			$context['canonical'] = '';
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$context['post'] = $post;
			}
		}

		return $context;
	}

	/**
	 * Get the featured image ID for the current singular post.
	 *
	 * @return int|null Attachment ID or null.
	 */
	private function get_primary_image_id(): ?int {
		if ( ! is_singular() ) {
			return null;
		}

		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$thumb_id = get_post_thumbnail_id( $post->ID );
		return $thumb_id ? (int) $thumb_id : null;
	}

	/**
	 * Build an ImageObject schema piece for an attachment.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $home_url      Site home URL.
	 * @return array<string, mixed>|null Null when image URL cannot be resolved.
	 */
	private function build_image_object( int $attachment_id, string $home_url ): ?array {
		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $url ) {
			return null;
		}

		$meta   = wp_get_attachment_metadata( $attachment_id );
		$alt    = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$width  = $meta['width'] ?? null;
		$height = $meta['height'] ?? null;

		$piece = [
			'@type' => 'ImageObject',
			'@id'   => $url . '#primaryimage',
			'url'   => $url,
		];

		if ( $width ) {
			$piece['width'] = (int) $width;
		}
		if ( $height ) {
			$piece['height'] = (int) $height;
		}
		if ( '' !== $alt ) {
			$piece['caption'] = $alt;
		}

		return $piece;
	}
}
