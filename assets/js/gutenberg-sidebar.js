/**
 * MM SEO – Gutenberg Sidebar
 * ES2020, no JSX, no build step.
 * Localized global: MMSEOSidebarData = { homeHost, siteName, sep, schemaTypes }
 */

( function () {
    'use strict';

    // Guard: bail early if required WP globals are unavailable.
    if ( typeof wp === 'undefined' || ! wp.plugins || ! wp.element ) {
        return;
    }

    // ------------------------------------------------------------------ //
    // WP globals
    // ------------------------------------------------------------------ //

    const el = wp.element.createElement;

    const { registerPlugin } = wp.plugins;

    // PluginSidebar / PluginSidebarMoreMenuItem / PluginPostStatusInfo
    // may live in wp.editPost or wp.editor depending on WP version.
    const editPostNS = ( wp.editPost && wp.editPost.PluginSidebar ) ? wp.editPost
        : ( wp.editor && wp.editor.PluginSidebar ) ? wp.editor
        : {};

    const PluginSidebar             = editPostNS.PluginSidebar             || null;
    const PluginSidebarMoreMenuItem = editPostNS.PluginSidebarMoreMenuItem || null;
    const PluginPostStatusInfo      = editPostNS.PluginPostStatusInfo      || null;

    const {
        PanelBody,
        TextControl,
        TextareaControl,
        SelectControl,
        ToggleControl,
        Button,
    } = wp.components || {};

    const { useSelect, useDispatch } = wp.data || {};
    const { useEffect, useState, useRef, useCallback } = wp.element || {};
    const { __ }    = ( wp.i18n   ) || { __: ( s ) => s };
    const { useDebounce } = ( wp.compose ) || {};

    // Localized sidebar data.
    const sidebarData = ( typeof MMSEOSidebarData !== 'undefined' ) ? MMSEOSidebarData : {};

    // ------------------------------------------------------------------ //
    // Schema types
    // ------------------------------------------------------------------ //

    const SCHEMA_TYPES = ( function () {
        const raw = sidebarData.schemaTypes;
        if ( raw && typeof raw === 'object' ) {
            return Object.entries( raw ).map( ( [ value, label ] ) => ( { label: String( label ), value: String( value ) } ) );
        }
        return [
            { label: 'None',    value: ''        },
            { label: 'Article', value: 'Article' },
            { label: 'Product', value: 'Product' },
        ];
    }() );

    const NOINDEX_OPTIONS = [
        { label: __( 'Default', 'mm-seo' ),     value: ''  },
        { label: __( 'No Index', 'mm-seo' ),    value: '1' },
        { label: __( 'Force Index', 'mm-seo' ), value: '0' },
    ];

    // ------------------------------------------------------------------ //
    // Helpers
    // ------------------------------------------------------------------ //

    function charHint( value, min, max ) {
        const count = value ? value.length : 0;
        let color = '#8c8f94';
        if ( count < min )             { color = '#dba617'; }
        else if ( count <= max )       { color = '#00a32a'; }
        else                           { color = '#d63638'; }
        return el( 'span', { style: { color, fontSize: '12px' } }, count + '/' + max );
    }

    function dotEl( status ) {
        let color = '#8c8f94';
        if ( status === 'good' ) { color = '#00a32a'; }
        else if ( status === 'ok' ) { color = '#dba617'; }
        else if ( status === 'bad' ) { color = '#d63638'; }
        return el( 'span', {
            style: {
                display      : 'inline-block',
                width        : '10px',
                height       : '10px',
                borderRadius : '50%',
                background   : color,
                flexShrink   : '0',
                marginTop    : '3px',
            },
        } );
    }

    function trafficLightColor( score ) {
        if ( score >= 71 ) { return '#00a32a'; }
        if ( score >= 41 ) { return '#dba617'; }
        return '#d63638';
    }

    /** Map rating key → translated label. */
    function ratingLabel( key ) {
        const map = {
            excellent : __( 'Excellent', 'mm-seo' ),
            good      : __( 'Good',      'mm-seo' ),
            fair      : __( 'Fair',      'mm-seo' ),
            poor      : __( 'Poor',      'mm-seo' ),
        };
        return map[ key ] || key;
    }

    function buildSnippetUrl( slug ) {
        const host = sidebarData.homeHost || window.location.host;
        return ( host ? ( 'https://' + host.replace( /\/$/, '' ) ) : '' ) + '/' + ( slug || '' ) + '/';
    }

    // ------------------------------------------------------------------ //
    // MMSEOSidebar component
    // ------------------------------------------------------------------ //

    function MMSEOSidebar() {
        if ( ! useSelect || ! useDispatch ) {
            return null;
        }

        const meta  = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' )  || {} );
        const title = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '' );
        const slug  = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'slug' )  || '' );
        const { editPost } = useDispatch( 'core/editor' );

        const [ analysisResult, setAnalysisResult ] = useState( null );

        const snippetRef = useRef( null );

        // Meta values with safe fallbacks.
        const seoTitle   = meta._mmseo_title      || '';
        const desc       = meta._mmseo_desc        || '';
        const kw         = meta._mmseo_focuskw     || '';
        const canonical  = meta._mmseo_canonical   || '';
        const noindex    = meta._mmseo_noindex     || '';
        const nofollow   = meta._mmseo_nofollow    === '1';
        const schemaType = meta._mmseo_schema_type || '';
        const breadcrumb = meta._mmseo_breadcrumb  || '';

        function updateMeta( key, value ) {
            editPost( { meta: Object.assign( {}, meta, { [ key ]: value } ) } );
        }

        // Snippet preview effect.
        useEffect( function () {
            if ( snippetRef.current && window.MMSEOSnippet ) {
                MMSEOSnippet.render( snippetRef.current, {
                    title : seoTitle || title,
                    desc  : desc,
                    url   : buildSnippetUrl( slug ),
                } );
            }
        }, [ seoTitle, title, desc, slug ] );

        // Debounced analysis effect — uses local MMSEOAnalyzer (no REST call in Gutenberg).
        useEffect( function () {
            const timer = setTimeout( function () {
                if ( window.MMSEOAnalyzer && typeof MMSEOAnalyzer.analyze === 'function' ) {
                    const result = MMSEOAnalyzer.analyze( {
                        title      : seoTitle || title,
                        metaDesc   : desc,
                        focusKw    : kw,
                        slug       : slug,
                        contentHtml: '',
                        homeHost   : sidebarData.homeHost || '',
                    } );
                    setAnalysisResult( result );
                }
            }, 1500 );
            return function () { clearTimeout( timer ); };
        }, [ seoTitle, title, desc, kw, slug ] );

        const score      = ( analysisResult && analysisResult.score    != null ) ? analysisResult.score    : null;
        const ratingKey  = ( analysisResult && analysisResult.rating           ) ? analysisResult.rating   : null;
        const breakdown  = ( analysisResult && analysisResult.breakdown        ) ? analysisResult.breakdown : null;
        const checks     = ( analysisResult && Array.isArray( analysisResult.checks ) ) ? analysisResult.checks : [];

        // ── MM Score badge ──────────────────────────────────────────────────
        const scoreBadgeEl = score != null
            ? el( 'div', { className: 'mmseo-score-badge', style: { margin: '8px 0' } },

                // Big score number.
                el( 'span', {
                    className: 'mmseo-score-number',
                    style: {
                        fontSize   : '36px',
                        fontWeight : '700',
                        lineHeight : '1',
                        color      : trafficLightColor( score ),
                        marginRight: '6px',
                    },
                }, String( score ) ),

                el( 'span', {
                    style: { fontSize: '16px', color: '#646970', verticalAlign: 'bottom', lineHeight: '2.2' },
                }, '/100' ),

                ' ',

                // Rating label chip.
                el( 'span', {
                    className: 'mmseo-score-rating mmseo-rating--' + ( ratingKey || 'poor' ),
                    style: {
                        display       : 'inline-block',
                        padding       : '2px 8px',
                        borderRadius  : '10px',
                        fontSize      : '12px',
                        fontWeight    : '600',
                        verticalAlign : 'bottom',
                        lineHeight    : '2.2',
                        marginLeft    : '6px',
                        background    : trafficLightColor( score ) + '22',
                        color         : trafficLightColor( score ),
                    },
                }, ratingLabel( ratingKey ) )
              )
            : null;

        // ── Breakdown chips ──────────────────────────────────────────────────
        const breakdownEl = breakdown
            ? el( 'div', {
                className: 'mmseo-breakdown-chips',
                style: { display: 'flex', gap: '6px', flexWrap: 'wrap', margin: '6px 0 10px' },
              },
                ...Object.keys( breakdown ).map( function ( catId ) {
                    const cat   = breakdown[ catId ];
                    const cc    = trafficLightColor( cat.score );
                    return el( 'span', {
                        key       : catId,
                        className : 'mmseo-breakdown-chip',
                        style     : {
                            display      : 'inline-flex',
                            alignItems   : 'center',
                            gap          : '4px',
                            padding      : '3px 8px',
                            borderRadius : '12px',
                            fontSize     : '12px',
                            background   : '#f6f7f7',
                            border       : '1px solid #c3c4c7',
                        },
                    },
                    el( 'span', { style: { color: '#646970' } }, cat.label ),
                    el( 'span', { style: { fontWeight: '700', color: cc } }, String( cat.score ) )
                    );
                } )
              )
            : null;

        // ── Grouped check list ──────────────────────────────────────────────
        const CAT_ORDER = [ 'basic', 'content', 'readability' ];

        function buildGroupedChecks() {
            if ( checks.length === 0 ) {
                return el( 'p', { style: { color: '#8c8f94', fontSize: '13px' } }, __( 'Waiting for input…', 'mm-seo' ) );
            }

            // Group.
            const groups = {};
            CAT_ORDER.forEach( function ( c ) { groups[ c ] = []; } );
            checks.forEach( function ( ch ) {
                const cat = ch.category || 'content';
                if ( ! groups[ cat ] ) { groups[ cat ] = []; }
                groups[ cat ].push( ch );
            } );

            const elements = [];
            CAT_ORDER.forEach( function ( catId ) {
                const group   = groups[ catId ];
                if ( ! group || group.length === 0 ) { return; }
                const catInfo = breakdown && breakdown[ catId ] ? breakdown[ catId ] : null;
                const catLabel = catInfo ? catInfo.label : catId;

                // Category heading.
                elements.push(
                    el( 'div', {
                        key      : 'heading-' + catId,
                        className: 'mmseo-analysis-cat-heading',
                        style    : {
                            display      : 'flex',
                            justifyContent: 'space-between',
                            alignItems   : 'center',
                            fontWeight   : '600',
                            fontSize     : '12px',
                            color        : '#646970',
                            textTransform: 'uppercase',
                            letterSpacing: '0.05em',
                            marginTop    : '10px',
                            marginBottom : '4px',
                            paddingBottom: '3px',
                            borderBottom : '1px solid #f0f0f1',
                        },
                    },
                    el( 'span', null, catLabel ),
                    catInfo ? el( 'span', { style: { color: trafficLightColor( catInfo.score ), fontWeight: '700' } }, String( catInfo.score ) ) : null
                    )
                );

                // Check items.
                group.forEach( function ( check, i ) {
                    elements.push(
                        el( 'div', {
                            key  : catId + '-' + i,
                            style: { display: 'flex', alignItems: 'flex-start', gap: '8px', padding: '3px 0', fontSize: '13px' },
                        },
                        dotEl( check.status ),
                        el( 'span', null, check.text || '' )
                        )
                    );
                } );
            } );

            return el( 'div', { style: { margin: '4px 0' } }, ...elements );
        }

        const checksEl = buildGroupedChecks();

        return el( wp.element.Fragment, null,

            // --- Snippet Preview ---
            PanelBody ? el( PanelBody, { title: __( 'Snippet Preview', 'mm-seo' ), initialOpen: true },
                el( 'div', { ref: snippetRef, style: { minHeight: '80px' } } )
            ) : null,

            // --- SEO Fields ---
            PanelBody ? el( PanelBody, { title: __( 'SEO Fields', 'mm-seo' ), initialOpen: true },

                // SEO Title
                el( 'div', { style: { marginBottom: '12px' } },
                    TextControl ? el( TextControl, {
                        label   : el( wp.element.Fragment, null,
                            __( 'SEO Title', 'mm-seo' ),
                            ' ',
                            charHint( seoTitle, 30, 60 )
                        ),
                        value   : seoTitle,
                        onChange: ( val ) => updateMeta( '_mmseo_title', val ),
                    } ) : null
                ),

                // Meta Description
                el( 'div', { style: { marginBottom: '12px' } },
                    TextareaControl ? el( TextareaControl, {
                        label   : el( wp.element.Fragment, null,
                            __( 'Meta Description', 'mm-seo' ),
                            ' ',
                            charHint( desc, 70, 156 )
                        ),
                        value   : desc,
                        rows    : 3,
                        onChange: ( val ) => updateMeta( '_mmseo_desc', val ),
                    } ) : null
                ),

                // Focus Keyword
                TextControl ? el( TextControl, {
                    label   : __( 'Focus Keyword', 'mm-seo' ),
                    value   : kw,
                    onChange: ( val ) => updateMeta( '_mmseo_focuskw', val ),
                } ) : null,

                // Canonical URL
                TextControl ? el( TextControl, {
                    label   : __( 'Canonical URL', 'mm-seo' ),
                    value   : canonical,
                    onChange: ( val ) => updateMeta( '_mmseo_canonical', val ),
                } ) : null

            ) : null,

            // --- MM Score Analysis ---
            PanelBody ? el( PanelBody, { title: __( 'MM Score', 'mm-seo' ), initialOpen: false },
                scoreBadgeEl,
                breakdownEl,
                checksEl
            ) : null,

            // --- Advanced ---
            PanelBody ? el( PanelBody, { title: __( 'Advanced', 'mm-seo' ), initialOpen: false },

                SelectControl ? el( SelectControl, {
                    label   : __( 'Robots: Index', 'mm-seo' ),
                    value   : noindex,
                    options : NOINDEX_OPTIONS,
                    onChange: ( val ) => updateMeta( '_mmseo_noindex', val ),
                } ) : null,

                ToggleControl ? el( ToggleControl, {
                    label   : __( 'No Follow', 'mm-seo' ),
                    checked : nofollow,
                    onChange: ( val ) => updateMeta( '_mmseo_nofollow', val ? '1' : '0' ),
                } ) : null,

                SelectControl ? el( SelectControl, {
                    label   : __( 'Schema Type', 'mm-seo' ),
                    value   : schemaType,
                    options : [ { label: __( '— None —', 'mm-seo' ), value: '' }, ...SCHEMA_TYPES ],
                    onChange: ( val ) => updateMeta( '_mmseo_schema_type', val ),
                } ) : null,

                TextControl ? el( TextControl, {
                    label   : __( 'Breadcrumb Title', 'mm-seo' ),
                    value   : breadcrumb,
                    onChange: ( val ) => updateMeta( '_mmseo_breadcrumb', val ),
                } ) : null

            ) : null

        ); // end Fragment
    }

    // ------------------------------------------------------------------ //
    // MMSEOStatusInfo – MM Score in post status bar
    // ------------------------------------------------------------------ //

    function MMSEOStatusInfo() {
        if ( ! useSelect ) { return null; }

        const meta  = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' )  || {} );
        const title = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '' );
        const slug  = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'slug' )  || '' );

        const seoTitle = meta._mmseo_title  || '';
        const desc     = meta._mmseo_desc   || '';
        const kw       = meta._mmseo_focuskw || '';

        const [ score, setScore ] = useState( null );

        useEffect( function () {
            const timer = setTimeout( function () {
                if ( window.MMSEOAnalyzer && typeof MMSEOAnalyzer.analyze === 'function' ) {
                    const result = MMSEOAnalyzer.analyze( {
                        title      : seoTitle || title,
                        metaDesc   : desc,
                        focusKw    : kw,
                        slug       : slug,
                        contentHtml: '',
                        homeHost   : sidebarData.homeHost || '',
                    } );
                    setScore( result ? result.score : null );
                }
            }, 1500 );
            return function () { clearTimeout( timer ); };
        }, [ seoTitle, title, desc, kw, slug ] );

        if ( ! PluginPostStatusInfo ) { return null; }

        const dotColor  = score != null ? trafficLightColor( score ) : '#8c8f94';
        const scoreText = score != null ? String( score ) : '—';

        return el( PluginPostStatusInfo, null,
            el( 'span', {
                style: {
                    display    : 'inline-flex',
                    alignItems : 'center',
                    gap        : '5px',
                    fontSize   : '13px',
                },
            },
            el( 'span', {
                style: {
                    display      : 'inline-block',
                    width        : '10px',
                    height       : '10px',
                    borderRadius : '50%',
                    background   : dotColor,
                },
            } ),
            __( 'MM Score', 'mm-seo' ) + ': ' + scoreText
            )
        );
    }

    // ------------------------------------------------------------------ //
    // Register plugin
    // ------------------------------------------------------------------ //

    const pluginIcon = el( 'svg',
        { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 20 20', width: 20, height: 20 },
        el( 'path', { d: 'M3 17l4-8 4 4 3-6 3 10H3z' } )
    );

    registerPlugin( 'mm-seo', {
        icon  : pluginIcon,
        render: function () {
            return el( wp.element.Fragment, null,
                PluginSidebarMoreMenuItem
                    ? el( PluginSidebarMoreMenuItem, { target: 'mm-seo-sidebar' },
                        __( 'MM SEO', 'mm-seo' )
                      )
                    : null,
                PluginSidebar
                    ? el( PluginSidebar, { name: 'mm-seo-sidebar', title: __( 'MM SEO', 'mm-seo' ) },
                        el( MMSEOSidebar, null )
                      )
                    : null,
                PluginPostStatusInfo
                    ? el( MMSEOStatusInfo, null )
                    : null
            );
        },
    } );

}() );
