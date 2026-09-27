/**
 * MM SEO – Snippet Preview
 * ES2020, no build step, no JSX, no external deps.
 * Exposes: window.MMSEOSnippet = { render, counter }
 */

( function () {
    'use strict';

    /**
     * Convert a URL string to a breadcrumb-style display.
     * Slashes become › separators; trailing slash is stripped.
     *
     * @param {string} url
     * @returns {string}
     */
    function urlToBreadcrumb( url ) {
        try {
            const parsed = new URL( url );
            const parts = [ parsed.hostname ];
            const pathParts = parsed.pathname
                .split( '/' )
                .filter( ( p ) => p.length > 0 );
            parts.push( ...pathParts );
            return parts.join( ' › ' );
        } catch ( _e ) {
            // Fall back to simple slash replacement if URL parsing fails.
            return url.replace( /\//g, ' › ' ).replace( /^ › | › $/g, '' );
        }
    }

    /**
     * Render a Google-style SERP snippet into a container element.
     *
     * @param {HTMLElement} container  - Element to render into.
     * @param {{ title: string, desc: string, url: string }} opts
     */
    function render( container, opts ) {
        if ( ! container ) {
            return;
        }

        const title = ( opts && opts.title ) ? String( opts.title ) : '';
        const desc  = ( opts && opts.desc )  ? String( opts.desc )  : '';
        const url   = ( opts && opts.url )   ? String( opts.url )   : '';

        // Clear existing content.
        container.innerHTML = '';

        // Outer wrapper.
        const wrapper = document.createElement( 'div' );
        wrapper.style.fontFamily = 'Arial, sans-serif';
        wrapper.style.maxWidth   = '600px';

        // --- URL / breadcrumb line ---
        const urlLine = document.createElement( 'div' );
        urlLine.style.display    = 'flex';
        urlLine.style.alignItems = 'center';
        urlLine.style.gap        = '6px';
        urlLine.style.color      = '#006621';
        urlLine.style.fontSize   = '12px';
        urlLine.style.marginBottom = '2px';

        // Favicon dot.
        const favicon = document.createElement( 'span' );
        favicon.style.display     = 'inline-block';
        favicon.style.width       = '12px';
        favicon.style.height      = '12px';
        favicon.style.borderRadius = '50%';
        favicon.style.background  = '#4CAF50';
        favicon.style.flexShrink  = '0';
        favicon.setAttribute( 'aria-hidden', 'true' );

        const urlText = document.createElement( 'span' );
        urlText.textContent = urlToBreadcrumb( url );

        urlLine.appendChild( favicon );
        urlLine.appendChild( urlText );

        // --- Title line ---
        const titleEl = document.createElement( 'div' );
        titleEl.style.color        = '#1a0dab';
        titleEl.style.fontSize     = '20px';
        titleEl.style.maxWidth     = '600px';
        titleEl.style.overflow     = 'hidden';
        titleEl.style.textOverflow = 'ellipsis';
        titleEl.style.whiteSpace   = 'nowrap';
        titleEl.style.cursor       = 'pointer';
        titleEl.style.lineHeight   = '1.3';
        titleEl.style.marginBottom = '4px';
        titleEl.textContent        = title;

        // --- Description ---
        const descEl = document.createElement( 'div' );
        descEl.style.color            = '#545454';
        descEl.style.fontSize         = '14px';
        descEl.style.lineHeight       = '1.4';
        descEl.style.display          = '-webkit-box';
        descEl.style.webkitLineClamp  = '2';
        descEl.style.webkitBoxOrient  = 'vertical';
        descEl.style.overflow         = 'hidden';
        // Non-prefixed property via setAttribute so browsers that support it pick it up.
        descEl.style.setProperty( '-webkit-line-clamp', '2' );
        descEl.style.setProperty( '-webkit-box-orient', 'vertical' );
        descEl.textContent = desc;

        wrapper.appendChild( urlLine );
        wrapper.appendChild( titleEl );
        wrapper.appendChild( descEl );

        container.appendChild( wrapper );
    }

    /**
     * Wire up a character counter to an input/textarea.
     * Sets initial state immediately and updates on every 'input' event.
     *
     * @param {HTMLElement} inputEl    - The text input or textarea.
     * @param {HTMLElement} counterEl  - Element that displays "count/max".
     * @param {{ min: number, max: number }} opts
     */
    function counter( inputEl, counterEl, opts ) {
        if ( ! inputEl || ! counterEl ) {
            return;
        }

        const min = ( opts && typeof opts.min === 'number' ) ? opts.min : 0;
        const max = ( opts && typeof opts.max === 'number' ) ? opts.max : 9999;

        function update() {
            const count = inputEl.value.length;
            counterEl.textContent = count + '/' + max;

            if ( count < min ) {
                counterEl.style.color = '#dba617'; // amber
            } else if ( count <= max ) {
                counterEl.style.color = '#00a32a'; // green
            } else {
                counterEl.style.color = '#d63638'; // red
            }
        }

        inputEl.addEventListener( 'input', update );

        // Set initial state.
        update();
    }

    // Expose public API.
    window.MMSEOSnippet = { render, counter };

}() );
