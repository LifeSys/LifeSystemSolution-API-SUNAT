# 📘 Sistema de Planes y Certificados Digitales

> Documentación técnica interna — cómo funciona cada pieza y cómo configurarla.
> Complementa [`01-Configuracion.md`](01-Configuracion.md) (referencia de endpoints) y
> [`20-Despliegue-VPS.md`](20-Despliegue-VPS.md) (despliegue).

## 📑 Contenido

- [1. Sistema de Planes](#1-sistema-de-planes)
  - [1.1 Cómo está modelado](#11-cómo-está-modelado)
  - [1.2 El seeder (`PlanSeeder`)](#12-el-seeder-planseeder)
  - [1.3 Panel de administración](#13-panel-de-administración)
  - [1.4 Cómo agregar/editar un plan](#14-cómo-agregarcambiar-un-plan)
  - [1.5 Cómo se aplica un plan a un tenant](#15-cómo-se-aplica-un-plan-a-un-tenant)
  - [1.6 Troubleshooting](#16-troubleshooting-planes)
- [2. Certificados digitales](#2-certificados-digitales)
  - [2.1 Qué es y para qué sirve](#21-qué-es-y-para-qué-sirve)
  - [2.2 Arquitectura multiempresa](#22-arquitectura-multiempresa)
  - [2.3 Orden de resolución del certificado](#23-orden-de-resolución-del-certificado)
  - [2.4 Dónde vive el certificado de cada empresa](#24-dónde-vive-el-certificado-de-cada-empresa)
  - [2.5 Variables de entorno globales — cuándo usarlas](#25-variables-de-entorno-globales--cuándo-usarlas)
  - [2.6 Cómo subir/actualizar el certificado de una empresa](#26-cómo-subiractualizar-el-certificado-de-una-empresa)
  - [2.7 Troubleshooting](#27-troubleshooting-certificados)
- [3. Railway y el filesystem efímero](#3-railway-y-el-filesystem-efímero)
- [4. Checklist de configuración inicial](#4-checklist-de-configuración-inicial)

---

## 1. Sistema de Planes

### 1.1 Cómo está modelado

Un **plan** define cuánto puede usar una empresa (tenant) del sistema: cuántos documentos SUNAT al mes, cuántas sucursales, qué módulos tiene habilitados (`features`), etc.

| Pieza | Archivo | Rol |
|---|---|---|
| Modelo | `app/Models/Plan.php` | Tabla `plans`. Cada fila es un plan (`free`, `pro`, `business`, ...). |
| Seeder | `database/seeders/PlanSeeder.php` | Define los planes por defecto y los inserta/actualiza en BD. |
| Catálogo de claves | `config/plans.php` | Lista de `limits`/`features` conocidos, con labels amigables para el formulario del panel. **No** es la fuente de los valores del plan — solo describe qué claves existen y cómo mostrarlas. |
| Panel admin | `app/Http/Controllers/Admin/PlanController.php` + `resources/js/pages/admin/planes/*` | CRUD visual de planes (`/admin/planes`). |
| Servicio de negocio | `app/Services/Plan/PlanService.php` | Resuelve el plan activo de un tenant, valida límites, cachea. |
| Endpoint público | `routes/api.php` → `GET /api/v1/planes` (`SubscriptionController::plans`) | Lista de planes activos para que un cliente externo elija uno al registrarse. |

**Estructura de la tabla `plans`:**

```
id, slug, name, price_monthly, price_yearly,
limits (json), features (json),
is_unlimited, duration_days,
sort_order, is_active, timestamps
```

- `limits` es un JSON de `clave => número`. `-1` significa **ilimitado**.
- `features` es un JSON de strings (flags). La presencia de la clave = feature habilitada.
- `slug` es el identificador estable (`free`, `pro`, `business`) que usan tenants, seeders y el endpoint de suscripción — **no cambiarlo** una vez en producción sin migrar los tenants que lo usan.

### 1.2 El seeder (`PlanSeeder`)

`database/seeders/PlanSeeder.php` define 3 planes (`free`, `pro`, `business`) con sus límites y features, y los inserta con:

```php
Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
```

`updateOrCreate` significa que **correr el seeder es idempotente**: si el plan ya existe (por `slug`), lo actualiza con los valores del seeder; si no existe, lo crea. Nunca duplica filas.

Al final del seeder también desactiva planes viejos que ya no aplican:

```php
Plan::whereIn('slug', ['starter', 'growth'])->update(['is_active' => false]);
```

`database/seeders/DatabaseSeeder.php` (el seeder raíz que corre `php artisan db:seed`) llama a `PlanSeeder`:

```php
public function run(): void
{
    $this->call([
        PlanSeeder::class,
    ]);
}
```

**⚠️ Importante — esto NO corre solo:** el script de arranque (`docker/start.sh`, ver [sección 3](#3-railway-y-el-filesystem-efímero)) solo ejecuta `php artisan migrate --force`. **No ejecuta `db:seed`.** Si conectas una base de datos nueva (o la borras y la vuelves a crear), las migraciones crean la tabla `plans` vacía, pero **nadie la llena automáticamente**. Hay que correr el seeder a mano:

```bash
php artisan db:seed --class=PlanSeeder
```

o, si además quieres correr otros seeders del proyecto:

```bash
php artisan db:seed
```

### 1.3 Panel de administración

`/admin/planes` (Inertia + React) lista todos los planes ordenados por `sort_order`, con acciones para crear, editar, activar/desactivar y eliminar (bloqueado si el plan tiene suscripciones activas).

El formulario de creación/edición lee `config('plans.limits')` y `config('plans.features')` para mostrar labels amigables agrupados por categoría (`SUNAT`, `Uso general`, `Marketplace y B2B`, etc.), pero **permite agregar cualquier clave personalizada** en `snake_case` que no esté en el catálogo — queda guardada igual en el JSON del plan, solo sin label bonito.

### 1.4 Cómo agregar/cambiar un plan

Hay dos formas, y no son excluyentes:

**A) Desde el panel** (`/admin/planes` → "Nuevo plan" o editar uno existente). Recomendado para cambios puntuales de precio/límites sin tocar código.

**B) Editando el seeder** (`database/seeders/PlanSeeder.php`) y volviendo a correrlo. Recomendado cuando el cambio debe quedar versionado en git y reproducirse igual en cualquier entorno (nuevo dev, nueva base de datos, staging, etc.):

```bash
php artisan db:seed --class=PlanSeeder
```

Como usa `updateOrCreate`, es seguro correrlo en producción sobre planes que ya existen — actualiza en vez de duplicar.

Si agregas una clave de `limit` o `feature` nueva, considera también agregarla a `config/plans.php` para que el panel la muestre con un label legible en vez de aparecer como texto libre.

### 1.5 Cómo se aplica un plan a un tenant

- El tenant tiene una columna `plan` (slug) y, para el flujo de suscripciones con pago, una fila en `subscriptions` (`tenant_id`, `plan_id`, `status`, `billing_cycle`, etc.).
- `PlanService` resuelve el plan activo del tenant, lo cachea 5 minutos por tenant (`tenant:{id}:active_plan`), y expone `getLimit()` / `hasFeature()` para que el resto de la app valide cupos (middleware `CheckPlanLimit`) y features.
- Al crear/editar/eliminar/activar un plan desde el panel, `PlanController::limpiarCachePlanes()` invalida esa cache **para todos los tenants** — así el cambio se refleja de inmediato sin esperar los 5 minutos.

### 1.6 Troubleshooting (planes)

| Síntoma | Causa probable | Solución |
|---|---|---|
| "Aún no hay planes definidos" en `/admin/planes` tras conectar una BD nueva | El seeder nunca corrió (ver 1.2) | `php artisan db:seed --class=PlanSeeder` |
| Cambié un plan en el panel pero un tenant sigue viendo el límite viejo | Cache de 5 min no se refrescó (edge case: falla de red al invalidar) | Esperar 5 min o limpiar manualmente: `Cache::forget("tenant:{id}:active_plan")` |
| El seeder "borra" mis cambios manuales hechos en el panel | `updateOrCreate` sobreescribe todos los campos del slug que coincide | Si vas a editar planes solo desde el panel, no vuelvas a correr el seeder para ese slug, o edita el seeder para reflejar el mismo estado |

---

## 2. Certificados digitales

### 2.1 Qué es y para qué sirve

Cada comprobante (factura, boleta, nota, guía) se firma digitalmente antes de enviarse a SUNAT. Esa firma se hace con el **certificado digital** (`.pfx`/`.p12`/`.pem`) de la empresa emisora — es el certificado que la empresa compró con su entidad certificadora, específico de su RUC.

Como el sistema es **multiempresa** (multi-tenant), cada empresa conectada a la API tiene (o debería tener) su propio certificado — no se comparte entre empresas.

### 2.2 Arquitectura multiempresa

El código responsable de firmar vive en `app/Services/Greenter/GreenterService.php`. Antes de firmar, resuelve **de dónde sacar el certificado** con `resolveCertificatePem()`.

Esto NO usa conexiones de base de datos separadas por tenant (no es tenancy por base de datos) — todos los tenants comparten la misma BD, y cada fila `tenants` tiene sus propias columnas de certificado.

### 2.3 Orden de resolución del certificado

`resolveCertificatePem()` prueba, en este orden, y usa el **primero que encuentre**:

```
0. CERTIFICATE_PEM_B64   (env global, Railway/cloud)     ← certificado ÚNICO para TODO el sistema
1. CERTIFICATE_PATH      (env global, archivo en disco)  ← certificado ÚNICO para TODO el sistema
2. tenant->certificate_content / certificate_path         ← certificado propio de CADA empresa
```

**Esto es clave:** los pasos 0 y 1 son *variables de entorno globales del servicio*. Si están configuradas, **se usan para firmar los comprobantes de todas las empresas**, sin importar cuál sea el tenant. Solo tiene sentido usarlas si la plataforma tiene un certificado propio de PSE para firmar en nombre de todos — **no son "un slot por empresa"**. No existe forma de tener `CERTIFICATE_PEM_B64` distinto por tenant; solo puede haber un valor global a la vez.

Para un sistema multiempresa donde cada RUC firma con su propio certificado (el caso normal), los pasos 0 y 1 deben quedar **vacíos**, y todo pasa por el paso 2.

### 2.4 Dónde vive el certificado de cada empresa

**Antes del fix (agosto 2026):** `tenant->certificate_path` apuntaba a un archivo en el disco local del contenedor (`storage/app/private/certificates/{ruc}/cert.pem`). Esto se rompía cada vez que Railway redeployaba o reiniciaba el contenedor, porque el filesystem es efímero (ver [sección 3](#3-railway-y-el-filesystem-efímero)) — el archivo se perdía y `getCertificateContent()` devolvía `null`, disparando:

```
RuntimeException: Certificado digital no encontrado.
Configure CERTIFICATE_PEM_B64 (Railway) o CERTIFICATE_PATH en .env.
```

**Después del fix:** el certificado se guarda **en la base de datos**, en la columna `tenants.certificate_content` (texto largo, cast `encrypted`, igual que `certificate_password`). `Tenant::getCertificateContent()` ahora prioriza esa columna:

```php
public function getCertificateContent(): ?string
{
    if (! empty($this->certificate_content)) {
        $decoded = base64_decode($this->certificate_content, strict: true);
        if ($decoded !== false && $decoded !== '') {
            return $decoded;
        }
    }

    // Fallback legado — solo funciona si el archivo sigue existiendo.
    if ($this->certificate_path && file_exists($this->certificate_path)) {
        return file_get_contents($this->certificate_path);
    }

    return null;
}
```

`DocumentStorageService::storeCertificate()` es el único punto de escritura: guarda el certificado en `certificate_content` (BD, fuente de verdad) y, además, intenta dejar una copia en disco local como *best-effort* (solo utilidad de depuración local — nunca se depende de ella).

Como la BD ya persiste entre redeploys de Railway (no es efímera como el filesystem de la app), el certificado sobrevive a reinicios, redeploys y escalado horizontal (varias réplicas del contenedor, si las hubiera).

### 2.5 Variables de entorno globales — cuándo usarlas

| Variable | Uso | Alcance |
|---|---|---|
| `CERTIFICATE_PEM_B64` | Certificado en base64, para entornos sin volumen persistente (Railway) | **Global**, un solo certificado para todo el sistema |
| `CERTIFICATE_PATH` | Ruta a un archivo de certificado en disco | **Global** |
| `CERTIFICATE_PASSWORD` | Contraseña del certificado global (si aplica) | **Global** |

**No las configures** si tu operación es "cada empresa firma con su propio RUC" (caso normal multiempresa) — déjalas vacías y usa exclusivamente el certificado por tenant (sección 2.6). Configúralas solo si:

- Actúas como PSE (Proveedor de Servicios Electrónicos) y firmas en nombre de terceros con **un único** certificado propio, o
- Quieres un certificado de respaldo temporal mientras migras/depuras un caso puntual (recuerda que ese respaldo se aplicaría a **cualquier** tenant sin certificado propio configurado, no solo al que estás depurando).

### 2.6 Cómo subir/actualizar el certificado de una empresa

**Desde el panel de administración** (`Empresas` → editar empresa → campo certificado), usa `app/Http/Controllers/Admin/TenantController.php::guardarArchivos()`.

**Desde el panel de configuración del propio tenant** (`/sunat/configuracion`), usa `app/Http/Controllers/Web/Sunat/ConfiguracionController.php::update()`.

**Vía API pública**, dos endpoints (ver también [`01-Configuracion.md`](01-Configuracion.md)):

```bash
# Al registrar una empresa nueva
curl -X POST https://tu-api.com/api/v1/registro \
  -F "ruc=20100000001" \
  ...
  -F "certificado=@certificado.pfx" \
  -F "contrasena_certificado=123456"

# Para actualizar el certificado de una empresa existente
curl -X POST https://tu-api.com/api/v1/empresa/certificado \
  -H "X-Api-Key: {api_key}" -H "X-Api-Secret: {api_secret}" \
  -F "certificado=@nuevo-cert.pfx" \
  -F "contrasena_certificado=nueva_pass"
```

Formatos aceptados: `.pfx`, `.p12`, `.pem`, `.cer`, `.crt`. Si es `.pfx`/`.p12`, la contraseña es obligatoria.

Todos estos puntos de entrada terminan llamando a `DocumentStorageService::storeCertificate()`, que persiste en BD (sección 2.4). No importa por cuál de los 4 flujos subas el certificado — el resultado es el mismo.

### 2.7 Troubleshooting (certificados)

| Síntoma | Causa probable | Solución |
|---|---|---|
| `RuntimeException: Certificado digital no encontrado` | El tenant no tiene `certificate_content` en BD (nunca lo subió, o lo subió antes del fix de agosto 2026 y el archivo se perdió en un redeploy) | Volver a subir el certificado de esa empresa desde el panel o la API |
| El panel dice "Certificado: Cargado" pero igual falla al emitir | `certificate_path` tiene un valor en BD (no está vacío) pero el archivo físico ya no existe — es el bug legado que el fix de agosto 2026 corrige | Re-subir el certificado; a partir de ahí queda en BD, no depende del archivo |
| Todas las empresas firman con el mismo certificado sin importar cuál configuraron | `CERTIFICATE_PEM_B64` o `CERTIFICATE_PATH` están seteadas en el entorno — tienen prioridad sobre el certificado del tenant | Vaciar esas variables en Railway si el objetivo es que cada empresa use el suyo |
| Error de contraseña / no se puede leer el `.pfx` | Contraseña incorrecta, o el archivo no es un PKCS12 válido | `toPem()` prueba varias contraseñas (la global, la del tenant, la del RUC PSE, vacía) antes de fallar — revisar `storage/logs` (canal `sunat` / log general) para ver cuál intento falló |

---

## 3. Railway y el filesystem efímero

**El problema de fondo detrás de los dos bugs de agosto 2026** (planes desaparecidos y certificado perdido) es el mismo: Railway (y la mayoría de plataformas de contenedores tipo PaaS) usa **contenedores efímeros** por defecto. Todo lo que se escriba en el disco local del contenedor —archivos subidos, `storage/app/...`, cachés en disco— **se pierde** cada vez que:

- Se hace un nuevo deploy (push a `main`, o rebuild manual).
- El contenedor se reinicia (crash, redeploy, escalado, mantenimiento de la plataforma).
- Se reemplaza/reconecta la base de datos (aunque esto es un caso distinto: ahí se pierde la BD, no el filesystem — pero ambos "reinician" el estado de la app en la práctica).

**Lo que SÍ persiste entre redeploys:**
- La base de datos (mientras no la borres/reconectes tú mismo).
- Las variables de entorno configuradas en Railway.
- Un **Volume** de Railway explícitamente montado (si lo configuras).

**Lo que NO persiste sin un Volume:**
- Cualquier archivo escrito con `Storage::disk('local')` o rutas directas a `storage/app/...`.

**Regla práctica para este proyecto:** cualquier dato que deba sobrevivir a un redeploy y no esté ya en la base de datos, debe:
1. Guardarse en la base de datos (como se hizo con el certificado), o
2. Vivir en un Volume persistente de Railway, o
3. Vivir en storage externo (S3 o similar) — no implementado actualmente en este proyecto.

El script de arranque `docker/start.sh` solo corre `php artisan migrate --force` en cada arranque — **no** corre `db:seed` ni restaura archivos. Cualquier dato de inicialización (planes, certificados, configuración) que dependa de un seeder o de un archivo debe re-ejecutarse/re-subirse manualmente tras conectar una base de datos nueva, salvo que se agregue explícitamente al script de arranque.

---

## 4. Checklist de configuración inicial

Al conectar una base de datos **nueva** (o recrear una borrada) en cualquier entorno:

```
1. [ ] php artisan migrate --force
       → crea las tablas (plans, tenants, subscriptions, etc.)

2. [ ] php artisan db:seed --class=PlanSeeder
       → llena la tabla plans con free/pro/business
       → verificar en /admin/planes que aparezcan 3 planes activos

3. [ ] Confirmar que CERTIFICATE_PEM_B64 / CERTIFICATE_PATH
       estén vacías en Railway (a menos que uses un cert. PSE
       global compartido — ver sección 2.5)

4. [ ] Por cada empresa/tenant existente:
       [ ] Re-subir su certificado digital (.pfx/.p12/.pem)
           desde /empresas → editar, o vía API
           POST /api/v1/empresa/certificado
       [ ] Verificar en la ficha de la empresa que diga
           "Certificado: Cargado"
       [ ] Emitir un comprobante de prueba (entorno beta)
           para confirmar que firma y SUNAT lo acepta

5. [ ] Si vas a usar un Volume de Railway para persistencia
       adicional de storage/app (XMLs, CDRs, PDFs generados),
       configurarlo ahora — los certificados ya no dependen
       de esto, pero los documentos generados sí.
```