<?php
/**
 * Shared AI prompt and response helpers.
 *
 * @package AABG
 */

namespace AABG\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait AI_Spec_Helper
 */
trait AI_Spec_Helper {

	/**
	 * System prompt for block analysis.
	 *
	 * @param bool $with_design Include design vision instructions.
	 * @return string
	 */
	protected function get_system_prompt( $with_design = false ) {
		$design_note = $with_design
			? ' When a design image is provided, you MUST analyze the visual design pixel-by-pixel and return layout_structure that EXACTLY matches the design. Use grid_system "theme" with WordPress theme grid classes: .row, .cell-md-6, .cell-md-4, .cell-12, align-items-center. Extract exact design_tokens (hex colors, spacing in rem/px, typography sizes) from the image. Map EVERY visible UI element (heading, paragraph, bullet list, pagination text, image, icons) to an ACF field. Never return only settings toggles (autoplay/navigation/color) without content fields. Match field placement to visual positions.'
			: ' Always set layout_structure.grid_system to "theme" and use cell_class values like cell-md-6, cell-md-4, cell-12 matching the layout.';

		return 'You are an expert WordPress and ACF developer building blocks for the plugins-demo theme (Zealous Web boilerplate). Analyze user prompts and return JSON for generating an ACF Gutenberg block.' . $design_note . '

THEME GRID SYSTEM (required for layout_structure):
- grid_system: "theme" (default, preferred) or "bem"
- Theme grid HTML: <div class="row align-items-center"><div class="cell-md-6">content</div><div class="cell-md-6">media</div></div>
- cell_class options: cell-12, cell-md-3, cell-md-4, cell-md-6, cell-md-8, cell-md-9, cell-lg-4, cell-lg-6
- Theme PHP helpers: acf_link($link, "btn"), acf_image($id), render_acf_block_preview($block)
- Theme SCSS: @use variables/function/mixins, respond-above("md"), rem(), $primary-100, $container-width

CRITICAL CONTENT RULES (never violate):
1. Content fields are mandatory: if the design/prompt shows titles, descriptions, bullet lists, or slides, you MUST create text/textarea/wysiwyg/repeater fields for them.
2. NEVER return a sparse field list of only true_false / color_picker / number settings (Autoplay, Navigation, Animation, Color) plus a single Image. That is INVALID.
3. Case-study / service / feature sliders (image left + title + description + focus/bullet list + 01/04 pagination): set layout "slider", needs_javascript true, javascript_type "slider", and create ONE repeater "slides" with sub_fields: image, slide_number (text e.g. "01 / 04"), title, description (textarea), focus_heading (text), focus_areas (nested repeater with point text). Then add Autoplay / Navigation / Pagination true_false controls AFTER the slides repeater.
4. Bullet / checkmark lists in a design MUST be a nested repeater (or a repeater of text items), never omitted.
5. Slider settings alone do not satisfy the prompt — content fields come first; settings are secondary.

Return ONLY valid JSON with this structure:
{
  "purpose": "Brief block purpose",
  "layout": "hero|two-column|slider|accordion|tabs|grid|cta|content|team|testimonial|custom",
  "layout_details": "Layout description",
  "fields": [
    {
      "label": "Field Label",
      "name": "field_name_snake_case",
      "type": "acf_field_type",
      "instructions": "optional",
      "required": false,
      "default_value": "optional",
      "wrapper": { "width": "50" },
      "sub_fields": [],
      "layouts": [],
      "choices": {},
      "new_lines": "br"
    }
  ],
  "needs_javascript": false,
  "javascript_type": "none|slider|accordion|tabs|counter|popup|video|animation",
  "bem_block": "block-name",
  "css_notes": "responsive layout notes",
  "design_tokens": {
    "primary_color": "#hex from design",
    "text_color": "#hex from design",
    "background_color": "#hex from design",
    "accent_color": "#hex accent/icon color",
    "section_padding": "4rem 0",
    "heading_size": "2.5rem",
    "gap": "1.5rem",
    "radius": "8px"
  },
  "layout_structure": {
    "grid_system": "theme",
    "rows": [
      {
        "align": "center",
        "columns": [
          {
            "width": "50%",
            "cell_class": "cell-md-6",
            "fields": ["field_name_1", "field_name_2"],
            "class": "col-content"
          },
          {
            "width": "50%",
            "cell_class": "cell-md-6",
            "fields": ["field_name_3"],
            "class": "col-media"
          }
        ]
      }
    ]
  },
  "suggestions": {
    "accessibility": ["tip1"],
    "performance": ["tip1"],
    "field_names": ["suggestion"],
    "reusable_groups": ["suggestion"]
  },
  "placeholder_content": {}
}

Supported ACF field types: text, textarea, image, gallery, link, url, email, number, color_picker, range, date_picker, select, checkbox, radio, true_false, file, relationship, post_object, taxonomy, repeater, flexible_content, group, clone, accordion, tab, message, button_group, wysiwyg.

For ANY repeating group of items (team members, cards, logos, features, testimonials, steps, stats, slides, focus areas), you MUST use a "repeater" field with a "sub_fields" array — never flatten repeated items into separate top-level fields. Each repeated card image/title/text becomes a sub_field.
For grid/cards/features/team layouts, set layout to the matching value and place the repeater field in a single full-width column (cell_class "cell-12"); the grid columns are handled by CSS automatically.
For two-column or hero layouts, split fields into exactly two columns in layout_structure with cell_class values that add up to 12 (e.g. cell-md-6 + cell-md-6, or cell-md-7 + cell-md-5) matching the visual proportions in the design.
For flexible_content include layouts array with name, label, sub_fields.
Set needs_javascript true only for interactive features: slider, accordion, tabs, counter, popup, video, animation.
Use semantic, snake_case, prefixed field names. Include slider settings (autoplay, navigation) when relevant — but NEVER instead of content fields.';
	}

