<?php
/**
 * Rule-based prompt analyzer (fallback when no AI API).
 *
 * @package AABG
 */

namespace AABG\AI;

use AABG\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rule_Based_Analyzer
 */
class Rule_Based_Analyzer {

	use AI_Spec_Helper;

	/**
	 * Field type keyword mappings.
	 *
	 * @var array
	 */
	private $type_map = array(
		'background image' => 'image',
		'bg image'         => 'image',
		'image'            => 'image',
		'photo'            => 'image',
		'gallery'          => 'gallery',
		'title'            => 'text',
		'heading'          => 'text',
		'subtitle'         => 'text',
		'sub title'        => 'text',
		'description'      => 'textarea',
		'content'          => 'wysiwyg',
		'button'           => 'link',
		'cta'              => 'link',
		'link'             => 'link',
		'url'              => 'url',
		'email'            => 'email',
		'number'           => 'number',
		'color'            => 'color_picker',
		'date'             => 'date_picker',
		'video'            => 'file',
		'file'             => 'file',
		'autoplay'         => 'true_false',
		'navigation'       => 'true_false',
		'slider'           => 'true_false',
		'accordion'        => 'true_false',
		'tabs'             => 'true_false',
		'counter'          => 'number',
		'popup'            => 'true_false',
		'animation'        => 'true_false',
		'name'             => 'text',
		'designation'      => 'text',
		'role'             => 'text',
		'position'         => 'text',
		'icon'             => 'text',
		'social'           => 'repeater',
		'testimonial'      => 'repeater',
		'team'             => 'repeater',
		'items'            => 'repeater',
		'cards'            => 'repeater',
		'slides'           => 'repeater',
	);

