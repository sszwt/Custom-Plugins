<?php
/**
 * Generates ACF field group JSON.
 *
 * @package AABG
 */

namespace AABG\Generator;

use AABG\Utils\Slug_Helper;
use AABG\Utils\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Field_Group_Generator
 */
class Field_Group_Generator {

	/**
	 * Generate fields.json content.
	 *
	 * @param array $spec Block specification from AI.
	 * @return string JSON string.
	 */
	public function generate( $spec ) {
		$slug      = $spec['block_slug'] ?? 'custom-block';
		$title     = $spec['block_name'] ?? ucwords( str_replace( '-', ' ', $slug ) );
		$group_key = Slug_Helper::group_key();
		$fields    = $this->build_fields( $spec['fields'] ?? array() );

		$group = array(
			'key'                   => $group_key,
			'title'                 => sprintf( 'Block: %s', $title ),
			'fields'                => $fields,
			'location'              => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/' . $slug,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => $spec['block_description'] ?? '',
			'show_in_rest'          => 0,
			'modified'              => time(),
		);

		return wp_json_encode( $group, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Build ACF fields array.
	 *
	 * @param array $fields Field definitions.
	 * @return array
	 */
	private function build_fields( $fields ) {
		$acf_fields = array();

		foreach ( $fields as $field ) {
			$acf_fields[] = $this->build_field( $field );
		}

		return $acf_fields;
	}

	/**
	 * Build a single ACF field.
	 *
	 * @param array  $field    Field definition.
	 * @param string $parent_key Parent key for sub-fields.
	 * @return array
	 */
	private function build_field( $field, $parent_key = '' ) {
		$key  = Slug_Helper::field_key();
		$type = $field['type'] ?? 'text';

		$acf_field = array(
			'key'               => $key,
			'label'             => $field['label'] ?? ucfirst( $field['name'] ?? 'Field' ),
			'name'              => Sanitizer::field_name( $field['name'] ?? 'field' ),
			'aria-label'        => '',
			'type'              => $type,
			'instructions'      => $field['instructions'] ?? '',
			'required'          => ! empty( $field['required'] ) ? 1 : 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => $field['wrapper']['width'] ?? '',
				'class' => $field['wrapper']['class'] ?? '',
				'id'    => $field['wrapper']['id'] ?? '',
			),
		);

		if ( isset( $field['default_value'] ) ) {
			$acf_field['default_value'] = $field['default_value'];
		}

		// Type-specific settings.
		switch ( $type ) {
			case 'text':
			case 'textarea':
				$acf_field['maxlength'] = '';
				$acf_field['placeholder'] = '';
				if ( 'textarea' === $type ) {
					$acf_field['rows']      = $field['rows'] ?? 4;
					$acf_field['new_lines'] = $field['new_lines'] ?? 'wpautop';
				}
				break;

			case 'image':
				$acf_field['return_format'] = 'array';
				$acf_field['preview_size']  = 'medium';
				$acf_field['library']       = 'all';
				break;

			case 'gallery':
				$acf_field['return_format'] = 'array';
				$acf_field['preview_size']  = 'medium';
				$acf_field['library']       = 'all';
				break;

			case 'link':
				$acf_field['return_format'] = 'array';
				break;

			case 'wysiwyg':
				$acf_field['tabs']         = 'all';
				$acf_field['toolbar']      = 'full';
				$acf_field['media_upload'] = 1;
				break;

			case 'true_false':
				$acf_field['ui']           = 1;
				$acf_field['default_value'] = $field['default_value'] ?? 0;
				break;

			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$acf_field['choices']       = $field['choices'] ?? array();
				$acf_field['return_format'] = 'value';
				if ( 'select' === $type ) {
					$acf_field['multiple'] = 0;
				}
				break;

			case 'repeater':
				$acf_field['layout']     = 'block';
				$acf_field['min']        = 0;
				$acf_field['max']        = 0;
				$acf_field['sub_fields'] = $this->build_sub_fields( $field['sub_fields'] ?? array(), $key );
				break;

			case 'flexible_content':
				$acf_field['layouts'] = $this->build_flexible_layouts( $field['layouts'] ?? array() );
				break;

			case 'group':
				$acf_field['layout']     = 'block';
				$acf_field['sub_fields'] = $this->build_sub_fields( $field['sub_fields'] ?? array(), $key );
				break;

			case 'accordion':
			case 'tab':
			case 'message':
				// Layout fields — minimal config.
				break;

			case 'number':
			case 'range':
				$acf_field['min']  = '';
				$acf_field['max']  = '';
				$acf_field['step'] = '';
				break;

			case 'color_picker':
				$acf_field['enable_opacity'] = 0;
				break;

			case 'date_picker':
				$acf_field['display_format'] = 'F j, Y';
				$acf_field['return_format']  = 'Y-m-d';
				break;

			case 'relationship':
			case 'post_object':
				$acf_field['post_type']     = $field['post_type'] ?? array();
				$acf_field['return_format'] = 'object';
				break;

			case 'taxonomy':
				$acf_field['taxonomy']       = $field['taxonomy'] ?? 'category';
				$acf_field['return_format'] = 'object';
				break;

			case 'file':
				$acf_field['return_format'] = 'array';
				$acf_field['library']       = 'all';
				break;
		}

		return $acf_field;
	}

	/**
	 * Build sub-fields for repeater/group.
	 *
	 * @param array  $sub_fields Sub field definitions.
	 * @param string $parent_key Parent key.
	 * @return array
	 */
	private function build_sub_fields( $sub_fields, $parent_key ) {
		$fields = array();

		foreach ( $sub_fields as $sub ) {
			$fields[] = $this->build_field( $sub, $parent_key );
		}

		return $fields;
	}

	/**
	 * Build flexible content layouts.
	 *
	 * @param array $layouts Layout definitions.
	 * @return array
	 */
	private function build_flexible_layouts( $layouts ) {
		$acf_layouts = array();

		foreach ( $layouts as $layout ) {
			$layout_key = Slug_Helper::field_key( 'layout' );

			$acf_layouts[ $layout_key ] = array(
				'key'        => $layout_key,
				'name'       => $layout['name'] ?? 'layout',
				'label'      => $layout['label'] ?? 'Layout',
				'display'    => 'block',
				'sub_fields' => $this->build_sub_fields( $layout['sub_fields'] ?? array(), $layout_key ),
			);
		}

		return $acf_layouts;
	}
}
