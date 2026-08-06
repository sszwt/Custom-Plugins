<?php
/**
 * Generates render.php template.
 *
 * @package AABG
 */

namespace AABG\Generator;

use AABG\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Render_Template_Generator
 */
class Render_Template_Generator {

	/**
	 * Generate render.php content.
	 *
	 * @param array $spec Block specification.
	 * @return string
	 */
	public function generate( $spec ) {
		$slug    = $spec['block_slug'] ?? 'custom-block';
		$bem     = $spec['bem_block'] ?? $slug;
		$fields  = $spec['fields'] ?? array();
		$layout  = $spec['layout'] ?? 'content';
		$textdomain = 'ai-acf-block-generator';

		$php  = "<?php\n";
		$php .= "/**\n";
		$php .= " * Render template for {$slug} block.\n";
		$php .= " *\n";
		$php .= " * @package AABG_Generated\n";
		$php .= " *\n";
		$php .= " * " . ( $spec['purpose'] ?? 'ACF Block' ) . "\n";
		$php .= " * Layout: " . ( $spec['layout_details'] ?? $layout ) . "\n";
		$php .= " */\n\n";
		$php .= "if ( ! defined( 'ABSPATH' ) ) {\n";
		$php .= "\texit;\n";
		$php .= "}\n\n";
		$php .= "// Block preview image in editor.\n";
		$php .= "if ( ! empty( \$block['data']['is_example'] ) ) {\n";
		$php .= "\t\$preview_path = __DIR__ . '/preview.png';\n";
		$php .= "\tif ( file_exists( \$preview_path ) ) {\n";
		$php .= "\t\t\$preview_url = plugins_url( 'preview.png', __FILE__ );\n";
		$php .= "\t\techo '<img src=\"' . esc_url( \$preview_url ) . '\" alt=\"' . esc_attr__( 'Block preview', '{$textdomain}' ) . '\" style=\"width:100%;height:auto;\" />';\n";
		$php .= "\t}\n";
		$php .= "\treturn;\n";
		$php .= "}\n\n";

		// Field variables.
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'] ?? '', array( 'accordion', 'tab', 'message' ), true ) ) {
				continue;
			}
			$name = $field['name'] ?? '';
			if ( $name ) {
				$var = $this->field_var_name( $name );
				$php .= "\${$var} = get_field( '{$name}' );\n";
			}
		}

		$php .= "\n";
		$php .= $this->generate_markup( $spec, $bem, $fields, $layout );

		return $php;
	}

	/**
	 * Convert field name to PHP variable.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private function field_var_name( $name ) {
		return Sanitizer::php_var( $name );
	}

	/**
	 * Generate HTML markup based on layout.
	 *
	 * @param array  $spec   Spec.
	 * @param string $bem    BEM block name.
	 * @param array  $fields Fields.
	 * @param string $layout Layout type.
	 * @return string
	 */
	private function generate_markup( $spec, $bem, $fields, $layout ) {
		$slug = $spec['block_slug'] ?? 'block';
		$out  = "echo '<section class=\"{$bem}\" id=\"' . esc_attr( '{$slug}-' . ( \$block['id'] ?? '' ) ) . '\">';\n";

		switch ( $layout ) {
			case 'hero':
			case 'two-column':
				$out .= $this->hero_markup( $bem, $fields );
				break;
			case 'slider':
				$out .= $this->slider_markup( $bem, $fields );
				break;
			case 'accordion':
				$out .= $this->accordion_markup( $bem, $fields );
				break;
			default:
				$out .= $this->default_markup( $bem, $fields );
		}

		$out .= "echo '</section>';\n";

		return $out;
	}

	/**
	 * Hero / two-column markup.
	 *
	 * @param string $bem    BEM block.
	 * @param array  $fields Fields.
	 * @return string
	 */
	private function hero_markup( $bem, $fields ) {
		$out  = "\techo '<div class=\"{$bem}__inner\">';\n";
		$out .= "\techo '<div class=\"{$bem}__content\">';\n";

		foreach ( $fields as $field ) {
			$out .= $this->field_output( $field, $bem );
		}

		$out .= "\techo '</div>';\n";
		$out .= "\techo '</div>';\n";

		return $out;
	}

	/**
	 * Slider markup.
	 *
	 * @param string $bem    BEM block.
	 * @param array  $fields Fields.
	 * @return string
	 */
	private function slider_markup( $bem, $fields ) {
		$out  = "\techo '<div class=\"{$bem}__slider swiper\" data-aabg-slider>';\n";
		$out .= "\techo '<div class=\"swiper-wrapper\">';\n";

		foreach ( $fields as $field ) {
			if ( 'repeater' === ( $field['type'] ?? '' ) ) {
				$var  = $this->field_var_name( $field['name'] );
				$out .= "\tif ( \${$var} ) {\n";
				$out .= "\t\tforeach ( \${$var} as \$item ) {\n";
				$out .= "\t\t\techo '<div class=\"swiper-slide {$bem}__slide\">';\n";

				foreach ( $field['sub_fields'] ?? array() as $sub ) {
					if ( 'repeater' === ( $sub['type'] ?? '' ) ) {
						$out .= "\t\t\tif ( ! empty( \$item['{$sub['name']}'] ) ) {\n";
						$out .= "\t\t\t\techo '<div class=\"{$bem}__social\">';\n";
						$out .= "\t\t\t\tforeach ( \$item['{$sub['name']}'] as \$social ) {\n";
						$out .= "\t\t\t\t\tif ( ! empty( \$social['url'] ) ) {\n";
						$out .= "\t\t\t\t\t\techo '<a href=\"' . esc_url( \$social['url'] ) . '\" class=\"{$bem}__social-link\" target=\"_blank\" rel=\"noopener noreferrer\">';\n";
						$out .= "\t\t\t\t\t\techo esc_html( \$social['icon'] ?? '' );\n";
						$out .= "\t\t\t\t\t\techo '</a>';\n";
						$out .= "\t\t\t\t\t}\n";
						$out .= "\t\t\t\t}\n";
						$out .= "\t\t\t\techo '</div>';\n";
						$out .= "\t\t\t}\n";
					} else {
						$out .= $this->repeater_sub_output( $sub, $bem, '$item' );
					}
				}

				$out .= "\t\t\techo '</div>';\n";
				$out .= "\t\t}\n";
				$out .= "\t}\n";
			} else {
				$out .= $this->field_output( $field, $bem, 1 );
			}
		}

		$out .= "\techo '</div>';\n";
		$out .= "\techo '<div class=\"{$bem}__nav swiper-button-prev\" aria-label=\"' . esc_attr__( 'Previous', 'ai-acf-block-generator' ) . '\"></div>';\n";
		$out .= "\techo '<div class=\"{$bem}__nav swiper-button-next\" aria-label=\"' . esc_attr__( 'Next', 'ai-acf-block-generator' ) . '\"></div>';\n";
		$out .= "\techo '</div>';\n";

		return $out;
	}

	/**
	 * Accordion markup.
	 *
	 * @param string $bem    BEM block.
	 * @param array  $fields Fields.
	 * @return string
	 */
	private function accordion_markup( $bem, $fields ) {
		$out  = "\techo '<div class=\"{$bem}__accordion\" data-aabg-accordion>';\n";

		foreach ( $fields as $field ) {
			if ( 'repeater' === ( $field['type'] ?? '' ) ) {
				$var  = $this->field_var_name( $field['name'] );
				$out .= "\tif ( \${$var} ) {\n";
				$out .= "\t\tforeach ( \${$var} as \$index => \$item ) {\n";
				$out .= "\t\t\techo '<div class=\"{$bem}__item\">';\n";
				$out .= "\t\t\techo '<button class=\"{$bem}__trigger\" aria-expanded=\"false\" aria-controls=\"{$bem}-item-' . esc_attr( \$index ) . '\">';\n";
				$out .= "\t\t\techo esc_html( \$item['title'] ?? '' );\n";
				$out .= "\t\t\techo '</button>';\n";
				$out .= "\t\t\techo '<div class=\"{$bem}__panel\" id=\"{$bem}-item-' . esc_attr( \$index ) . '\">';\n";
				$out .= "\t\t\techo wp_kses_post( \$item['content'] ?? '' );\n";
				$out .= "\t\t\techo '</div>';\n";
				$out .= "\t\t\techo '</div>';\n";
				$out .= "\t\t}\n";
				$out .= "\t}\n";
			} else {
				$out .= $this->field_output( $field, $bem, 1 );
			}
		}

		$out .= "\techo '</div>';\n";

		return $out;
	}

	/**
	 * Default content markup.
	 *
	 * @param string $bem    BEM block.
	 * @param array  $fields Fields.
	 * @return string
	 */
	private function default_markup( $bem, $fields ) {
		$out  = "\techo '<div class=\"{$bem}__inner\">';\n";

		foreach ( $fields as $field ) {
			$out .= $this->field_output( $field, $bem, 1 );
		}

		$out .= "\techo '</div>';\n";

		return $out;
	}

	/**
	 * Generate PHP output for a field.
	 *
	 * @param array  $field  Field.
	 * @param string $bem    BEM block.
	 * @param int    $indent Indent level.
	 * @return string
	 */
	private function field_output( $field, $bem, $indent = 1 ) {
		$type = $field['type'] ?? 'text';
		$name = $field['name'] ?? '';
		$var  = $this->field_var_name( $name );
		$tab  = str_repeat( "\t", $indent );
		$out  = '';

		if ( in_array( $type, array( 'accordion', 'tab', 'message', 'true_false' ), true ) ) {
			if ( 'true_false' === $type ) {
				// Used in JS data attributes, skip visible output.
				return '';
			}
			return '';
		}

		if ( 'repeater' === $type ) {
			return $this->repeater_output( $field, $bem, $indent );
		}

		$out .= "{$tab}if ( \${$var} ) {\n";

		switch ( $type ) {
			case 'image':
				$out .= "{$tab}\t\$img = \${$var};\n";
				$out .= "{$tab}\tif ( ! empty( \$img['url'] ) ) {\n";
				$out .= "{$tab}\t\techo '<figure class=\"{$bem}__image\">';\n";
				$out .= "{$tab}\t\techo '<img src=\"' . esc_url( \$img['url'] ) . '\" alt=\"' . esc_attr( \$img['alt'] ?? '' ) . '\" loading=\"lazy\" />';\n";
				$out .= "{$tab}\t\techo '</figure>';\n";
				$out .= "{$tab}\t}\n";
				break;

			case 'link':
				$out .= "{$tab}\t\$link = \${$var};\n";
				$out .= "{$tab}\tif ( ! empty( \$link['url'] ) ) {\n";
				$out .= "{$tab}\t\techo '<a href=\"' . esc_url( \$link['url'] ) . '\" class=\"{$bem}__button\"';\n";
				$out .= "{$tab}\t\tif ( ! empty( \$link['target'] ) ) { echo ' target=\"' . esc_attr( \$link['target'] ) . '\" rel=\"noopener noreferrer\"'; }\n";
				$out .= "{$tab}\t\techo '>' . esc_html( \$link['title'] ?? '' ) . '</a>';\n";
				$out .= "{$tab}\t}\n";
				break;

			case 'textarea':
			case 'wysiwyg':
				$out .= "{$tab}\techo '<div class=\"{$bem}__" . sanitize_html_class( str_replace( '_', '-', $name ) ) . "\">';\n";
				$out .= "{$tab}\techo wp_kses_post( \${$var} );\n";
				$out .= "{$tab}\techo '</div>';\n";
				break;

			case 'gallery':
				$out .= "{$tab}\techo '<div class=\"{$bem}__gallery\">';\n";
				$out .= "{$tab}\tforeach ( \${$var} as \$gallery_img ) {\n";
				$out .= "{$tab}\t\tif ( ! empty( \$gallery_img['url'] ) ) {\n";
				$out .= "{$tab}\t\t\techo '<img src=\"' . esc_url( \$gallery_img['url'] ) . '\" alt=\"' . esc_attr( \$gallery_img['alt'] ?? '' ) . '\" loading=\"lazy\" />';\n";
				$out .= "{$tab}\t\t}\n";
				$out .= "{$tab}\t}\n";
				$out .= "{$tab}\techo '</div>';\n";
				break;

			case 'file':
				$out .= "{$tab}\tif ( ! empty( \${$var}['url'] ) ) {\n";
				$out .= "{$tab}\t\techo '<video class=\"{$bem}__video\" autoplay muted loop playsinline>';\n";
				$out .= "{$tab}\t\techo '<source src=\"' . esc_url( \${$var}['url'] ) . '\" type=\"' . esc_attr( \${$var}['mime_type'] ?? 'video/mp4' ) . '\">';\n";
				$out .= "{$tab}\t\techo '</video>';\n";
				$out .= "{$tab}\t}\n";
				break;

			default:
				$class = sanitize_html_class( str_replace( '_', '-', $name ) );
				$tag   = ( false !== strpos( $name, 'title' ) && false === strpos( $name, 'subtitle' ) ) ? 'h2' : 'p';
				if ( false !== strpos( $name, 'subtitle' ) ) {
					$tag = 'h3';
				}
				$out .= "{$tab}\techo '<{$tag} class=\"{$bem}__{$class}\">' . esc_html( \${$var} ) . '</{$tag}>';\n";
		}

		$out .= "{$tab}}\n";

		return $out;
	}

	/**
	 * Repeater output.
	 *
	 * @param array  $field  Field.
	 * @param string $bem    BEM.
	 * @param int    $indent Indent.
	 * @return string
	 */
	private function repeater_output( $field, $bem, $indent ) {
		$var  = $this->field_var_name( $field['name'] );
		$tab  = str_repeat( "\t", $indent );
		$out  = "{$tab}if ( \${$var} ) {\n";
		$out .= "{$tab}\techo '<div class=\"{$bem}__items\">';\n";
		$out .= "{$tab}\tforeach ( \${$var} as \$item ) {\n";
		$out .= "{$tab}\t\techo '<div class=\"{$bem}__item\">';\n";

		foreach ( $field['sub_fields'] ?? array() as $sub ) {
			$out .= $this->repeater_sub_output( $sub, $bem, '$item' );
		}

		$out .= "{$tab}\t\techo '</div>';\n";
		$out .= "{$tab}\t}\n";
		$out .= "{$tab}\techo '</div>';\n";
		$out .= "{$tab}}\n";

		return $out;
	}

	/**
	 * Repeater sub-field output.
	 *
	 * @param array  $sub   Sub field.
	 * @param string $bem   BEM.
	 * @param string $array Array variable.
	 * @return string
	 */
	private function repeater_sub_output( $sub, $bem, $array ) {
		$name = $sub['name'] ?? '';
		$type = $sub['type'] ?? 'text';
		$out  = '';

		switch ( $type ) {
			case 'image':
				$out .= "\t\t\tif ( ! empty( {$array}['{$name}']['url'] ) ) {\n";
				$out .= "\t\t\t\techo '<img class=\"{$bem}__{$name}\" src=\"' . esc_url( {$array}['{$name}']['url'] ) . '\" alt=\"' . esc_attr( {$array}['{$name}']['alt'] ?? '' ) . '\" loading=\"lazy\" />';\n";
				$out .= "\t\t\t}\n";
				break;
			default:
				$tag = ( 'name' === $name || 'title' === $name ) ? 'h3' : 'p';
				$out .= "\t\t\tif ( ! empty( {$array}['{$name}'] ) ) {\n";
				$out .= "\t\t\t\techo '<{$tag} class=\"{$bem}__{$name}\">' . esc_html( {$array}['{$name}'] ) . '</{$tag}>';\n";
				$out .= "\t\t\t}\n";
		}

		return $out;
	}
}
