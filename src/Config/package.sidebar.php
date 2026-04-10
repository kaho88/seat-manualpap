<?php

return [
    'manualpap' => [
        'name' => 'manualpap',
        'label' => 'manualpap::seat.plugin_name',
        'icon' => 'fas fa-user-plus',
        'route_segment' => 'manual-pap',
        'permission' => 'manualpap.view',
        'entries' => [
            [
                'name' => 'Manual PAP',
                'label' => 'manualpap::seat.manual_pap',
                'icon' => 'fas fa-plus-circle',
                'route' => 'manualpap.index',
                'permission' => 'manualpap.use',
            ],
            [
                'name' => 'Bulk Import',
                'label' => 'manualpap::seat.bulk_title',
                'icon' => 'fas fa-list',
                'route' => 'manualpap.bulk',
                'permission' => 'manualpap.use',
            ],
            [
                'name' => 'Report',
                'label' => 'manualpap::seat.report_title',
                'icon' => 'fas fa-chart-bar',
                'route' => 'manualpap.report',
                'permission' => 'manualpap.view',
            ],
            [
                'name' => 'Inactive',
                'label' => 'manualpap::seat.inactive_title',
                'icon' => 'fas fa-user-slash',
                'route' => 'manualpap.inactive',
                'permission' => 'manualpap.view',
            ],
            [
                'name' => 'Settings',
                'label' => 'manualpap::seat.settings_title',
                'icon' => 'fas fa-cog',
                'route' => 'manualpap.settings',
                'permission' => 'manualpap.use',
            ],
        ],
    ],
];
