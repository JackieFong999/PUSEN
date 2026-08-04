<?php

/**
 * Sidebar navigation configuration.
 * Edit this file to add / rename / reorder menu items.
 * Each item: label, bootstrap-icon class, color class (ic-*), optional badge, href.
 * An item with 'children' renders as a collapsible group.
 */

return [

    'profile' => [
        'name'    => 'Jackie',
        'role'    => 'System Analyst',
        'initial' => 'J',
    ],

    'sections' => [

        'Browse' => [
            ['label' => 'All Types',        'icon' => 'bi-grid-1x2-fill',        'color' => 'ic-gray',   'badge' => '248', 'href' => '/dashboard'],
            ['label' => 'Dashboards',       'icon' => 'bi-speedometer2',          'color' => 'ic-blue',   'badge' => '64',  'href' => '#'],
            ['label' => 'Navigation Bars',  'icon' => 'bi-window-sidebar',        'color' => 'ic-green',  'badge' => '82',  'href' => '#'],
            ['label' => 'Menu Bars',        'icon' => 'bi-layout-sidebar-inset',  'color' => 'ic-pink',   'badge' => '47',  'href' => '#'],
            [
                'label' => 'More Types', 'icon' => 'bi-collection', 'color' => 'ic-yellow',
                'children' => [
                    ['label' => 'Search Bars',     'icon' => 'bi-search',          'color' => 'ic-green',  'href' => '#'],
                    ['label' => 'Announcements',   'icon' => 'bi-megaphone',       'color' => 'ic-green',  'href' => '#'],
                    ['label' => 'Breadcrumbs',     'icon' => 'bi-signpost-split',  'color' => 'ic-purple', 'href' => '#'],
                ],
            ],
        ],

        'Collections' => [
            ['label' => 'Favorites',       'icon' => 'bi-star',          'color' => 'ic-yellow', 'badge' => '12', 'href' => '#'],
            ['label' => 'Recently Viewed', 'icon' => 'bi-clock-history', 'color' => 'ic-cyan',   'href' => '#'],
            ['label' => 'Saved',           'icon' => 'bi-bookmark',      'color' => 'ic-pink',   'href' => '#'],
        ],

        'Support' => [
            ['label' => 'Help Center',        'icon' => 'bi-question-circle', 'color' => 'ic-blue',   'href' => '#'],
            ['label' => 'Keyboard Shortcuts', 'icon' => 'bi-keyboard',        'color' => 'ic-purple', 'href' => '#'],
            ['label' => 'Send Feedback',      'icon' => 'bi-chat-dots',       'color' => 'ic-green',  'href' => '#'],
        ],
    ],
];
