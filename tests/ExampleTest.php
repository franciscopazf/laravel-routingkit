<?php

namespace Fp\RoutingKit\Tests;

use Orchestra\Testbench\TestCase;

class ExampleTest extends TestCase
{
    public function test_example()
    {
        $this->assertTrue(!false);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Fp\RoutingKit\RoutingKitServiceProvider::class,
        ];
    }
}
