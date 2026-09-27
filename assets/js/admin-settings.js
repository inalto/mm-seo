/**
 * MM SEO – Admin Settings Page
 * ES2020, no framework, no build step.
 * Optional localized global: MMSEOAdmin = {}
 */

( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {

        // Helpers.
        const qs  = ( sel, ctx ) => ( ctx || document ).querySelector( sel );
        const qsa = ( sel, ctx ) => Array.from( ( ctx || document ).querySelectorAll( sel ) );

        // ------------------------------------------------------------------ //
        // 1. %%var%% inserter
        // ------------------------------------------------------------------ //

        let lastFocusedTemplateInput = null;

        qsa( '.mmseo-template-input' ).forEach( function ( input ) {
            input.addEventListener( 'focus', function () {
                lastFocusedTemplateInput = input;
            } );
        } );

        const varInserter = qs( '#mmseo-var-inserter' );
        if ( varInserter ) {
            varInserter.addEventListener( 'change', function () {
                const varValue = varInserter.value;
                if ( ! varValue || ! lastFocusedTemplateInput ) {
                    varInserter.value = '';
                    return;
                }

                const el  = lastFocusedTemplateInput;
                const tag = el.tagName.toLowerCase();

                if ( tag === 'input' || tag === 'textarea' ) {
                    // Prefer execCommand for contenteditable support; use manual splice as fallback.
                    el.focus();
                    const start = el.selectionStart;
                    const end   = el.selectionEnd;
                    const current = el.value;
                    el.value = current.slice( 0, start ) + varValue + current.slice( end );
                    const newPos = start + varValue.length;
                    el.setSelectionRange( newPos, newPos );
                    // Trigger change event so WP hooks can react.
                    el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
                }

                varInserter.value = '';
            } );
        }

        // ------------------------------------------------------------------ //
        // 2. Redirects table – add / remove rows
        // ------------------------------------------------------------------ //

        const addRedirectBtn  = qs( '#mmseo-add-redirect' );
        const redirectsTbody  = qs( '#mmseo-redirects-tbody' );
        const rowTemplate     = qs( '.mmseo-redirect-row-template' );

        if ( addRedirectBtn && redirectsTbody && rowTemplate ) {
            addRedirectBtn.addEventListener( 'click', function () {
                const index = Date.now();
                const clone = rowTemplate.cloneNode( true );
                clone.classList.remove( 'mmseo-redirect-row-template' );
                clone.classList.add( 'mmseo-redirect-row' );
                clone.style.display = '';
                clone.dataset.newRow = '1';

                // Replace [ROW_INDEX] placeholder in all attribute values and inner HTML.
                clone.innerHTML = clone.innerHTML.replace( /\[ROW_INDEX\]/g, index );

                // Clear input values in the clone.
                qsa( 'input, select, textarea', clone ).forEach( function ( input ) {
                    if ( input.type !== 'hidden' || ! input.name.includes( '[id]' ) ) {
                        input.value = '';
                    }
                } );

                redirectsTbody.appendChild( clone );
            } );

            // Delegated delete handler.
            redirectsTbody.addEventListener( 'click', function ( e ) {
                const btn = e.target.closest( '.mmseo-delete-redirect' );
                if ( ! btn ) { return; }

                const row = btn.closest( 'tr' );
                if ( ! row ) { return; }

                if ( row.dataset.newRow === '1' ) {
                    // New row (not yet saved) – just remove it.
                    row.remove();
                } else {
                    // Existing row – mark for deletion.
                    row.classList.add( 'mmseo-row-deleted' );
                    row.style.opacity = '0.4';
                    const deleteInput = qs( 'input[name*="[delete]"]', row );
                    if ( deleteInput ) {
                        deleteInput.value = '1';
                    } else {
                        // Create hidden delete flag if it doesn't exist.
                        const hiddenField = document.createElement( 'input' );
                        hiddenField.type  = 'hidden';
                        hiddenField.value = '1';
                        const nameAttr = ( qs( 'input', row ) || {} ).name || '';
                        hiddenField.name  = nameAttr.replace( /\[[^\]]*\]$/, '[delete]' );
                        row.appendChild( hiddenField );
                    }
                }
            } );
        }

        // ------------------------------------------------------------------ //
        // 3. Media picker – Logo
        // ------------------------------------------------------------------ //

        const logoBrowseBtn  = qs( '#mmseo-logo-media-btn' );

        if ( logoBrowseBtn && window.wp && window.wp.media ) {
            logoBrowseBtn.addEventListener( 'click', function ( e ) {
                e.preventDefault();

                const frame = wp.media( {
                    title   : 'Select Logo',
                    button  : { text: 'Use this logo' },
                    multiple: false,
                } );

                frame.on( 'select', function () {
                    const attachment = frame.state().get( 'selection' ).first().toJSON();
                    const previewImg = qs( '#mmseo-logo-preview' );
                    const urlInput   = qs( '#mmseo-org_logo' );
                    const idInput    = qs( '#mmseo-org_logo_id' );

                    if ( urlInput )   { urlInput.value  = attachment.url || ''; }
                    if ( idInput )    { idInput.value   = attachment.id  || ''; }
                    if ( previewImg ) { previewImg.src  = attachment.url || ''; }
                } );

                frame.open();
            } );
        }

        // ------------------------------------------------------------------ //
        // 4. Media picker – OG default image
        // ------------------------------------------------------------------ //

        const ogImageBtn = qs( '#mmseo-og-image-media-btn' );

        if ( ogImageBtn && window.wp && window.wp.media ) {
            ogImageBtn.addEventListener( 'click', function ( e ) {
                e.preventDefault();

                const frame = wp.media( {
                    title   : 'Select Default OG Image',
                    button  : { text: 'Use this image' },
                    multiple: false,
                } );

                frame.on( 'select', function () {
                    const attachment = frame.state().get( 'selection' ).first().toJSON();
                    const previewImg = qs( '#mmseo-og-image-preview' );
                    const urlInput   = qs( '#mmseo-og_default_image' );
                    const idInput    = qs( '#mmseo-og_default_image_id' );

                    if ( urlInput )   { urlInput.value  = attachment.url || ''; }
                    if ( idInput )    { idInput.value   = attachment.id  || ''; }
                    if ( previewImg ) { previewImg.src  = attachment.url || ''; }
                } );

                frame.open();
            } );
        }

        // ------------------------------------------------------------------ //
        // 5. Bulk analyze
        // ------------------------------------------------------------------ //

        const bulkBtn       = qs( '#mmseo-bulk-analyze-btn' );
        const bulkProgress  = qs( '#mmseo-bulk-progress' );
        const progressFill  = qs( '.mmseo-progress-bar-fill' );
        const progressText  = qs( '#mmseo-bulk-progress-text' );

        if ( bulkBtn ) {
            bulkBtn.addEventListener( 'click', function () {
                bulkBtn.disabled = true;
                if ( bulkProgress ) { bulkProgress.style.display = ''; }

                const nonce = bulkBtn.dataset.nonce || '';
                let currentOffset = 0;

                function runBatch() {
                    const formData = new FormData();
                    formData.append( 'action',      'mmseo_bulk_analyze' );
                    formData.append( '_ajax_nonce', nonce );
                    formData.append( 'offset',      String( currentOffset ) );

                    fetch( ( typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php' ), {
                        method: 'POST',
                        body  : formData,
                    } )
                    .then( ( res ) => res.json() )
                    .then( function ( response ) {
                        if ( ! response ) { return; }

                        const processed = response.processed || 0;
                        const total     = response.total     || 1;
                        const pct       = Math.round( ( processed / total ) * 100 );

                        if ( progressFill ) { progressFill.style.width = pct + '%'; }
                        if ( progressText ) { progressText.textContent  = 'Processed ' + processed + ' of ' + total; }

                        if ( response.done ) {
                            if ( progressText ) { progressText.textContent = 'Complete!'; }
                            bulkBtn.disabled = false;
                        } else {
                            currentOffset = processed;
                            setTimeout( runBatch, 100 );
                        }
                    } )
                    .catch( function () {
                        if ( progressText ) { progressText.textContent = 'Error – please retry.'; }
                        bulkBtn.disabled = false;
                    } );
                }

                runBatch();
            } );
        }

        // ------------------------------------------------------------------ //
        // 6. Social profiles add / remove
        // ------------------------------------------------------------------ //

        const addSocialBtn = qs( '#mmseo-add-social' );
        const socialWrap   = addSocialBtn ? addSocialBtn.closest( '.mmseo-social-profiles' ) || addSocialBtn.parentElement : null;

        if ( addSocialBtn && socialWrap ) {
            addSocialBtn.addEventListener( 'click', function ( e ) {
                e.preventDefault();

                const rows = qsa( '.mmseo-social-row', socialWrap );
                if ( rows.length === 0 ) { return; }

                const lastRow = rows[ rows.length - 1 ];
                const clone   = lastRow.cloneNode( true );

                // Clear the value in the cloned input.
                qsa( 'input', clone ).forEach( function ( input ) {
                    input.value = '';
                } );

                // Insert before the add button row (or append).
                socialWrap.insertBefore( clone, addSocialBtn.parentElement || addSocialBtn );
            } );

            // Delegated remove handler.
            socialWrap.addEventListener( 'click', function ( e ) {
                const removeBtn = e.target.closest( '.mmseo-social-remove' );
                if ( ! removeBtn ) { return; }

                const row = removeBtn.closest( '.mmseo-social-row' );
                if ( row ) { row.remove(); }
            } );
        }

        // ------------------------------------------------------------------ //
        // 7. Tab-prefill from URL (?src=...)
        // ------------------------------------------------------------------ //

        const params = new URLSearchParams( window.location.search );
        const srcParam = params.get( 'src' );

        if ( srcParam && redirectsTbody ) {
            // Find the first empty source input in the table.
            const srcInputs = qsa( '.mmseo-redirect-src', redirectsTbody );
            const emptyInput = srcInputs.find( ( i ) => ! i.value );
            if ( emptyInput ) {
                emptyInput.value = srcParam;
                emptyInput.focus();
            }
        }

    } ); // end DOMContentLoaded

}() );
