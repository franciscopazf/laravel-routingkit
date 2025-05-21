<?php

namespace Fp\FullRoute\Tests\Unit;

use Fp\FullRoute\Services\RouteFileManager;
use Orchestra\Testbench\TestCase;

class RouteFileManagerTest extends TestCase
{
    protected string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear archivo temporal para los tests
        $this->tempFile = tempnam(sys_get_temp_dir(), 'routefile_');

        // Escribir un archivo PHP que retorne un arreglo para el método getContents()
        file_put_contents($this->tempFile, "<?php return ['route1' => 'value1', 'route2' => 'value2'];");
    }

    protected function tearDown(): void
    {
        // Borrar archivo temporal después del test
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    public function testGetContentsReturnsArray()
    {
        $manager = new RouteFileManager($this->tempFile);
        $contents = $manager->getContents();

        $this->assertIsArray($contents);
        $this->assertArrayHasKey('route1', $contents);
        $this->assertEquals('value1', $contents['route1']);
    }

    public function testGetContentsStringReturnsFileContents()
    {
        $manager = new RouteFileManager($this->tempFile);
        $contentsString = $manager->getContentsString();

        $this->assertStringContainsString('route1', $contentsString);
        $this->assertStringContainsString('value1', $contentsString);
    }

    public function testPutContentsWritesFile()
    {
        $manager = new RouteFileManager($this->tempFile);

        $newContent = "<?php return ['newroute' => 'newvalue'];";
        $manager->putContents($newContent);

        $this->assertStringEqualsFile($this->tempFile, $newContent);

        // También probar que getContents() devuelve lo nuevo
        $contents = $manager->getContents();
        $this->assertArrayHasKey('newroute', $contents);
        $this->assertEquals('newvalue', $contents['newroute']);
    }
}