	/**
	 * Extra vision instructions appended to the user message.
	 *
	 * @return string
	 */
	protected function get_vision_user_preamble() {
		return 'Analyze this design mockup carefully. Replicate the EXACT visual design: row/column structure using theme grid classes (row, cell-md-6, cell-md-4, cell-12), exact hex colors, spacing, typography, and field positions. Map every visible UI element to an ACF field — headings, body copy, bullet/focus lists, slide counters (01/04), images, and icons. If the mockup is a slider/carousel/case-study section, return layout "slider" with a slides repeater (image, slide_number, title, description, focus_heading, focus_areas nested repeater). Do NOT return only Autoplay/Navigation/Animation/Color toggles. Return layout_structure with grid_system "theme". Extract design_tokens from the image colors. ';
	}

	/**
	 * Build user message JSON.
	 *
	 * @param string $prompt       Prompt.
	 * @param array  $block_config Config.
	 * @return string
	 */
	protected function build_user_message( $prompt, $block_config ) {
		return wp_json_encode(
			array(
				'block_name'        => $block_config['name'] ?? '',
				'block_slug'        => $block_config['slug'] ?? '',
				'block_category'    => $block_config['category'] ?? 'custom-blocks',
				'block_description' => $block_config['description'] ?? '',
				'user_prompt'       => $prompt,
			)
		);
	}

	/**
	 * Normalize AI response.
	 *
	 * @param array  $parsed       Parsed response.
	 * @param array  $block_config Block config.
	 * @param string $source       Provider source tag.
	 * @param string $prompt       Original user prompt (optional).
	 * @return array
	 */
	protected function normalize_response( $parsed, $block_config, $source = 'ai', $prompt = '' ) {
		$parsed['block_name']        = $block_config['name'] ?? '';
		$parsed['block_slug']        = \AABG\Utils\Sanitizer::block_slug( $block_config['slug'] ?? ( $parsed['block_slug'] ?? 'custom-block' ) );
		$parsed['block_category']    = $block_config['category'] ?? 'custom-blocks';
		$parsed['block_icon']        = $block_config['icon'] ?? 'layout';
		$parsed['block_description'] = $block_config['description'] ?? '';
		$parsed['source']            = $source;

		if ( empty( $parsed['fields'] ) || ! is_array( $parsed['fields'] ) ) {
			$parsed['fields'] = array();
		}

		if ( empty( $parsed['bem_block'] ) ) {
			$parsed['bem_block'] = $parsed['block_slug'];
		}

		$parsed['bem_block'] = \AABG\Utils\Sanitizer::block_slug( $parsed['bem_block'] );
		$parsed['fields']    = $this->sanitize_field_tree( $parsed['fields'] );

		$parsed = $this->enrich_sparse_content_spec( $parsed, $block_config, $prompt );

		$parsed['fields'] = $this->sanitize_field_tree( $parsed['fields'] ?? array() );

		if ( empty( $parsed['layout_structure'] ) ) {
			$parsed['layout_structure'] = $this->infer_layout_structure( $parsed );
		} else {
			$parsed['layout_structure'] = $this->sanitize_layout_structure_names( $parsed['layout_structure'], $parsed['fields'] );
		}

		return $parsed;
	}

