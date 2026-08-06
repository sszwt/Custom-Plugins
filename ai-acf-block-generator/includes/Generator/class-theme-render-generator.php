<?php
/**
 * Generates theme-compatible block PHP templates.
 *
 * @package AABG
 */

namespace AABG\Generator;

use AABG\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Theme_Render_Generator
 */
class Theme_Render_Generator {

	/**
	 * Generate theme block PHP template.
	 *
	 * @param array $spec Block specification.
	 * @return string
	 */
	public function generate( $spec ) {
		$slug   = $spec['block_slug'] ?? 'custom-block';
		$title  = $spec['block_name'] ?? ucwords( str_replace( '-', ' ', $slug ) );
		$fields = $spec['fields'] ?? array();
		$layout = $spec['layout'] ?? 'content';
		$comp   = $slug . '-component';

		$php  = "<?php\n";
		$php .= "/**\n";
		$php .= " * {$title} Block.\n";
		$php .= " *\n";
		$php .= " * @package ThemeName\n";
		$php .= " *\n";
		$php .= " * " . ( $spec['purpose'] ?? '' ) . "\n";
		$php .= " * Layout: " . ( $spec['layout_details'] ?? $layout ) . "\n";
		$php .= " */\n\n";
		$php .= "render_acf_block_preview( \$block );\n\n";

		foreach ( $fields as $field ) {
			if ( in_array( $field['type'] ?? '', array( 'accordion', 'tab', 'message' ), true ) ) {
				continue;
			}
			$name = $field['name'] ?? '';
			if ( $name ) {
				$var = Sanitizer::php_var( $name );
				$php .= "\${$var} = get_field( '{$name}' );\n";
			}
		}

		$php .= "\n";
		$php .= $this->build_markup( $spec, $comp, $fields, $layout );

		return $php;
	}

	/**
	 * Build theme markup.
	 *
	 * @param array  $spec   Spec.
	 * @param string $comp   Component class.
	 * @param array  $fields Fields.
	 * @param string $layout Layout.
	 * @return string
	 */
	private function build_markup( $spec, $comp, $fields, $layout ) {
		if ( ! $this->has_output_fields( $fields ) ) {
			return "echo '<!-- {$comp} -->';\n";
		}

		$fields_map = $this->map_fields_by_name( $fields );
		$condition  = $this->build_condition( $fields );
		$out        = "if ( {$condition} ) {\n";
		$out       .= "\techo '<!-- {$comp} -->';\n";
		$out       .= "\techo '<section class=\"{$comp}\">';\n";
		$out       .= "\techo '<div class=\"container\">';\n";

		if ( 'slider' === $layout ) {
			$out .= "\techo '<div class=\"{$comp}__inner\">';\n";
			$out .= $this->slider_markup( $comp, $fields );
			$out .= "\techo '</div>';\n";
		} else {
			$out .= $this->structured_markup( $spec, $comp, $fields_map, $layout );
		}

		$out .= "\techo '</div>';\n";
		$out .= "\techo '</section>';\n";
		$out .= "}\n";

		return $out;
	}

	/**
	 * Build row/column markup using theme grid or BEM structure.
	 *
	 * @param array  $spec       Block spec.
	 * @param string $comp       Component class.
	 * @param array  $fields_map Fields keyed by name.
	 * @param string $layout     Layout type.
	 * @return string
	 */
	private function structured_markup( $spec, $comp, $fields_map, $layout ) {
		$structure   = $spec['layout_structure'] ?? array();
		$rows        = $structure['rows'] ?? array();
		$grid_system = $structure['grid_system'] ?? 'theme';
		$out         = '';

		if ( empty( $rows ) ) {
			return $this->auto_layout_markup( $spec, $comp, $fields_map, $layout, $grid_system );
		}

		foreach ( $rows as $row ) {
			$row_align = ! empty( $row['align'] ) ? ' align-items-' . sanitize_html_class( $row['align'] ) : ' align-items-center';
			$row_extra = ! empty( $row['class'] ) ? ' ' . sanitize_html_class( $row['class'] ) : '';

			if ( 'theme' === $grid_system ) {
				$out .= "\techo '<div class=\"row{$row_align}{$row_extra}\">';\n";
			} else {
				$row_class = ! empty( $row['class'] ) ? sanitize_html_class( $row['class'] ) : 'row';
				$out      .= "\techo '<div class=\"{$comp}__row {$comp}__row--{$row_class}\">';\n";
			}

			foreach ( $row['columns'] ?? array() as $column ) {
				$out .= $this->column_open( $comp, $column, $grid_system );

				foreach ( $column['fields'] ?? array() as $field_name ) {
					if ( isset( $fields_map[ $field_name ] ) ) {
						$out .= $this->field_output( $fields_map[ $field_name ], $comp, 1 );
					}
				}

				$out .= $this->column_close( $grid_system );
			}

			if ( 'theme' === $grid_system ) {
				$out .= "\techo '</div>';\n";
			} else {
				$out .= "\techo '</div>';\n";
			}
		}

		return $out;
	}

