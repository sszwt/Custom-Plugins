/**
 * Admin dashboard — CPT / taxonomy editor
 */
( function () {
	'use strict';

	var settings = ( window.cptflmAdmin && cptflmAdmin.settings ) ? JSON.parse( JSON.stringify( cptflmAdmin.settings ) ) : { post_types: [], taxonomies: [], frontend: {} };

	function toast( msg, isError ) {
		var el = document.getElementById( 'cptflm-toast' );
		if ( ! el ) {
			return;
		}
		el.hidden = false;
		el.textContent = msg;
		el.classList.toggle( 'is-error', !! isError );
		clearTimeout( toast._t );
		toast._t = setTimeout( function () { el.hidden = true; }, 3200 );
	}

	function esc( s ) {
		return String( s == null ? '' : s )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function toggleHtml( attrs, label, checked ) {
		return '<label class="cptflm-check">' +
			'<input type="checkbox" ' + attrs + ( checked ? ' checked' : '' ) + ' />' +
			'<span class="cptflm-switch" aria-hidden="true"></span>' +
			'<span class="cptflm-check__label">' + label + '</span>' +
			'</label>';
	}

	function supportChecks( selected ) {
		var opts = [ 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments', 'custom-fields' ];
		selected = selected || [];
		return opts.map( function ( o ) {
			return '<label><input type="checkbox" data-support="' + o + '"' + ( selected.indexOf( o ) > -1 ? ' checked' : '' ) + ' /><span>' + o + '</span></label>';
		} ).join( '' );
	}

	function renderCpts() {
		var list = document.getElementById( 'cptflm-cpt-list' );
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		( settings.post_types || [] ).forEach( function ( cpt, i ) {
			var item = document.createElement( 'div' );
			item.className = 'cptflm-item';
			item.innerHTML =
				'<div class="cptflm-item__top">' +
					'<div><strong>' + esc( cpt.label || cpt.slug || 'Post Type' ) + '</strong> ' +
					'<span class="cptflm-item__badge">' + esc( cpt.slug || 'slug' ) + '</span></div>' +
					'<button type="button" class="cptflm-btn cptflm-btn--danger" data-remove-cpt="' + i + '">Remove</button>' +
				'</div>' +
				'<div class="cptflm-card-fields">' +
					'<label><span>Slug</span><input data-cpt="' + i + '" data-key="slug" value="' + esc( cpt.slug || '' ) + '" /></label>' +
					'<label><span>Plural label</span><input data-cpt="' + i + '" data-key="label" value="' + esc( cpt.label || '' ) + '" /></label>' +
					'<label><span>Singular</span><input data-cpt="' + i + '" data-key="singular" value="' + esc( cpt.singular || '' ) + '" /></label>' +
					'<label><span>Menu icon</span><input data-cpt="' + i + '" data-key="menu_icon" value="' + esc( cpt.menu_icon || 'dashicons-admin-post' ) + '" placeholder="dashicons-portfolio" /></label>' +
					'<div class="cptflm-toggles">' +
						toggleHtml( 'data-cpt="' + i + '" data-key="enabled"', 'Enabled', !! cpt.enabled ) +
						toggleHtml( 'data-cpt="' + i + '" data-key="public"', 'Public', !! cpt.public ) +
						toggleHtml( 'data-cpt="' + i + '" data-key="has_archive"', 'Has archive', !! cpt.has_archive ) +
						toggleHtml( 'data-cpt="' + i + '" data-key="show_in_rest"', 'Show in REST / Gutenberg', !! cpt.show_in_rest ) +
					'</div>' +
					'<div class="full"><span class="cptflm-section-label">Supports</span><div class="cptflm-chips" data-cpt-supports="' + i + '">' + supportChecks( cpt.supports ) + '</div></div>' +
				'</div>';
			list.appendChild( item );
		} );
	}

	function renderTax() {
		var list = document.getElementById( 'cptflm-tax-list' );
		if ( ! list ) {
			return;
		}
		var cptOptions = ( settings.post_types || [] ).map( function ( c ) {
			return { slug: c.slug, label: c.label || c.slug };
		} ).filter( function ( c ) { return !! c.slug; } );

		list.innerHTML = '';
		( settings.taxonomies || [] ).forEach( function ( tax, i ) {
			var pts = tax.post_types || [];
			var checks = cptOptions.map( function ( c ) {
				return '<label><input type="checkbox" data-tax-pt="' + i + '" value="' + esc( c.slug ) + '"' + ( pts.indexOf( c.slug ) > -1 ? ' checked' : '' ) + ' /><span>' + esc( c.label ) + '</span></label>';
			} ).join( '' ) || '<em style="color:#7a8290;font-size:13px">Add a post type first</em>';

			var item = document.createElement( 'div' );
			item.className = 'cptflm-item';
			item.innerHTML =
				'<div class="cptflm-item__top">' +
					'<div><strong>' + esc( tax.label || tax.slug || 'Taxonomy' ) + '</strong> ' +
					'<span class="cptflm-item__badge">' + esc( tax.slug || 'slug' ) + '</span></div>' +
					'<button type="button" class="cptflm-btn cptflm-btn--danger" data-remove-tax="' + i + '">Remove</button>' +
				'</div>' +
				'<div class="cptflm-card-fields">' +
					'<label><span>Slug</span><input data-tax="' + i + '" data-key="slug" value="' + esc( tax.slug || '' ) + '" /></label>' +
					'<label><span>Plural label</span><input data-tax="' + i + '" data-key="label" value="' + esc( tax.label || '' ) + '" /></label>' +
					'<label><span>Singular</span><input data-tax="' + i + '" data-key="singular" value="' + esc( tax.singular || '' ) + '" /></label>' +
					'<div class="cptflm-toggles">' +
						toggleHtml( 'data-tax="' + i + '" data-key="enabled"', 'Enabled', !! tax.enabled ) +
						toggleHtml( 'data-tax="' + i + '" data-key="hierarchical"', 'Hierarchical (categories)', !! tax.hierarchical ) +
						toggleHtml( 'data-tax="' + i + '" data-key="show_in_rest"', 'Show in REST', !! tax.show_in_rest ) +
					'</div>' +
					'<div class="full"><span class="cptflm-section-label">Attach to post types</span><div class="cptflm-chips">' + checks + '</div></div>' +
				'</div>';
			list.appendChild( item );
		} );
	}

	function collectFrontend() {
		var form = document.getElementById( 'cptflm-display-form' );
		if ( ! form ) {
			return settings.frontend || {};
		}
		var out = Object.assign( {}, settings.frontend || {} );
		form.querySelectorAll( '[data-front]' ).forEach( function ( el ) {
			var key = el.getAttribute( 'data-front' );
			if ( el.type === 'checkbox' ) {
				out[ key ] = el.checked ? 1 : 0;
			} else if ( el.type === 'number' ) {
				out[ key ] = parseInt( el.value, 10 ) || 0;
			} else {
				out[ key ] = el.value;
			}
		} );
		return out;
	}

	function syncFromDom() {
		document.querySelectorAll( '[data-cpt][data-key]' ).forEach( function ( el ) {
			var i = parseInt( el.getAttribute( 'data-cpt' ), 10 );
			var key = el.getAttribute( 'data-key' );
			if ( ! settings.post_types[ i ] ) {
				return;
			}
			if ( el.type === 'checkbox' ) {
				settings.post_types[ i ][ key ] = el.checked ? 1 : 0;
			} else {
				settings.post_types[ i ][ key ] = el.value;
			}
		} );

		document.querySelectorAll( '[data-cpt-supports]' ).forEach( function ( wrap ) {
			var i = parseInt( wrap.getAttribute( 'data-cpt-supports' ), 10 );
			if ( ! settings.post_types[ i ] ) {
				return;
			}
			var supports = [];
			wrap.querySelectorAll( '[data-support]' ).forEach( function ( cb ) {
				if ( cb.checked ) {
					supports.push( cb.getAttribute( 'data-support' ) );
				}
			} );
			settings.post_types[ i ].supports = supports;
		} );

		document.querySelectorAll( '[data-tax][data-key]' ).forEach( function ( el ) {
			var i = parseInt( el.getAttribute( 'data-tax' ), 10 );
			var key = el.getAttribute( 'data-key' );
			if ( ! settings.taxonomies[ i ] ) {
				return;
			}
			if ( el.type === 'checkbox' ) {
				settings.taxonomies[ i ][ key ] = el.checked ? 1 : 0;
			} else {
				settings.taxonomies[ i ][ key ] = el.value;
			}
		} );

		( settings.taxonomies || [] ).forEach( function ( tax, i ) {
			var pts = [];
			document.querySelectorAll( '[data-tax-pt="' + i + '"]' ).forEach( function ( cb ) {
				if ( cb.checked ) {
					pts.push( cb.value );
				}
			} );
			tax.post_types = pts;
		} );

		settings.frontend = collectFrontend();
	}

	function save() {
		syncFromDom();
		if ( ! window.cptflmAdmin ) {
			return;
		}
		var body = new FormData();
		body.append( 'action', 'cptflm_save_settings' );
		body.append( 'nonce', cptflmAdmin.nonce );
		body.append( 'settings', JSON.stringify( settings ) );

		var btn = document.getElementById( 'cptflm-save-btn' );
		if ( btn ) {
			btn.disabled = true;
		}

		fetch( cptflmAdmin.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( ! json.success ) {
					throw new Error( ( json.data && json.data.message ) || 'fail' );
				}
				settings = json.data.settings || settings;
				toast( json.data.message || cptflmAdmin.i18n.saved );
				renderCpts();
				renderTax();
			} )
			.catch( function ( err ) {
				toast( err.message || cptflmAdmin.i18n.error, true );
			} )
			.finally( function () {
				if ( btn ) {
					btn.disabled = false;
				}
			} );
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target;
		if ( t.id === 'cptflm-save-btn' ) {
			e.preventDefault();
			save();
		}
		if ( t.id === 'cptflm-add-cpt' ) {
			e.preventDefault();
			syncFromDom();
			settings.post_types.push( {
				slug: 'custom_type',
				label: 'Custom Types',
				singular: 'Custom Type',
				menu_icon: 'dashicons-admin-post',
				public: 1,
				has_archive: 1,
				show_in_rest: 1,
				supports: [ 'title', 'editor', 'thumbnail', 'excerpt' ],
				enabled: 1,
			} );
			renderCpts();
		}
		if ( t.id === 'cptflm-add-tax' ) {
			e.preventDefault();
			syncFromDom();
			var first = ( settings.post_types[ 0 ] && settings.post_types[ 0 ].slug ) || 'project';
			settings.taxonomies.push( {
				slug: 'custom_category',
				label: 'Categories',
				singular: 'Category',
				post_types: [ first ],
				hierarchical: 1,
				show_in_rest: 1,
				enabled: 1,
			} );
			renderTax();
		}
		if ( t.getAttribute( 'data-remove-cpt' ) != null ) {
			e.preventDefault();
			if ( ! confirm( cptflmAdmin.i18n.confirm ) ) {
				return;
			}
			syncFromDom();
			settings.post_types.splice( parseInt( t.getAttribute( 'data-remove-cpt' ), 10 ), 1 );
			renderCpts();
			renderTax();
		}
		if ( t.getAttribute( 'data-remove-tax' ) != null ) {
			e.preventDefault();
			if ( ! confirm( cptflmAdmin.i18n.confirm ) ) {
				return;
			}
			syncFromDom();
			settings.taxonomies.splice( parseInt( t.getAttribute( 'data-remove-tax' ), 10 ), 1 );
			renderTax();
		}
		if ( t.classList.contains( 'cptflm-copy' ) ) {
			var text = t.getAttribute( 'data-copy' ) || t.textContent;
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( function () {
					toast( 'Copied' );
				} );
			}
		}
	} );

	renderCpts();
	renderTax();
} )();
