Laravel RoutingKit

Laravel RoutingKit es un paquete para Laravel que permite gestionar rutas, navegación y controladores de manera estructurada y con soporte para permisos avanzados usando Spatie Laravel Permission.

## 📦 Instalación

### Requisitos Previos
Antes de comenzar, asegúrate de cumplir con los requisitos del sistema.

### Paso 1: Instalar el Paquete
Ejecuta en la raíz de tu proyecto Laravel:

```bash
composer require francisco-paz/laravel-routingkit
```

### Paso 2: Configurar Spatie Permission
Si aún no tienes Spatie Laravel Permission:

```bash
# Instalar Spatie Permission
composer require spatie/laravel-permission

# Publicar las migraciones
php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"

# Ejecutar migraciones
php artisan migrate
```

Añade el trait `HasRoles` a tu modelo User:

```php
// app/Models/User.php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // resto de tu modelo...
}
```

### Paso 3: Publicar Archivos de Configuración
```bash
php artisan vendor:publish --provider="Rk\\RoutingKit\\RoutingKitServiceProvider"
```

Se crearán:
- Archivo de configuración: `config/routing-kit.php`
- Carpeta `routing-kit/` con archivos esenciales

### Paso 4: Registrar las Rutas
En `routes/web.php`:

```php
use Rk\RoutingKit\Entities\RkRoute;

// Registrar rutas de RoutingKit
RkRoute::registerRoutes();
```

### Verificación de Instalación
```bash
php artisan rk:route --help
ls -la config/routing-kit.php
ls -la routing-kit/
composer show francisco-paz/laravel-routingkit
composer show spatie/laravel-permission
```

## 🚀 Funcionalidades Principales

- **Gestión de Rutas:** Tipos de rutas, creación y sincronización de permisos.
- **Navegación Dinámica:** Creación de menús, integración con Blade y filtros de búsqueda.
- **Controladores:** Configuración y creación de controladores.
- **Ejemplos:** Proyectos de ejemplo y guías paso a paso.

## 🔗 Enlaces de Interés

- Documentación oficial: [https://routingkit.isproyectos.com](https://routingkit.isproyectos.com)
- Repositorio GitHub principal: [Laravel RoutingKit](https://github.com/francisco-paz/laravel-routingkit)
- Ejemplo completo de un sistema SAAS: [Laravel SaaS Starter](https://github.com/francisco-paz/laravel-saas-starter)

## Próximos Pasos

1. Revisar la configuración inicial
2. Crear tu primera ruta
3. Explorar comandos Artisan disponibles
4. Integrar con tu aplicación Laravel