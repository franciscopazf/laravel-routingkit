<?php

namespace FP\RoutingKit\Features\InteractiveFeature;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class FPileBrowser
{
    // Constantes para señales de navegación especial
    const BACK_SIGNAL = '__BACK__';
    const CANCEL_SIGNAL = '__CANCEL__';
    const BACK_TO_ROOT_SELECTION_SIGNAL = '__BACK_TO_ROOT_SELECTION__';

    public function __construct()
    {
        // Constructor puede usarse para la inyección de dependencias si es necesario
    }

    public static function make(): self
    {
        return new self();
    }
    public function browseMultipleFolders(
        array $startPaths,
        bool $allowReturnToPreviousLevel = true,
        bool $allowFolderCreation = true
    ): string {
        $basePath = base_path();

        while (true) {
            // Mostrar el menú raíz con todas las rutas posibles
            $options = [];

            foreach ($startPaths as $key => $path) {
                $label = str_replace($basePath . DIRECTORY_SEPARATOR, '', $path);
                $options["path:$key"] = "📁 {$label}";
            }

            $options[self::CANCEL_SIGNAL] = '❌ Cancelar';

            $choice = \Laravel\Prompts\select("📂 Selecciona una ruta base para explorar:", $options);

            if ($choice === self::CANCEL_SIGNAL) {
                return self::CANCEL_SIGNAL;
            }

            if (str_starts_with($choice, 'path:')) {
                $index = intval(substr($choice, 5));
                $selectedPath = $startPaths[$index];

                // Entrar al explorador de carpetas desde la ruta seleccionada
                $result = $this->browseFolderWithRootReturn($selectedPath, $allowReturnToPreviousLevel, $allowFolderCreation);

                if ($result === self::BACK_TO_ROOT_SELECTION_SIGNAL) {
                    continue; // Vuelve al menú principal
                }

                return $result . '/'; // Devuelve la ruta seleccionada o señal
            }
        }
    }

