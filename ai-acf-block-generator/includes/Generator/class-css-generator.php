<?php
/**
 * Generates responsive BEM CSS.
 *
 * @package AABG
 */

namespace AABG\Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CSS_Generator
 */
class CSS_Generator {

	/**
	 * Generate style.css.
	 *
	 * @param array $spec Block specification.
	 * @return string
	 */
	public function generate_style( $spec ) {
		$bem    = $spec['bem_block'] ?? ( $spec['block_slug'] ?? 'aabg-block' );
		$layout = $spec['layout'] ?? 'content';

		$css  = "/**\n";
		$css .= " * Frontend styles for {$bem}\n";
		$css .= " * BEM naming | Mobile-first | Minimal CSS\n";
		$css .= " */\n\n";

		$css .= ".{$bem} {\n";
		$css .= "\tposition: relative;\n";
		$css .= "\twidth: 100%;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__inner {\n";
		$css .= "\tdisplay: flex;\n";
		$css .= "\tflex-direction: column;\n";
		$css .= "\tgap: 1.5rem;\n";
		$css .= "\tmax-width: 1200px;\n";
		$css .= "\tmargin: 0 auto;\n";
		$css .= "\tpadding: 2rem 1rem;\n";
		$css .= "}\n\n";

		if ( in_array( $layout, array( 'hero', 'two-column' ), true ) ) {
			$css .= $this->two_column_css( $bem );
		}

		if ( 'slider' === $layout ) {
			$css .= $this->slider_css( $bem );
		}

		if ( 'accordion' === $layout ) {
			$css .= $this->accordion_css( $bem );
		}

		$css .= $this->common_css( $bem );

		return $css;
	}

	/**
	 * Generate editor.css.
	 *
	 * @param array $spec Block specification.
	 * @return string
	 */
	public function generate_editor( $spec ) {
		$bem = $spec['bem_block'] ?? ( $spec['block_slug'] ?? 'aabg-block' );

		$css  = "/**\n";
		$css .= " * Editor styles for {$bem}\n";
		$css .= " */\n\n";
		$css .= ".editor-styles-wrapper .{$bem} {\n";
		$css .= "\tborder: 1px dashed #ccc;\n";
		$css .= "\tpadding: 1rem;\n";
		$css .= "}\n";

		return $css;
	}

	/**
	 * Two-column layout CSS.
	 *
	 * @param string $bem BEM block.
	 * @return string
	 */
	private function two_column_css( $bem ) {
		$css  = "@media (min-width: 768px) {\n";
		$css .= "\t.{$bem}__inner {\n";
		$css .= "\t\tflex-direction: row;\n";
		$css .= "\t\talign-items: center;\n";
		$css .= "\t}\n\n";
		$css .= "\t.{$bem}__content {\n";
		$css .= "\t\tflex: 1;\n";
		$css .= "\t}\n\n";
		$css .= "\t.{$bem}__image {\n";
		$css .= "\t\tflex: 1;\n";
		$css .= "\t}\n";
		$css .= "}\n\n";

		return $css;
	}

	/**
	 * Slider CSS.
	 *
	 * @param string $bem BEM block.
	 * @return string
	 */
	private function slider_css( $bem ) {
		$css  = ".{$bem}__slider {\n";
		$css .= "\toverflow: hidden;\n";
		$css .= "\tposition: relative;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__slide {\n";
		$css .= "\ttext-align: center;\n";
		$css .= "\tpadding: 1rem;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__slide img {\n";
		$css .= "\twidth: 100%;\n";
		$css .= "\theight: auto;\n";
		$css .= "\tborder-radius: 8px;\n";
		$css .= "\tobject-fit: cover;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__social {\n";
		$css .= "\tdisplay: flex;\n";
		$css .= "\tgap: 0.5rem;\n";
		$css .= "\tjustify-content: center;\n";
		$css .= "\tmargin-top: 0.75rem;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__social-link {\n";
		$css .= "\tcolor: inherit;\n";
		$css .= "\ttext-decoration: none;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__nav {\n";
		$css .= "\tcolor: currentColor;\n";
		$css .= "}\n\n";

		return $css;
	}

	/**
	 * Accordion CSS.
	 *
	 * @param string $bem BEM block.
	 * @return string
	 */
	private function accordion_css( $bem ) {
		$css  = ".{$bem}__item {\n";
		$css .= "\tborder-bottom: 1px solid #e0e0e0;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__trigger {\n";
		$css .= "\twidth: 100%;\n";
		$css .= "\ttext-align: left;\n";
		$css .= "\tpadding: 1rem 0;\n";
		$css .= "\tbackground: none;\n";
		$css .= "\tborder: none;\n";
		$css .= "\tcursor: pointer;\n";
		$css .= "\tfont-size: 1rem;\n";
		$css .= "\tfont-weight: 600;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__panel {\n";
		$css .= "\tdisplay: none;\n";
		$css .= "\tpadding-bottom: 1rem;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__panel.is-open {\n";
		$css .= "\tdisplay: block;\n";
		$css .= "}\n\n";

		return $css;
	}

	/**
	 * Common element CSS.
	 *
	 * @param string $bem BEM block.
	 * @return string
	 */
	private function common_css( $bem ) {
		$css  = ".{$bem}__image img {\n";
		$css .= "\twidth: 100%;\n";
		$css .= "\theight: auto;\n";
		$css .= "\tdisplay: block;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__button {\n";
		$css .= "\tdisplay: inline-block;\n";
		$css .= "\tpadding: 0.75rem 1.5rem;\n";
		$css .= "\tbackground: #2271b1;\n";
		$css .= "\tcolor: #fff;\n";
		$css .= "\ttext-decoration: none;\n";
		$css .= "\tborder-radius: 4px;\n";
		$css .= "\tfont-weight: 600;\n";
		$css .= "\ttransition: background 0.2s ease;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__button:hover,\n";
		$css .= ".{$bem}__button:focus {\n";
		$css .= "\tbackground: #135e96;\n";
		$css .= "\tcolor: #fff;\n";
		$css .= "}\n\n";

		$css .= ".{$bem} h2,\n";
		$css .= ".{$bem} h3 {\n";
		$css .= "\tmargin: 0 0 0.5rem;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__gallery {\n";
		$css .= "\tdisplay: grid;\n";
		$css .= "\tgrid-template-columns: repeat(auto-fill, minmax(150px, 1fr));\n";
		$css .= "\tgap: 1rem;\n";
		$css .= "}\n\n";

		$css .= ".{$bem}__video {\n";
		$css .= "\twidth: 100%;\n";
		$css .= "\theight: auto;\n";
		$css .= "}\n";

		return $css;
	}
}
