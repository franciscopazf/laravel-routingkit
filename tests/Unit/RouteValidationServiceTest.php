<?php

namespace Fp\FullRoute\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Fp\FullRoute\Services\RouteValidationService;
use Fp\FullRoute\Clases\FullRoute;
use Mockery;

class RouteValidationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            // Si tu paquete tiene un ServiceProvider, agréguelo aquí, por ejemplo:
            // \Fp\FullRoute\FullRouteServiceProvider::class,
        ];
    }

    public function testValidateRouteThrowsWhenIdIsEmpty()
    {
        $route = Mockery::mock(FullRoute::class);
        $route->shouldReceive('getId')->andReturn('');
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('El ID no puede estar vacío.');

        RouteValidationService::validateRoute($route);
    }

    
}