	/**
	 * Analyze prompt using rules.
	 *
	 * @param string $prompt       User prompt.
	 * @param array  $block_config Block configuration.
	 * @return array
	 */
	public function analyze( $prompt, $block_config ) {
		$slug     = $block_config['slug'] ?? 'custom-block';
		$prefix   = $this->get_prefix( $slug );
		$lines    = $this->parse_lines( $prompt );
		$fields   = array();
		$layout   = $this->detect_layout( $prompt );
		$needs_js = false;
		$js_type  = 'none';

		$seen = array();

		foreach ( $lines as $line ) {
			$line_lower = strtolower( trim( $line ) );

			if ( empty( $line_lower ) ) {
				continue;
			}

			$field = $this->match_field( $line_lower, $prefix, $seen );

			if ( $field ) {
				$fields[] = $field;
				$seen[]   = $field['name'];
			}
		}

		// Add common fields if prompt mentions them but wasn't parsed line by line.
		$fields = $this->ensure_common_fields( $prompt, $prefix, $fields, $seen );

		// Detect JS needs.
		$js_keywords = array(
			'slider'    => 'slider',
			'carousel'  => 'slider',
			'autoplay'  => 'slider',
			'accordion' => 'accordion',
			'tabs'      => 'tabs',
			'counter'   => 'counter',
			'popup'     => 'popup',
			'modal'     => 'popup',
			'video'     => 'video',
			'animation' => 'animation',
			'marquee'   => 'slider',
		);

		foreach ( $js_keywords as $keyword => $type ) {
			if ( false !== stripos( $prompt, $keyword ) ) {
				$needs_js = true;
				$js_type  = $type;
				break;
			}
		}

		// Testimonial slider / card pattern (highest priority — replaces the
		// noisy line-by-line output with one clean repeater + sub-fields).
		if ( false !== stripos( $prompt, 'testimonial' ) || false !== stripos( $prompt, 'review' ) ) {
			$fields = $this->build_testimonial_fields( $prefix, $prompt );

			if ( false !== stripos( $prompt, 'slider' ) || false !== stripos( $prompt, 'carousel' ) || false !== stripos( $prompt, 'swiper' ) || false !== stripos( $prompt, 'autoplay' ) ) {
				$layout   = 'slider';
				$needs_js = true;
				$js_type  = 'slider';
			} else {
				$layout = 'testimonial';
			}
		}

		// Case-study / services / image+text Swiper (before generic team).
		$wants_case_slider = (
			false !== stripos( $prompt, 'swiper' )
			|| false !== stripos( $prompt, 'slider' )
			|| false !== stripos( $prompt, 'carousel' )
			|| false !== stripos( $prompt, 'case study' )
			|| false !== stripos( $prompt, 'focus area' )
		) && (
			false !== stripos( $prompt, 'image' )
			|| false !== stripos( $prompt, 'seo' )
			|| false !== stripos( $prompt, 'description' )
			|| false !== stripos( $prompt, 'focus' )
			|| false !== stripos( $prompt, 'bullet' )
			|| false !== stripos( $prompt, 'exactly as shown' )
			|| false !== stripos( $prompt, 'pixel' )
		);

		$used_case_study = false;
		if ( $wants_case_slider && false === stripos( $prompt, 'testimonial' ) && false === stripos( $prompt, 'team' ) ) {
			$fields          = $this->build_case_study_slider_fields( $prefix );
			$layout          = 'slider';
			$needs_js        = true;
			$js_type         = 'slider';
			$used_case_study = true;
		}

		// Team mosaic (desktop grid + mobile slider) — before generic team slider.
		$wants_team_mosaic = (
			false !== stripos( $prompt, 'team' )
			|| false !== stripos( $prompt, 'core team' )
			|| false !== stripos( $prompt, 'meet our team' )
		) && (
			false !== stripos( $prompt, 'stagger' )
			|| false !== stripos( $prompt, 'mosaic' )
			|| false !== stripos( $prompt, 'desktop' )
			|| false !== stripos( $prompt, 'checkerboard' )
			|| false !== stripos( $prompt, 'view all our team' )
			|| false !== stripos( $prompt, 'our team' )
		);

		if ( $wants_team_mosaic && false === stripos( $prompt, 'testimonial' ) ) {
			$fields   = $this->build_team_mosaic_fields( $prefix );
			$layout   = 'team';
			$needs_js = true;
			$js_type  = 'slider';
		}

		// Team slider pattern (simple carousel of members — only if not mosaic).
		if ( ! $wants_team_mosaic && false !== stripos( $prompt, 'team' ) && ( false !== stripos( $prompt, 'slider' ) || false !== stripos( $prompt, 'social' ) ) ) {
			$fields          = $this->build_team_slider_fields( $prefix );
			$layout          = 'slider';
			$needs_js        = true;
			$js_type         = 'slider';
			$used_case_study = false;
		}

		// Hero pattern.
		if ( false !== stripos( $prompt, 'hero' ) ) {
			$fields = $this->merge_fields( $fields, $this->build_hero_fields( $prefix ) );
			$layout = 'hero';
		}

		if ( empty( $fields ) ) {
			$fields = $this->build_default_fields( $prefix );
		}

		$result = array(
			'purpose'             => sprintf( 'Block: %s', $block_config['name'] ?? $slug ),
			'layout'              => $layout,
			'layout_details'      => $this->get_layout_details( $layout, $prompt ),
			'fields'              => $fields,
			'layout_structure'    => $this->build_layout_structure( $fields, $layout ),
			'needs_javascript'    => $needs_js,
			'javascript_type'     => $js_type,
			'bem_block'           => $slug,
			'css_notes'           => 'Mobile-first responsive layout',
			'block_name'          => $block_config['name'] ?? '',
			'block_slug'          => $slug,
			'block_category'      => $block_config['category'] ?? 'custom-blocks',
			'block_icon'          => $block_config['icon'] ?? 'layout',
			'block_description'   => $block_config['description'] ?? '',
			'source'              => 'rule-based',
			'suggestions'         => $this->generate_suggestions( $fields, $layout ),
			'placeholder_content' => $this->generate_placeholders( $fields ),
		);

		if ( $used_case_study ) {
			$result['design_tokens'] = array(
				'primary_color'    => '#32CD32',
				'text_color'       => '#FFFFFF',
				'background_color' => '#001529',
				'accent_color'     => '#32CD32',
				'section_padding'  => '4rem 0',
				'heading_size'     => '2.25rem',
				'gap'              => '2rem',
				'radius'           => '0px',
			);
		}

		return $result;
	}

