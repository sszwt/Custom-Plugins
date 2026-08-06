<?php
/**
 * Generates JavaScript when interactive features are needed.
 *
 * @package AABG
 */

namespace AABG\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class JS_Generator
 */
class JS_Generator {

	/**
	 * Generate frontend script.js.
	 *
	 * @param array $spec Block specification.
	 * @return string|false False if no JS needed.
	 */
	public function generate_script( $spec ) {
		if ( empty( $spec['needs_javascript'] ) ) {
			return false;
		}

		$type = $spec['javascript_type'] ?? 'none';
		$bem  = $spec['bem_block'] ?? ( $spec['block_slug'] ?? 'aabg-block' );
		$slug = $spec['block_slug'] ?? 'block';

		switch ( $type ) {
			case 'slider':
				return $this->slider_js( $bem, $slug, $spec );
			case 'accordion':
				return $this->accordion_js( $bem );
			case 'tabs':
				return $this->tabs_js( $bem );
			case 'counter':
				return $this->counter_js( $bem );
			default:
				return $this->generic_js( $bem, $type );
		}
	}

	/**
	 * Generate editor.js.
	 *
	 * @param array $spec Block specification.
	 * @return string
	 */
	public function generate_editor_script( $spec ) {
		$slug = $spec['block_slug'] ?? 'block';

		$js  = "/**\n";
		$js .= " * Editor script for {$slug}\n";
		$js .= " */\n";
		$js .= "( function () {\n";
		$js .= "\t'use strict';\n";
		$js .= "\t// Editor-specific enhancements can be added here.\n";
		$js .= "} )();\n";

		return $js;
	}

