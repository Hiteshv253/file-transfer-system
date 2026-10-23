<?php

return [
    'positions' => array_filter([
        [
            'name' => 'drive-navbar',
            'label' => 'Drive navbar',
            'route' => '/drive',
        ],
        [
            'name' => 'drive-sidebar',
            'label' => 'Drive sidebar',
            'route' => '/drive',
        ],
        [
            'name' => 'homepage-navbar',
            'label' => 'Homepage navbar',
            'route' => '/',
        ],
        [
            'name' => 'shareable-link-page',
            'label' => 'Shareable link page',
            'route' => null,
        ],
        [
            'name' => 'footer',
            'label' => 'Footer',
            'route' => '/',
        ],
        [
            'name' => 'footer-secondary',
            'label' => 'Footer secondary',
            'route' => '/',
        ],
    ]),
    'available_routes' => array_filter([
        '/drive',
        '/drive/shares',
        '/drive/recent',
        '/drive/starred',
        '/drive/trash',
    ]),
];
