/**
 * MM SEO — Client-side SEO analysis engine (MM Score).
 *
 * Mirrors the PHP Analyzer + 14 Check classes 1:1 (same IDs, thresholds, status logic).
 * Implements the same three-category MM Score breakdown as the PHP backend.
 * Dependency-free IIFE; exposes window.MMSEOAnalyzer.
 *
 * Input  (passed to analyze()):
 *   { title, metaDesc, focusKw, slug, contentHtml, homeHost }
 *
 * Output:
 *   {
 *     score:     0-100,
 *     rating:    'excellent'|'good'|'fair'|'poor',
 *     breakdown: {
 *       basic:       { score: int, label: string },
 *       content:     { score: int, label: string },
 *       readability: { score: int, label: string },
 *     },
 *     checks: [{ id, category, status, score, text }]
 *   }
 *
 * @package mm-seo
 */
/* global wp */
( function () {
	'use strict';

	// i18n shim — use wp.i18n when available, otherwise identity function.
	var __ = ( window.wp && window.wp.i18n )
		? window.wp.i18n.__
		: function ( s ) { return s; };

	// ---------------------------------------------------------------------------
	// Category definitions (mirrors Analyzer::categories())
	// ---------------------------------------------------------------------------

	/**
	 * Category membership: check id → category id.
	 * Mirrors PHP check category() methods.
	 */
	var CHECK_CATEGORY = {
		kw_in_title         : 'basic',
		kw_in_metadesc      : 'basic',
		kw_in_slug          : 'basic',
		title_length        : 'basic',
		metadesc_length     : 'basic',
		kw_in_first_paragraph: 'content',
		kw_in_headings      : 'content',
		kw_density          : 'content',
		content_length      : 'content',
		image_alts          : 'content',
		internal_links      : 'content',
		external_links      : 'content',
		sentence_length     : 'readability',
		paragraph_length    : 'readability',
	};

	/**
	 * Category weights (mirrors PHP default for mmseo_score_category_weights filter).
	 */
	var CAT_WEIGHTS = {
		basic       : 50,
		content     : 35,
		readability : 15,
	};

	/**
	 * Category labels (mirrors PHP Analyzer::categories()).
	 */
	function catLabels() {
		return {
			basic       : __( 'Basic SEO',   'mm-seo' ),
			content     : __( 'Content',     'mm-seo' ),
			readability : __( 'Readability', 'mm-seo' ),
		};
	}

	// ---------------------------------------------------------------------------
	// Accent folding (mirrors PHP remove_accents + mb_strtolower)
	// ---------------------------------------------------------------------------

	/**
	 * Fold combining diacritical marks from a string and lower-case it.
	 *
	 * @param {string} str
	 * @returns {string}
	 */
	function foldAccents( str ) {
		return str
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' );
	}

	// ---------------------------------------------------------------------------
	// Italian stem heuristic (mirrors PHP ContentExtractor::keyword_match)
	// ---------------------------------------------------------------------------

	/**
	 * Strip final vowel from an accent-folded word >4 chars.
	 *
	 * @param {string} word Already lower-cased + accent-folded.
	 * @returns {string}
	 */
	function stem( word ) {
		if ( word.length > 4 ) {
			return word.replace( /[aeiou]$/u, '' );
		}
		return word;
	}

	/**
	 * Case- and accent-insensitive keyword match with Italian stem heuristic.
	 *
	 * @param {string} haystack
	 * @param {string} keyword
	 * @returns {boolean}
	 */
	function keywordMatch( haystack, keyword ) {
		if ( ! keyword ) { return false; }

		var h = foldAccents( haystack );
		var k = foldAccents( keyword );

		if ( h.indexOf( k ) !== -1 ) { return true; }

		// Italian stem: for each kw word >4 chars also try with final vowel stripped.
		var kWords  = k.split( /\s+/ ).filter( Boolean );
		var stemmed = kWords.map( function ( w ) { return stem( w ); } ).join( ' ' );
		if ( stemmed !== k && h.indexOf( stemmed ) !== -1 ) { return true; }

		return false;
	}

	/**
	 * Count keyword occurrences in text (accent- and case-insensitive, overlapping).
	 *
	 * @param {string} text
	 * @param {string} keyword
	 * @returns {number}
	 */
	function keywordOccurrences( text, keyword ) {
		if ( ! keyword || ! text ) { return 0; }

		var h = foldAccents( text );
		var k = foldAccents( keyword );
		var count = 0;
		var idx   = 0;

		while ( ( idx = h.indexOf( k, idx ) ) !== -1 ) {
			count++;
			idx += k.length;
		}
		return count;
	}

	// ---------------------------------------------------------------------------
	// Unicode-safe word count
	// ---------------------------------------------------------------------------

	/**
	 * Split text on whitespace and return word count.
	 *
	 * @param {string} text
	 * @returns {number}
	 */
	function wordCount( text ) {
		var words = text.trim().split( /\s+/u ).filter( Boolean );
		return words.length;
	}

	// ---------------------------------------------------------------------------
	// Content parsing — mirrors ContentExtractor::extract()
	// ---------------------------------------------------------------------------

	/**
	 * Strip Gutenberg block comment delimiters, keep inner HTML.
	 *
	 * @param {string} raw
	 * @returns {string}
	 */
	function stripBlockComments( raw ) {
		return raw.replace( /<!--\s*\/?wp:[^-]*?-->/gs, '' );
	}

	/**
	 * Harvest Divi shortcode attributes then strip wrappers.
	 * Returns { html, extraHeadings, extraImages }.
	 *
	 * @param {string} html
	 * @returns {{ html: string, extraHeadings: string[], extraImages: Array<{src:string,alt:string}> }}
	 */
	function stripDiviShortcodes( html ) {
		var extraHeadings = [];
		var extraImages   = [];

		// Harvest title= from et_pb_blurb / et_pb_slide.
		var blurbRe = /\[et_pb_(?:blurb|slide)[^\]]*\btitle="([^"]+)"/g;
		var m;
		while ( ( m = blurbRe.exec( html ) ) !== null ) {
			var title = m[1].replace( /&amp;/g, '&' ).replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' ).replace( /&quot;/g, '"' );
			extraHeadings.push( title );
			html += '<h3>' + title + '</h3>';
		}

		// Harvest src= and alt= from each et_pb_image tag (attribute order varies).
		var imgTagRe = /\[et_pb_image\b([^\]]*)\]/g;
		var seenSrcs = {};
		while ( ( m = imgTagRe.exec( html ) ) !== null ) {
			var attrs = m[1];
			var srcM  = attrs.match( /\bsrc="([^"]+)"/ );
			if ( ! srcM ) {
				continue;
			}
			var altM = attrs.match( /\balt="([^"]*)"/ );
			if ( ! seenSrcs[ srcM[1] ] ) {
				seenSrcs[ srcM[1] ] = true;
				extraImages.push( { src: srcM[1], alt: altM ? altM[1] : '' } );
			}
		}

		// Harvest button_text= as plain text.
		var btnRe = /\[et_pb_[^\]]*\bbutton_text="([^"]+)"/g;
		while ( ( m = btnRe.exec( html ) ) !== null ) {
			html += '<span>' + m[1] + '</span>';
		}

		// Strip [/et_pb_*] closing tags → newline.
		html = html.replace( /\[\/et_pb_[^\]]+\]/g, '\n' );
		// Strip [et_pb_*] opening tags → newline.
		html = html.replace( /\[et_pb_[^\]]+\]/g, '\n' );

		// Strip remaining shortcode wrappers.
		html = html.replace( /\[\/[a-zA-Z0-9_-]+\]/g, ' ' );
		html = html.replace( /\[[a-zA-Z0-9_-]+(?:[^\]]*?)?\]/g, ' ' );

		return { html: html, extraHeadings: extraHeadings, extraImages: extraImages };
	}

	/**
	 * Strip all HTML tags, returning plain text.
	 *
	 * @param {string} html
	 * @returns {string}
	 */
	function stripTags( html ) {
		return html.replace( /<[^>]+>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
	}

	/**
	 * Parse raw post content into structured data.
	 * Mirrors PHP ContentExtractor::extract().
	 *
	 * @param {string} rawContent   Raw post_content.
	 * @param {string} homeHost     Hostname of the site (e.g. 'example.com').
	 * @returns {{
	 *   text: string,
	 *   html: string,
	 *   headings: string[],
	 *   images: Array<{src:string,alt:string}>,
	 *   links: Array<{href:string,internal:boolean}>,
	 *   firstParagraph: string,
	 *   wordCount: number
	 * }}
	 */
	function extractContent( rawContent, homeHost ) {
		// Step A: strip Gutenberg block comments.
		var html = stripBlockComments( rawContent );

		// Step B+C: Divi attribute harvesting + wrapper stripping.
		var divi = stripDiviShortcodes( html );
		html              = divi.html;
		var extraHeadings = divi.extraHeadings;
		var extraImages   = divi.extraImages;

		// Step E: parse headings (h2–h6).
		var headings = extraHeadings.slice();
		var hRe = /<h([2-6])[^>]*>([\s\S]*?)<\/h\1>/gi;
		var m;
		while ( ( m = hRe.exec( html ) ) !== null ) {
			var hText = stripTags( m[2] ).trim();
			if ( hText ) { headings.push( hText ); }
		}

		// Parse images.
		var images  = extraImages.slice();
		var seenSrcs = {};
		extraImages.forEach( function ( img ) { seenSrcs[ img.src ] = true; } );
		var imgRe = /<img[^>]+>/gi;
		while ( ( m = imgRe.exec( html ) ) !== null ) {
			var tag  = m[0];
			var src  = '';
			var alt  = '';
			var srcM = tag.match( /\bsrc=['"]([^'"]+)['"]/i );
			var altM = tag.match( /\balt=['"]([^'"]*)['"]/i );
			if ( srcM ) { src = srcM[1]; }
			if ( altM ) { alt = altM[1]; }
			if ( src && ! seenSrcs[ src ] ) {
				seenSrcs[ src ] = true;
				images.push( { src: src, alt: alt } );
			}
		}

		// Parse links.
		var links = [];
		var linkRe = /<a\s[^>]*\bhref=['"]([^'"]+)['"][^>]*>/gi;
		while ( ( m = linkRe.exec( html ) ) !== null ) {
			var href     = m[1];
			var internal = false;
			try {
				if ( /^https?:\/\//i.test( href ) ) {
					var parsed = new URL( href );
					internal = ( parsed.hostname === homeHost );
				} else if ( href.charAt( 0 ) === '/' ) {
					internal = true;
				}
			} catch ( e ) {
				// Malformed URL — leave internal = false.
			}
			links.push( { href: href, internal: internal } );
		}

		// First paragraph.
		var firstParagraph = '';
		var pMatch = html.match( /<p[^>]*>([\s\S]*?)<\/p>/i );
		if ( pMatch ) {
			firstParagraph = stripTags( pMatch[1] ).trim();
		}

		// Plain text.
		var text = stripTags( html )
			.replace( /&amp;/g, '&' )
			.replace( /&lt;/g, '<' )
			.replace( /&gt;/g, '>' )
			.replace( /&quot;/g, '"' )
			.replace( /&#039;/g, "'" )
			.replace( /&nbsp;/g, ' ' )
			.replace( /\s+/g, ' ' )
			.trim();

		// Fallback first paragraph.
		if ( ! firstParagraph && text ) {
			var lines = text.split( /\n+/ );
			for ( var i = 0; i < lines.length; i++ ) {
				var line = lines[ i ].trim();
				if ( line ) { firstParagraph = line; break; }
			}
		}

		return {
			text           : text,
			html           : html,
			headings       : headings,
			images         : images,
			links          : links,
			firstParagraph : firstParagraph,
			wordCount      : wordCount( text ),
		};
	}

	// ---------------------------------------------------------------------------
	// The 14 checks — each returns { status, score, text }
	// ---------------------------------------------------------------------------

	var GOOD = 'good';
	var OK   = 'ok';
	var BAD  = 'bad';

	/** Shared "no focus keyword" message for all kw_* checks. */
	function noKwResult() {
		return { status: OK, score: 5, text: __( 'Set a focus keyword to get keyword checks.', 'mm-seo' ) };
	}

	var CHECKS = [

		// kw_in_title — weight 3 — basic
		{
			id       : 'kw_in_title',
			weight   : 3,
			category : 'basic',
			run      : function ( ctx ) {
				if ( ! ctx.focusKw ) { return noKwResult(); }
				if ( ! keywordMatch( ctx.title, ctx.focusKw ) ) {
					return { status: BAD, score: 2, text: __( 'The focus keyword does not appear in the SEO title.', 'mm-seo' ) };
				}
				var half = ctx.title.slice( 0, Math.ceil( ctx.title.length / 2 ) );
				if ( keywordMatch( half, ctx.focusKw ) ) {
					return { status: GOOD, score: 9, text: __( 'The focus keyword appears at the beginning of the SEO title. Well done!', 'mm-seo' ) };
				}
				return { status: GOOD, score: 9, text: __( 'The focus keyword appears in the SEO title.', 'mm-seo' ) };
			},
		},

		// kw_in_slug — weight 2 — basic
		{
			id       : 'kw_in_slug',
			weight   : 2,
			category : 'basic',
			run      : function ( ctx ) {
				if ( ! ctx.focusKw ) { return noKwResult(); }
				var slugWords = ctx.slug.replace( /[-_]/g, ' ' );
				var slugNorm  = foldAccents( slugWords );
				var kwNorm    = foldAccents( ctx.focusKw );
				// Also try ASCII slug form of keyword.
				var kwSlug    = foldAccents( ctx.focusKw ).replace( /[^a-z0-9\s]/g, '' ).replace( /\s+/g, ' ' ).trim().replace( /\s/g, '-' ).replace( /-/g, ' ' );
				if ( slugNorm.indexOf( kwNorm ) !== -1 || ( kwSlug && slugNorm.indexOf( kwSlug ) !== -1 ) ) {
					return { status: GOOD, score: 9, text: __( 'The focus keyword appears in the post slug.', 'mm-seo' ) };
				}
				return { status: BAD, score: 3, text: __( 'The focus keyword does not appear in the post slug.', 'mm-seo' ) };
			},
		},

		// kw_in_metadesc — weight 2 — basic
		{
			id       : 'kw_in_metadesc',
			weight   : 2,
			category : 'basic',
			run      : function ( ctx ) {
				if ( ! ctx.focusKw ) { return noKwResult(); }
				if ( ! ctx.metaDesc ) {
					return { status: BAD, score: 2, text: __( 'No meta description set. Add one and include the focus keyword.', 'mm-seo' ) };
				}
				if ( keywordMatch( ctx.metaDesc, ctx.focusKw ) ) {
					return { status: GOOD, score: 9, text: __( 'The focus keyword appears in the meta description.', 'mm-seo' ) };
				}
				return { status: BAD, score: 2, text: __( 'The focus keyword does not appear in the meta description.', 'mm-seo' ) };
			},
		},

		// title_length — weight 3 — basic
		{
			id       : 'title_length',
			weight   : 3,
			category : 'basic',
			run      : function ( ctx ) {
				var len = ctx.title.length; // JS string length (char units, sufficient for title)
				if ( len === 0 ) {
					return { status: BAD, score: 1, text: __( 'The SEO title is empty. Add a descriptive title.', 'mm-seo' ) };
				}
				if ( len >= 30 && len <= 60 ) {
					return { status: GOOD, score: 9, text: __( 'The SEO title is ', 'mm-seo' ) + len + ' ' + __( 'characters — good length.', 'mm-seo' ) };
				}
				if ( ( len >= 20 && len <= 29 ) || ( len >= 61 && len <= 70 ) ) {
					return { status: OK, score: 6, text: __( 'The SEO title is ', 'mm-seo' ) + len + ' ' + __( 'characters. For best results aim for 30–60 characters.', 'mm-seo' ) };
				}
				return { status: BAD, score: 3, text: __( 'The SEO title is ', 'mm-seo' ) + len + ' ' + __( 'characters. Keep it between 30 and 60 characters.', 'mm-seo' ) };
			},
		},

		// metadesc_length — weight 2 — basic
		{
			id       : 'metadesc_length',
			weight   : 2,
			category : 'basic',
			run      : function ( ctx ) {
				var len = ctx.metaDesc.length;
				if ( len === 0 ) {
					return { status: BAD, score: 2, text: __( 'No meta description set. Write one to improve click-through rates.', 'mm-seo' ) };
				}
				if ( len >= 70 && len <= 156 ) {
					return { status: GOOD, score: 9, text: __( 'The meta description is ', 'mm-seo' ) + len + ' ' + __( 'characters — good length.', 'mm-seo' ) };
				}
				if ( len > 156 ) {
					return { status: OK, score: 5, text: __( 'The meta description is ', 'mm-seo' ) + len + ' ' + __( 'characters and will be truncated by search engines. Keep it under 156 characters.', 'mm-seo' ) };
				}
				return { status: OK, score: 5, text: __( 'The meta description is only ', 'mm-seo' ) + len + ' ' + __( 'characters. Aim for 70–156 characters.', 'mm-seo' ) };
			},
		},

		// kw_in_first_paragraph — weight 2 — content
		{
			id       : 'kw_in_first_paragraph',
			weight   : 2,
			category : 'content',
			run      : function ( ctx ) {
				if ( ! ctx.focusKw ) { return noKwResult(); }
				if ( keywordMatch( ctx.content.firstParagraph, ctx.focusKw ) ) {
					return { status: GOOD, score: 9, text: __( 'The focus keyword appears in the first paragraph.', 'mm-seo' ) };
				}
				return { status: BAD, score: 3, text: __( 'The focus keyword does not appear in the first paragraph. Add it to the opening of your content.', 'mm-seo' ) };
			},
		},

		// kw_in_headings — weight 2 — content
		{
			id       : 'kw_in_headings',
			weight   : 2,
			category : 'content',
			run      : function ( ctx ) {
				if ( ! ctx.focusKw ) { return noKwResult(); }
				var headings = ctx.content.headings;
				for ( var i = 0; i < headings.length; i++ ) {
					if ( keywordMatch( headings[ i ], ctx.focusKw ) ) {
						return { status: GOOD, score: 9, text: __( 'The focus keyword appears in a subheading.', 'mm-seo' ) };
					}
				}
				return { status: OK, score: 5, text: __( 'The focus keyword does not appear in any subheading. Consider adding it to at least one H2 or H3.', 'mm-seo' ) };
			},
		},

		// kw_density — weight 2 — content
		{
			id       : 'kw_density',
			weight   : 2,
			category : 'content',
			run      : function ( ctx ) {
				if ( ! ctx.focusKw ) { return noKwResult(); }
				var wc = ctx.content.wordCount;
				if ( wc <= 0 ) {
					return { status: BAD, score: 2, text: __( 'No content found. Add content to evaluate keyword density.', 'mm-seo' ) };
				}
				var occ     = keywordOccurrences( ctx.content.text, ctx.focusKw );
				var density = ( occ / wc ) * 100;
				if ( occ === 0 ) {
					return { status: BAD, score: 2, text: __( 'The focus keyword does not appear in the content.', 'mm-seo' ) };
				}
				if ( density > 3.0 ) {
					return { status: BAD, score: 3, text: __( 'Keyword density is ', 'mm-seo' ) + density.toFixed( 1 ) + '% — ' + __( 'over-optimization. Aim for 0.5–3%.', 'mm-seo' ) };
				}
				if ( density >= 0.5 ) {
					return { status: GOOD, score: 9, text: __( 'Keyword density is ', 'mm-seo' ) + density.toFixed( 1 ) + '% — ' + __( 'good.', 'mm-seo' ) };
				}
				return { status: OK, score: 5, text: __( 'Keyword density is ', 'mm-seo' ) + density.toFixed( 1 ) + '% — ' + __( 'too low. Aim for at least 0.5%.', 'mm-seo' ) };
			},
		},

		// content_length — weight 3 — content
		{
			id       : 'content_length',
			weight   : 3,
			category : 'content',
			run      : function ( ctx ) {
				var wc = ctx.content.wordCount;
				if ( wc >= 900 ) {
					return { status: GOOD, score: 9, text: __( 'The content is ', 'mm-seo' ) + wc + ' ' + __( 'words — excellent length.', 'mm-seo' ) };
				}
				if ( wc >= 300 ) {
					return { status: GOOD, score: 8, text: __( 'The content is ', 'mm-seo' ) + wc + ' ' + __( 'words — good length. Consider expanding to 900+ words for better rankings.', 'mm-seo' ) };
				}
				if ( wc >= 150 ) {
					return { status: OK, score: 5, text: __( 'The content is ', 'mm-seo' ) + wc + ' ' + __( 'words. Aim for at least 300 words for better SEO.', 'mm-seo' ) };
				}
				return { status: BAD, score: 2, text: __( 'The content is only ', 'mm-seo' ) + wc + ' ' + __( 'words — too short. Write at least 300 words.', 'mm-seo' ) };
			},
		},

		// image_alts — weight 2 — content
		{
			id       : 'image_alts',
			weight   : 2,
			category : 'content',
			run      : function ( ctx ) {
				var images = ctx.content.images;
				if ( ! images || images.length === 0 ) {
					return { status: OK, score: 5, text: __( 'No images found. Add images to enrich your content.', 'mm-seo' ) };
				}
				var missingAlt = 0;
				var kwInAlt    = false;
				var total      = images.length;
				images.forEach( function ( img ) {
					if ( ! img.alt ) {
						missingAlt++;
					} else if ( ctx.focusKw && keywordMatch( img.alt, ctx.focusKw ) ) {
						kwInAlt = true;
					}
				} );
				if ( missingAlt > 0 ) {
					return {
						status : BAD,
						score  : 3,
						text   : missingAlt + ' ' + __( 'of', 'mm-seo' ) + ' ' + total + ' ' +
							( missingAlt === 1
								? __( 'image is missing an alt attribute.', 'mm-seo' )
								: __( 'images are missing alt attributes.', 'mm-seo' ) ),
					};
				}
				if ( ctx.focusKw && ! kwInAlt ) {
					return { status: OK, score: 6, text: __( 'All images have alt attributes, but the focus keyword does not appear in any of them. Consider adding it to one image alt.', 'mm-seo' ) };
				}
				return { status: GOOD, score: 9, text: __( 'All images have alt attributes.', 'mm-seo' ) };
			},
		},

		// internal_links — weight 2 — content
		{
			id       : 'internal_links',
			weight   : 2,
			category : 'content',
			run      : function ( ctx ) {
				var count = ctx.content.links.filter( function ( l ) { return l.internal; } ).length;
				if ( count >= 1 ) {
					return { status: GOOD, score: 9, text: count + ' ' + ( count === 1 ? __( 'internal link found — good.', 'mm-seo' ) : __( 'internal links found — good.', 'mm-seo' ) ) };
				}
				return { status: BAD, score: 3, text: __( 'No internal links found. Add links to other pages on your site.', 'mm-seo' ) };
			},
		},

		// external_links — weight 1 — content
		{
			id       : 'external_links',
			weight   : 1,
			category : 'content',
			run      : function ( ctx ) {
				var count = ctx.content.links.filter( function ( l ) { return l.internal === false; } ).length;
				if ( count >= 1 ) {
					return { status: GOOD, score: 9, text: count + ' ' + ( count === 1 ? __( 'external link found — good.', 'mm-seo' ) : __( 'external links found — good.', 'mm-seo' ) ) };
				}
				return { status: OK, score: 5, text: __( 'No external links found. Consider linking to authoritative sources.', 'mm-seo' ) };
			},
		},

		// sentence_length — weight 1 — readability
		{
			id       : 'sentence_length',
			weight   : 1,
			category : 'readability',
			run      : function ( ctx ) {
				var text = ctx.content.text;
				if ( ! text ) {
					return { status: OK, score: 5, text: __( 'No content found to evaluate sentence length.', 'mm-seo' ) };
				}
				var sentences = text.split( /(?<=[.!?])\s+/u ).filter( Boolean );
				var total     = sentences.length;
				if ( total < 3 ) {
					return { status: OK, score: 5, text: __( 'Not enough sentences to evaluate sentence length. Add more content.', 'mm-seo' ) };
				}
				var longCount = 0;
				sentences.forEach( function ( s ) {
					var wc = s.trim().split( /\s+/u ).filter( Boolean ).length;
					if ( wc > 25 ) { longCount++; }
				} );
				var pct = ( longCount / total ) * 100;
				var pctStr = pct.toFixed( 0 ) + '%';
				if ( pct <= 25 ) {
					return { status: GOOD, score: 9, text: pctStr + ' ' + __( 'of sentences exceed 25 words — good readability.', 'mm-seo' ) };
				}
				if ( pct <= 40 ) {
					return { status: OK, score: 5, text: pctStr + ' ' + __( 'of sentences exceed 25 words. Try to shorten some sentences for better readability.', 'mm-seo' ) };
				}
				return { status: BAD, score: 3, text: pctStr + ' ' + __( 'of sentences exceed 25 words — too many long sentences. Break them up for better readability.', 'mm-seo' ) };
			},
		},

		// paragraph_length — weight 1 — readability
		{
			id       : 'paragraph_length',
			weight   : 1,
			category : 'readability',
			run      : function ( ctx ) {
				var text = ctx.content.text;
				if ( ! text ) {
					return { status: GOOD, score: 9, text: __( 'No content found to evaluate paragraph length.', 'mm-seo' ) };
				}
				var paragraphs = text.split( /\n{2,}/u ).filter( Boolean );
				if ( paragraphs.length === 0 ) {
					return { status: GOOD, score: 9, text: __( 'Paragraph length looks fine.', 'mm-seo' ) };
				}
				var longCount = 0;
				paragraphs.forEach( function ( p ) {
					var wc = p.trim().split( /\s+/u ).filter( Boolean ).length;
					if ( wc > 200 ) { longCount++; }
				} );
				if ( longCount > 0 ) {
					return {
						status : BAD,
						score  : 4,
						text   : longCount + ' ' + ( longCount === 1
							? __( 'paragraph exceeds 200 words. Break it into smaller paragraphs for better readability.', 'mm-seo' )
							: __( 'paragraphs exceed 200 words. Break them into smaller paragraphs for better readability.', 'mm-seo' ) ),
					};
				}
				return { status: GOOD, score: 9, text: __( 'All paragraphs are within the recommended length.', 'mm-seo' ) };
			},
		},

	]; // end CHECKS array

	// ---------------------------------------------------------------------------
	// MM Score aggregation
	// ---------------------------------------------------------------------------

	/**
	 * Convert a 0–100 MM Score to a rating label string.
	 * Mirrors PHP Analyzer::rating().
	 *
	 * @param {number} score
	 * @returns {'excellent'|'good'|'fair'|'poor'}
	 */
	function rating( score ) {
		if ( score >= 81 ) { return 'excellent'; }
		if ( score >= 61 ) { return 'good'; }
		if ( score >= 41 ) { return 'fair'; }
		return 'poor';
	}

	/**
	 * Convert a 0–100 score to a traffic-light colour.
	 * Thresholds intentionally differ from rating() to preserve list-table dot behaviour.
	 *
	 * @param {number} score
	 * @returns {'green'|'amber'|'red'}
	 */
	function trafficLight( score ) {
		if ( score >= 71 ) { return 'green'; }
		if ( score >= 41 ) { return 'amber'; }
		return 'red';
	}

	// ---------------------------------------------------------------------------
	// Public API
	// ---------------------------------------------------------------------------

	/**
	 * Run all checks against the provided input and return the full MM Score result.
	 *
	 * @param {{
	 *   title: string,
	 *   metaDesc: string,
	 *   focusKw: string,
	 *   slug: string,
	 *   contentHtml: string,
	 *   homeHost: string
	 * }} input
	 * @returns {{
	 *   score: number,
	 *   rating: string,
	 *   breakdown: {basic:{score:number,label:string}, content:{score:number,label:string}, readability:{score:number,label:string}},
	 *   checks: Array<{id:string, category:string, status:string, score:number, text:string}>
	 * }}
	 */
	function analyze( input ) {
		input = input || {};

		var title       = String( input.title      || '' );
		var metaDesc    = String( input.metaDesc   || '' );
		var focusKw     = String( input.focusKw    || '' );
		var slug        = String( input.slug       || '' );
		var contentHtml = String( input.contentHtml || '' );
		var homeHost    = String( input.homeHost   || '' );

		var content = extractContent( contentHtml, homeHost );

		var ctx = {
			title    : title,
			metaDesc : metaDesc,
			focusKw  : focusKw,
			slug     : slug,
			content  : content,
		};

		// Per-category accumulators.
		var catSums   = { basic: 0, content: 0, readability: 0 };
		var catTotals = { basic: 0, content: 0, readability: 0 };
		var checkResults = [];

		CHECKS.forEach( function ( check ) {
			var result   = check.run( ctx );
			var weight   = check.weight || 1;
			var cat      = check.category || 'content';
			var score    = ( typeof result.score === 'number' ) ? result.score : 0;

			// Accumulate (unknown category falls back to content).
			var effCat = ( cat in catSums ) ? cat : 'content';
			catSums[ effCat ]   += score * weight;
			catTotals[ effCat ] += weight;

			checkResults.push( {
				id       : check.id,
				category : cat,
				status   : result.status || BAD,
				score    : score,
				text     : result.text   || '',
			} );
		} );

		// Per-category scores (0–100), null when no checks.
		var catScores = {};
		var cats = Object.keys( CAT_WEIGHTS );
		cats.forEach( function ( cat ) {
			if ( catTotals[ cat ] > 0 ) {
				var s = Math.round( catSums[ cat ] / catTotals[ cat ] / 9 * 100 );
				catScores[ cat ] = Math.max( 0, Math.min( 100, s ) );
			} else {
				catScores[ cat ] = null;
			}
		} );

		// Redistribute weights of empty categories.
		var effWeights = {};
		var orphanW    = 0;
		cats.forEach( function ( cat ) {
			if ( catScores[ cat ] === null ) {
				orphanW += CAT_WEIGHTS[ cat ];
			} else {
				effWeights[ cat ] = CAT_WEIGHTS[ cat ];
			}
		} );
		if ( orphanW > 0 ) {
			var activeSum = 0;
			Object.keys( effWeights ).forEach( function ( c ) { activeSum += effWeights[ c ]; } );
			if ( activeSum > 0 ) {
				Object.keys( effWeights ).forEach( function ( c ) {
					effWeights[ c ] = effWeights[ c ] + orphanW * ( effWeights[ c ] / activeSum );
				} );
			}
		}

		// Overall MM Score.
		var totalW  = 0;
		var overall = 0;
		Object.keys( effWeights ).forEach( function ( c ) { totalW += effWeights[ c ]; } );
		if ( totalW > 0 ) {
			Object.keys( effWeights ).forEach( function ( c ) {
				overall += ( catScores[ c ] || 0 ) * ( effWeights[ c ] / totalW );
			} );
		}
		var finalScore = Math.max( 0, Math.min( 100, Math.round( overall ) ) );

		// Build breakdown.
		var labels    = catLabels();
		var breakdown = {};
		cats.forEach( function ( cat ) {
			breakdown[ cat ] = {
				score : catScores[ cat ] !== null ? catScores[ cat ] : 0,
				label : labels[ cat ] || cat,
			};
		} );

		return {
			score     : finalScore,
			rating    : rating( finalScore ),
			breakdown : breakdown,
			checks    : checkResults,
		};
	}

	// Expose public API.
	window.MMSEOAnalyzer = {
		analyze      : analyze,
		rating       : rating,
		trafficLight : trafficLight,
	};

}() );
