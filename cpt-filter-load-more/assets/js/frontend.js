/**
 * Frontend filter + load more
 */
( function () {
	'use strict';

	function qs( root, sel ) {
		return root.querySelector( sel );
	}

	function qsa( root, sel ) {
		return Array.prototype.slice.call( root.querySelectorAll( sel ) );
	}

	function init( root ) {
		if ( root.dataset.ready ) {
			return;
		}
		root.dataset.ready = '1';

		var grid = qs( root, '[data-cptflm-grid]' );
		var more = qs( root, '[data-cptflm-more]' );
		var empty = qs( root, '[data-cptflm-empty]' );
		var chips = qsa( root, '.cptflm__chip' );
		var state = {
			term: 'all',
			page: 1,
			loading: false,
		};

		function cfg() {
			return {
				post_type: root.dataset.postType || 'project',
				taxonomy: root.dataset.taxonomy || '',
				per_page: root.dataset.perPage || 6,
				orderby: root.dataset.orderby || 'date',
				order: root.dataset.order || 'DESC',
				show_image: root.dataset.showImage === '1' ? 1 : 0,
				show_excerpt: root.dataset.showExcerpt === '1' ? 1 : 0,
			};
		}

		function setLoading( on ) {
			state.loading = on;
			root.classList.toggle( 'is-loading', on );
			if ( more ) {
				more.disabled = on;
				more.textContent = on
					? ( window.cptflmFront && cptflmFront.i18n.loading ) || 'Loading…'
					: more.dataset.label || more.textContent;
			}
		}

		if ( more && ! more.dataset.label ) {
			more.dataset.label = more.textContent;
		}

		function request( page, replace ) {
			if ( state.loading || typeof cptflmFront === 'undefined' ) {
				return;
			}
			setLoading( true );

			var body = new FormData();
			var c = cfg();
			body.append( 'action', 'cptflm_load_posts' );
			body.append( 'nonce', cptflmFront.nonce );
			body.append( 'post_type', c.post_type );
			body.append( 'taxonomy', c.taxonomy );
			body.append( 'term', state.term );
			body.append( 'page', String( page ) );
			body.append( 'per_page', String( c.per_page ) );
			body.append( 'orderby', c.orderby );
			body.append( 'order', c.order );
			body.append( 'show_image', String( c.show_image ) );
			body.append( 'show_excerpt', String( c.show_excerpt ) );

			fetch( cptflmFront.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( ! json || ! json.success ) {
						throw new Error( 'fail' );
					}
					var data = json.data || {};
					state.page = data.page || page;
					root.dataset.page = String( state.page );
					root.dataset.max = String( data.max_pages || 1 );

					if ( replace ) {
						grid.innerHTML = data.html || '';
					} else {
						grid.insertAdjacentHTML( 'beforeend', data.html || '' );
					}

					var hasItems = !!( data.items && data.items.length ) || ( grid.children && grid.children.length );
					if ( empty ) {
						empty.hidden = hasItems;
					}
					if ( more ) {
						more.hidden = ! data.has_more;
					}
				} )
				.catch( function () {
					alert( ( cptflmFront.i18n && cptflmFront.i18n.error ) || 'Error' );
				} )
				.finally( function () {
					setLoading( false );
					if ( more ) {
						more.textContent = more.dataset.label;
					}
				} );
		}

		chips.forEach( function ( chip ) {
			chip.addEventListener( 'click', function () {
				chips.forEach( function ( c ) { c.classList.remove( 'is-active' ); } );
				chip.classList.add( 'is-active' );
				state.term = chip.getAttribute( 'data-term' ) || 'all';
				state.page = 1;
				request( 1, true );
			} );
		} );

		if ( more ) {
			more.addEventListener( 'click', function () {
				var next = state.page + 1;
				var max = parseInt( root.dataset.max || '1', 10 );
				if ( next > max ) {
					return;
				}
				request( next, false );
			} );
		}
	}

	function boot() {
		qsa( document, '[data-cptflm]' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
