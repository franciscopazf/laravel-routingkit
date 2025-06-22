<?php

namespace FpF\RoutingKit\Features\FileCreatorFeature;

use FpF\RoutingKit\Contracts\FpFileCreatorInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;


class FpFileCreator implements FpFileCreatorInterface
{
    public string $filePath;
    public string $fileName;
    public string $fileContent;
    public string $fileExtension;

    public function __construct(
        string $filePath,
        string $fileName,
        string $fileContent,
        string $fileExtension = 'php'
    ) {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->fileContent = $fileContent;
        $this->fileExtension = $fileExtension;
    }

    /**
     * Factory method to create an instance of FpFileCreator.
     *
     * @param string $filePath
     * @param string $fileName
     * @param string $fileContent
     * @param string $fileExtension
     * @return self
     */
    public static function make(
        string $filePath,
        string $fileName,
        string $fileContent,
        string $fileExtension = 'php'
    ): self {
        return new self($filePath, $fileName, $fileContent, $fileExtension);
    }

    public function createFile(): bool
    {
        $directory = rtrim($this->filePath, DIRECTORY_SEPARATOR);
        $fullPath = $directory . DIRECTORY_SEPARATOR . $this->fileName . '.' . $this->fileExtension;

        // Crear la carpeta si no existe
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true); // true = recursivo
        }

        // Verificar si el archivo ya existe
        if (File::exists($fullPath)) {
            return false; // Archivo ya existe
        }

        // Crear el archivo
        return File::put($fullPath, $this->fileContent) !== false;
    }


    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
    }
    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }
    public function setFileContent(string $fileContent): void
    {
        $this->fileContent = $fileContent;
    }
    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }
    public function getFilePath(): string
    {
        return $this->filePath;
    }
    public function getFileName(): string
    {
        return $this->fileName;
    }
    public function getFileContent(): string
    {
        return $this->fileContent;
    }
    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }
}
