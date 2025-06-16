# FpRoutingKit: Gestión Interactiva de Rutas y Navegación para Laravel

¡Bienvenido a FpRoutingKit! Este paquete Laravel te proporciona una forma intuitiva e interactiva de gestionar tus rutas y la navegación de tu aplicación directamente desde la consola. Olvídate de editar archivos \`web.php\` o \`nav.php\` manualmente para cada nueva ruta o elemento de menú; con FpRoutingKit, puedes hacerlo con comandos de Artisan y una interfaz de consola amigable.

## ✨ Características Principales

* **Creación Interactiva**: Define rutas y elementos de navegación a través de prompts de consola.
* **Soporte para Livewire**: Integra componentes Livewire directamente como rutas de página completa.
* **Permisos Spatie**: Se integra con \`spatie/laravel-permission\` para asignar permisos de forma interactiva a rutas y navegaciones.
* **Estructura Flexible**: Guarda tus rutas y navegaciones en formatos de árbol (\`object_file_tree\`) o planos (\`object_file_plain\`).
* **Filtrado Dinámico**: Un potente sistema de query builder para recuperar rutas y navegaciones basadas en contextos, profundidad, usuarios y más.

---

## 🚀 Instalación

Sigue estos pasos para integrar FpRoutingKit en tu proyecto Laravel.

### 1. Requisitos Previos

* **Laravel**: Asegúrate de tener un proyecto Laravel existente. Puedes crear uno nuevo con:

\`\`\`bash
composer create-project laravel/laravel nombre-de-tu-proyecto
\`\`\`

* **spatie/laravel-permission**: Este paquete se integra con \`spatie/laravel-permission\` para la gestión de permisos. Asegúrate de instalarlo y configurarlo correctamente **antes** de usar FpRoutingKit.

* Instalación:

\`\`\`bash
composer require spatie/laravel-permission
\`\`\`

* **¡MUY IMPORTANTE! Publicar migraciones de Spatie**:

\`\`\`bash
php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider" --tag="permission-migrations"
\`\`\`

* Luego, ejecuta tus migraciones:

\`\`\`bash
php artisan migrate:fresh --seed
# O
php artisan migrate
\`\`\`

* Asegúrate de agregar el trait \`HasRoles\` a tu modelo \`App\\Models\\User\`.

### 2. Modificar \`composer.json\` (Fase Beta)

Durante la fase beta, es necesario cambiar la \`minimum-stability\` en tu archivo \`composer.json\`:

\`\`\`json
{
  "minimum-stability": "dev",
  "prefer-stable": true
}
\`\`\`

### 3. Instalar FpRoutingKit

\`\`\`bash
composer require franciscopazf/routing-kit
\`\`\`

### 4. Publicar el Archivo de Configuración y Archivos Base

\`\`\`bash
php artisan vendor:publish --provider="Fp\\RoutingKit\\YourPackageServiceProvider" --tag="routingkit-full"
\`\`\`

> Nota: Asegúrate de reemplazar el provider si tu namespace es diferente.

---

## ⚙️ Configuración (\`config/routingkit.php\`)

El paquete genera un archivo \`config/routingkit.php\` que puedes personalizar:

[... contenido completo de configuración ...]
> NOTA: Aquí puedes seguir el ejemplo de cómo está en tu README original. Incluye toda la configuración del archivo PHP como bloque dentro de esta misma variable.

---

## 🤝 Uso Básico

1. Registrar rutas FP en \`routes/web.php\`:

\`\`\`php
use Fp\\RoutingKit\\Routes\\FpRegisterRouter;

FpRegisterRouter::registerRoutes();
\`\`\`

2. Comandos interactivos:

\`\`\`bash
php artisan fp:route
php artisan fp:ro
php artisan fp:navigation
php artisan fp:na
\`\`\`

3. Grupos en rutas y navegación:

Explicación...

---

## 👁️ Uso en Vistas (Helper \`fp_navigation()\`)

\`\`\`php
$allNavItems = fp_navigation()->all();
$flattenedNavItems = fp_navigation()->allFlattened();
\`\`\`

---

## 🔍 Filtrado y Recuperación de Datos con el Query Builder

[... toda la sección de query builder ...]

---

## 📚 Más Información

Para más detalles, consulta la documentación completa o el código fuente en el directorio \`src/\` de tu paquete.
