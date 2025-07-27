<?php

namespace Rk\RoutingKit\Tests;

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
            \Rk\RoutingKit\RoutingKitServiceProvider::class,
        ];
    }
}
