<?php

namespace Fp\FullRoute\Clases;

use Illuminate\Support\Facades\Route as LaravelRoute;
use Illuminate\Routing\Route as RealRoute;
use Fp\FullRoute\Clases\Navbar;
use Fp\FullRoute\Traits\HasDynamicAccessors;

class FullRoute
{
    use HasDynamicAccessors;

    public string $parentId;
    public string $id;
    public string $type;
    public string $permission;

    public string $title;
    public string $description;
    public string $keywords;
    public string $icon;
    public string $visibleNavbar;
    public string $enabledNavbarHorizontal;

    public string $url;
    public string $urlName;
    public string $enabledUrl;
    public string $urlMethod;
    public string $urlController;
    public string $urlAction;
    public string $urlMiddleware;

    public array $permissions = [];
    public array $roles = [];
    public array $childrens = [];

    # public RealRoute $route;
    public RealRoute $laravelRoute;
    public Navbar $navbar;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function Make(string $id): FullRoute
    {
        return new FullRoute($id);
    }

    
}
