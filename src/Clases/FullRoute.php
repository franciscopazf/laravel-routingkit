<?php

namespace Fp\FullRoute\Clases;

use Fp\FullRoute\Clases\Navbar;
use Fp\FullRoute\Traits\HasDynamicAccessors;
use Fp\FullRoute\Services\Route\RouteContext;
use Fp\FullRoute\Helpers\CollectionSelector;
use Fp\FullRoute\Services\Navigator\Navigator;
use Fp\FullRoute\Helpers\RegisterRouter;
//use Fp\FullRoute\Contracts\RouteEntityInterface;
use Fp\FullRoute\Services\Route\Strategies\RouteStrategyFactory;



use Illuminate\Support\Facades\Route as LaravelRoute;
use Illuminate\Routing\Route as RealRoute;
use Illuminate\Support\Collection;

class FullRoute // implements RouteEntityInterface
{
    use HasDynamicAccessors;

    public ?string $parentId = null;
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

    // this values are because of the route
    public string $fullUrlName;
    public string $fullUrl;

    public array $permissions = [];
    public array $roles = [];


    public int $level;

    # public string $routeName;
    # public RealRoute $route;
    public RealRoute $laravelRoute;
    public Navbar $navbar;
    public FullRoute $parent;


    public array $childrens = [];
    public string $endBlock;


    public function __construct(string $id)
    {
        $this->id = $id;
        $this->urlName = $id;
        $this->parentId = null;
        $this->level = 0;
    }

    /**
     * @param string $id
     * @return FullRoute
     */
    public static function Make(string $id): FullRoute
    {
        return new FullRoute($id);
    }

    /**
     * @param string $id
     * @return FullRoute
     */
    public function save(string|FullRoute|null $parent = null): self
    {
        if (is_string($parent))
            $parent = self::getRouteContext()
                ->findRoute($parent);

        $this->parentId = $parent ? $parent->id : null;

        self::getRouteContext()
            ->addRoute($this, $parent);
        return $this;
    }

    public function parent(string|FullRoute $parent): FullRoute
    {
        return $this->parent;
    }

    /**
     * @param string $id
     * @return FullRoute
     */
    public function delete(): self
    {
        self::getRouteContext()
            ->removeRoute($this->id);
        return $this;
    }



    /**
     * @param string $id
     * @return FullRoute
     */
    public function getBreadcrumbs(): Collection
    {
        return self::getRouteContext()
            ->getBreadcrumbs($this);
    }



    /**
     * @param string $id
     * @return FullRoute
     */
    public function moveTo(string|FullRoute $parent): self
    {
        // si la variable pasada es un string entonces se debe buscar la route con el metodo find
        if (is_string($parent))
            $parent = self::getRouteContext()
                ->findRoute($parent);
        self::getRouteContext()
            ->moveRoute($this, $parent);
        return $this;
    }

    /**
     * @param string $id
     * @return FullRoute|null
     */
    public static function find(string $id): ?FullRoute
    {
        return self::getRouteContext()
            ->findRoute($id);
    }

    /**
     * @param string $routeName
     * @return FullRoute|null
     */
    public static function findByRouteName(string $routeName): ?FullRoute
    {

        return self::getRouteContext()
            ->findByRouteName($routeName);
    }

    /**
     * @param string $paramName
     * @param string $value
     * @return Collection|null
     */
    public static function findByParamName(string $paramName, string $value): ?Collection
    {
        return self::getRouteContext()
            ->findByParamName($paramName, $value);
    }

    /**
     * @param string $id
     * @return FullRoute|null
     */
    public function getParentRoute(): ?FullRoute
    {
        return $this->parent;
    }

    public function addChild(FullRoute $child): self
    {
        $this->childrens[] = $child;
        return $this;
    }

    // validar si una ruta es hijo o subhija (contenida en hijos de hijos de una ruta)
    // de otra ruta recursivamente
    public function routeIsChild(string $id): bool
    {
        if ($this->id === $id)
            return true;

        foreach ($this->childrens as $child)
            return $child->routeIsChild($id);

        return false;
    }

    /**
     * @param string $id
     * @return FullRoute|null
     */
    public static function all(): Collection
    {

        return self::getRouteContext()
            ->getAllRoutes();
    }


    public static function allFlattened(): Collection
    {
        return self::getRouteContext()
            ->getAllFlattenedRoutes(self::all());
    }

    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): ?string
    {
        //dd(self::all());
        return Navigator::make()
            ->treeNavigator(self::all(), null, [], $omitId, $label);
    }



    public static function exists(string $id): bool
    {
        return self::getRouteContext()
            ->exists($id);
    }

    public static function RegisterRoutes()
    {
        RegisterRouter::registerRoutes();
    }


    public static function getRouteContext(): RouteContext
    {
        return RouteStrategyFactory::make(config('fproute.support_app'));
    }
}