	/**
	 * Build row/column structure from fields.
	 *
	 * @param array  $fields Field list.
	 * @param string $layout Layout type.
	 * @return array
	 */
	private function build_layout_structure( $fields, $layout ) {
		$content = array();
		$media   = array();

		foreach ( $fields as $field ) {
			$name = $field['name'] ?? '';
			$type = $field['type'] ?? 'text';
			if ( ! $name || in_array( $type, array( 'accordion', 'tab', 'message', 'true_false' ), true ) ) {
				continue;
			}
			if ( in_array( $type, array( 'image', 'gallery', 'file' ), true ) || false !== strpos( $name, 'image' ) ) {
				$media[] = $name;
			} else {
				$content[] = $name;
			}
		}

		$image_right = false !== stripos( $layout, 'two-column' ) || false !== stripos( $layout, 'hero' );

		if ( $image_right && ! empty( $media ) && ! empty( $content ) ) {
			return array(
				'grid_system' => 'theme',
				'rows'        => array(
					array(
						'align'   => 'center',
						'columns' => array(
							array(
								'width'      => '50%',
								'cell_class' => 'cell-md-6',
								'fields'     => $content,
								'class'      => 'col-content',
							),
							array(
								'width'      => '50%',
								'cell_class' => 'cell-md-6',
								'fields'     => $media,
								'class'      => 'col-media',
							),
						),
					),
				),
			);
		}

		$all = array_merge( $content, $media );
		return array(
			'grid_system' => 'theme',
			'rows'        => array(
				array(
					'columns' => array(
						array(
							'width'      => '100%',
							'cell_class' => 'cell-12',
							'fields'     => $all,
							'class'      => 'col-full',
						),
					),
				),
			),
		);
	}

	/**
	 * Parse prompt into lines.
	 *
	 * @param string $prompt Prompt.
	 * @return array
	 */
	private function parse_lines( $prompt ) {
		$prompt = str_replace( array( ',', ';' ), "\n", $prompt );
		return array_filter( array_map( 'trim', explode( "\n", $prompt ) ) );
	}

	/**
	 * Detect layout from prompt.
	 *
	 * @param string $prompt Prompt.
	 * @return string
	 */
	private function detect_layout( $prompt ) {
		$prompt_lower = strtolower( $prompt );

		$layouts = array(
			'hero'       => array( 'hero', 'banner' ),
			'two-column' => array( 'two column', 'two-column', '2 column', 'image on right', 'image on left' ),
			'slider'     => array( 'slider', 'carousel', 'swiper' ),
			'accordion'  => array( 'accordion', 'collapsible' ),
			'tabs'       => array( 'tabs', 'tabbed' ),
			'grid'       => array( 'grid', 'cards', 'columns' ),
			'cta'        => array( 'call to action', 'cta' ),
			'team'       => array( 'team' ),
			'testimonial'=> array( 'testimonial', 'review' ),
		);

		foreach ( $layouts as $layout => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( false !== strpos( $prompt_lower, $keyword ) ) {
					return $layout;
				}
			}
		}

