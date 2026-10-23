<?php

return [
    'roles' => [
        [
            'name' => 'Users',
            'internal' => true,
            'default' => true,
            'type' => 'users',
            'permissions' => [
                'files.create',
                'api.access',
                'links.view',
                'links.create',
                'workspaces.create',
            ],
        ],
        [
            'name' => 'Guests',
            'internal' => true,
            'type' => 'users',
            'guests' => true,
            'permissions' => ['links.view'],
        ],
        [
            'name' => 'Workspace admin',
            'type' => 'workspace',
            'description' =>
                'Manage workspace content, members, settings and invite new members.',
            'permissions' => [
                'workspace_members.invite',
                'workspace_members.update',
                'workspace_members.delete',
                'files.view',
                'files.download',
                'files.create',
                'files.update',
                'files.delete',
            ],
        ],
        [
            'name' => 'Workspace editor',
            'type' => 'workspace',
            'description' => 'Add, edit, move and delete workspace files.',
            'permissions' => [
                'files.view',
                'files.download',
                'files.create',
                'files.update',
                'files.delete',
            ],
        ],
        [
            'name' => 'Workspace contributor',
            'type' => 'workspace',
            'description' => 'View files and add new files to the workspace.',
            'permissions' => ['files.view', 'files.download', 'files.create'],
        ],
    ],
    'all' => [
        'files' => [
            [
                'name' => 'files.create',
                'advanced' => false,
                'display_name' => 'Upload files',
                'description' =>
                    'Allow user to upload files into personal workspace.',
                'restrictions' => [
                    [
                        'name' => 'max_space_usage',
                        'display_name' => 'Maximum storage space',
                        'type' => 'number',
                        'description' =>
                            'Maximum storage space all user uploads are allowed to take up (in bytes).',
                    ],
                ],
            ],
        ],

        'Shareable links' => [
            'links.view' => [
                'name' => 'links.view',
                'display_name' => 'View shareable links',
                'description' => 'Allows viewing shareable links.',
            ],
            'links.create' => [
                'name' => 'links.create',
                'display_name' => 'Create shareable links',
                'description' =>
                    'Allows creating shareable links from drive page.',
            ],
        ],

        'Workspaces' => [
            [
                'name' => 'workspaces.create',
                'display_name' => 'Create workspaces',
                'description' =>
                    'Allow creating new workspaces from drive page.',
                'restrictions' => [
                    [
                        'name' => 'count',
                        'display_name' => 'Count',
                        'type' => 'number',
                        'description' => __('policies.count_description', [
                            'resources' => 'workspaces',
                        ]),
                    ],
                    [
                        'name' => 'member_count',
                        'display_name' => 'Member count',
                        'type' => 'number',
                        'description' =>
                            'Maximum number of members workspace is allowed to have.',
                    ],
                ],
            ],
        ],

        'REST API' => [
            [
                'name' => 'api.access',
                'display_name' => 'REST API',
                'description' =>
                    'Allow usage of REST API and accessing API section in account settings page.',
            ],
        ],

        'Admin' => [
            [
                'name' => 'admin.access',
                'display_name' => 'Access admin area',
                'description' =>
                    'Required in order to access any admin area page.',
            ],
            [
                'name' => 'admin',
                'display_name' => 'Super admin',
                'description' => 'Gives full permissions.',
            ],
            [
                'name' => 'reports.view',
                'display_name' => 'View reports',
                'description' => 'Allow viewing reports.',
            ],
            [
                'name' => 'settings.update',
                'display_name' => 'Manage settings',
                'description' => 'Allow settings management from admin area.',
            ],
            [
                'name' => 'roles.update',
                'display_name' => 'Role management',
                'description' => 'Allow role management from admin area.',
            ],
            [
                'name' => 'subscriptions.update',
                'display_name' => 'Manage subscriptions',
                'description' =>
                    'Allow subscription and plan management from admin area.',
            ],
            [
                'name' => 'localizations.update',
                'display_name' => 'Manage localizations',
                'description' =>
                    'Allow localization management from admin area.',
            ],
            [
                'name' => 'files.update',
                'display_name' => 'Manage files',
                'description' => 'Allow file management from admin area.',
            ],
            [
                'name' => 'tags.update',
                'display_name' => 'Manage tags',
                'description' => 'Allow tag management from admin area.',
            ],
            [
                'name' => 'users.update',
                'display_name' => 'Manage users',
                'description' => 'Allow user management from admin area.',
            ],
            [
                'name' => 'workspaces.update',
                'display_name' => 'Manage workspaces',
                'description' => 'Allow workspace management from admin area.',
            ],
            [
                'name' => 'custom_pages.update',
                'display_name' => 'Manage custom pages',
                'description' =>
                    'Allow custom page management from admin area.',
            ],
        ],

        'Workspace members' => [
            [
                'name' => 'workspace_members.invite',
                'display_name' => 'Invite Members',
                'type' => 'workspace',
                'description' =>
                    'Allow user to invite new members into a workspace.',
            ],
            [
                'name' => 'workspace_members.update',
                'display_name' => 'Update Members',
                'type' => 'workspace',
                'description' => 'Allow user to change role of other members.',
            ],
            [
                'name' => 'workspace_members.delete',
                'display_name' => 'Delete Members',
                'type' => 'workspace',
                'description' => 'Allow user to remove members from workspace.',
            ],
        ],

        'Workspace files' => [
            [
                'name' => 'files.view',
                'display_name' => 'View files',
                'description' =>
                    'Allow user to view files uploaded by other workspace members.',
                'type' => 'workspace',
            ],
            [
                'name' => 'files.create',
                'display_name' => 'Upload files',
                'description' => 'Allow user to upload files into a workspace.',
                'type' => 'workspace',
            ],
            [
                'name' => 'files.download',
                'display_name' => 'Download files',
                'description' =>
                    'Allow user to download workspace files uploaded by other members.',
                'type' => 'workspace',
            ],
            [
                'name' => 'files.update',
                'display_name' => 'Edit files',
                'description' =>
                    'Allow user to edit workspace files uploaded by other members.',
                'type' => 'workspace',
            ],
            [
                'name' => 'files.delete',
                'display_name' => 'Delete files',
                'description' =>
                    'Allow user to delete workspace files uploaded by other members.',
                'type' => 'workspace',
            ],
        ],
    ],
];