	/**
	 * Recursively sanitize ACF field names so generated PHP stays valid.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	protected function sanitize_field_tree( $fields ) {
		$clean = array();

		foreach ( (array) $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( ! empty( $field['name'] ) ) {
				$field['name'] = \AABG\Utils\Sanitizer::field_name( $field['name'] );
			}

			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$field['sub_fields'] = $this->sanitize_field_tree( $field['sub_fields'] );
			}

			if ( ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as &$layout ) {
					if ( ! empty( $layout['name'] ) ) {
						$layout['name'] = \AABG\Utils\Sanitizer::field_name( $layout['name'] );
					}
					if ( ! empty( $layout['sub_fields'] ) ) {
						$layout['sub_fields'] = $this->sanitize_field_tree( $layout['sub_fields'] );
					}
				}
				unset( $layout );
			}

			$clean[] = $field;
		}

		return $clean;
	}

	/**
	 * Keep layout_structure field name refs in sync after sanitization.
	 *
	 * @param array $structure Layout structure.
	 * @param array $fields    Sanitized fields.
	 * @return array
	 */
	protected function sanitize_layout_structure_names( $structure, $fields ) {
		$valid = array();
		foreach ( $fields as $field ) {
			if ( ! empty( $field['name'] ) ) {
				$valid[ $field['name'] ] = true;
			}
		}

		if ( empty( $structure['rows'] ) || ! is_array( $structure['rows'] ) ) {
			return $structure;
		}

		foreach ( $structure['rows'] as &$row ) {
			if ( empty( $row['columns'] ) || ! is_array( $row['columns'] ) ) {
				continue;
			}
			foreach ( $row['columns'] as &$column ) {
				if ( empty( $column['fields'] ) || ! is_array( $column['fields'] ) ) {
					continue;
				}
				$mapped = array();
				foreach ( $column['fields'] as $fname ) {
					$safe = \AABG\Utils\Sanitizer::field_name( $fname );
					if ( isset( $valid[ $safe ] ) ) {
						$mapped[] = $safe;
					}
				}
				$column['fields'] = $mapped;
			}
			unset( $column );
		}
		unset( $row );

		return $structure;
	}

	/**
	 * Replace sparse AI specs (settings-only) with a proper case-study slider schema.
	 *
	 * @param array  $parsed       Spec.
	 * @param array  $block_config Block config.
	 * @param string $prompt       Prompt.
	 * @return array
	 */
	protected function enrich_sparse_content_spec( $parsed, $block_config, $prompt = '' ) {
		$fields = $parsed['fields'] ?? array();
		$layout = strtolower( (string) ( $parsed['layout'] ?? 'content' ) );
		$js     = strtolower( (string) ( $parsed['javascript_type'] ?? '' ) );
		$slug   = $block_config['slug'] ?? ( $parsed['block_slug'] ?? 'custom-block' );
		$prefix = \AABG\Utils\Slug_Helper::field_prefix( $slug );

		$prompt_l = strtolower( $prompt . ' ' . ( $parsed['purpose'] ?? '' ) . ' ' . ( $parsed['layout_details'] ?? '' ) . ' ' . ( $block_config['name'] ?? '' ) . ' ' . ( $block_config['description'] ?? '' ) );

		$wants_slider = in_array( $layout, array( 'slider', 'carousel' ), true )
			|| 'slider' === $js
			|| false !== strpos( $prompt_l, 'swiper' )
			|| false !== strpos( $prompt_l, 'slider' )
			|| false !== strpos( $prompt_l, 'carousel' )
			|| false !== strpos( $prompt_l, 'case study' )
			|| false !== strpos( $prompt_l, 'focus area' );

		if ( ! $this->is_content_sparse( $fields ) ) {
			// Still ensure nested bullet lists exist when prompt asks for focus areas.
			if ( $wants_slider && ( false !== strpos( $prompt_l, 'focus' ) || false !== strpos( $prompt_l, 'bullet' ) ) ) {
				$parsed['fields'] = $this->ensure_focus_areas_on_slides( $fields );
			}
			return $parsed;
		}

		if ( ! $wants_slider && ! $this->looks_like_image_text_section( $fields, $prompt_l ) ) {
			return $parsed;
		}

		$parsed['layout']           = 'slider';
		$parsed['needs_javascript'] = true;
		$parsed['javascript_type']  = 'slider';
		$parsed['fields']           = $this->build_case_study_slider_fields( $prefix );
		$parsed['layout_details']   = $parsed['layout_details'] ?? 'Two-column case-study Swiper: image left, content + focus list right.';
		$parsed['css_notes']        = $parsed['css_notes'] ?? 'Dark navy section, white typography, green focus icons, responsive Swiper.';

		if ( empty( $parsed['design_tokens'] ) || ! is_array( $parsed['design_tokens'] ) ) {
			$parsed['design_tokens'] = array();
		}
		$parsed['design_tokens'] = array_merge(
			array(
				'primary_color'     => '#32CD32',
				'text_color'        => '#FFFFFF',
				'background_color'  => '#001529',
				'accent_color'      => '#32CD32',
				'section_padding'   => '4rem 0',
				'heading_size'      => '2.25rem',
				'gap'               => '2rem',
				'radius'            => '0px',
			),
			$parsed['design_tokens']
		);

		$parsed['layout_structure'] = array(
			'grid_system' => 'theme',
			'rows'        => array(
				array(
					'align'   => 'center',
					'columns' => array(
						array(
							'width'      => '100%',
							'cell_class' => 'cell-12',
							'fields'     => array( $prefix . '_slides' ),
							'class'      => 'col-full',
						),
					),
				),
			),
		);

		$parsed['enriched'] = 'case_study_slider';

		return $parsed;
	}

