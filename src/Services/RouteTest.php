<?php


namespace Fp\FullRoute\Services;


class RouteTest
{
    # validar si todas las rutas se cargan correctamente
    # validar si todas las rutas tienen un id único
    # validar si todas las rutas tienen un método válido
    # validar si todas las rutas tienen una ruta válida
    # validar si todas las rutas tienen un padre válido
    # validar si todas las rutas tienen un hijo válido
    # validar si todas las rutas tienen un permiso válido
    # validar si todas las rutas tienen un middleware válido
    # validar si todas las rutas tienen un nombre válido
    # validar si todas las rutas tienen un controlador válido

    public function testRouteService()
    {
        $routeService = new RouteService();
        $route = new FullRoute();
        $route->setId('test_route')
            ->setRoute('/test')
            ->setMethod('GET')
            ->setParentId(null)
            ->setChildrens([]);
        
        // Add route
        $routeService->addRoute($route);
        
        // Validate route
        RouteValidationService::validateRoute($route);
        
        // Check if the route was added correctly
        $this->assertTrue(RouteService::isIdDuplicate('test_route'));
    }
}