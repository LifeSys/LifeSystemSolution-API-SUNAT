# Módulo Consultas (DNI/RUC vía ApiPeru.dev) — Guía de aplicación

Este zip contiene **solo** los archivos nuevos o modificados. Cópialos sobre tu
repo respetando las rutas (mismo árbol de carpetas).

## Archivos NUEVOS (no existían antes)

```
config/consultas.php
app/Consultas/Contracts/IdentityProviderInterface.php
app/Consultas/DTOs/ConsultaResultado.php
app/Consultas/Exceptions/ConsultaException.php
app/Consultas/Exceptions/ProveedorNoDisponibleException.php
app/Consultas/Exceptions/ProveedorTimeoutException.php
app/Consultas/Exceptions/CredencialesInvalidasException.php
app/Consultas/Exceptions/DatosInvalidosException.php
app/Consultas/Providers/ApiPeru/ApiPeruClient.php
app/Consultas/Providers/ApiPeru/ApiPeruProvider.php
app/Consultas/Services/AuditLookupService.php
app/Consultas/Services/ConsultaService.php
app/Http/Requests/Api/V1/Consultas/ConsultarDniRequest.php
app/Http/Requests/Api/V1/Consultas/ConsultarRucRequest.php
app/Http/Requests/Api/V1/Consultas/ConsultarDniRucRequest.php
app/Http/Controllers/Api/V1/Consultas/ConsultaController.php
app/Models/LookupQuery.php
database/migrations/2026_07_24_193617_create_lookup_queries_table.php
```

## Archivos MODIFICADOS (reemplazan a los tuyos — revisa el diff antes de sobreescribir)

```
app/Console/Commands/TestLookup.php        → repurposed hacia ApiPeru.dev (php artisan consultas:test)
app/Services/DocumentLookupService.php     → convertido en adaptador de compatibilidad (NO se eliminó)
app/Http/Controllers/Web/Sunat/ClienteController.php → lookupRuc() ya no llama a decolecta.com directo
app/Providers/AppServiceProvider.php       → +binding de IdentityProviderInterface (feature flag)
bootstrap/app.php                          → +rama para ConsultaException en el handler global
config/facturacion.php                     → -clave 'lookup' (huérfana, ya nadie la usa)
config/services.php                        → -clave 'apis_net_pe' (huérfana, ya nadie la usa)
routes/api.php                             → +3 rutas nuevas, -bloque debug/lookup (obsoleto)
.env.example                               → +CONSULTA_PROVIDER, APIPERU_*, CACHE_*_MINUTES
```

## Pasos después de copiar los archivos

1. **Variables de entorno reales** (en tu `.env`, no en `.env.example`):
   ```
   CONSULTA_PROVIDER=apiperu
   APIPERU_BASE_URL=https://apiperu.dev/api
   APIPERU_TOKEN=tu_token_real_aqui
   APIPERU_TIMEOUT=10
   CACHE_DNI_MINUTES=1440
   CACHE_RUC_MINUTES=1440
   CACHE_DNI_RUC_MINUTES=1440
   ```

2. **Migrar la base de datos**:
   - En Railway: no hace falta nada manual — tu `docker/start.sh` ya corre
     `php artisan migrate --force` en cada arranque.
   - En local/otro entorno sin ese script:
     ```
     php artisan migrate
     ```

3. **Limpiar config cacheada** (si usas `config:cache` en producción):
   ```
   php artisan config:clear
   php artisan optimize:clear
   ```

4. **Probar el proveedor de forma aislada** antes de exponerlo:
   ```
   php artisan consultas:test 73832932 --tipo=dni
   php artisan consultas:test 20601234567 --tipo=ruc
   php artisan consultas:test 73832932 --tipo=dni_ruc
   ```

5. **Probar los endpoints nuevos**:
   ```
   POST /api/v1/consulta/dni       {"dni": "73832932"}
   POST /api/v1/consulta/ruc       {"ruc": "20601234567"}
   POST /api/v1/consulta/dni-ruc   {"dni": "73832932"}
   ```