	/**
	 * Whether fields are settings-heavy / missing real content.
	 *
	 * @param array $fields Fields.
	 * @return bool
	 */
	protected function is_content_sparse( $fields ) {
		if ( empty( $fields ) ) {
			return true;
		}

		$content_count         = 0;
		$has_rich_repeater     = false;
		$control_types         = array( 'true_false', 'number', 'color_picker', 'range', 'select', 'button_group', 'checkbox', 'radio' );

		foreach ( $fields as $field ) {
			$type = $field['type'] ?? 'text';

			if ( in_array( $type, $control_types, true ) ) {
				continue;
			}

			if ( 'repeater' === $type ) {
				$subs         = $field['sub_fields'] ?? array();
				$sub_content  = 0;
				$has_title    = false;
				$has_desc     = false;
				$has_image    = false;

				foreach ( $subs as $sub ) {
					$st = $sub['type'] ?? '';
					$sn = $sub['name'] ?? '';
					if ( in_array( $st, $control_types, true ) ) {
						continue;
					}
					++$sub_content;
					if ( 'image' === $st ) {
						$has_image = true;
					}
					if ( in_array( $st, array( 'text', 'wysiwyg' ), true ) && ( false !== strpos( $sn, 'title' ) || false !== strpos( $sn, 'heading' ) || false !== strpos( $sn, 'name' ) ) ) {
						$has_title = true;
					}
					if ( in_array( $st, array( 'textarea', 'wysiwyg', 'text' ), true ) && ( false !== strpos( $sn, 'desc' ) || false !== strpos( $sn, 'content' ) || false !== strpos( $sn, 'text' ) || false !== strpos( $sn, 'point' ) ) ) {
						$has_desc = true;
					}
					if ( 'repeater' === $st ) {
						$has_desc = true;
					}
				}

				if ( $sub_content >= 2 && ( $has_title || $has_desc || ( $has_image && $sub_content >= 2 ) ) ) {
					$has_rich_repeater = true;
				}
				++$content_count;
				continue;
			}

			++$content_count;
		}

		if ( $has_rich_repeater ) {
			return false;
		}

		// Only image + toggles, or fewer than 2 real content fields.
		return $content_count < 2;
	}

	/**
	 * Heuristic: image + text section (even without "slider" keyword).
	 *
	 * @param array  $fields   Fields.
	 * @param string $prompt_l Lowercase prompt blob.
	 * @return bool
	 */
	protected function looks_like_image_text_section( $fields, $prompt_l ) {
		if ( false !== strpos( $prompt_l, 'image' ) && ( false !== strpos( $prompt_l, 'text' ) || false !== strpos( $prompt_l, 'description' ) || false !== strpos( $prompt_l, 'seo' ) ) ) {
			return true;
		}

		$has_image = false;
		foreach ( $fields as $field ) {
			if ( 'image' === ( $field['type'] ?? '' ) ) {
				$has_image = true;
				break;
			}
		}

		return $has_image && $this->is_content_sparse( $fields );
	}

