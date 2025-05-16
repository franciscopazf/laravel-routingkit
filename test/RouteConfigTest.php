<?php

namespace Fp\FullRoute\Tests;

use Fp\FullRoute\Services\RouteService;
use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

class RouteConfigTest extends BaseTestCase
{
    use CreatesApplication; // Asegúrate de que esta clase exista en tu proyecto Laravel

    public function testRoutesAreLoadedCorrectly()
    {
        $routes = RouteService::getAllRoutes();
        $this->assertNotEmpty($routes, 'Las rutas no se cargaron correctamente.');
    }

    public function testRouteCanBeAdded()
    {
        $route = new FullRoute();
        $route->setId('test_route')
            ->setRoute('/test')
            ->setMethod('GET')
            ->setParentId(null)
            ->setChildrens([]);

        RouteService::addRoute($route);

        $routes = RouteService::getAllRoutes();
        $this->assertTrue(
            collect($routes)
            ->contains(fn($r) => $r->getId() === 'test_route'),
            'La ruta no se agregó correctamente.'
        );
    }

    public function testRouteValidation()
    {
        $route = new FullRoute();
        $route->setId('invalid_route')
            ->setRoute('invalid') // Ruta inválida (no comienza con "/")
            ->setMethod('INVALID') // Método inválido
            ->setParentId(null)
            ->setChildrens([]);

        $this->expectException(\Exception::class);
        RouteService::addRoute($route);
    }

    public function testRoutesIntegrationWithLaravel()
    {
        $response = $this->get('/test'); // Simula una solicitud HTTP a la ruta "/test"
        $response->assertStatus(200); // Verifica que la respuesta sea exitosa
    }
}