6. **Verificar que lo legado sigue igual** (deben responder exactamente como antes, sin cambios visibles):
   ```
   GET /api/v1/buscar-documento?numero=...    (POS)
   GET /clientes/buscar-ruc?numero=...         (panel web)
   ```

7. **Monitorear adopción del adaptador legado** por unas semanas:
   ```
   grep "DocumentLookupService (legacy) invocado" storage/logs/laravel.log
   ```
   Cuando ese log deje de aparecer, `DocumentLookupService` puede eliminarse
   con confianza (no antes).

8. **Auditoría de consumo por tenant** (nueva tabla):
   ```sql
   SELECT tenant_id, lookup_type, COUNT(*), AVG(response_time_ms), SUM(cache_hit::int)
   FROM lookup_queries
   GROUP BY tenant_id, lookup_type;
   ```

## Notas específicas para Railway

Revisé tu `Dockerfile`, `docker/start.sh` y `railway.json` reales antes de escribir esto:

- **Migraciones**: ya corren solas. Tu `start.sh` ejecuta `php artisan migrate --force`
  en cada arranque — la migración de `lookup_queries` se aplicará sola en el próximo
  deploy, no necesitas hacer nada manual.
- **Config cacheada**: tu `start.sh` ya hace `config:clear` antes y `config:cache`
  después de migrar, así que `config/consultas.php` se recachea solo en cada deploy.
  No necesitas correr nada aparte.
- **`CACHE_STORE`**: cambiado de `file` a `database` en `.env.example`. El filesystem
  de Railway es efímero y no se comparte entre réplicas — con `file`, el
  `Cache::lock()` anti-stampede que armamos en `ConsultaService` solo protegería
  dentro de una misma instancia, no entre réplicas. `database` ya funciona sin
  pasos extra (la tabla `cache` ya existe en tus migraciones base de Laravel).
  **Mejora opcional**: noté que tu `Dockerfile` ya compila la extensión `redis`
  de PHP (`pecl install redis`), como preparándose para usarlo. Si provisionas
  el addon de Redis de Railway (un clic desde el dashboard), cambia a
  `CACHE_STORE=redis` y usa las variables `REDIS_*` que Railway te da — es más
  rápido y con locks más confiables que `database`. No es obligatorio para que
  esto funcione, solo una mejora futura ya que la base está lista.
- **`LOG_CHANNEL`**: cambiado de `stack` (que apunta a `single`, un archivo en
  disco) a `stderr`. Railway captura stdout/stderr y lo muestra en su panel de
  logs; un archivo en `storage/logs/` se pierde en cada redeploy y no es
  visible desde el dashboard. Con este cambio, el log de
  `"DocumentLookupService (legacy) invocado"` que necesitas monitorear
  aparecerá en `railway logs` sin configuración adicional.
- **Variables de entorno**: `APIPERU_TOKEN`, `CONSULTA_PROVIDER`, etc. se
  configuran en el dashboard de Railway (pestaña *Variables* del servicio),
  no en un archivo `.env` subido al repo — tu `Dockerfile` no copia ningún
  `.env`, así que esto ya es consistente con cómo despliegas hoy.



- **No se eliminó nada** de forma destructiva: `DocumentLookupService`,
  `config('facturacion.lookup')` (comentado, no borrado del todo) y las rutas
  legadas siguen intactas en su contrato externo.
- **Feature flag**: si ApiPeru.dev tiene una caída, cambiar `CONSULTA_PROVIDER`
  requiere primero implementar el proveedor alternativo (hoy solo existe
  `apiperu`). El `match()` en `AppServiceProvider` lanza un error claro si
  pones un valor no soportado, en vez de fallar silenciosamente.
- **No hay linter PHP disponible** en el entorno donde se generó este código;
  se revisó manualmente la sintaxis y la integración de firmas, pero corre
  `composer dump-autoload` y tu suite de tests antes de desplegar a producción.
