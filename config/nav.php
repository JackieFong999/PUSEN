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
                // Hidden 2026-08-19 (demo purpose; restore in future):
                // ['label' => 'Dashboards',       'icon' => 'bi-speedometer2',          'color' => 'ic-blue',   'href' => '/dashboard', 'roles' => ['SA', 'AU']],
                ['label' => 'SEN Search',        'icon' => 'bi-layout-sidebar-inset',  'color' => 'ic-pink',   'href' => '/admin/sen-search', 'roles' => ['SA', 'AU', 'KS']],
                ['label' => 'Create SEN',  'icon' => 'bi-window-sidebar',        'color' => 'ic-green',  'href' => '/admin/create-sen', 'roles' => ['SA', 'AU']],
                ['label' => 'Email Management', 'icon' => 'bi-envelope-paper', 'color' => 'ic-orange', 'href' => '/admin/email-management', 'roles' => ['SA']],
                // Hidden 2026-08-12 (requested by Jackie):
                // ['label' => 'User Management',       'icon' => 'bi-star',          'color' => 'ic-yellow', 'href' => '#'],
                ['label' => 'Data Import', 'icon' => 'bi-clock-history', 'color' => 'ic-cyan',   'href' => '/admin/data-import', 'roles' => ['SA']],
                [
                    'label' => 'Admin', 'icon' => 'bi-collection', 'color' => 'ic-yellow', 'roles' => ['SA'],
                    'children' => [
                        ['label' => 'Staff List',     'icon' => 'bi-signpost-split',  'color' => 'ic-purple', 'href' => '/admin/staff-list'],
                        ['label' => 'Student List',    'icon' => 'bi-people',          'color' => 'ic-blue',   'href' => '/admin/student-list'],
                        ['label' => 'Email Template',    'icon' => 'bi-envelope',          'color' => 'ic-cyan',   'href' => '/admin/email-template-list'],
                        // Hidden 2026-08-12 (requested by Jackie):
                        // ['label' => 'Role List',     'icon' => 'bi-search',          'color' => 'ic-green',  'href' => '/admin/role-list'],
                        // ['label' => 'Target User List',   'icon' => 'bi-megaphone',       'color' => 'ic-green',  'href' => '/admin/target-user-list'],
                        ['label' => 'Subject/Lecture List',     'icon' => 'bi-star',  'color' => 'ic-yellow', 'href' => '/admin/subject-list'],
                        ['label' => 'Student Registration',     'icon' => 'bi-clock-history',  'color' => 'ic-cyan', 'href' => '/admin/student-registration-list'],
                        ['label' => 'Advisor List for the Student',     'icon' => 'bi-bookmark',  'color' => 'ic-blue', 'href' => '/admin/advisor-list'],
                        // ['label' => 'Fund Type',     'icon' => 'bi-question-circle',  'color' => 'ic-purple', 'href' => '/admin/fund-type-list'],
                        // ['label' => 'Student Status',     'icon' => 'bi-keyboard',  'color' => 'ic-green', 'href' => '/admin/student-status-list'],
                        ['label' => 'SEN Type',          'icon' => 'bi-bandaid',    'color' => 'ic-yellow', 'href' => '/admin/sen-type-list'],
                        // ['label' => 'Subject Type',     'icon' => 'bi-window-sidebar',  'color' => 'ic-blue', 'href' => '/admin/subject-type-list'],
                        // ['label' => 'Advisor Type',     'icon' => 'bi-layout-sidebar-inset',  'color' => 'ic-pink', 'href' => '/admin/advisor-type-list'],
                        ['label' => 'Academic Year Semester',     'icon' => 'bi-signpost-split',  'color' => 'ic-cyan', 'href' => '/admin/academic-year-semester-list'],
                        ['label' => 'Temporary Special Support',     'icon' => 'bi-heart-pulse',  'color' => 'ic-pink', 'href' => '/admin/temporary-special-support-list'],
                    ],
                ],
                ['label' => 'Housekeeping',           'icon' => 'bi-bookmark',      'color' => 'ic-purple',   'href' => '/admin/housekeeping', 'roles' => ['SA']],
            ],
        ],

    ],
];
