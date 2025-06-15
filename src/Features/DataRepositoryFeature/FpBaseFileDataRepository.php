<?php

namespace Fp\RoutingKit\Features\DataRepositoryFeature;

use Fp\RoutingKit\Contracts\FpDataRepositoryInterface;
use Fp\RoutingKit\Contracts\FpFileTransformerInterface;
use Illuminate\Support\Collection;
use Fp\RoutingKit\Features\DataFileTransformersFeature\FpFileTransformerFactory;
use Symfony\Component\Process\Process;
use RuntimeException; // Agregado para manejo de excepciones

class FpBaseFileDataRepository implements FpDataRepositoryInterface
{
    public string $filePath;
    public string $fileSave;
    public bool $onlyStringSupport = false;

    private FpFileTransformerInterface $fpFileTransformer;

    public function __construct(
        string $filePath,
        string $fileSave,
        bool $onlyStringSupport = false,
        ?FpFileTransformerInterface $fpFileTransformer = null
    ) {
        $this->filePath = $filePath;
        $this->fileSave = $fileSave;
        $this->onlyStringSupport = $onlyStringSupport;
       
        $this->fpFileTransformer = $fpFileTransformer ?? FpFileTransformerFactory::getFileTransformer(
            $this->getContentsString(), // Podrías considerar si necesitas obtener el contenido aquí si siempre lo transformas
            $this->fileSave,
            $this->onlyStringSupport
        );
    }

    public static function make(
        string $filePath,
        string $fileSave,
        bool $onlyStringSupport = false,
        ?FpFileTransformerInterface $fpFileTransformer = null
    ): self {
        return new self($filePath, $fileSave, $onlyStringSupport, $fpFileTransformer);
    }

    public function setFpFileTransformer(FpFileTransformerInterface $fpFileTransformer): self
    {
        $this->fpFileTransformer = $fpFileTransformer;
        return $this;
    }

    public static function create(string $filePath, string $fileSave, bool $onlyStringSupport = false): self
    {
        return new self($filePath, $fileSave, $onlyStringSupport);
    }

    // metodo o logica de escritura en el archivo ya que esto puede ser arbol o plano
    public function rewrite(Collection $newDataIntree): self
    {
        $newContent = $this->fpFileTransformer->transform($newDataIntree);
        
        // Formatea el contenido ANTES de escribirlo en el archivo final
        $formattedContent = $this->formatContentWithPint($newContent);
        
        // Escribe el contenido ya formateado
        $this->putContents($formattedContent);

        return $this;
    }

    public function getContents(): array
    {
        if (!file_exists($this->filePath)) {
            // Si el archivo no existe, podrías devolver un array vacío o lanzar una excepción,
            // dependiendo de lo que esperes en tu lógica de negocio.
            // Aquí, lanzamos una excepción para indicar que el archivo es requerido.
            throw new RuntimeException("File not found: {$this->filePath}");
        }

        // Advertencia: 'include' puede ejecutar código PHP. Asegúrate de que los archivos
        // que incluyes contengan solo arrays de configuración o datos seguros.
        $content = include $this->filePath;
        
        // Asegúrate de que lo que se incluye realmente devuelva un array.
        if (!is_array($content)) {
            throw new RuntimeException("The file {$this->filePath} did not return an array.");
        }
        return $content;
    }

    public function getContentsString(): string
    {
        if (!file_exists($this->filePath)) {
            return ''; // Devuelve una cadena vacía si el archivo no existe.
        }
        return file_get_contents($this->filePath);
    }

    public function putContents(string $content): void
    {
        if (file_put_contents($this->filePath, $content) === false) {
            throw new RuntimeException("Failed to write content to file: {$this->filePath}");
        }
    }

    /**
     * Formatea un string de contenido PHP usando Laravel Pint.
     * Crea un archivo temporal, lo formatea con Pint y devuelve el contenido formateado.
     *
     * @param string $contentToFormat El contenido PHP como string.
     * @return string El contenido PHP formateado.
     * @throws RuntimeException Si Laravel Pint no se encuentra o falla al formatear.
     */
    protected function formatContentWithPint(string $contentToFormat): string
    {
        $pintPath = base_path('vendor/bin/pint');

        if (!file_exists($pintPath)) {
            error_log("Laravel Pint executable not found at: {$pintPath}. Skipping formatting.");
            // Devuelve el contenido original si Pint no está disponible.
            return $contentToFormat; 
        }

        // 1. Crear un archivo temporal
        // Usamos tempnam para crear un nombre de archivo único en el directorio temporal del sistema.
        // Asegúrate de que el archivo temporal tenga una extensión .php para que Pint lo reconozca.
        $tempFilePath = tempnam(sys_get_temp_dir(), 'pint_format_') . '.php';

        // 2. Escribir el contenido sin formato al archivo temporal
        if (file_put_contents($tempFilePath, $contentToFormat) === false) {
            throw new RuntimeException("Failed to write to temporary file: {$tempFilePath}");
        }

        // 3. Ejecutar Pint sobre el archivo temporal
        $command = ['php', $pintPath, $tempFilePath];
        $process = new Process($command);

        echo "Formatting content with Pint via temporary file. This operation may take a while...\n";

        $process->run();

        // 4. Manejar errores si Pint falla
        if (!$process->isSuccessful()) {
            // Eliminar el archivo temporal antes de lanzar la excepción
            @unlink($tempFilePath); 
            throw new RuntimeException(sprintf(
                'Laravel Pint failed to format temporary file %s: %s',
                $tempFilePath,
                $process->getErrorOutput()
            ));
        }

        // 5. Leer el contenido formateado del archivo temporal
        $formattedContent = file_get_contents($tempFilePath);
        if ($formattedContent === false) {
            // Eliminar el archivo temporal antes de lanzar la excepción
            @unlink($tempFilePath); 
            throw new RuntimeException("Failed to read formatted content from temporary file: {$tempFilePath}");
        }

        // 6. Eliminar el archivo temporal
        @unlink($tempFilePath); // Usar @ para suprimir advertencias si la eliminación falla por alguna razón.

        return $formattedContent;
    }
}