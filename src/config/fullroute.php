<?php


return [

    /*
     * Define the path for the routes
     * 
     * @var string
     */
    'support_app' => 'file', // | DB


    /*
     * Define the path for the routes
     * 
     * @var string
     */
    'livewire_support' => true,

    /*
     * Define the path for the routes
     * 
     * @var string
     */
    'controllers_path' => [
        'app/Http/Controllers' => 'Controladores',
        'app/Livewire'    => 'Livewire',
    ],

    /*
     * Define what is the model to use
     * 
     * @var string
     */
    'model_ussage' => \Fp\FullRoute\Clases\FullRoute::class,


    /*
     * Define the path for the routes
     * 
     * @var string
     */
    'model_route' =>  \Fp\FullRoute\Clases\Route::class,


    /*
     * Define the path for usage whe the metod ::navbarall() is invoked
     * 
     * @var string
     */
    'model_navbar' => \Fp\FullRoute\Clases\Navbar::class,

    /*
     * Route usage
     * 
     * Define the file where the routes will be saved
     * @var array
     */
    'routes_fyle_path' => [
        'web' => base_path('config/fullroute_config.php'),
    ],

    /*
     * Define the class for the permission
     * 
     * @var string
     */
    'permission_class' => \Fp\FullRoute\Clases\Permission::class,


    /*
     * Define the class for the role
     * 
     * @var string
     */
    'role_class' => \Fp\FullRoute\Clases\Role::class,


    /*
     * Define the roles ussagge for the selection in the routes
     * 
     * @var array
     */
    'roles' => [
        'admin' => 'Admin',
        'user' => 'User',
        'guest' => 'Guest',
    ],

    /*
     * Define the required values for creating a route
     * 
     * @var array
     */
    'required' => [
        'permission' => 'permission',
        'title' => 'title',
        'description' => 'description',
        'keywords' => 'keywords',
        'icon' => 'icon',
        'url' => 'url',
        'url_name' => 'url_name',
        'url_method' => 'url_method',
        'url_controller' => 'url_controller',
        'url_action' => 'url_action',
        'roles' => 'roles',
        'childrens' => 'childrens',
    ],


];