	/**
	 * Slider JavaScript.
	 *
	 * @param string $bem  BEM block.
	 * @param string $slug Block slug.
	 * @param array  $spec Spec.
	 * @return string
	 */
	private function slider_js( $bem, $slug, $spec ) {
		$js  = "/**\n";
		$js .= " * Horizontal slider for {$slug}.\n";
		$js .= " * Controls: data-autoplay, data-nav (arrows), data-dots.\n";
		$js .= " */\n";
		$js .= "( function () {\n";
		$js .= "\t'use strict';\n\n";
		$js .= "\tfunction pad( n ) { return ( n < 10 ? '0' : '' ) + n; }\n\n";
		$js .= "\tfunction initSlider( root ) {\n";
		$js .= "\t\tif ( root.dataset.aabgReady ) { return; }\n";
		$js .= "\t\troot.dataset.aabgReady = '1';\n\n";
		$js .= "\t\tvar track  = root.querySelector( '[data-aabg-track]' ) || root.querySelector( '.{$bem}__track' ) || root.querySelector( '.swiper-wrapper' );\n";
		$js .= "\t\tvar slides = root.querySelectorAll( '[data-aabg-slide]' );\n";
		$js .= "\t\tif ( ! slides.length ) { slides = root.querySelectorAll( '.{$bem}__slide, .swiper-slide' ); }\n";
		$js .= "\t\tif ( ! track || ! slides.length ) { return; }\n\n";
		$js .= "\t\tvar count    = slides.length;\n";
		$js .= "\t\tvar single   = count <= 1;\n";
		$js .= "\t\tvar autoplay = root.getAttribute( 'data-autoplay' ) === '1' && ! single;\n";
		$js .= "\t\tvar showNav  = root.getAttribute( 'data-nav' ) !== '0' && ! single;\n";
		$js .= "\t\tvar showDots = root.getAttribute( 'data-dots' ) !== '0' && ! single;\n";
		$js .= "\t\tvar delay    = parseInt( root.getAttribute( 'data-delay' ) || '5000', 10 ) || 5000;\n";
		$js .= "\t\tvar navEl    = root.querySelector( '[data-aabg-nav]' ) || root.querySelector( '.{$bem}__nav' );\n";
		$js .= "\t\tvar dotsEl   = root.querySelector( '[data-aabg-dots]' ) || root.querySelector( '.{$bem}__dots' );\n";
		$js .= "\t\tvar prevBtn  = root.querySelector( '[data-aabg-prev]' ) || root.querySelector( '.{$bem}__arrow--prev, .swiper-button-prev' );\n";
		$js .= "\t\tvar nextBtn  = root.querySelector( '[data-aabg-next]' ) || root.querySelector( '.{$bem}__arrow--next, .swiper-button-next' );\n\n";
		$js .= "\t\tif ( single ) { root.classList.add( 'is-single' ); }\n";
		$js .= "\t\tif ( ! showNav && navEl ) { navEl.hidden = true; navEl.setAttribute( 'aria-hidden', 'true' ); }\n";
		$js .= "\t\tif ( ! showDots && dotsEl ) { dotsEl.hidden = true; dotsEl.setAttribute( 'aria-hidden', 'true' ); }\n";
		$js .= "\t\tif ( ! autoplay ) { root.classList.add( 'is-autoplay-off' ); }\n\n";
		$js .= "\t\tvar index = 0;\n";
		$js .= "\t\tvar timer = null;\n";
		$js .= "\t\tvar paused = false;\n\n";
		$js .= "\t\t// Build dots.\n";
		$js .= "\t\tvar dots = [];\n";
		$js .= "\t\tif ( showDots && dotsEl ) {\n";
		$js .= "\t\t\tdotsEl.innerHTML = '';\n";
		$js .= "\t\t\tfor ( var d = 0; d < count; d++ ) {\n";
		$js .= "\t\t\t\t( function ( i ) {\n";
		$js .= "\t\t\t\t\tvar btn = document.createElement( 'button' );\n";
		$js .= "\t\t\t\t\tbtn.type = 'button';\n";
		$js .= "\t\t\t\t\tbtn.className = '{$bem}__dot';\n";
		$js .= "\t\t\t\t\tbtn.setAttribute( 'aria-label', 'Go to slide ' + ( i + 1 ) );\n";
		$js .= "\t\t\t\t\tbtn.addEventListener( 'click', function () { goTo( i ); } );\n";
		$js .= "\t\t\t\t\tdotsEl.appendChild( btn );\n";
		$js .= "\t\t\t\t\tdots.push( btn );\n";
		$js .= "\t\t\t\t} )( d );\n";
		$js .= "\t\t\t}\n";
		$js .= "\t\t}\n\n";
		$js .= "\t\tfunction updateCounter() {\n";
		$js .= "\t\t\tvar label = pad( index + 1 ) + ' / ' + pad( count );\n";
		$js .= "\t\t\tvar counters = slides[ index ].querySelectorAll( '[data-aabg-counter]' );\n";
		$js .= "\t\t\tArray.prototype.forEach.call( counters, function ( el ) { el.textContent = label; } );\n";
		$js .= "\t\t}\n\n";
		$js .= "\t\tfunction apply() {\n";
		$js .= "\t\t\ttrack.style.transform = 'translate3d(' + ( -index * 100 ) + '%, 0, 0)';\n";
		$js .= "\t\t\tArray.prototype.forEach.call( slides, function ( slide, i ) {\n";
		$js .= "\t\t\t\tslide.classList.toggle( 'is-active', i === index );\n";
		$js .= "\t\t\t\tslide.setAttribute( 'aria-hidden', i === index ? 'false' : 'true' );\n";
		$js .= "\t\t\t} );\n";
		$js .= "\t\t\tdots.forEach( function ( dot, i ) { dot.classList.toggle( 'is-active', i === index ); } );\n";
		$js .= "\t\t\tupdateCounter();\n";
		$js .= "\t\t}\n\n";
		$js .= "\t\tfunction stop() {\n";
		$js .= "\t\t\tif ( timer ) { window.clearInterval( timer ); timer = null; }\n";
		$js .= "\t\t}\n\n";
		$js .= "\t\tfunction start() {\n";
		$js .= "\t\t\tstop();\n";
		$js .= "\t\t\tif ( ! autoplay || paused || single ) { return; }\n";
		$js .= "\t\t\ttimer = window.setInterval( function () { goTo( index + 1 ); }, delay );\n";
		$js .= "\t\t}\n\n";
		$js .= "\t\tfunction goTo( i ) {\n";
		$js .= "\t\t\tindex = ( ( i % count ) + count ) % count;\n";
		$js .= "\t\t\tapply();\n";
		$js .= "\t\t\tif ( autoplay ) { start(); }\n";
		$js .= "\t\t}\n\n";
		$js .= "\t\tapply();\n\n";
		$js .= "\t\tif ( showNav ) {\n";
		$js .= "\t\t\tif ( nextBtn ) { nextBtn.addEventListener( 'click', function () { goTo( index + 1 ); } ); }\n";
		$js .= "\t\t\tif ( prevBtn ) { prevBtn.addEventListener( 'click', function () { goTo( index - 1 ); } ); }\n";
		$js .= "\t\t}\n\n";
		$js .= "\t\troot.addEventListener( 'mouseenter', function () { paused = true; stop(); } );\n";
		$js .= "\t\troot.addEventListener( 'mouseleave', function () { paused = false; start(); } );\n\n";
		$js .= "\t\tvar startX = null;\n";
		$js .= "\t\ttrack.addEventListener( 'touchstart', function ( e ) { startX = e.touches[0].clientX; paused = true; stop(); }, { passive: true } );\n";
		$js .= "\t\ttrack.addEventListener( 'touchend', function ( e ) {\n";
		$js .= "\t\t\tif ( startX === null ) { return; }\n";
		$js .= "\t\t\tvar dx = e.changedTouches[0].clientX - startX;\n";
		$js .= "\t\t\tif ( Math.abs( dx ) > 40 ) { goTo( index + ( dx < 0 ? 1 : -1 ) ); }\n";
		$js .= "\t\t\tstartX = null;\n";
		$js .= "\t\t\tpaused = false;\n";
		$js .= "\t\t\tstart();\n";
		$js .= "\t\t}, { passive: true } );\n\n";
		$js .= "\t\tstart();\n";
		$js .= "\t}\n\n";
		$js .= "\tfunction initAll() {\n";
		$js .= "\t\tvar list = document.querySelectorAll( '[data-aabg-slider]' );\n";
		$js .= "\t\tArray.prototype.forEach.call( list, initSlider );\n";
		$js .= "\t}\n\n";
		$js .= "\tif ( document.readyState === 'loading' ) {\n";
		$js .= "\t\tdocument.addEventListener( 'DOMContentLoaded', initAll );\n";
		$js .= "\t} else {\n";
		$js .= "\t\tinitAll();\n";
		$js .= "\t}\n";
		$js .= "} )();\n";

		return $js;
	}

