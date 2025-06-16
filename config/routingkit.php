<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Livewire Support
    |--------------------------------------------------------------------------
    |
    | This option enables or disables specific functionalities related to
    | Livewire components within your package. When set to true, it might
    | activate Livewire-specific parsing or routing behaviors.
    |
    */
    'livewire_support' => true,

    /*
    |--------------------------------------------------------------------------
    | Controllers Paths
    |--------------------------------------------------------------------------
    |
    | Define the base paths where your application's controllers and Livewire
    | components are located. These paths are used, for example, by the
    | FpFileBrowser to allow interactive selection of classes.
    |
    | Each key represents a directory path (relative to base_path()),
    | and its value is a user-friendly label for that path.
    |
    */
    'controllers_path' => [
        'app/Http/Controllers' => 'Controladores',
        'app/Livewire'         => 'Livewire',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Entity Model
    |--------------------------------------------------------------------------
    |
    | Specify the fully qualified class name (FQCN) of the model that
    | represents your routes. This model should extend FpBaseEntity
    | and is used throughout the RoutingKit package for managing route data.
    |
    */
    'model_ussage' => \Fp\RoutingKit\Entities\FpRoute::class,

    /*
    |--------------------------------------------------------------------------
    | Route File Paths Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where your routes will be saved and how they are structured.
    | This section defines different storage strategies for your routes.
    |
    | 'default_file': Specifies the default strategy (key from 'items').
    | 'items': An array defining each route storage strategy.
    |   - 'path': The full path to the file where routes are stored.
    |   - 'support_file': Indicates the structure of the data within the file
    |                     (e.g., "object_file_tree", "object_file_plain").
    |   - 'only_string_support': If true, indicates that the file expects
    |                            only string values for certain configurations.
    |
    */
    'routes_file_path' => [
        'default_file' => 'dashboard_routes',
        'items'        => [
            'dashboard_routes' => [
                'path'              => base_path('routingkit/Routes/fpRoutes.php'),
                'support_file'      => "object_file_tree",
                'only_string_support' => true,
            ],
            
            //...
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation File Paths Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where your navigation data will be saved and how it is structured.
    | This section defines different storage strategies for your navigations,
    | similar to how routes are handled.
    |
    | 'default_file': Specifies the default navigation file (key from 'items').
    | 'items': An array defining each navigation storage strategy.
    |   - 'path': The full path to the file where navigation data is stored.
    |   - 'support_file': Indicates the structure of the data within the file.
    |   - 'only_string_support': If true, indicates that the file expects
    |                            only string values for certain configurations.
    |
    */
    'navigators_file_path' => [
        'default_file' => 'dashboard_navigators',
        'items'        => [
            'dashboard_navigators' => [
                'path'              => base_path('routingkit/Navigation/fpNavigation.php'),
                'support_file'      => "object_file_plain",
                'only_string_support' => true,
            ],
            // ...
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Roles
    |--------------------------------------------------------------------------
    |
    | Define the roles available in your application. These roles can be
    | used for permission management and access control within your routes
    | and navigation. These are typically used for console prompts
    | and internal logic.
    |
    | The keys are the internal identifiers for the roles, and the values
    | are often the same for simplicity or can be descriptive names.
    |
    */
    'roles' => [
        'admin_general' => 'admin_general',
        'guest'         => 'guest',
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Users
    |--------------------------------------------------------------------------
    |
    | Configure credentials and roles for development-specific users.
    | These users can be automatically created or seeded during development
    | to facilitate testing and access control setup.
    |
    | It's recommended to store sensitive information like email and password
    | in your .env file and access them using the env() helper.
    |
    */
    'development_users' => [
        'admin_general' => [
            'email'    => env('ADMIN_USER_EMAIL', 'admin@example.com'),
            'password' => env('ADMIN_USER_PASSWORD', 'password'),
            'roles'    => ['admin_general'],
        ],
    ],

];
