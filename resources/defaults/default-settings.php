<?php

return [
    // logos
    [
        'name' => 'branding.logo_dark',
        'value' => 'images/logo-dark.png',
    ],
    [
        'name' => 'branding.logo_light',
        'value' => 'images/logo-light.png',
    ],

    //cache
    ['name' => 'cache.report_minutes', 'value' => 60],

    // homepage
    ['name' => 'homepage.type', 'value' => 'loginPage'],

    //menus
    [
        'name' => 'menus',
        'value' => json_encode([
            [
                'name' => 'Drive Sidebar',
                'positions' => ['drive-sidebar'],
                'id' => 'v01akw',
                'items' => [
                    [
                        'id' => 'wkd771',
                        'type' => 'route',
                        'order' => 1,
                        'label' => 'Shared with me',
                        'action' => '/drive/shares',
                    ],
                    [
                        'id' => 'jo2m1s',
                        'type' => 'route',
                        'order' => 2,
                        'label' => 'Recent',
                        'action' => '/drive/recent',
                    ],
                    [
                        'id' => '4e6cie',
                        'type' => 'route',
                        'order' => 3,
                        'label' => 'Starred',
                        'action' => '/drive/starred',
                    ],
                    [
                        'id' => 'h5p54n',
                        'type' => 'route',
                        'order' => 4,
                        'label' => 'Trash',
                        'action' => '/drive/trash',
                    ],
                ],
            ],
            [
                'name' => 'footer',
                'id' => '4tbwog',
                'positions' => ['footer'],
                'items' => [
                    [
                        'type' => 'route',
                        'id' => 'c1sf2g',
                        'position' => 1,
                        'label' => 'Developers',
                        'action' => '/api-docs',
                        'condition' => 'auth',
                    ],
                    [
                        'type' => 'route',
                        'id' => 'rlz27v',
                        'position' => 2,
                        'label' => 'Privacy Policy',
                        'action' => '/pages/privacy-policy',
                    ],
                    [
                        'type' => 'route',
                        'id' => 'p80pvk',
                        'position' => 3,
                        'label' => 'Terms of Service',
                        'action' => '/pages/terms-of-service',
                    ],
                    [
                        'type' => 'route',
                        'id' => 'q8dtht',
                        'position' => 4,
                        'label' => 'Contact Us',
                        'action' => '/contact',
                    ],
                ],
            ],
            [
                'name' => 'Footer Social',
                'id' => 'odw4ah',
                'positions' => ['footer-secondary'],
                'items' => [
                    [
                        'type' => 'link',
                        'id' => '6j747e',
                        'position' => 1,
                        'action' => 'https://facebook.com',
                    ],
                    [
                        'type' => 'link',
                        'id' => 'jo96zw',
                        'position' => 2,
                        'action' => 'https://twitter.com',
                    ],
                    [
                        'type' => 'link',
                        'id' => '57dsea',
                        'position' => 3,
                        'action' => 'https://instagram.com',
                    ],
                    [
                        'type' => 'link',
                        'id' => 'lzntr2',
                        'position' => 4,
                        'action' => 'https://youtube.com',
                    ],
                ],
            ],
            [
                'name' => 'Auth Dropdown',
                'id' => 'h8r6vg',
                'items' => [
                    [
                        'label' => 'Admin Area',
                        'id' => 'upm1rv',
                        'action' => '/admin/reports',
                        'type' => 'route',
                        'permissions' => ['admin.access'],
                    ],
                    [
                        'label' => 'My Files',
                        'id' => 'ehj0uk',
                        'action' => '/drive',
                        'type' => 'route',
                    ],
                    [
                        'label' => 'Account Settings',
                        'id' => '6a89z5',
                        'action' => '/account-settings',
                        'type' => 'route',
                    ],
                ],
                'positions' => ['auth-dropdown'],
            ],
            [
                'name' => 'Admin Sidebar',
                'id' => '2d43u1',
                'items' => [
                    [
                        'label' => 'Analytics',
                        'id' => '886nz4',
                        'action' => '/admin/reports',
                        'type' => 'route',
                        'condition' => 'admin',
                        'role' => 1,
                        'permissions' => ['admin.access'],
                        'roles' => [],
                    ],
                    [
                        'label' => 'Settings',
                        'id' => 'x5k484',
                        'action' => '/admin/settings',
                        'type' => 'route',
                        'permissions' => ['settings.update'],
                    ],
                    [
                        'label' => 'Plans',
                        'id' => '7o42rt',
                        'action' => '/admin/plans',
                        'type' => 'route',
                        'settings' => ['billing.enable' => true],
                        'permissions' => ['plans.update'],
                    ],
                    [
                        'label' => 'Subscriptions',
                        'action' => '/admin/subscriptions',
                        'type' => 'route',
                        'id' => 'sdcb5a',
                        'settings' => ['billing.enable' => true],
                        'permissions' => ['subscriptions.update'],
                    ],
                    [
                        'label' => 'Users',
                        'action' => '/admin/users',
                        'type' => 'route',
                        'id' => 'fzfb45',
                        'permissions' => ['users.update'],
                    ],

                    [
                        'label' => 'Roles',
                        'action' => '/admin/roles',
                        'type' => 'route',
                        'id' => 'mwdkf0',
                        'permissions' => ['roles.update'],
                    ],
                    [
                        'label' => 'Pages',
                        'action' => '/admin/custom-pages',
                        'type' => 'route',
                        'id' => '63bwv9',
                        'permissions' => ['custom_pages.update'],
                    ],
                    [
                        'label' => 'Tags',
                        'action' => '/admin/tags',
                        'type' => 'route',
                        'id' => '2x0pzq',
                        'permissions' => ['tags.update'],
                    ],
                    [
                        'label' => 'Files',
                        'action' => '/admin/files',
                        'type' => 'route',
                        'id' => 'vguvti',
                        'permissions' => ['files.update'],
                    ],
                    [
                        'label' => 'Localizations',
                        'action' => '/admin/localizations',
                        'type' => 'route',
                        'id' => 'w91yql',
                        'permissions' => ['localizations.update'],
                    ],
                    [
                        'label' => 'Logs',
                        'action' => '/admin/logs',
                        'type' => 'route',
                        'id' => '8j435f',
                    ],
                ],
                'positions' => ['admin-sidebar'],
            ],
        ]),
    ],

    //landing page
    [
        'name' => 'landingPage',
        'value' => json_encode([
            'sections' => [
                [
                    'name' => 'hero-split-with-screenshot',
                    'badge' => '30-Day Money-Back Guarantee',
                    'title' => 'Massive Cloud Storage. A new home for your files.',
                    'description' =>
                        'Experience the next generation of cloud storage. Massive Cloud Storage provides a secure, fast, and intuitive platform to store, share, and manage all your digital assets.',
                    'image' => [
                        'src' => 'images/landing/hero.webp',
                        'width' => 2432,
                        'height' => 1442,
                    ],
                    'buttons' => [
                        [
                            'color' => 'primary',
                            'variant' => 'flat',
                            'label' => 'Get started',
                            'type' => 'route',
                            'action' => '/pricing',
                        ],
                        [
                            'color' => 'default',
                            'variant' => 'outline',
                            'label' => 'Login',
                            'type' => 'route',
                            'action' => '/login',
                        ],
                    ],
                ],
                [
                    'name' => 'features-grid',
                    'badge' => 'POWERFUL FEATURES',
                    'title' => 'Everything you need to manage your files',
                    'description' =>
                        'We built Massive Cloud Storage with the tools you need to stay organized, efficient, and in control of your data, whether you\'re working alone or as part of a team.',
                    'features' => [
                        [
                            'title' => 'Large file uploads',
                            'description' =>
                                'Effortlessly upload massive files. Our resumable upload technology means a dropped connection won\'t stop your progress.',
                            'icon' => 'fileUpload',
                        ],
                        [
                            'title' => 'Modern, intuitive interface',
                            'description' =>
                                'Navigate your files with ease. Switch between detailed list and visual grid views to find what you need, fast.',
                            'icon' => 'dashboard',
                        ],
                        [
                            'title' => 'Enterprise-grade security',
                            'description' =>
                                'Your files are protected with end-to-end encryption and robust access controls, ensuring your data stays private',
                            'icon' => 'lock',
                        ],
                        [
                            'title' => 'Access anywhere',
                            'description' =>
                                'Sync your files across all your devices—desktop, tablet, and mobile. Your work is always within reach.',
                            'icon' => 'sync',
                        ],
                        [
                            'title' => 'Powerful search',
                            'description' =>
                                'Find the exact file you need in seconds with advanced search capabilities and built-in filters.',
                            'icon' => 'search',
                        ],
                        [
                            'title' => 'Developer-friendly API',
                            'description' =>
                                'Integrate Massive Cloud Storage into your existing workflow. Our robust Rest API allows for powerful custom automations.',
                            'icon' => 'code',
                        ],
                    ],
                    'maxColumns' => '3',
                    'iconsOnTop' => true,
                ],
                [
                    'name' => 'feature-with-screenshot',
                    'badge' => 'SEAMLESS SHARING',
                    'title' => 'Share your files, your way',
                    'description' =>
                        'Move projects forward by sharing files and folders securely with anyone. Set passwords, expiration dates, and permissions to maintain full control over your data.',
                    'features' => [
                        [
                            'title' => 'Invite via email.',
                            'description' =>
                                'Grant secure access to specific users or groups by simply entering their email address.',
                            'icon' => 'email',
                        ],
                        [
                            'title' => 'Create shareable links.',
                            'description' =>
                                'Generate a secure link to any file or folder for easy sharing with clients, vendors, or external partners.',
                            'icon' => 'share',
                        ],
                        [
                            'title' => 'Get direct links.',
                            'description' =>
                                'Link directly to assets like images or documents for easy embedding in websites, reports, or other applications.',
                            'icon' => 'link',
                        ],
                    ],
                    'image' => [
                        'src' => 'images/landing/share.webp',
                        'width' => '2432',
                        'height' => '1442',
                    ],
                    'imageSize' => 'lg',
                    'wrapIconsInBg' => true,
                ],
                [
                    'name' => 'feature-with-screenshot',
                    'badge' => 'BUILT FOR TEAMS',
                    'title' => 'Collaborate in shared workspaces',
                    'description' =>
                        'Create centralized, shared folders for your projects, departments, or entire company. Workspaces act as your team\'s single source of truth, ensuring everyone has the latest files.',
                    'features' => [
                        [
                            'title' => 'Centralized ownership.',
                            'description' =>
                                'Files remain in the Workspace even if team members change, ensuring business continuity and data retention.',
                            'icon' => 'groups',
                        ],
                        [
                            'title' => 'Granular permissions.',
                            'description' =>
                                'Assign specific roles (like Viewer or Uploader) to different members within each Workspace to control who can do what.',
                            'icon' => 'lock',
                        ],
                    ],
                    'image' => [
                        'src' => 'images/landing/workspace.webp',
                        'width' => '2432',
                        'height' => '1442',
                    ],
                    'alignLeft' => true,
                    'wrapIconsInBg' => true,
                ],
                [
                    'name' => 'feature-with-screenshot',
                    'badge' => 'POWERFUL PREVIEWS',
                    'title' => 'View your files without downloading',
                    'description' =>
                        'Save time and bandwidth with high-fidelity previews for over 100 file types. Review documents, watch videos, and listen to audio right from your browser—no software required.',
                    'image' => [
                        'src' => 'images/landing/preview.webp',
                        'width' => '2432',
                        'height' => '1442',
                    ],
                    'features' => [
                        [
                            'title' => 'Rich media previews.',
                            'description' =>
                                'Stream high-definition video (MP4, MOV) and listen to audio files (MP3, WAV) instantly without waiting for a download.',
                            'icon' => 'videoLibrary',
                        ],
                        [
                            'title' => 'Office & PDF previews.',
                            'description' =>
                                'Open and review Microsoft Office documents (Word, Excel, PowerPoint) and Adobe PDFs directly in Massive Cloud Storage.',
                            'icon' => 'pictureAsPdf',
                        ],
                    ],
                    'wrapIconsInBg' => true,
                ],
                [
                    'name' => 'pricing',
                    'title' => 'Find the plan that\'s right for you',
                    'description' =>
                        'From a generous free plan for personal use to powerful business plans with advanced collaboration tools and more storage, we have a simple option that fits your needs.',
                ],
                [
                    'name' => 'cta-simple-centered',
                    'title' => 'Get started with Massive Cloud Storage today',
                    'description' =>
                        'Sign up for free and get 10 GB of secure storage, or explore our business plans for advanced collaboration, unlimited space, and powerful admin tools.',
                    'buttons' => [
                        [
                            'color' => 'primary',
                            'variant' => 'flat',
                            'label' => 'Get started today →',
                            'type' => 'route',
                            'action' => '/register',
                        ],
                    ],
                ],
                [
                    'name' => 'footer',
                ],
            ],
        ]),
    ],

    // drive
    ['name' => 'drive.default_view', 'value' => 'grid'],
    ['name' => 'drive.send_share_notification', 'value' => false],
    ['name' => 'share.suggest_emails', 'value' => false],
    ['name' => 'drive.default_available_space', 'value' => 1024 * 1024 * 1024], // 1GB
    ['name' => 'drive.direct_links', 'value' => true],
];
