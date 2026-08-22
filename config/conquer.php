<?php

return [
    'interests' => [
        'Cycling',
        'Duathlon',
        'Hiking',
        'Marathon',
        'Trail Run',
        'Triathlon',
    ],

    'event_interest_types' => [
        'Cycling',
        'Duathlon',
        'Hiking',
        'Marathon',
        'Trail Run',
        'Triathlon',
    ],

    'event_category_labels' => [
        'Cycling' => 'Ride Categories',
        'Duathlon' => 'Competition Categories',
        'Hiking' => 'Hiking Routes / Registration Options',
        'Marathon' => 'Race Categories',
        'Trail Run' => 'Race Categories',
        'Triathlon' => 'Competition Categories',
    ],

    'event_type_details' => [
        'Cycling' => [
            'route_distance_km' => ['label' => 'Route Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'surface_type' => ['label' => 'Surface Type', 'type' => 'select', 'options' => ['Road', 'Gravel', 'Trail', 'Mixed'], 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
            'elevation_gain_m' => ['label' => 'Elevation Gain', 'type' => 'number', 'suffix' => 'm', 'rules' => ['nullable', 'numeric', 'min:0'], 'required_for_publication' => false],
            'bike_type' => ['label' => 'Bike Type', 'type' => 'select', 'options' => ['Road Bike', 'Mountain Bike', 'Gravel Bike', 'Any Bike'], 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
            'helmet_required' => ['label' => 'Helmet Required', 'type' => 'boolean', 'rules' => ['required', 'boolean'], 'required_for_publication' => true],
        ],
        'Hiking' => [
            'trail_length_km' => ['label' => 'Trail Length', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'difficulty' => ['label' => 'Difficulty', 'type' => 'select', 'options' => ['Easy', 'Moderate', 'Difficult', 'Expert'], 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
            'elevation_gain_m' => ['label' => 'Elevation Gain', 'type' => 'number', 'suffix' => 'm', 'rules' => ['nullable', 'numeric', 'min:0'], 'required_for_publication' => false],
            'estimated_duration' => ['label' => 'Estimated Duration', 'type' => 'text', 'placeholder' => 'e.g. 4-5 hours', 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
            'required_gear' => ['label' => 'Required Gear', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'required_for_publication' => false, 'category_owned' => true],
        ],
        'Marathon' => [
            'distances' => ['label' => 'Distances', 'type' => 'text', 'placeholder' => 'e.g. 5K, 10K, 21K, 42K', 'rules' => ['nullable', 'string', 'max:255'], 'required_for_publication' => false, 'category_owned' => true],
            'cutoff_time' => ['label' => 'Cutoff Time', 'type' => 'text', 'placeholder' => 'e.g. 6 hours', 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
        ],
        'Trail Run' => [
            'distance_km' => ['label' => 'Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'trail_difficulty' => ['label' => 'Trail Difficulty', 'type' => 'select', 'options' => ['Easy', 'Moderate', 'Difficult', 'Technical'], 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => false, 'category_owned' => true],
            'elevation_gain_m' => ['label' => 'Elevation Gain', 'type' => 'number', 'suffix' => 'm', 'rules' => ['nullable', 'numeric', 'min:0'], 'required_for_publication' => false],
            'terrain' => ['label' => 'Terrain', 'type' => 'text', 'placeholder' => 'e.g. rocky, muddy, forest trail', 'rules' => ['nullable', 'string', 'max:255'], 'required_for_publication' => false],
            'mandatory_gear' => ['label' => 'Mandatory Gear', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'required_for_publication' => false, 'category_owned' => true],
            'cutoff_time' => ['label' => 'Cutoff Time', 'type' => 'text', 'placeholder' => 'e.g. 8 hours', 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
        ],
        'Triathlon' => [
            'swim_distance_m' => ['label' => 'Swim Distance', 'type' => 'number', 'suffix' => 'm', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'swim_type' => ['label' => 'Swim Type', 'type' => 'select', 'options' => ['Pool', 'Open Water'], 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
            'bike_distance_km' => ['label' => 'Bike Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'run_distance_km' => ['label' => 'Run Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'transition_details' => ['label' => 'Transition Details', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'required_for_publication' => true],
            'cutoff_time' => ['label' => 'Cutoff Time', 'type' => 'text', 'placeholder' => 'e.g. 8 hours', 'rules' => ['nullable', 'string', 'max:100'], 'required_for_publication' => true],
        ],
        'Duathlon' => [
            'first_run_distance_km' => ['label' => 'First-run Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'bike_distance_km' => ['label' => 'Bike Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'second_run_distance_km' => ['label' => 'Second-run Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['nullable', 'numeric', 'min:0.01'], 'required_for_publication' => false, 'category_owned' => true],
            'transition_details' => ['label' => 'Transition Details', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'required_for_publication' => true],
        ],
    ],

    'event_category_type_details' => [
        'Hiking' => [
            'required_gear' => [
                'label' => 'Required Gear',
                'type' => 'textarea',
                'placeholder' => 'List the gear required for this route or registration option.',
                'rules' => ['required', 'string', 'max:2000'],
                'required_for_publication' => true,
            ],
        ],
        'Trail Run' => [
            'trail_difficulty' => [
                'label' => 'Trail Difficulty',
                'type' => 'select',
                'options' => ['Easy', 'Moderate', 'Difficult', 'Technical'],
                'rules' => ['nullable', 'string', 'max:100'],
                'required_for_publication' => false,
            ],
            'mandatory_gear' => [
                'label' => 'Mandatory Gear',
                'type' => 'textarea',
                'placeholder' => 'List the mandatory gear for this race category.',
                'rules' => ['required', 'string', 'max:2000'],
                'required_for_publication' => true,
            ],
        ],
        'Triathlon' => [
            'swim_distance_m' => ['label' => 'Swim Distance', 'type' => 'number', 'suffix' => 'm', 'rules' => ['required', 'numeric', 'min:0.01'], 'locked_when_in_use' => true],
            'bike_distance_km' => ['label' => 'Bike Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['required', 'numeric', 'min:0.01'], 'locked_when_in_use' => true],
            'run_distance_km' => ['label' => 'Run Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['required', 'numeric', 'min:0.01'], 'locked_when_in_use' => true],
        ],
        'Duathlon' => [
            'first_run_distance_km' => ['label' => 'First-run Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['required', 'numeric', 'min:0.01'], 'locked_when_in_use' => true],
            'bike_distance_km' => ['label' => 'Bike Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['required', 'numeric', 'min:0.01'], 'locked_when_in_use' => true],
            'second_run_distance_km' => ['label' => 'Second-run Distance', 'type' => 'number', 'suffix' => 'km', 'rules' => ['required', 'numeric', 'min:0.01'], 'locked_when_in_use' => true],
        ],
    ],

    'shirt_sizes' => [
        'XS',
        'S',
        'M',
        'L',
        'XL',
        '2XL',
        '3XL',
        'Small',
        'Medium',
        'Large',
        'Extra Large',
    ],
];
