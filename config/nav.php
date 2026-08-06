<?php

/**
 * Sidebar navigation configuration.
 * Edit this file to add / rename / reorder menu items.
 * Each item: label, bootstrap-icon class, color class (ic-*), optional badge, href.
 * An item with 'children' renders as a collapsible group.
 *
 * Sections: key = section name, value = [
 *   'show_label' => bool,      // whether to render the uppercase section label
 *   'items'      => array,     // menu items
 * ]
 */

return [

    'profile' => [
        'name'    => 'Jackie',
        'role'    => 'System Analyst',
        'initial' => 'J',
    ],

    'sections' => [

        'Browse' => [
            'show_label' => false,
            'items' => [
                ['label' => 'Dashboards',       'icon' => 'bi-speedometer2',          'color' => 'ic-blue',   'href' => '/dashboard'],
                ['label' => 'Create SEN',  'icon' => 'bi-window-sidebar',        'color' => 'ic-green',  'href' => '/admin/create-sen'],
                ['label' => 'SEN Search',        'icon' => 'bi-layout-sidebar-inset',  'color' => 'ic-pink',   'href' => '#'],
                ['label' => 'User Management',       'icon' => 'bi-star',          'color' => 'ic-yellow', 'href' => '#'],
                ['label' => 'Data Import', 'icon' => 'bi-clock-history', 'color' => 'ic-cyan',   'href' => '#'],
                [
                    'label' => 'Admin', 'icon' => 'bi-collection', 'color' => 'ic-yellow',
                    'children' => [
                        ['label' => 'Role List',     'icon' => 'bi-search',          'color' => 'ic-green',  'href' => '/admin/role-list'],
                        ['label' => 'Target User List',   'icon' => 'bi-megaphone',       'color' => 'ic-green',  'href' => '/admin/target-user-list'],
                        ['label' => 'Staff List',     'icon' => 'bi-signpost-split',  'color' => 'ic-purple', 'href' => '/admin/staff-list'],
                        ['label' => 'Student List',    'icon' => 'bi-people',          'color' => 'ic-blue',   'href' => '/admin/student-list'],
                        ['label' => 'Subject/Lecture List',     'icon' => 'bi-star',  'color' => 'ic-yellow', 'href' => '/admin/subject-list'],
                        ['label' => 'Student Registration',     'icon' => 'bi-clock-history',  'color' => 'ic-cyan', 'href' => '/admin/student-registration-list'],
                        ['label' => 'Advisor List',     'icon' => 'bi-bookmark',  'color' => 'ic-blue', 'href' => '/admin/advisor-list'],
                        ['label' => 'Fund Type',     'icon' => 'bi-question-circle',  'color' => 'ic-purple', 'href' => '/admin/fund-type-list'],
                        ['label' => 'Student Status',     'icon' => 'bi-keyboard',  'color' => 'ic-green', 'href' => '/admin/student-status-list'],
                        ['label' => 'Subject Type',     'icon' => 'bi-window-sidebar',  'color' => 'ic-blue', 'href' => '/admin/subject-type-list'],
                        ['label' => 'Advisor Type',     'icon' => 'bi-layout-sidebar-inset',  'color' => 'ic-pink', 'href' => '/admin/advisor-type-list'],
                        ['label' => 'SEN Type',     'icon' => 'bi-star',  'color' => 'ic-yellow', 'href' => '#'],
                        ['label' => 'Academic Year Semester',     'icon' => 'bi-signpost-split',  'color' => 'ic-cyan', 'href' => '/admin/academic-year-semester-list'],
                    ],
                ],
                ['label' => 'Housekeeping',           'icon' => 'bi-bookmark',      'color' => 'ic-purple',   'href' => '#'],
            ],
        ],

    ],
];
