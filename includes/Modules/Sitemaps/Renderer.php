<?php
namespace MMSEO\Modules\Sitemaps;

defined( 'ABSPATH' ) || exit;

/**
 * Renders sitemap XML strings.
 *
 * Static methods only — no state, no WP globals required.
 */
class Renderer {

	/**
	 * XSL stylesheet URL path.
	 *
	 * @var string
	 */
	private const XSL_PATH = '/mmseo-sitemap.xsl';

	/**
	 * Render a <sitemapindex> XML document from an array of index entries.
	 *
	 * @param array<int, array{loc: string, lastmod: string|null}> $entries
	 * @return string Well-formed XML string.
	 */
	public static function index( array $entries ): string {
		$xsl_url = home_url( self::XSL_PATH );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
		$xml .= '<!-- MM SEO -->' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $entries as $entry ) {
			$xml .= "\t<sitemap>\n";
			$xml .= "\t\t<loc>" . self::escape( $entry['loc'] ) . "</loc>\n";
			if ( ! empty( $entry['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . self::escape( $entry['lastmod'] ) . "</lastmod>\n";
			}
			$xml .= "\t</sitemap>\n";
		}

		$xml .= '</sitemapindex>';

		return $xml;
	}

	/**
	 * Render a <urlset> XML document from an array of URL entries.
	 *
	 * @param array<int, array{loc: string, lastmod: string|null, images: array}> $urls
	 * @return string Well-formed XML string.
	 */
	public static function urlset( array $urls ): string {
		$xsl_url = home_url( self::XSL_PATH );

		// Determine if any URL has images so we can add the image namespace.
		$has_images = false;
		foreach ( $urls as $url ) {
			if ( ! empty( $url['images'] ) ) {
				$has_images = true;
				break;
			}
		}

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( $xsl_url ) . '"?>' . "\n";
		$xml .= '<!-- MM SEO -->' . "\n";

		if ( $has_images ) {
			$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
				. ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'
				. "\n";
		} else {
			$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		}

		foreach ( $urls as $url ) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . self::escape( $url['loc'] ) . "</loc>\n";

			if ( ! empty( $url['lastmod'] ) ) {
				$xml .= "\t\t<lastmod>" . self::escape( $url['lastmod'] ) . "</lastmod>\n";
			}

			if ( ! empty( $url['images'] ) ) {
				foreach ( $url['images'] as $image ) {
					if ( empty( $image['src'] ) ) {
						continue;
					}
					$xml .= "\t\t<image:image>\n";
					$xml .= "\t\t\t<image:loc>" . self::escape( $image['src'] ) . "</image:loc>\n";
					if ( ! empty( $image['title'] ) ) {
						$xml .= "\t\t\t<image:title>" . self::escape( $image['title'] ) . "</image:title>\n";
					}
					$xml .= "\t\t</image:image>\n";
				}
			}

			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	/**
	 * Escape a string for XML output (ENT_XML1).
	 *
	 * Uses htmlspecialchars with ENT_XML1 to produce valid XML character escaping.
	 *
	 * @param string $value Raw string.
	 * @return string Escaped XML-safe string.
	 */
	private static function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}
}
