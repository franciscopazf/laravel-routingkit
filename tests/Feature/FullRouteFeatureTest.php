<?php

namespace Tests\Feature;

use Fp\FullRoute\Clases\FullRoute;
use Fp\FullRoute\Services\Route\Strategies\RouteStrategyFactory;
use Illuminate\Support\Collection;
use Orchestra\Testbench\TestCase;

class FullRouteFeatureTest extends TestCase
{
    protected string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear archivo temporal con rutas predefinidas
        $this->tempFile = base_path('tests/temp_routes.php');

        file_put_contents(
            $this->tempFile,
            <<<PHP
<?php

use Fp\\FullRoute\\Clases\\FullRoute;

return [
    FullRoute::make('test')
        ->setPermission('admin')
        ->setTitle('Dashboard3')
        ->setDescription('Dashboard de la aplicacion')
        ->setKeywords('dashboard, fp-full-route')
        ->setIcon('fa-solid fa-house')
        ->setUrl('/dashboard')
        ->setUrlName('dashboard3')
        ->setUrlMethod('GET')
        ->setUrlController('App\\Http\\Controllers\\DashboardController')
        ->setUrlAction('index')
        ->setRoles(['admin', 'user'])
        ->setChildrens([])
        ->setEndBlock('test'),
];
PHP
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_can_add_a_route()
    {
        $context = RouteStrategyFactory::make('file_array', $this->tempFile);

        $newRoute = FullRoute::make('route-1')
            ->setPermission('admin')
            ->setTitle('Dashboard3')
            ->setDescription('Dashboard de la aplicacion')
            ->setKeywords('dashboard, fp-full-route')
            ->setIcon('fa-solid fa-house')
            ->setUrl('/dashboard')
            ->setUrlName('dashboard3')
            ->setUrlMethod('GET')
            ->setUrlController('App\\Http\\Controllers\\DashboardController');

        $context->addRoute($newRoute, 'test');

        $this->assertTrue($context->exists('route-1'));
    }

    /** @test */
    public function it_can_get_all_routes()
    {
        $context = RouteStrategyFactory::make('file_array', $this->tempFile);

        $routes = $context->getAllRoutes();

        $this->assertInstanceOf(Collection::class, $routes);
        $this->assertTrue($routes->contains(fn($route) => $route->getId() === 'test'));
    }

    /** @test */
    public function it_can_find_a_specific_route()
    {
        $context = RouteStrategyFactory::make('file_array', $this->tempFile);

        $route = $context->findRoute('test');

        $this->assertNotNull($route);
        $this->assertEquals('Dashboard3', $route->getTitle());
    }

    /** @test */
    public function it_can_move_a_route()
    {
        $context = RouteStrategyFactory::make('file_array', $this->tempFile);

        $routeFrom = FullRoute::make('route-1')
            ->setTitle('From')
            ->setUrlMethod('GET')
            ->setUrlName('name')
            ->setUrl('/from');

        $routeTo   = FullRoute::make('route-2')
            ->setTitle('To')
            ->setUrlMethod('GET')
            ->setUrlName('name2')
            ->setUrl('/to');

        $context->addRoute($routeFrom, 'test');
        $context->addRoute($routeTo, 'test');

        $context->moveRoute($context->findRoute('route-1'), $context->findRoute('route-2'));


        // Validar que la ruta 1 es un hijo de la ruta 2
        $this->assertTrue($context->findRoute('route-2')
            ->routeIsChild('route-1'));
    }

    /** @test */
    public function it_can_remove_a_route()
    {
        $context = RouteStrategyFactory::make('file_array', $this->tempFile);

        $route = FullRoute::make('route-delete')
            ->setPermission('admin')
            ->setUrlMethod('GET')
            ->setTitle('Eliminar')
            ->setUrlName('name')
            ->setUrl('/delete');
        $context->addRoute($route, 'test');

        $this->assertTrue($context->exists('route-delete'));

        $context->removeRoute('route-delete');

        $this->assertFalse($context->exists('route-delete'));
    }
}
