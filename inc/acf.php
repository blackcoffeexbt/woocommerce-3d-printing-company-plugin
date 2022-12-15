<?php
if (function_exists('acf_add_local_field_group')):

    acf_add_local_field_group(array(
        'key'                   => 'group_6399f3139289d',
        'title'                 => 'Product',
        'fields'                => array(
            array(
                'key'               => 'field_6399f31ee1447',
                'label'             => 'Has 3D Printed Components?',
                'name'              => 'has_3d_printed_components',
                'type'              => 'radio',
                'instructions'      => 'Does this product include 3D printed components that need to be manufactured?',
                'required'          => 1,
                'conditional_logic' => 0,
                'wrapper'           => array(
                    'width' => '',
                    'class' => '',
                    'id'    => '',
                ),
                'choices'           => array(
                    'yes' => 'Yes',
                    'no'  => 'No',
                ),
                'allow_null'        => 1,
                'other_choice'      => 0,
                'default_value'     => '',
                'layout'            => 'vertical',
                'return_format'     => 'value',
                'save_other_choice' => 0,
            ),
        ),
        'location'              => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'product',
                ),
            ),
        ),
        'menu_order'            => 0,
        'position'              => 'acf_after_title',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen'        => '',
        'active'                => true,
        'description'           => '',
        'show_in_rest'          => 0,
    ));

endif;
