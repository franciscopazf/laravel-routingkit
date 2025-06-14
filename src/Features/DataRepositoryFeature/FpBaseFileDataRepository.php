<?php

namespace Fp\RoutingKit\Features\DataRepositoryFeature;

use Fp\RoutingKit\Contracts\FpDataRepositoryInterface;
use Fp\RoutingKit\Contracts\FpFileTransformerInterface;
use Illuminate\Support\Collection;
use Fp\RoutingKit\Features\DataFileTransformersFeature\FpFileTransformerFactory;

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
            $this->filePath,
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

    // metodo abstracto para obtener el contenido que ira dentro del archivo.
   // abstract protected function getNewContent(): array;

    // metodo o logica de escritura en el archivo ya que esto puede ser arbol o plano
    public function rewrite(Collection $newDataIntree): self
    {
        $newContent = $this->fpFileTransformer->transform($newDataIntree);
        $this->putContents($newContent);
        return $this;
    }

    public function getContents(): array
    {
        if (!file_exists($this->filePath)) {
            throw new \RuntimeException("File not found: {$this->filePath}");
        }

        $content = include $this->filePath;
        return $content;
    }

    public function getContentsString(): string
    {
        return file_get_contents($this->filePath);
    }

    public function putContents(string $content): void
    {
        file_put_contents($this->filePath, $content);
    }
}
