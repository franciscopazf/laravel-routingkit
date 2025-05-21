<?php

namespace Fp\FullRoute\Tests;

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
            \Fp\FullRoute\FullRouteServiceProvider::class,
        ];
    }
}
