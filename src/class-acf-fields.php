<?php
namespace Aviation_Scholarships;

if (!defined('ABSPATH')) exit;

class ACF_Fields {

    public static function register() {

        if( function_exists('acf_add_local_field_group') ):

            acf_add_local_field_group(array(
                'key' => 'group_scholarship_details',
                'title' => 'Scholarship Details',
                'fields' => array(

                    // Deadline
                    array(
                        'key' => 'field_sch_deadline',
                        'label' => 'Deadline',
                        'name' => 'sch_deadline',
                        'type' => 'date_picker',
                        'required' => 1,
                        'display_format' => 'Y-m-d',
                        'return_format' => 'Y-m-d',
                        'first_day' => 1,
                    ),

                    // Number of Awards
                    array(
                        'key' => 'field_sch_num_awards',
                        'label' => 'Number of Awards',
                        'name' => 'sch_num_awards',
                        'type' => 'number',
                        'min' => 0,
                        'step' => 1,
                    ),

                    // Maximum Amount
                    array(
                        'key' => 'field_sch_max_amount',
                        'label' => 'Maximum Amount ($)',
                        'name' => 'sch_max_amount',
                        'type' => 'number',
                        'min' => 0,
                        'step' => 100,
                    ),

                    // College Program
                    array(
                        'key' => 'field_sch_college_program',
                        'label' => 'College Program?',
                        'name' => 'sch_college_program',
                        'type' => 'text',
                    ),

                    // Eligibility
                    array(
                        'key' => 'field_sch_eligibility',
                        'label' => 'Eligibility',
                        'name' => 'sch_eligibility',
                        'type' => 'select',
                        'choices' => array(
                            'every' => 'Everyone',
                            'female' => 'Female Only',
                            'minority' => 'Minority',
                        ),
                        'default_value' => 'every',
                        'allow_null' => 0,
                        'multiple' => 0,
                    ),

                    // Location
                    array(
                        'key' => 'field_sch_location',
                        'label' => 'Location',
                        'name' => 'sch_location',
                        'type' => 'text',
                    ),

                    // External Link
                    array(
                        'key' => 'field_sch_link',
                        'label' => 'Application Link',
                        'name' => 'sch_link',
                        'type' => 'url',
                    ),

                    // Status
                    array(
                        'key' => 'field_sch_status',
                        'label' => 'Status',
                        'name' => 'sch_status',
                        'type' => 'select',
                        'choices' => array(
                            'active' => 'Active',
                            'expired' => 'Expired',
                            'discontinued' => 'Discontinued',
                        ),
                        'default_value' => 'active',
                    ),

                    // Source ID (hidden)
                    array(
                        'key' => 'field_sch_source_id',
                        'label' => 'Source Row ID',
                        'name' => 'sch_source_id',
                        'type' => 'text',
                        'wrapper' => array('class' => 'acf-hidden'),
                    ),

                    // Raw JSON (hidden)
                    array(
                        'key' => 'field_sch_raw',
                        'label' => 'Raw Import Data',
                        'name' => 'sch_raw',
                        'type' => 'textarea',
                        'wrapper' => array('class' => 'acf-hidden'),
                    ),

                ),
                'location' => array(
                    array(
                        array(
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'scholarship',
                        ),
                    ),
                ),
            ));

        endif;
    }
}