    public function browseFolderWithRootReturn(
        string $startPath,
        bool $allowReturnToPreviousLevel = true,
        bool $allowFolderCreation = true
    ): string {
        $basePath = base_path();
        $currentPath = $startPath;

        while (true) {
            $folders = collect(File::directories($currentPath))
                ->map(fn($dir) => basename($dir))
                ->toArray();

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $currentPath);
            $currentFolderName = basename($currentPath);

            $options = [];

            $options['select'] = "✅ Usar esta carpeta ({$currentFolderName})";

            if ($allowFolderCreation) {
                $options['__CREATE_DIR__'] = '📁 Crear nueva carpeta';
            }

            foreach ($folders as $folder) {
                $options["dir:{$folder}"] = "📂 {$folder}";
            }

            if ($currentPath !== $startPath && $allowReturnToPreviousLevel) {
                $options[self::BACK_SIGNAL] = '🔙 Volver atrás';
            }

            $options[self::BACK_TO_ROOT_SELECTION_SIGNAL] = '🏠 Volver a selección de rutas base';
            $options[self::CANCEL_SIGNAL] = '❌ Cancelar';

            $choice = \Laravel\Prompts\select("📁 Estás en: {$relativePath}", $options);

            if ($choice === 'select') {
                return $currentPath;
            }

            if ($choice === '__CREATE_DIR__' && $allowFolderCreation) {
                $newFolderName = \Laravel\Prompts\text("📝 Escribe el nombre de la nueva carpeta:");

                if (empty($newFolderName)) {
                    \Laravel\Prompts\warning("⚠️ El nombre no puede estar vacío.");
                    continue;
                }

                if (preg_match('/[\/\\\\]/', $newFolderName)) {
                    \Laravel\Prompts\warning("⚠️ El nombre de la carpeta no puede contener / ni \\.");
                    continue;
                }

                $newFolderPath = $currentPath . DIRECTORY_SEPARATOR . $newFolderName;

                if (File::exists($newFolderPath)) {
                    \Laravel\Prompts\warning("⚠️ Ya existe una carpeta con ese nombre.");
                    continue;
                }

                File::makeDirectory($newFolderPath);
                \Laravel\Prompts\info("✅ Carpeta creada: {$newFolderName}");
                $currentPath = $newFolderPath;
                continue;
            }

            if ($choice === self::BACK_SIGNAL) {
                $currentPath = dirname($currentPath);
                continue;
            }

            if ($choice === self::BACK_TO_ROOT_SELECTION_SIGNAL) {
                return self::BACK_TO_ROOT_SELECTION_SIGNAL;
            }

            if ($choice === self::CANCEL_SIGNAL) {
                return self::CANCEL_SIGNAL;
            }

            if (str_starts_with($choice, 'dir:')) {
                $selectedFolder = substr($choice, 4);
                $currentPath = $currentPath . DIRECTORY_SEPARATOR . $selectedFolder;
            }
        }
    }

    /**
     * Permite al usuario navegar por carpetas y seleccionar una.
     *
     * @param string $startPath La ruta inicial desde la que empezar a navegar.
     * @param bool $allowReturnToPreviousLevel Indica si se debe mostrar la opción "Volver atrás" (a la carpeta padre).
     * @return string La ruta de la carpeta seleccionada, o una señal de navegación.
     */
    public function browseFolder(
        string $startPath,
        bool $allowReturnToPreviousLevel = true,
        bool $allowFolderCreation = true
    ): string {
        $basePath = base_path();
        $currentPath = $startPath;

        while (true) {
            $folders = collect(File::directories($currentPath))
                ->map(fn($dir) => basename($dir))
                ->toArray();

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $currentPath);
            $currentFolderName = basename($currentPath);

            $options = [];

            // Opción para seleccionar esta carpeta
            $options['select'] = "✅ Usar esta carpeta ({$currentFolderName})";

            // Opción para crear nueva carpeta (solo si está permitido)
            if ($allowFolderCreation) {
                $options['__CREATE_DIR__'] = '📁 Crear nueva carpeta';
            }

            // Agregar subcarpetas
            foreach ($folders as $folder) {
                $options["dir:{$folder}"] = "📂 {$folder}";
            }

            // Opción para volver atrás
            if ($currentPath !== $startPath && $allowReturnToPreviousLevel) {
                $options[self::BACK_SIGNAL] = '🔙 Volver atrás';
            }

            // Opción para cancelar
            $options[self::CANCEL_SIGNAL] = '❌ Cancelar / Volver a selección de bases';

            $choice = \Laravel\Prompts\select("📁 Estás en: {$relativePath}", $options);

            if ($choice === 'select') {
                return $currentPath;
            }

            if ($choice === '__CREATE_DIR__' && $allowFolderCreation) {
                $newFolderName = \Laravel\Prompts\text("📝 Escribe el nombre de la nueva carpeta:");

                if (empty($newFolderName)) {
                    \Laravel\Prompts\warning("⚠️ El nombre no puede estar vacío.");
                    continue;
                }

                if (preg_match('/[\/\\\\]/', $newFolderName)) {
                    \Laravel\Prompts\warning("⚠️ El nombre de la carpeta no puede contener / ni \\.");
                    continue;
                }

                $newFolderPath = $currentPath . DIRECTORY_SEPARATOR . $newFolderName;

                if (File::exists($newFolderPath)) {
                    \Laravel\Prompts\warning("⚠️ Ya existe una carpeta con ese nombre.");
                    continue;
                }

                File::makeDirectory($newFolderPath);
                \Laravel\Prompts\info("✅ Carpeta creada: {$newFolderName}");
                $currentPath = $newFolderPath;
                continue;
            }

            if ($choice === self::BACK_SIGNAL) {
                $currentPath = dirname($currentPath);
                continue;
            }

            if ($choice === self::CANCEL_SIGNAL) {
                return self::CANCEL_SIGNAL;
            }

            if (str_starts_with($choice, 'dir:')) {
                $selectedFolder = substr($choice, 4);
                $currentPath = $currentPath . DIRECTORY_SEPARATOR . $selectedFolder;
            }
        }
    }


    /**
     * Permite al usuario navegar por archivos y seleccionar un archivo PHP.
     *
     * @param string $startPath La ruta inicial desde la que empezar a navegar.
     * @param bool $allowReturnToPreviousLevel Indica si se debe mostrar la opción "Volver atrás" (a la carpeta padre).
     * @param bool $allowBackToRootSelection Indica si se debe mostrar la opción "Volver a selección de bases".
     * @return string La ruta del archivo PHP seleccionado, o una señal de navegación.
     */
    public function browsePhpFile(string $startPath, bool $allowReturnToPreviousLevel = true, bool $allowBackToRootSelection = false): string
    {
        $currentPath = $startPath;
        $originalStartPath = $startPath; // Guardar la ruta inicial de esta sesión de browsePhpFile

        while (true) {
            $folders = collect(File::directories($currentPath))->map(fn($d) => basename($d))->toArray();
            $phpFiles = collect(File::files($currentPath))
                ->filter(fn($file) => $file->getExtension() === 'php')
                ->map(fn($file) => $file->getFilename())
                ->toArray();

            $options = [];

            foreach ($phpFiles as $file) {
                $options["file:{$file}"] = "📄 {$file}";
            }

            foreach ($folders as $folder) {
                $options["dir:{$folder}"] = "📂 {$folder}";
            }

            // Opción para volver a la carpeta padre
            if ($currentPath !== $originalStartPath && $allowReturnToPreviousLevel) {
                $options[self::BACK_SIGNAL] = '🔙 Volver atrás';
            }

            // Opción para volver a la selección de rutas base (si se permite)
            if ($allowBackToRootSelection) {
                $options[self::BACK_TO_ROOT_SELECTION_SIGNAL] = '🏠 Volver a la selección de bases';
            }

            $choice = \Laravel\Prompts\select('📁 Selecciona un archivo .php:', $options);

            if (str_starts_with($choice, 'file:')) {
                return $currentPath . DIRECTORY_SEPARATOR . substr($choice, 5);
            }

            if ($choice === self::BACK_SIGNAL) {
                $currentPath = dirname($currentPath);
                continue;
            }

            if ($choice === self::BACK_TO_ROOT_SELECTION_SIGNAL) {
                return self::BACK_TO_ROOT_SELECTION_SIGNAL;
            }

            if (str_starts_with($choice, 'dir:')) {
                $currentPath .= DIRECTORY_SEPARATOR . substr($choice, 4);
            }
        }
    }

    /**
     * Extrae el primer nombre de clase totalmente cualificado de un archivo PHP dado.
     * Este es un analizador básico y podría no manejar todos los casos especiales (ej. múltiples clases, traits, interfaces, comentarios).
     *
     * @param string $filePath La ruta al archivo PHP.
     * @return string|null El nombre de la clase totalmente cualificado, o null si no se encuentra.
     */
    protected function getFullyQualifiedClassNameFromFile(string $filePath): ?string
    {
        $contents = File::get($filePath);
        $namespace = null;
        $className = null;

        // Encontrar el namespace
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Encontrar el nombre de la clase (ignorando clases abstractas, finales, anónimas por simplicidad)
        if (preg_match('/(?:class|trait|interface)\s+([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $contents, $matches)) {
            $className = trim($matches[1]);
        }

        if ($className) {
            return $namespace ? "{$namespace}\\{$className}" : $className;
        }

        return null;
    }

    /**
     * Permite al usuario seleccionar una clase de un archivo PHP y opcionalmente un método.
     *
     * @param string $startPath La ruta inicial desde la que buscar archivos PHP.
     * @param bool $isLivewireComponent Si es true, el resultado será solo el FQCN (NAMESPACE\CLASE), sin selección de método.
     * @param bool $allowBackToRootSelection Si es true, se añade una opción para volver a la selección de bases.
     * @return string El FQCN@metodo, FQCN, o una señal de navegación.
     * @throws \RuntimeException Si no se encuentra ninguna clase en el archivo seleccionado.
     */
    public function selectClassAndOptionalMethod(
        string $startPath,
        bool $isLivewireComponent = false,
        bool $allowBackToRootSelection = false
    ): string {
        while (true) {
            $phpFilePath = $this->browsePhpFile($startPath, true, $allowBackToRootSelection);

            if ($phpFilePath === self::BACK_TO_ROOT_SELECTION_SIGNAL) {
                return self::BACK_TO_ROOT_SELECTION_SIGNAL;
            }
            if ($phpFilePath === self::CANCEL_SIGNAL) {
                return self::CANCEL_SIGNAL;
            }
            if (empty($phpFilePath)) {
                \Laravel\Prompts\warning("No se seleccionó ningún archivo PHP. Inténtalo de nuevo.");
                continue;
            }

            $fullClass = $this->getFullyQualifiedClassNameFromFile($phpFilePath);

            if (!$fullClass) {
                \Laravel\Prompts\warning("No se encontró ninguna clase en el archivo: {$phpFilePath}. Por favor, selecciona otro archivo.");
                continue;
            }

            if (!class_exists($fullClass)) {
                \Laravel\Prompts\warning("La clase '{$fullClass}' no pudo ser cargada. Asegúrate de que el archivo es correcto y las rutas de carga están configuradas. Por favor, selecciona otro archivo.");
                continue;
            }

            if ($isLivewireComponent) {
                // Para Livewire, solo retornamos el FQCN
                return $fullClass;
            } else {
                // Para controladores/clases normales, seleccionamos un método
                $inspector = FPClassInspector::make();
                try {
                    $publicMethods = $inspector->getPublicMethods($fullClass);
                } catch (\RuntimeException $e) {
                    \Laravel\Prompts\error("Error al inspeccionar la clase {$fullClass}: " . $e->getMessage());
                    continue;
                }

                if (empty($publicMethods)) {
                    \Laravel\Prompts\warning("La clase '{$fullClass}' no tiene métodos públicos seleccionables (excluyendo mágicos). Por favor, selecciona otra clase o vuelve atrás.");
                    continue; // Permite re-seleccionar archivo/clase
                }

                $methodOptions = [];
                foreach ($publicMethods as $method) {
                    $methodOptions[$method] = $method;
                }

                $selectedMethod = \Laravel\Prompts\select(
                    "✨ Selecciona un método para '{$fullClass}':",
                    $methodOptions
                );

                if ($selectedMethod) {
                    return "{$fullClass}@{$selectedMethod}";
                } else {
                    \Laravel\Prompts\warning("No se seleccionó ningún método. Inténtalo de nuevo.");
                    continue; // Permite re-seleccionar un método o volver atrás
                }
            }
        }
    }

    /**
     * Permite al usuario navegar y seleccionar un archivo/clase/método
     * desde un conjunto predefinido de rutas base.
     *
     * @param array $pathsConfig Un arreglo de configuraciones de ruta:
     * Cada elemento debe ser un arreglo con:
     * - 'path': (string) La ruta de directorio base.
     * - 'is_livewire': (bool, opcional) True si los archivos en esta ruta son componentes Livewire. Por defecto es false.
     * @return string|null El FQCN@metodo o FQCN del archivo/clase/método seleccionado, o null si se cancela.
     */
    public function browseFromPaths(array $pathsConfig): ?string
    {
        // Validar y normalizar pathsConfig
        $normalizedPaths = [];
        foreach ($pathsConfig as $index => $config) {
            if (is_string($config)) { // Si solo se pasa la ruta como string
                $normalizedPaths[$index] = ['path' => $config, 'is_livewire' => false];
            } elseif (is_array($config) && isset($config['path'])) {
                $normalizedPaths[$index] = [
                    'path' => $config['path'],
                    'is_livewire' => $config['is_livewire'] ?? false
                ];
            } else {
                \Laravel\Prompts\warning("Configuración de ruta inválida en el índice {$index}. Ignorando.");
                continue;
            }
        }

        if (empty($normalizedPaths)) {
            \Laravel\Prompts\error("No se proporcionaron rutas base válidas para la navegación.");
            return null;
        }

        while (true) {
            $options = [];
            foreach ($normalizedPaths as $key => $config) {
                $label = rtrim(str_replace(base_path() . DIRECTORY_SEPARATOR, '', $config['path']), DIRECTORY_SEPARATOR);
                $type = $config['is_livewire'] ? '⚡️ Livewire' : '⚙️ Controlador/Clase';
                $options["path:{$key}"] = "{$type}: {$label}";
            }
            $options[self::CANCEL_SIGNAL] = '❌ Cancelar la selección';

            \Laravel\Prompts\info("Selecciona una ruta base para empezar a navegar:");
            $choice = \Laravel\Prompts\select('Elige una de las rutas de partida:', $options);

            if ($choice === self::CANCEL_SIGNAL) {
                return null; // El usuario canceló la operación principal
            }

            if (str_starts_with($choice, 'path:')) {
                $selectedIndex = substr($choice, 5);
                $selectedPathConfig = $normalizedPaths[$selectedIndex];

                $selectedPath = $selectedPathConfig['path'];
                $isLivewire = $selectedPathConfig['is_livewire'];

                // Entrar en el modo de selección de clase/método para la ruta elegida
                $result = $this->selectClassAndOptionalMethod($selectedPath, $isLivewire, true);

                if ($result === self::BACK_TO_ROOT_SELECTION_SIGNAL) {
                    // Volver al bucle de selección de rutas base
                    continue;
                }
                if ($result === self::CANCEL_SIGNAL) {
                    return null; // Si se cancela la selección interna, se cancela todo
                }

                return $result; // Se ha seleccionado un resultado válido
            }
        }
    }
}
