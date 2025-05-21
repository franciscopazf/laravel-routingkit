<?php

namespace Fp\FullRoute\Clases;

use Fp\FullRoute\Clases\Navbar;
use Fp\FullRoute\Traits\HasDynamicAccessors;
use Fp\FullRoute\Services\RouteService;
use Fp\FullRoute\Helpers\CollectionSelector;
use Fp\FullRoute\Helpers\RegisterRouter;

use Illuminate\Support\Facades\Route as LaravelRoute;
use Illuminate\Routing\Route as RealRoute;
use Illuminate\Support\Collection;



class FullRoute
{
    use HasDynamicAccessors;

    public string $parentId;
    public string $id;
    public string $type;
    public string $permission;
    public string $endBlock;

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
    public FullRoute $parent;



    public function __construct(string $id)
    {
        $this->id = $id;
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
    public function save(string|FullRoute $parent): self
    {
        if (is_string($parent))
            $parent = RouteService::findRoute($parent);
        $this->parent = $parent;
        RouteService::addRoute($this);
        return $this;
    }

    /**
     * @param string $id
     * @return FullRoute
     */
    public function delete(): self
    {
        RouteService::removeRoute($this->id);
        return $this;
    }

    /**
     * @param string $id
     * @return FullRoute
     */
    public function moveTo(string|FullRoute $parent): self
    {
        // si la variable pasada es un string entonces se debe buscar la route con el metodo find
        if (is_string($parent))
            $parent = RouteService::findRoute($parent);
        RouteService::moveRoute($this, $parent);
        return $this;
    }

    /**
     * @param string $id
     * @return FullRoute|null
     */
    public static function find(string $id): ?FullRoute
    {
        return RouteService::findRoute($id);
    }

    /**
     * @param string $id
     * @return FullRoute|null
     */
    public function getParentRoute(): ?FullRoute
    {
        return $this->parent;
    }

    // validar si una ruta es hijo o subhija (contenida en hijos de hijos de una ruta)
    // de otra ruta recursivamente
    public function routeIsChild(string $id): bool
    {
        if ($this->id === $id) {
            return true;
        }

        foreach ($this->childrens as $child) {
            if ($child->routeIsChild($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $id
     * @return FullRoute|null
     */
    public static function all(): Collection
    {
        return RouteService::getAllRoutes();
    }


    public static function allFlattened(): Collection
    {
        return RouteService::getAllFlattenedRoutes(self::all());
    }

    public static function seleccionar(?string $omitId = null, string $label = 'Selecciona una ruta'): string
    {
        //dd(self::all());
        return CollectionSelector::navegar(self::all(), omitId: $omitId);
    }



    public static function exists(string $id): bool
    {
        return RouteService::exists($id);
    }

    public static function RegisterRoutes() 
    {
        RegisterRouter::registerRoutes();
    }
}