	/**
	 * Ensure slides repeater has a nested focus_areas list.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	protected function ensure_focus_areas_on_slides( $fields ) {
		foreach ( $fields as &$field ) {
			if ( 'repeater' !== ( $field['type'] ?? '' ) ) {
				continue;
			}

			$subs     = $field['sub_fields'] ?? array();
			$has_focus = false;
			foreach ( $subs as $sub ) {
				$name = $sub['name'] ?? '';
				if ( 'repeater' === ( $sub['type'] ?? '' ) || false !== strpos( $name, 'focus' ) || false !== strpos( $name, 'bullet' ) || false !== strpos( $name, 'point' ) ) {
					$has_focus = true;
					break;
				}
			}

			if ( ! $has_focus ) {
				$subs[]               = array(
					'label'         => 'Focus Areas Heading',
					'name'          => 'focus_heading',
					'type'          => 'text',
					'default_value' => 'The Focus Areas:',
				);
				$subs[]               = array(
					'label'      => 'Focus Areas',
					'name'       => 'focus_areas',
					'type'       => 'repeater',
					'sub_fields' => array(
						array(
							'label' => 'Point',
							'name'  => 'point',
							'type'  => 'text',
						),
					),
				);
				$field['sub_fields'] = $subs;
			}
		}
		unset( $field );

		return $fields;
	}

	/**
	 * Canonical case-study / services Swiper field schema.
	 *
	 * @param string $prefix Field prefix.
	 * @return array
	 */
	protected function build_case_study_slider_fields( $prefix ) {
		return array(
			array(
				'label'        => 'Slides',
				'name'         => $prefix . '_slides',
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => 'Add Slide',
				'instructions' => 'Each slide: image left, title/description/focus list right.',
				'sub_fields'   => array(
					array(
						'label'   => 'Image',
						'name'    => 'image',
						'type'    => 'image',
						'wrapper' => array( 'width' => '50' ),
					),
					array(
						'label'        => 'Slide Number',
						'name'         => 'slide_number',
						'type'         => 'text',
						'instructions' => 'e.g. 01 / 04',
						'wrapper'      => array( 'width' => '50' ),
					),
					array(
						'label' => 'Title',
						'name'  => 'title',
						'type'  => 'text',
					),
					array(
						'label'     => 'Description',
						'name'      => 'description',
						'type'      => 'textarea',
						'new_lines' => 'br',
					),
					array(
						'label'         => 'Focus Areas Heading',
						'name'          => 'focus_heading',
						'type'          => 'text',
						'default_value' => 'The Focus Areas:',
					),
					array(
						'label'        => 'Focus Areas',
						'name'         => 'focus_areas',
						'type'         => 'repeater',
						'button_label' => 'Add Point',
						'sub_fields'   => array(
							array(
								'label' => 'Point',
								'name'  => 'point',
								'type'  => 'text',
							),
						),
					),
				),
			),
			array(
				'label'         => 'Autoplay',
				'name'          => $prefix . '_autoplay',
				'type'          => 'true_false',
				'default_value' => 1,
				'ui'            => 1,
				'instructions'  => 'Automatically advance slides.',
			),
			array(
				'label'         => 'Show Arrows',
				'name'          => $prefix . '_navigation',
				'type'          => 'true_false',
				'default_value' => 1,
				'ui'            => 1,
				'instructions'  => 'Show previous / next arrow buttons.',
			),
			array(
				'label'         => 'Show Dots',
				'name'          => $prefix . '_pagination',
				'type'          => 'true_false',
				'default_value' => 1,
				'ui'            => 1,
				'instructions'  => 'Show pagination dots under the slider.',
			),
		);
	}

	/**
	 * Infer row/column structure when AI omits it.
	 *
	 * @param array $parsed Parsed spec.
	 * @return array
	 */
	protected function infer_layout_structure( $parsed ) {
		$fields  = $parsed['fields'] ?? array();
		$layout  = $parsed['layout'] ?? 'content';
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

		if ( in_array( $layout, array( 'hero', 'two-column' ), true ) && ! empty( $media ) && ! empty( $content ) ) {
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
}
