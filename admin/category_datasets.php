<?php
return [
  'barangay' => 
  [
    'label' => 'Barangays',
    'singular' => 'Barangay',
    'table' => 'barangay',
    'order_by' => 'barangay_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'barangay_id',
      'auto_increment' => false,
      'label' => 'Barangay ID',
      'type' => 'number',
      'immutable' => true,
    ],
    'display_field' => 'barangay_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'barangay_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'barangay_name',
        'label' => 'Barangay',
      ],
      2 => 
      [
        'key' => 'city_mun_id',
        'label' => 'City / Mun ID',
      ],
      3 => 
      [
        'key' => 'city_mun_code',
        'label' => 'City / Mun Code',
      ],
    ],
    'form_fields' => 
    [
      'barangay_name' => 
      [
        'label' => 'Barangay Name',
        'type' => 'text',
        'required' => true,
      ],
      'city_mun_id' => 
      [
        'label' => 'City / Mun ID',
        'type' => 'number',
        'required' => true,
      ],
      'city_mun_code' => 
      [
        'label' => 'City / Mun Code',
        'type' => 'text',
        'required' => false,
      ],
      'province_code' => 
      [
        'label' => 'Province Code',
        'type' => 'text',
        'required' => false,
      ],
      'barangay_code' => 
      [
        'label' => 'Barangay Code',
        'type' => 'text',
        'required' => false,
      ],
    ],
  ],
  'city_mun' => 
  [
    'label' => 'Cities / Municipalities',
    'singular' => 'City / Municipality',
    'table' => 'city_mun',
    'order_by' => 'city_mun_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'city_mun_id',
      'auto_increment' => false,
      'label' => 'City / Mun ID',
      'type' => 'number',
      'immutable' => true,
    ],
    'display_field' => 'city_mun_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'city_mun_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'city_mun_name',
        'label' => 'City / Municipality',
      ],
      2 => 
      [
        'key' => 'province_code',
        'label' => 'Province Code',
      ],
    ],
    'form_fields' => 
    [
      'city_mun_name' => 
      [
        'label' => 'City / Municipality',
        'type' => 'text',
        'required' => true,
      ],
      'province_code' => 
      [
        'label' => 'Province Code',
        'type' => 'text',
        'required' => false,
      ],
    ],
  ],
  'education_level' => 
  [
    'label' => 'Education Levels',
    'singular' => 'Education Level',
    'table' => 'education_level',
    'order_by' => 'education_level_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'education_level_id',
      'auto_increment' => true,
      'label' => 'Education Level ID',
    ],
    'display_field' => 'education_level_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'education_level_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'education_level_name',
        'label' => 'Level Name',
      ],
      2 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'datetime',
      ],
    ],
    'form_fields' => 
    [
      'education_level_name' => 
      [
        'label' => 'Level Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'experience_level' => 
  [
    'label' => 'Experience Levels',
    'singular' => 'Experience Level',
    'table' => 'experience_level',
    'order_by' => 'experience_level_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'experience_level_id',
      'auto_increment' => true,
      'label' => 'Experience Level ID',
    ],
    'display_field' => 'experience_level_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'experience_level_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'experience_level_name',
        'label' => 'Level Name',
      ],
      2 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'datetime',
      ],
    ],
    'form_fields' => 
    [
      'experience_level_name' => 
      [
        'label' => 'Level Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'industry' => 
  [
    'label' => 'Industries',
    'singular' => 'Industry',
    'table' => 'industry',
    'order_by' => 'industry_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'industry_id',
      'auto_increment' => true,
      'label' => 'Industry ID',
    ],
    'display_field' => 'industry_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'industry_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'industry_name',
        'label' => 'Industry Name',
      ],
      2 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'datetime',
      ],
    ],
    'form_fields' => 
    [
      'industry_name' => 
      [
        'label' => 'Industry Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'job_category' => 
  [
    'label' => 'Job Categories',
    'singular' => 'Job Category',
    'table' => 'job_category',
    'order_by' => 'job_category_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'job_category_id',
      'auto_increment' => true,
      'label' => 'Category ID',
    ],
    'display_field' => 'job_category_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'job_category_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'job_category_name',
        'label' => 'Category Name',
      ],
      2 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'date',
      ],
    ],
    'form_fields' => 
    [
      'job_category_name' => 
      [
        'label' => 'Category Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'job_id' => 
  [
    'label' => 'jobaydi',
    'singular' => 'jobaydi',
    'table' => 'jobaydi',
    'order_by' => 'jobaydi_name ASC',
    'per_page' => 10,
    'primary_key' => 
    [
      'field' => 'id',
      'auto_increment' => true,
      'label' => 'ID',
    ],
    'display_field' => 'jobaydi_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => false,
      'restore' => false,
    ],
    'archive' => 
    [
      'enabled' => false,
      'column' => '',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'jobaydi_name',
        'label' => 'Jobaydi Name',
      ],
      1 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'datetime',
      ],
      2 => 
      [
        'key' => 'updated_at',
        'label' => 'Updated At',
        'format' => 'datetime',
      ],
    ],
    'form_fields' => 
    [
      'jobaydi_name' => 
      [
        'label' => 'Jobaydi Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'job_status' => 
  [
    'label' => 'Job Statuses',
    'singular' => 'Job Status',
    'table' => 'job_status',
    'order_by' => 'job_status_id ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'job_status_id',
      'auto_increment' => false,
      'label' => 'Status ID',
      'type' => 'number',
      'immutable' => true,
    ],
    'display_field' => 'job_status_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'job_status_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'job_status_name',
        'label' => 'Status Name',
      ],
      2 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'date',
      ],
    ],
    'form_fields' => 
    [
      'job_status_name' => 
      [
        'label' => 'Status Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'job_type' => 
  [
    'label' => 'Job Types',
    'singular' => 'Job Type',
    'table' => 'job_type',
    'order_by' => 'job_type_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'job_type_id',
      'auto_increment' => true,
      'label' => 'Type ID',
    ],
    'display_field' => 'job_type_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'job_type_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'job_type_name',
        'label' => 'Type Name',
      ],
    ],
    'form_fields' => 
    [
      'job_type_name' => 
      [
        'label' => 'Type Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'proficiency_level' => 
  [
    'label' => 'Proficiency Levels',
    'singular' => 'Proficiency Level',
    'table' => 'proficiency_level',
    'order_by' => 'proficiency_level ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'proficiency_id',
      'auto_increment' => true,
      'label' => 'Proficiency ID',
    ],
    'display_field' => 'proficiency_level',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'proficiency_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'proficiency_level',
        'label' => 'Level Name',
      ],
      2 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'datetime',
      ],
    ],
    'form_fields' => 
    [
      'proficiency_level' => 
      [
        'label' => 'Level Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'skills' => 
  [
    'label' => 'Skills Library',
    'singular' => 'Skill',
    'table' => 'skills',
    'order_by' => 'name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'skill_id',
      'auto_increment' => true,
      'label' => 'Skill ID',
    ],
    'display_field' => 'name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'skill_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'name',
        'label' => 'Skill Name',
      ],
      2 => 
      [
        'key' => 'job_category_id',
        'label' => 'Job Category ID',
      ],
      3 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'datetime',
      ],
    ],
    'form_fields' => 
    [
      'name' => 
      [
        'label' => 'Skill Name',
        'type' => 'text',
        'required' => true,
      ],
      'job_category_id' => 
      [
        'label' => 'Job Category ID',
        'type' => 'number',
        'required' => false,
      ],
    ],
  ],
  'user_status' => 
  [
    'label' => 'User Statuses',
    'singular' => 'User Status',
    'table' => 'user_status',
    'order_by' => 'user_status_id ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'user_status_id',
      'auto_increment' => true,
      'label' => 'Status ID',
    ],
    'display_field' => 'user_status_description',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'user_status_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'user_status_description',
        'label' => 'Status Name',
      ],
      2 => 
      [
        'key' => 'user_status_created_at',
        'label' => 'Created On',
        'format' => 'date',
      ],
      3 => 
      [
        'key' => 'user_status_updated_at',
        'label' => 'Updated On',
        'format' => 'date',
      ],
    ],
    'form_fields' => 
    [
      'user_status_description' => 
      [
        'label' => 'Status Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
  'work_setup' => 
  [
    'label' => 'Work Setups',
    'singular' => 'Work Setup',
    'table' => 'work_setup',
    'order_by' => 'work_setup_name ASC',
    'per_page' => 9,
    'primary_key' => 
    [
      'field' => 'work_setup_id',
      'auto_increment' => true,
      'label' => 'Work Setup ID',
    ],
    'display_field' => 'work_setup_name',
    'actions' => 
    [
      'create' => true,
      'update' => true,
      'delete' => false,
      'archive' => true,
      'restore' => true,
    ],
    'archive' => 
    [
      'enabled' => true,
      'column' => 'is_archived',
    ],
    'columns' => 
    [
      0 => 
      [
        'key' => 'work_setup_id',
        'label' => 'ID',
      ],
      1 => 
      [
        'key' => 'work_setup_name',
        'label' => 'Setup Name',
      ],
      2 => 
      [
        'key' => 'created_at',
        'label' => 'Created At',
        'format' => 'datetime',
      ],
    ],
    'form_fields' => 
    [
      'work_setup_name' => 
      [
        'label' => 'Setup Name',
        'type' => 'text',
        'required' => true,
      ],
    ],
  ],
];