	/**
	 * Accordion JavaScript.
	 *
	 * @param string $bem BEM block.
	 * @return string
	 */
	private function accordion_js( $bem ) {
		$js  = "/**\n * Accordion for {$bem}\n */\n";
		$js .= "( function () {\n\t'use strict';\n\n";
		$js .= "\tdocument.querySelectorAll( '.{$bem}__accordion[data-aabg-accordion]' ).forEach( function ( accordion ) {\n";
		$js .= "\t\taccordion.querySelectorAll( '.{$bem}__trigger' ).forEach( function ( trigger ) {\n";
		$js .= "\t\t\ttrigger.addEventListener( 'click', function () {\n";
		$js .= "\t\t\t\tvar panel = trigger.nextElementSibling;\n";
		$js .= "\t\t\t\tvar isOpen = panel.classList.contains( 'is-open' );\n";
		$js .= "\t\t\t\taccordion.querySelectorAll( '.{$bem}__panel' ).forEach( function ( p ) { p.classList.remove( 'is-open' ); } );\n";
		$js .= "\t\t\t\taccordion.querySelectorAll( '.{$bem}__trigger' ).forEach( function ( t ) { t.setAttribute( 'aria-expanded', 'false' ); } );\n";
		$js .= "\t\t\t\tif ( ! isOpen ) {\n";
		$js .= "\t\t\t\t\tpanel.classList.add( 'is-open' );\n";
		$js .= "\t\t\t\t\ttrigger.setAttribute( 'aria-expanded', 'true' );\n";
		$js .= "\t\t\t\t}\n";
		$js .= "\t\t\t} );\n";
		$js .= "\t\t} );\n";
		$js .= "\t} );\n";
		$js .= "} )();\n";

		return $js;
	}

