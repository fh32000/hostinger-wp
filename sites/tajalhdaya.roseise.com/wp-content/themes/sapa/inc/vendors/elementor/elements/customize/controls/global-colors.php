<?php
if (!function_exists('sapa_settings_global_colors_advanced')) {
    function sapa_settings_global_colors_advanced($widget, $args)
    {
		$active_theme = sapa_tbay_get_theme();

		switch ($active_theme) {
			case 'headphone':
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#CBFF0F',
					],
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#A2BC00',
					],
				];

				break; 

			case 'headphone-2':
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#abffff',
					],
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#017bc2',
					],
				];

				break; 

			case 'glasses':
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#0000ff',
					], 
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#CA6D6F',
					],
				];

				break; 

			case 'shoes':
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#CA6D6F',
					], 
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#CA6D6F',
					],
				];

				break; 
				
			case 'bicycle':
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#D2D21A',
					], 
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#6BD21A',
					],
				];

				break; 
				
			case 'drone':
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#01B1EC',
					], 
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#CA6D6F',
					],
				];

				break; 
				
			case 'cosmetics':
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#8dcc11',
					], 
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#CA6D6F',
					],
				];

				break; 

			default:
				$default_colors_primary = [
					[
						'_id' => 'primary',
						'title' => esc_html__( 'Primary', 'sapa' ),
						'color' => '#FF6A25',
					],
				];

				$default_colors_secondary = [
					[
						'_id' => 'secondary',
						'title' => esc_html__( 'Secondary', 'sapa' ),
						'color' => '#CA6D6F',
					],
				];
		}

		$default_colors = [
			[
				'_id' => 'text',
				'title' => esc_html__( 'Text', 'sapa' ),
				'color' => '#121216',
			],
			[
				'_id' => 'accent',
				'title' => esc_html__( 'Border', 'sapa' ),
				'color' => '#DDDDDD',
			],
		];


		$default_colors = array_merge($default_colors_primary, $default_colors_secondary, $default_colors);

        $widget->update_control(
            'system_colors',
            [
                'default' => $default_colors,
            ] 
        );
    }

    add_action('elementor/element/kit/section_global_colors/before_section_end', 'sapa_settings_global_colors_advanced', 10, 2);
}