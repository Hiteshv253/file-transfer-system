<?php

return [
    'sections' => [
        [
            'name' => 'hero-split-with-screenshot',
            'badge' => '30-Day Money-Back Guarantee',
            'title' => 'BeDrive. A new home for your files.',
            'description' =>
                'Experience the next generation of cloud storage. BeDrive provides a secure, fast, and intuitive platform to store, share, and manage all your digital assets.',
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
                'We built BeDrive with the tools you need to stay organized, efficient, and in control of your data, whether you\'re working alone or as part of a team.',
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
                        'Integrate BeDrive into your existing workflow. Our robust Rest API allows for powerful custom automations.',
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
                        'Open and review Microsoft Office documents (Word, Excel, PowerPoint) and Adobe PDFs directly in BeDrive.',
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
            'title' => 'Get started with BeDrive today',
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
];