	/**
	 * Tabs JavaScript.
	 *
	 * @param string $bem BEM block.
	 * @return string
	 */
	private function tabs_js( $bem ) {
		$js  = "/**\n * Tabs for {$bem}\n */\n";
		$js .= "( function () {\n\t'use strict';\n";
		$js .= "\tdocument.querySelectorAll( '.{$bem}__tabs [data-tab]' ).forEach( function ( tab ) {\n";
		$js .= "\t\ttab.addEventListener( 'click', function ( e ) {\n";
		$js .= "\t\t\te.preventDefault();\n";
		$js .= "\t\t\tvar target = tab.getAttribute( 'data-tab' );\n";
		$js .= "\t\t\ttab.closest( '.{$bem}' ).querySelectorAll( '.{$bem}__panel' ).forEach( function ( p ) { p.hidden = true; } );\n";
		$js .= "\t\t\ttab.closest( '.{$bem}' ).querySelector( '#' + target ).hidden = false;\n";
		$js .= "\t\t} );\n";
		$js .= "\t} );\n";
		$js .= "} )();\n";

		return $js;
	}

	/**
	 * Counter JavaScript.
	 *
	 * @param string $bem BEM block.
	 * @return string
	 */
	private function counter_js( $bem ) {
		$js  = "/**\n * Counter animation for {$bem}\n */\n";
		$js .= "( function () {\n\t'use strict';\n";
		$js .= "\tvar counters = document.querySelectorAll( '.{$bem}__counter' );\n";
		$js .= "\tcounters.forEach( function ( el ) {\n";
		$js .= "\t\tvar target = parseInt( el.getAttribute( 'data-count' ), 10 );\n";
		$js .= "\t\tvar current = 0;\n";
		$js .= "\t\tvar step = Math.ceil( target / 60 );\n";
		$js .= "\t\tvar timer = setInterval( function () {\n";
		$js .= "\t\t\tcurrent += step;\n";
		$js .= "\t\t\tif ( current >= target ) { current = target; clearInterval( timer ); }\n";
		$js .= "\t\t\tel.textContent = current;\n";
		$js .= "\t\t}, 16 );\n";
		$js .= "\t} );\n";
		$js .= "} )();\n";

		return $js;
	}

	/**
	 * Generic JS placeholder.
	 *
	 * @param string $bem  BEM.
	 * @param string $type Type.
	 * @return string
	 */
	private function generic_js( $bem, $type ) {
		$js  = "/**\n * {$type} functionality for {$bem}\n */\n";
		$js .= "( function () {\n\t'use strict';\n";
		$js .= "\t// {$type} initialization\n";
		$js .= "} )();\n";

		return $js;
	}

	/**
	 * Get field prefix from fields array.
	 *
	 * @param array $fields Fields.
	 * @return string
	 */
	private function get_field_prefix( $fields ) {
		if ( empty( $fields[0]['name'] ) ) {
			return '';
		}
		$parts = explode( '_', $fields[0]['name'] );
		return $parts[0] ?? '';
	}
}
