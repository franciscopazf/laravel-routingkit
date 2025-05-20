<?php

namespace Tests\Unit;

use Tests\TestCase;
use Fp\FullRoute\Repositories\FileRouteRepository;
use Fp\FullRoute\Clases\FullRoute;
use Illuminate\Support\Facades\File;

class FileRouteRepositoryTest extends TestCase
{
    public function testCanAddAndFindRoute()
    {
        $repo = new FileRouteRepository(base_path('tests/stubs/fullroute_config.php'));

        $route = FullRoute::make('test-route')
            ->setTitle('Test')
            ->setEndBlock('test-route');

        $repo->addRoute($route);

        $found = $repo->findRoute('test-route');

        $this->assertNotNull($found);
        $this->assertEquals('test-route', $found->getId());
    }

    // Agrega más pruebas para move y delete
}