	/**
	 * Auto layout when AI omits layout_structure.
	 *
	 * @param array  $spec       Spec.
	 * @param string $comp       Component.
	 * @param array  $fields_map Fields map.
	 * @param string $layout     Layout.
	 * @param string $grid_system Grid system.
	 * @return string
	 */
	private function auto_layout_markup( $spec, $comp, $fields_map, $layout, $grid_system ) {
		$content = array();
		$media   = array();

		foreach ( $fields_map as $field ) {
			$type = $field['type'] ?? 'text';
			$name = $field['name'] ?? '';
			if ( in_array( $type, array( 'accordion', 'tab', 'message', 'true_false' ), true ) ) {
				continue;
			}
			if ( $this->is_media_field( $field ) ) {
				$media[] = $field;
			} else {
				$content[] = $field;
			}
		}

		$two_col = in_array( $layout, array( 'hero', 'two-column' ), true ) && ! empty( $media ) && ! empty( $content );
		$out     = '';

		if ( $two_col && 'theme' === $grid_system ) {
			$out .= "\techo '<div class=\"row align-items-center\">';\n";
			$out .= "\techo '<div class=\"cell-md-6\">';\n";
			$out .= "\techo '<div class=\"{$comp}__content\">';\n";
			foreach ( $content as $field ) {
				$out .= $this->field_output( $field, $comp, 1 );
			}
			$out .= "\techo '</div>';\n";
			$out .= "\techo '</div>';\n";
			$out .= "\techo '<div class=\"cell-md-6\">';\n";
			$out .= "\techo '<div class=\"{$comp}__media\">';\n";
			foreach ( $media as $field ) {
				$out .= $this->field_output( $field, $comp, 1 );
			}
			$out .= "\techo '</div>';\n";
			$out .= "\techo '</div>';\n";
			$out .= "\techo '</div>';\n";
			return $out;
		}

		if ( $two_col ) {
			$out .= "\techo '<div class=\"{$comp}__row {$comp}__row--row\">';\n";
			$out .= "\techo '<div class=\"{$comp}__col {$comp}__col--col-content\" style=\"--col-width: 50%\">';\n";
			$out .= "\techo '<div class=\"{$comp}__content\">';\n";
			foreach ( $content as $field ) {
				$out .= $this->field_output( $field, $comp, 1 );
			}
			$out .= "\techo '</div>';\n";
			$out .= "\techo '</div>';\n";
			$out .= "\techo '<div class=\"{$comp}__col {$comp}__col--col-media\" style=\"--col-width: 50%\">';\n";
			$out .= "\techo '<div class=\"{$comp}__media\">';\n";
			foreach ( $media as $field ) {
				$out .= $this->field_output( $field, $comp, 1 );
			}
			$out .= "\techo '</div>';\n";
			$out .= "\techo '</div>';\n";
			$out .= "\techo '</div>';\n";
			return $out;
		}

		$out .= "\techo '<div class=\"{$comp}__inner\">';\n";
		foreach ( $fields_map as $field ) {
			$out .= $this->field_output( $field, $comp, 1 );
		}
		$out .= "\techo '</div>';\n";

		return $out;
	}

	/**
	 * Open column wrapper.
	 *
	 * @param string $comp        Component.
	 * @param array  $column      Column data.
	 * @param string $grid_system Grid system.
	 * @return string
	 */
	private function column_open( $comp, $column, $grid_system ) {
		if ( 'theme' === $grid_system ) {
			$cell = ! empty( $column['cell_class'] ) ? sanitize_html_class( $column['cell_class'] ) : $this->width_to_cell_class( $column['width'] ?? '100%' );
			return "\techo '<div class=\"{$cell}\">';\n";
		}

		$col_class = ! empty( $column['class'] ) ? sanitize_html_class( $column['class'] ) : 'col';
		$width     = esc_attr( $column['width'] ?? '100%' );
		return "\techo '<div class=\"{$comp}__col {$comp}__col--{$col_class}\" style=\"--col-width: {$width}\">';\n";
	}