		return 'content';
	}

	/**
	 * Match a line to a field definition.
	 *
	 * @param string $line   Line text.
	 * @param string $prefix Field prefix.
	 * @param array  $seen   Already used names.
	 * @return array|null
	 */
	private function match_field( $line, $prefix, $seen ) {
		foreach ( $this->type_map as $keyword => $type ) {
			if ( false !== strpos( $line, $keyword ) ) {
				$name = Sanitizer::field_name( str_replace( ' ', '_', $keyword ) );

				if ( in_array( $prefix . '_' . $name, $seen, true ) ) {
					continue;
				}

				$field = array(
					'label'   => ucwords( str_replace( '_', ' ', $keyword ) ),
					'name'    => $prefix . '_' . $name,
					'type'    => $type,
					'required'=> false,
				);

				if ( 'repeater' === $type ) {
					if ( false !== strpos( $line, 'social' ) ) {
						$field['sub_fields'] = array(
							array( 'label' => 'Icon', 'name' => 'icon', 'type' => 'text' ),
							array( 'label' => 'URL', 'name' => 'url', 'type' => 'url' ),
						);
					} else {
						// Never emit a repeater with no sub-fields — that renders
						// as empty slides/cards. Provide sensible defaults.
						$field['sub_fields'] = array(
							array( 'label' => 'Image', 'name' => 'image', 'type' => 'image' ),
							array( 'label' => 'Title', 'name' => 'title', 'type' => 'text' ),
							array( 'label' => 'Description', 'name' => 'description', 'type' => 'textarea' ),
						);
					}
				}

				if ( 'link' === $type ) {
					if ( false !== strpos( $line, 'primary' ) ) {
						$field['label'] = 'Primary Button';
						$field['name']  = $prefix . '_primary_button';
					} elseif ( false !== strpos( $line, 'secondary' ) ) {
						$field['label'] = 'Secondary Button';
						$field['name']  = $prefix . '_secondary_button';
					}
				}

				if ( 'image' === $type && false !== strpos( $line, 'background' ) ) {
					$field['label'] = 'Background Image';
					$field['name']  = $prefix . '_background_image';
				}

				return $field;
			}
		}

		return null;
	}

	/**
	 * Build hero fields.
	 *
	 * @param string $prefix Prefix.
	 * @return array
	 */
	private function build_hero_fields( $prefix ) {
		return array(
			array( 'label' => 'Background Image', 'name' => $prefix . '_background_image', 'type' => 'image' ),
			array( 'label' => 'Title', 'name' => $prefix . '_title', 'type' => 'text', 'default_value' => 'Your Hero Title' ),
			array( 'label' => 'Subtitle', 'name' => $prefix . '_subtitle', 'type' => 'text' ),
			array( 'label' => 'Description', 'name' => $prefix . '_description', 'type' => 'textarea' ),
			array( 'label' => 'Primary Button', 'name' => $prefix . '_primary_button', 'type' => 'link' ),
			array( 'label' => 'Secondary Button', 'name' => $prefix . '_secondary_button', 'type' => 'link' ),
			array( 'label' => 'Side Image', 'name' => $prefix . '_side_image', 'type' => 'image' ),
		);
	}

	/**
	 * Build team slider fields.
	 *
	 * @param string $prefix Prefix.
	 * @return array
	 */
	private function build_team_slider_fields( $prefix ) {
		return array(
			array( 'label' => 'Section Title', 'name' => $prefix . '_section_title', 'type' => 'text', 'default_value' => 'Our Team' ),
			array(
				'label'      => 'Team Members',
				'name'       => $prefix . '_team_members',
				'type'       => 'repeater',
				'sub_fields' => array(
					array( 'label' => 'Image', 'name' => 'image', 'type' => 'image' ),
					array( 'label' => 'Name', 'name' => 'name', 'type' => 'text' ),
					array( 'label' => 'Designation', 'name' => 'designation', 'type' => 'text' ),
					array(
						'label'      => 'Social Links',
						'name'       => 'social_links',
						'type'       => 'repeater',
						'sub_fields' => array(
							array( 'label' => 'Icon', 'name' => 'icon', 'type' => 'text' ),
							array( 'label' => 'URL', 'name' => 'url', 'type' => 'url' ),
						),
					),
				),
			),
			array( 'label' => 'Autoplay', 'name' => $prefix . '_autoplay', 'type' => 'true_false', 'default_value' => 1 ),
			array( 'label' => 'Show Navigation', 'name' => $prefix . '_show_navigation', 'type' => 'true_false', 'default_value' => 1 ),
			array( 'label' => 'Slides Per View (Mobile)', 'name' => $prefix . '_slides_mobile', 'type' => 'number', 'default_value' => 1 ),
			array( 'label' => 'Slides Per View (Desktop)', 'name' => $prefix . '_slides_desktop', 'type' => 'number', 'default_value' => 3 ),
		);
	}

	/**
	 * Desktop staggered mosaic + mobile slider (Core Team / Meet Our Team).
	 *
	 * @param string $prefix Prefix.
	 * @return array
	 */
	private function build_team_mosaic_fields( $prefix ) {
		return array(
			array(
				'label'         => 'Eyebrow',
				'name'          => $prefix . '_eyebrow',
				'type'          => 'text',
				'default_value' => '/ OUR TEAM /',
			),
			array(
				'label'         => 'Title',
				'name'          => $prefix . '_section_title',
				'type'          => 'text',
				'default_value' => 'The Minds That Engineer Your Measurable Growth',
			),
			array(
				'label' => 'Description',
				'name'  => $prefix . '_description',
				'type'  => 'textarea',
			),
			array(
				'label' => 'CTA Link',
				'name'  => $prefix . '_link',
				'type'  => 'link',
			),
			array(
				'label'        => 'Team Members',
				'name'         => $prefix . '_team_members',
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => 'Add Member',
				'sub_fields'   => array(
					array( 'label' => 'Photo', 'name' => 'image', 'type' => 'image' ),
					array( 'label' => 'Name', 'name' => 'name', 'type' => 'text' ),
					array( 'label' => 'Designation', 'name' => 'designation', 'type' => 'text' ),
				),
			),
			array(
				'label'         => 'Autoplay',
				'name'          => $prefix . '_autoplay',
				'type'          => 'true_false',
				'default_value' => 1,
				'instructions'  => 'Mobile slider only.',
				'ui'            => 1,
			),
			array(
				'label'         => 'Show Arrows',
				'name'          => $prefix . '_show_navigation',
				'type'          => 'true_false',
				'default_value' => 1,
				'instructions'  => 'Mobile slider only.',
				'ui'            => 1,
			),
			array(
				'label'         => 'Show Dots',
				'name'          => $prefix . '_pagination',
				'type'          => 'true_false',
				'default_value' => 1,
				'instructions'  => 'Mobile slider only.',
				'ui'            => 1,
			),
		);
	}

	/**
	 * Build testimonial slider / card fields.
	 *
	 * Produces a single repeater with the sub-fields a testimonial block needs,
	 * plus the section header fields — instead of the flat, empty-repeater mess
	 * the generic line matcher creates for a rich prompt.
	 *
	 * @param string $prefix Prefix.
	 * @param string $prompt Original prompt.
	 * @return array
	 */
	private function build_testimonial_fields( $prefix, $prompt = '' ) {
		$sub_fields = array(
			array( 'label' => 'Featured Image', 'name' => 'featured_image', 'type' => 'image' ),
			array( 'label' => 'Testimonial', 'name' => 'testimonial', 'type' => 'wysiwyg' ),
			array( 'label' => 'User Image', 'name' => 'user_image', 'type' => 'image' ),
			array( 'label' => 'User Name', 'name' => 'user_name', 'type' => 'text' ),
			array( 'label' => 'User Designation', 'name' => 'user_designation', 'type' => 'text' ),
		);

		$fields = array(
			array( 'label' => 'Eyebrow Text', 'name' => $prefix . '_eyebrow', 'type' => 'text', 'default_value' => 'Testimonials' ),
			array( 'label' => 'Title', 'name' => $prefix . '_title', 'type' => 'text', 'default_value' => 'What Our Clients Say' ),
			array(
				'label'      => 'Testimonials',
				'name'       => $prefix . '_testimonials',
				'type'       => 'repeater',
				'sub_fields' => $sub_fields,
			),
		);

		if ( false !== stripos( $prompt, 'autoplay' ) || false !== stripos( $prompt, 'slider' ) ) {
			$fields[] = array( 'label' => 'Autoplay', 'name' => $prefix . '_autoplay', 'type' => 'true_false', 'default_value' => 1 );
			$fields[] = array( 'label' => 'Autoplay Delay (ms)', 'name' => $prefix . '_delay', 'type' => 'number', 'default_value' => 5000 );
			$fields[] = array( 'label' => 'Show Navigation', 'name' => $prefix . '_show_navigation', 'type' => 'true_false', 'default_value' => 1 );
		}

		return $fields;
	}

	/**
	 * Build default fields.
	 *
	 * @param string $prefix Prefix.
	 * @return array
	 */
	private function build_default_fields( $prefix ) {
		return array(
			array( 'label' => 'Title', 'name' => $prefix . '_title', 'type' => 'text', 'default_value' => 'Section Title' ),
			array( 'label' => 'Content', 'name' => $prefix . '_content', 'type' => 'wysiwyg' ),
		);
	}

	/**
	 * Ensure common fields exist.
	 *
	 * @param string $prompt Prompt.
	 * @param string $prefix Prefix.
	 * @param array  $fields Existing fields.
	 * @param array  $seen   Seen names.
	 * @return array
	 */
	private function ensure_common_fields( $prompt, $prefix, $fields, $seen ) {
		$checks = array(
			'title'       => array( 'title', 'heading' ),
			'description' => array( 'description', 'desc' ),
			'image'       => array( 'image', 'photo' ),
			'button'      => array( 'button', 'cta' ),
		);

		foreach ( $checks as $key => $keywords ) {
			$found_in_prompt = false;
			foreach ( $keywords as $kw ) {
				if ( false !== stripos( $prompt, $kw ) ) {
					$found_in_prompt = true;
					break;
				}
			}

			if ( ! $found_in_prompt ) {
				continue;
			}

			$fname = $prefix . '_' . $key;
			if ( in_array( $fname, $seen, true ) ) {
				continue;
			}

			$type_map = array(
				'title'       => 'text',
				'description' => 'textarea',
				'image'       => 'image',
				'button'      => 'link',
			);

			$fields[] = array(
				'label' => ucfirst( $key ),
				'name'  => $fname,
				'type'  => $type_map[ $key ],
			);
		}

		return $fields;
	}

	/**
	 * Merge field arrays without duplicates.
	 *
	 * @param array $existing Existing.
	 * @param array $new      New fields.
	 * @return array
	 */
	private function merge_fields( $existing, $new ) {
		$names = wp_list_pluck( $existing, 'name' );

		foreach ( $new as $field ) {
			if ( ! in_array( $field['name'], $names, true ) ) {
				$existing[] = $field;
			}
		}

		return $existing;
	}

	/**
	 * Get field prefix from slug.
	 *
	 * @param string $slug Slug.
	 * @return string
	 */
	private function get_prefix( $slug ) {
		return \AABG\Utils\Slug_Helper::field_prefix( $slug );
	}

	/**
	 * Get layout details.
	 *
	 * @param string $layout Layout.
	 * @param string $prompt Prompt.
	 * @return string
	 */
	private function get_layout_details( $layout, $prompt ) {
		if ( false !== stripos( $prompt, 'image on right' ) ) {
			return 'Two-column layout with image on the right';
		}
		if ( false !== stripos( $prompt, 'image on left' ) ) {
			return 'Two-column layout with image on the left';
		}
		if ( false !== stripos( $prompt, 'responsive' ) ) {
			return ucfirst( $layout ) . ' layout with responsive design';
		}
		return ucfirst( $layout ) . ' layout';
	}

	/**
	 * Generate AI suggestions.
	 *
	 * @param array  $fields Fields.
	 * @param string $layout Layout.
	 * @return array
	 */
	private function generate_suggestions( $fields, $layout ) {
		return array(
			'accessibility'    => array(
				'Use semantic HTML elements (section, h2, article)',
				'Add alt text to all images via ACF return format',
				'Ensure sufficient color contrast for text',
				'Make interactive elements keyboard accessible',
			),
			'performance'      => array(
				'Use lazy loading for images below the fold',
				'Minimize JavaScript — only load when block is present',
				'Use CSS containment for layout sections',
			),
			'field_names'      => array_map(
				function ( $f ) {
					return $f['name'] . ' — consider shorter prefix for reusability';
				},
				array_slice( $fields, 0, 3 )
			),
			'reusable_groups'  => array(
				'Consider a shared "Section Settings" group (padding, background)',
				'Button fields could use a cloned "CTA" field group',
			),
		);
	}

	/**
	 * Generate placeholder content.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	private function generate_placeholders( $fields ) {
		$placeholders = array();

		foreach ( $fields as $field ) {
			if ( ! empty( $field['default_value'] ) ) {
				$placeholders[ $field['name'] ] = $field['default_value'];
			}
		}

		return $placeholders;
	}
}
