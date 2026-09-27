/**
 * MM SEO – Classic Editor Metabox
 * ES2020, no jQuery, no build step.
 * Localized global: MMSEOMetabox = { postId, homeHost, siteName, sep, permalink, nonce }
 */

( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {

        // ------------------------------------------------------------------ //
        // Helpers
        // ------------------------------------------------------------------ //

        /** Safely query a single element; returns null if not found. */
        const qs = ( sel, ctx ) => ( ctx || document ).querySelector( sel );

        /** Safely query all elements; returns empty array if none found. */
        const qsa = ( sel, ctx ) => Array.from( ( ctx || document ).querySelectorAll( sel ) );

        // i18n shim.
        const __ = ( window.wp && window.wp.i18n )
            ? window.wp.i18n.__
            : function ( s ) { return s; };

        // Localized data (with safe fallback).
        const data = ( typeof MMSEOMetabox !== 'undefined' ) ? MMSEOMetabox : {};
        const permalink = data.permalink || window.location.href;

        // ------------------------------------------------------------------ //
        // 1. Tab switching
        // ------------------------------------------------------------------ //

        const tabLinks  = qsa( '.mmseo-metabox-tabs a' );
        const tabPanels = qsa( '.mmseo-tab-panel' );

        if ( tabLinks.length > 0 ) {
            tabLinks.forEach( function ( link ) {
                link.addEventListener( 'click', function ( e ) {
                    e.preventDefault();

                    const target = ( link.getAttribute( 'href' ) || '' ).replace( /^#/, '' );

                    // Update active tab.
                    tabLinks.forEach( ( l ) => l.classList.remove( 'active' ) );
                    link.classList.add( 'active' );

                    // Show matching panel.
                    tabPanels.forEach( function ( panel ) {
                        const panelId = panel.getAttribute( 'data-tab' ) || panel.id;
                        if ( panelId === target ) {
                            panel.classList.add( 'active' );
                        } else {
                            panel.classList.remove( 'active' );
                        }
                    } );
                } );
            } );

            // Activate first tab by default if none is active.
            if ( ! tabLinks.some( ( l ) => l.classList.contains( 'active' ) ) ) {
                tabLinks[ 0 ] && tabLinks[ 0 ].dispatchEvent( new MouseEvent( 'click' ) );
            }
        }

        // ------------------------------------------------------------------ //
        // 2. Character counters
        // ------------------------------------------------------------------ //

        const titleInput    = qs( '#mmseo-title-input' );
        const titleCounter  = qs( '#mmseo-title-counter' );
        const descTextarea  = qs( '#mmseo-desc-textarea' );
        const descCounter   = qs( '#mmseo-desc-counter' );

        if ( window.MMSEOSnippet ) {
            if ( titleInput && titleCounter ) {
                MMSEOSnippet.counter( titleInput, titleCounter, { min: 30, max: 60 } );
            }
            if ( descTextarea && descCounter ) {
                MMSEOSnippet.counter( descTextarea, descCounter, { min: 70, max: 156 } );
            }
        }

        // ------------------------------------------------------------------ //
        // 3. Snippet preview
        // ------------------------------------------------------------------ //

        const snippetContainer = qs( '#mmseo-snippet' );

        function getSnippetData() {
            const title = titleInput ? titleInput.value : ( document.title || '' );
            const desc  = descTextarea ? descTextarea.value : '';
            const url   = permalink;
            return { title, desc, url };
        }

        function updateSnippet() {
            if ( snippetContainer && window.MMSEOSnippet ) {
                MMSEOSnippet.render( snippetContainer, getSnippetData() );
            }
        }

        // Initial render.
        updateSnippet();

        // Re-render on input changes.
        [ titleInput, descTextarea ].forEach( function ( el ) {
            if ( el ) {
                el.addEventListener( 'input', updateSnippet );
            }
        } );

        // ------------------------------------------------------------------ //
        // 4. Debounced analysis
        // ------------------------------------------------------------------ //

        const focusKwInput   = qs( '#mmseo-focuskw-input' );
        const analysisList   = qs( '#mmseo-analysis' );
        const scoreWrap      = qs( '.mmseo-score-wrap' );
        const breakdownWrap  = qs( '.mmseo-breakdown-wrap' );

        let analysisTimer = null;

        /** Map check status → dot colour class. */
        const dotColorClass = ( status ) => {
            if ( status === 'good' ) { return 'mmseo-dot--green'; }
            if ( status === 'ok' )   { return 'mmseo-dot--amber'; }
            return 'mmseo-dot--red';
        };

        /** Map 0-100 score → traffic-light colour string. */
        const scoreColor = ( score ) => {
            if ( score >= 71 ) { return 'green'; }
            if ( score >= 40 ) { return 'amber'; }
            return 'red';
        };

        /** Map colour string → CSS hex (matches admin palette). */
        const colorHex = ( color ) => {
            if ( color === 'green' )  { return '#00a32a'; }
            if ( color === 'amber' )  { return '#dba617'; }
            return '#d63638';
        };

        /** Map rating key → translated label. */
        const ratingLabel = ( key ) => {
            const map = {
                excellent : __( 'Excellent', 'mm-seo' ),
                good      : __( 'Good',      'mm-seo' ),
                fair      : __( 'Fair',      'mm-seo' ),
                poor      : __( 'Poor',      'mm-seo' ),
            };
            return map[ key ] || key;
        };

        /**
         * Render the MM Score badge (big number + rating) + breakdown chips.
         *
         * @param {number} score    Overall 0-100.
         * @param {string} ratingKey Rating key.
         * @param {Object} breakdown {basic, content, readability} each {score, label}.
         */
        function renderScoreBadge( score, ratingKey, breakdown ) {
            if ( ! scoreWrap ) { return; }

            const color  = colorHex( scoreColor( score ) );
            const rlabel = ratingLabel( ratingKey );

            scoreWrap.innerHTML = '';

            // Badge element.
            const badge = document.createElement( 'div' );
            badge.className = 'mmseo-score-badge';

            const numEl = document.createElement( 'span' );
            numEl.className = 'mmseo-score-number';
            numEl.style.color = color;
            numEl.textContent = String( score );

            const sepEl = document.createElement( 'span' );
            sepEl.className = 'mmseo-score-sep';
            sepEl.textContent = '/100';

            const labelEl = document.createElement( 'span' );
            labelEl.className = 'mmseo-score-rating mmseo-rating--' + ( ratingKey || 'poor' );
            labelEl.textContent = rlabel;

            badge.appendChild( numEl );
            badge.appendChild( sepEl );
            badge.appendChild( labelEl );
            scoreWrap.appendChild( badge );

            // Breakdown chips.
            if ( breakdownWrap && breakdown ) {
                breakdownWrap.innerHTML = '';
                const chips = document.createElement( 'div' );
                chips.className = 'mmseo-breakdown-chips';

                Object.keys( breakdown ).forEach( function ( catId ) {
                    const cat   = breakdown[ catId ];
                    const cs    = cat.score;
                    const cc    = colorHex( scoreColor( cs ) );
                    const chip  = document.createElement( 'span' );
                    chip.className = 'mmseo-breakdown-chip';
                    chip.style.setProperty( '--chip-color', cc );
                    chip.innerHTML =
                        '<span class="mmseo-breakdown-chip-label">' + escHtml( cat.label ) + '</span>' +
                        '<span class="mmseo-breakdown-chip-score" style="color:' + cc + '">' + cs + '</span>';
                    chips.appendChild( chip );
                } );

                breakdownWrap.appendChild( chips );
            }
        }

        /**
         * Render grouped check list under category headings.
         *
         * @param {Array}  checks   Array of {id, category, status, score, text}.
         * @param {Object} breakdown {basic, content, readability} each {score, label}.
         */
        function renderChecks( checks, breakdown ) {
            if ( ! analysisList ) { return; }
            analysisList.innerHTML = '';

            if ( ! checks || checks.length === 0 ) { return; }

            // Group checks by category (preserve order: basic, content, readability).
            const catOrder = [ 'basic', 'content', 'readability' ];
            const groups   = {};
            catOrder.forEach( function ( c ) { groups[ c ] = []; } );
            checks.forEach( function ( ch ) {
                const cat = ch.category || 'content';
                if ( ! groups[ cat ] ) { groups[ cat ] = []; }
                groups[ cat ].push( ch );
            } );

            catOrder.forEach( function ( catId ) {
                const group = groups[ catId ];
                if ( ! group || group.length === 0 ) { return; }

                const catInfo = ( breakdown && breakdown[ catId ] ) ? breakdown[ catId ] : null;

                // Category heading item.
                const headingLi = document.createElement( 'li' );
                headingLi.className = 'mmseo-analysis-cat-heading';

                const headingText = catInfo ? catInfo.label : catId;
                const headingScore = catInfo ? catInfo.score : null;

                headingLi.innerHTML =
                    '<span class="mmseo-cat-heading-label">' + escHtml( headingText ) + '</span>' +
                    ( headingScore !== null
                        ? '<span class="mmseo-cat-heading-score">' + headingScore + '</span>'
                        : '' );

                analysisList.appendChild( headingLi );

                // Check items.
                group.forEach( function ( check ) {
                    const li  = document.createElement( 'li' );
                    li.className = 'mmseo-analysis-check-item';

                    const dot = document.createElement( 'span' );
                    dot.className = 'mmseo-dot ' + dotColorClass( check.status );

                    const txt = document.createElement( 'span' );
                    txt.textContent = check.text || '';

                    li.appendChild( dot );
                    li.appendChild( txt );
                    analysisList.appendChild( li );
                } );
            } );
        }

        /** Minimal HTML escaping for untrusted strings. */
        function escHtml( str ) {
            return String( str )
                .replace( /&/g, '&amp;' )
                .replace( /</g, '&lt;' )
                .replace( />/g, '&gt;' )
                .replace( /"/g, '&quot;' );
        }

        function runAnalysis() {
            if ( ! ( window.wp && window.wp.apiFetch ) ) {
                return;
            }

            const titleVal = titleInput   ? titleInput.value   : '';
            const descVal  = descTextarea ? descTextarea.value  : '';
            const kwVal    = focusKwInput ? focusKwInput.value : '';

            wp.apiFetch( {
                path   : '/mmseo/v1/analyze',
                method : 'POST',
                data   : {
                    post_id   : data.postId || 0,
                    title     : titleVal,
                    meta_desc : descVal,
                    focus_kw  : kwVal,
                },
            } ).then( function ( response ) {
                if ( ! response ) { return; }

                const score     = parseInt( response.score, 10 ) || 0;
                const ratingKey = response.rating    || 'poor';
                const breakdown = response.breakdown || null;
                const checks    = Array.isArray( response.checks ) ? response.checks : [];

                renderScoreBadge( score, ratingKey, breakdown );
                renderChecks( checks, breakdown );

            } ).catch( function () {
                // Silently ignore network / API errors.
            } );
        }

        function scheduleAnalysis() {
            clearTimeout( analysisTimer );
            analysisTimer = setTimeout( runAnalysis, 1500 );
        }

        [ titleInput, descTextarea, focusKwInput ].forEach( function ( el ) {
            if ( el ) {
                el.addEventListener( 'input', scheduleAnalysis );
            }
        } );

        // ------------------------------------------------------------------ //
        // 5. Media picker buttons
        // ------------------------------------------------------------------ //

        const mediaButtons = qsa( '.mmseo-media-button' );

        mediaButtons.forEach( function ( btn ) {
            btn.addEventListener( 'click', function ( e ) {
                e.preventDefault();

                if ( ! ( window.wp && window.wp.media ) ) {
                    return;
                }

                const targetId       = btn.getAttribute( 'data-target' );
                const previewId      = btn.getAttribute( 'data-preview-target' );
                const targetInput    = targetId  ? qs( '#' + targetId )  : null;
                const previewImg     = previewId ? qs( '#' + previewId ) : null;
                const hiddenIdInput  = targetId  ? qs( '#' + targetId + '_id' ) : null;

                const frame = wp.media( {
                    title  : 'Select Image',
                    button : { text: 'Use this image' },
                    multiple: false,
                } );

                frame.on( 'select', function () {
                    const attachment = frame.state().get( 'selection' ).first().toJSON();
                    const url        = attachment.url  || '';
                    const id         = attachment.id   || '';

                    if ( targetInput )   { targetInput.value   = url; }
                    if ( hiddenIdInput ) { hiddenIdInput.value = id;  }
                    if ( previewImg )    { previewImg.src       = url; }
                } );

                frame.open();
            } );
        } );

        // ------------------------------------------------------------------ //
        // 6. Analysis note
        // ------------------------------------------------------------------ //

        const analysisNote = qs( '#mmseo-analysis-note' );
        if ( analysisNote ) {
            analysisNote.textContent = __( 'Analysis uses the last saved builder content.', 'mm-seo' );
        }

    } ); // end DOMContentLoaded

}() );