	/**
	 * Close column wrapper.
	 *
	 * @param string $grid_system Grid system.
	 * @return string
	 */
	private function column_close( $grid_system ) {
		return "\techo '</div>';\n";
	}

	/**
	 * Convert width percentage to theme cell class.
	 *
	 * @param string $width Width.
	 * @return string
	 */
	private function width_to_cell_class( $width ) {
		$map = array(
			'100%'    => 'cell-12',
			'50%'     => 'cell-md-6',
			'33%'     => 'cell-md-4',
			'33.33%'  => 'cell-md-4',
			'25%'     => 'cell-md-3',
			'66%'     => 'cell-md-8',
			'66.66%'  => 'cell-md-8',
			'75%'     => 'cell-md-9',
		);

		return $map[ $width ] ?? 'cell-md-6';
	}

	/**
	 * Check if field is media type.
	 *
	 * @param array $field Field.
	 * @return bool
	 */
	private function is_media_field( $field ) {
		$type = $field['type'] ?? 'text';
		$name = $field['name'] ?? '';
		return in_array( $type, array( 'image', 'gallery', 'file' ), true )
			|| false !== strpos( $name, 'image' )
			|| false !== strpos( $name, 'background' );
	}

	/**
	 * Index fields by name.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	private function map_fields_by_name( $fields ) {
		$map = array();
		foreach ( $fields as $field ) {
			if ( ! empty( $field['name'] ) ) {
				$map[ $field['name'] ] = $field;
			}
		}
		return $map;
	}

	/**
	 * Build if condition from fields.
	 *
	 * @param array $fields Fields.
	 * @return string
	 */
	private function build_condition( $fields ) {
		$vars = array();
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'] ?? '', array( 'accordion', 'tab', 'message', 'true_false' ), true ) ) {
				continue;
			}
			if ( ! empty( $field['name'] ) ) {
				$vars[] = '$' . Sanitizer::php_var( $field['name'] );
			}
		}
		return ! empty( $vars ) ? implode( ' || ', $vars ) : 'true';
	}

	/**
	 * Check if fields produce output.
	 *
	 * @param array $fields Fields.
	 * @return bool
	 */
	private function has_output_fields( $fields ) {
		foreach ( $fields as $field ) {
			if ( ! in_array( $field['type'] ?? '', array( 'accordion', 'tab', 'message', 'true_false' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Slider markup for theme.
	 *
	 * @param string $comp   Component class.
	 * @param array  $fields Fields.
	 * @return string
	 */
	private function slider_markup( $comp, $fields ) {
		$out = '';

		$autoplay_var = '';
		$delay_var    = '';
		$nav_var      = '';
		$dots_var     = '';

		foreach ( $fields as $field ) {
			$name = $field['name'] ?? '';
			if ( '' === $name ) {
				continue;
			}
			if ( preg_match( '/_autoplay$/', $name ) ) {
				$autoplay_var = Sanitizer::php_var( $name );
			} elseif ( preg_match( '/_delay$/', $name ) ) {
				$delay_var = Sanitizer::php_var( $name );
			} elseif ( preg_match( '/(_show_navigation|_navigation|_show_nav|_arrows)$/', $name ) ) {
				$nav_var = Sanitizer::php_var( $name );
			} elseif ( preg_match( '/(_pagination|_show_dots|_dots)$/', $name ) ) {
				$dots_var = Sanitizer::php_var( $name );
			}
		}

		$out .= "\techo '<div class=\"{$comp}__header\">';\n";
		foreach ( $fields as $field ) {
			$type = $field['type'] ?? '';
			if ( in_array( $type, array( 'repeater', 'true_false', 'number', 'color_picker' ), true ) ) {
				continue;
			}
			$out .= $this->field_output( $field, $comp, 1 );
		}
		$out .= "\techo '</div>';\n";

		foreach ( $fields as $field ) {
			if ( 'repeater' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			$var = Sanitizer::php_var( $field['name'] );
			$out .= "\tif ( \${$var} ) {\n";
			$out .= "\t\t\$slide_count = is_array( \${$var} ) ? count( \${$var} ) : 0;\n";

			$out .= "\t\techo '<div class=\"{$comp}__slider\" data-aabg-slider";
			if ( $autoplay_var ) {
				$out .= " data-autoplay=\"' . ( \${$autoplay_var} ? '1' : '0' ) . '\"";
			} else {
				$out .= " data-autoplay=\"1\"";
			}
			if ( $delay_var ) {
				$out .= " data-delay=\"' . esc_attr( \${$delay_var} ? \${$delay_var} : 5000 ) . '\"";
			} else {
				$out .= " data-delay=\"5000\"";
			}
			if ( $nav_var ) {
				$out .= " data-nav=\"' . ( \${$nav_var} ? '1' : '0' ) . '\"";
			} else {
				$out .= " data-nav=\"1\"";
			}
			if ( $dots_var ) {
				$out .= " data-dots=\"' . ( \${$dots_var} ? '1' : '0' ) . '\"";
			} else {
				$out .= " data-dots=\"1\"";
			}
			$out .= " data-count=\"' . esc_attr( \$slide_count ) . '\">';\n";

			$out .= "\t\techo '<div class=\"{$comp}__track\" data-aabg-track>';\n";
			$out .= "\t\tforeach ( \${$var} as \$slide_index => \$item ) {\n";
			$out .= "\t\t\t\$num = ! empty( \$item['slide_number'] ) ? \$item['slide_number'] : sprintf( '%02d / %02d', (int) \$slide_index + 1, (int) \$slide_count );\n";
			$out .= "\t\t\techo '<div class=\"{$comp}__slide\" data-aabg-slide>';\n";
			$out .= "\t\t\techo '<div class=\"{$comp}__slide-inner\">';\n";

			$media_subs   = array();
			$content_subs = array();
			foreach ( $field['sub_fields'] ?? array() as $sub ) {
				$stype = $sub['type'] ?? '';
				$sname = $sub['name'] ?? '';
				if ( 'slide_number' === $sname ) {
					continue;
				}
				if ( 'image' === $stype || false !== strpos( $sname, 'image' ) ) {
					$media_subs[] = $sub;
				} else {
					$content_subs[] = $sub;
				}
			}

			if ( ! empty( $media_subs ) && ! empty( $content_subs ) ) {
				$out .= "\t\t\techo '<div class=\"{$comp}__slide-media\">';\n";
				foreach ( $media_subs as $sub ) {
					$out .= $this->repeater_sub_output( $sub, $comp, '$item', 4 );
				}
				$out .= "\t\t\techo '</div>';\n";
				$out .= "\t\t\techo '<div class=\"{$comp}__slide-content\">';\n";
				$out .= "\t\t\techo '<p class=\"{$comp}__slide-number\" data-aabg-counter>' . esc_html( \$num ) . '</p>';\n";
				foreach ( $content_subs as $sub ) {
					$out .= $this->repeater_sub_output( $sub, $comp, '$item', 4 );
				}
				$out .= "\t\t\techo '</div>';\n";
			} else {
				$out .= "\t\t\techo '<p class=\"{$comp}__slide-number\" data-aabg-counter>' . esc_html( \$num ) . '</p>';\n";
				foreach ( $field['sub_fields'] ?? array() as $sub ) {
					if ( 'slide_number' === ( $sub['name'] ?? '' ) ) {
						continue;
					}
					$out .= $this->repeater_sub_output( $sub, $comp, '$item', 4 );
				}
			}

			$out .= "\t\t\techo '</div>';\n";
			$out .= "\t\t\techo '</div>';\n";
			$out .= "\t\t}\n";
			$out .= "\t\techo '</div>';\n";

			// Dots — shown/hidden by data-dots via JS/CSS.
			$out .= "\t\techo '<div class=\"{$comp}__dots\" data-aabg-dots role=\"tablist\" aria-label=\"Slide pagination\"></div>';\n";

			// Arrows — shown/hidden by data-nav via JS/CSS.
			$out .= "\t\techo '<div class=\"{$comp}__nav\" data-aabg-nav>';\n";
			$out .= "\t\techo '<button type=\"button\" class=\"{$comp}__arrow {$comp}__arrow--prev\" data-aabg-prev aria-label=\"Previous slide\"></button>';\n";
			$out .= "\t\techo '<button type=\"button\" class=\"{$comp}__arrow {$comp}__arrow--next\" data-aabg-next aria-label=\"Next slide\"></button>';\n";
			$out .= "\t\techo '</div>';\n";

			$out .= "\t\techo '</div>';\n";
			$out .= "\t}\n";
		}

		return $out;
	}

	/**
	 * Field output for theme template.
	 *
	 * @param array  $field  Field.
	 * @param string $comp   Component class.
	 * @param int    $indent Indent.
	 * @return string
	 */
	private function field_output( $field, $comp, $indent = 1 ) {
		$type = $field['type'] ?? 'text';
		$name = $field['name'] ?? '';
		$var  = Sanitizer::php_var( $name );
		$tab  = str_repeat( "\t", $indent );
		$out  = '';

		if ( in_array( $type, array( 'accordion', 'tab', 'message', 'true_false' ), true ) ) {
			return '';
		}

		if ( 'repeater' === $type ) {
			$out .= "{$tab}if ( \${$var} ) {\n";
			$out .= "{$tab}\techo '<div class=\"{$comp}__items\">';\n";
			$out .= "{$tab}\tforeach ( \${$var} as \$item ) {\n";
			$out .= "{$tab}\t\techo '<div class=\"{$comp}__item\">';\n";
			foreach ( $field['sub_fields'] ?? array() as $sub ) {
				$out .= $this->repeater_sub_output( $sub, $comp, '$item', $indent + 2 );
			}
			$out .= "{$tab}\t\techo '</div>';\n";
			$out .= "{$tab}\t}\n";
			$out .= "{$tab}\techo '</div>';\n";
			$out .= "{$tab}}\n";
			return $out;
		}

		$out .= "{$tab}if ( \${$var} ) {\n";

		switch ( $type ) {
			case 'image':
				$out .= "{$tab}\tif ( ! empty( \${$var}['url'] ) ) {\n";
				$out .= "{$tab}\t\techo '<figure class=\"{$comp}__image\">';\n";
				$out .= "{$tab}\t\techo '<img src=\"' . esc_url( \${$var}['url'] ) . '\" alt=\"' . esc_attr( \${$var}['alt'] ?? '' ) . '\" loading=\"lazy\" decoding=\"async\" />';\n";
				$out .= "{$tab}\t\techo '</figure>';\n";
				$out .= "{$tab}\t} elseif ( is_numeric( \${$var} ) ) {\n";
				$out .= "{$tab}\t\techo '<figure class=\"{$comp}__image\">';\n";
				$out .= "{$tab}\t\techo acf_image( \${$var}, 'full', '{$comp}__image-img' );\n";
				$out .= "{$tab}\t\techo '</figure>';\n";
				$out .= "{$tab}\t}\n";
				break;
			case 'link':
				$out .= "{$tab}\techo acf_link( \${$var}, '{$comp}__button btn' );\n";
				break;
			case 'textarea':
				$new_lines = $field['new_lines'] ?? '';
				if ( 'br' === $new_lines ) {
					$out .= "{$tab}\techo '<div class=\"{$comp}__description\">' . nl2br( esc_html( \${$var} ) ) . '</div>';\n";
				} else {
					$out .= "{$tab}\techo '<div class=\"{$comp}__description\">' . wp_kses_post( \${$var} ) . '</div>';\n";
				}
				break;
			case 'wysiwyg':
				$out .= "{$tab}\techo '<div class=\"{$comp}__description bullet-styled\">' . wp_kses_post( \${$var} ) . '</div>';\n";
				break;
			default:
				$class = $this->field_css_class( $name );
				$tag   = $this->field_tag( $name );
				if ( 'heading' === $class ) {
					$out .= "{$tab}\techo '<{$tag} class=\"{$comp}__heading\">' . esc_html( \${$var} ) . '</{$tag}>';\n";
				} else {
					$out .= "{$tab}\techo '<{$tag} class=\"{$comp}__{$class}\">' . esc_html( \${$var} ) . '</{$tag}>';\n";
				}
		}

		$out .= "{$tab}}\n";
		return $out;
	}

	/**
	 * Repeater sub-field output.
	 *
	 * @param array  $sub    Sub field.
	 * @param string $comp   Component.
	 * @param string $array  Array var.
	 * @param int    $indent Indent.
	 * @return string
	 */
	private function repeater_sub_output( $sub, $comp, $array, $indent ) {
		$name = $sub['name'] ?? '';
		$type = $sub['type'] ?? 'text';
		$css  = sanitize_html_class( str_replace( '_', '-', $name ) );
		$tab  = str_repeat( "\t", $indent );
		$out  = '';

		if ( 'repeater' === $type ) {
			$out .= "{$tab}if ( ! empty( {$array}['{$name}'] ) && is_array( {$array}['{$name}'] ) ) {\n";
			$out .= "{$tab}\techo '<ul class=\"{$comp}__{$css}\">';\n";
			$out .= "{$tab}\tforeach ( {$array}['{$name}'] as \$nested ) {\n";
			$out .= "{$tab}\t\techo '<li class=\"{$comp}__{$css}-item\">';\n";
			foreach ( $sub['sub_fields'] ?? array() as $nested_sub ) {
				$nname = $nested_sub['name'] ?? 'point';
				$ntype = $nested_sub['type'] ?? 'text';
				if ( 'image' === $ntype ) {
					continue;
				}
				$out .= "{$tab}\t\tif ( ! empty( \$nested['{$nname}'] ) ) {\n";
				$out .= "{$tab}\t\t\techo '<span class=\"{$comp}__{$css}-text\">' . esc_html( \$nested['{$nname}'] ) . '</span>';\n";
				$out .= "{$tab}\t\t}\n";
			}
			$out .= "{$tab}\t\techo '</li>';\n";
			$out .= "{$tab}\t}\n";
			$out .= "{$tab}\techo '</ul>';\n";
			$out .= "{$tab}}\n";
			return $out;
		}

		if ( 'image' === $type ) {
			$out .= "{$tab}if ( ! empty( {$array}['{$name}']['url'] ) ) {\n";
			$out .= "{$tab}\techo '<figure class=\"{$comp}__{$css}\">';\n";
			$out .= "{$tab}\techo '<img src=\"' . esc_url( {$array}['{$name}']['url'] ) . '\" alt=\"' . esc_attr( {$array}['{$name}']['alt'] ?? '' ) . '\" loading=\"lazy\" decoding=\"async\" />';\n";
			$out .= "{$tab}\techo '</figure>';\n";
			$out .= "{$tab}} elseif ( ! empty( {$array}['{$name}'] ) && is_numeric( {$array}['{$name}'] ) ) {\n";
			$out .= "{$tab}\techo '<figure class=\"{$comp}__{$css}\">';\n";
			$out .= "{$tab}\techo acf_image( {$array}['{$name}'], 'full', '{$comp}__{$css}-img' );\n";
			$out .= "{$tab}\techo '</figure>';\n";
			$out .= "{$tab}}\n";
		} elseif ( 'link' === $type ) {
			$out .= "{$tab}echo acf_link( {$array}['{$name}'] ?? array(), '{$comp}__button btn' );\n";
		} elseif ( in_array( $type, array( 'wysiwyg', 'textarea' ), true ) ) {
			$out .= "{$tab}if ( ! empty( {$array}['{$name}'] ) ) {\n";
			$out .= "{$tab}\techo '<div class=\"{$comp}__{$css}\">' . wp_kses_post( {$array}['{$name}'] ) . '</div>';\n";
			$out .= "{$tab}}\n";
		} else {
			$tag = ( false !== strpos( $name, 'name' ) || false !== strpos( $name, 'title' ) || false !== strpos( $name, 'heading' ) ) ? 'h3' : 'p';
			if ( false !== strpos( $name, 'slide_number' ) || false !== strpos( $name, 'number' ) ) {
				$tag = 'p';
			}
			$out .= "{$tab}if ( ! empty( {$array}['{$name}'] ) ) {\n";
			$out .= "{$tab}\techo '<{$tag} class=\"{$comp}__{$css}\">' . esc_html( {$array}['{$name}'] ) . '</{$tag}>';\n";
			$out .= "{$tab}}\n";
		}

		return $out;
	}

	/**
	 * Get HTML tag for field.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private function field_tag( $name ) {
		if ( false !== strpos( $name, 'heading' ) || ( false !== strpos( $name, 'title' ) && false === strpos( $name, 'subtitle' ) ) ) {
			return 'h2';
		}
		if ( false !== strpos( $name, 'subtitle' ) ) {
			return 'h3';
		}
		return 'p';
	}

	/**
	 * Get CSS class suffix from field name.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	private function field_css_class( $name ) {
		if ( false !== strpos( $name, 'eyebrow' ) ) {
			return 'eyebrow';
		}
		if ( false !== strpos( $name, 'heading' ) ) {
			return 'heading';
		}
		if ( false !== strpos( $name, 'title' ) && false === strpos( $name, 'subtitle' ) ) {
			return 'title';
		}
		if ( false !== strpos( $name, 'subtitle' ) ) {
			return 'subtitle';
		}
		if ( false !== strpos( $name, 'description' ) || false !== strpos( $name, 'content' ) ) {
			return 'description';
		}
		return sanitize_html_class( str_replace( '_', '-', $name ) );
	}
}
