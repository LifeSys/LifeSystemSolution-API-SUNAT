# Guía: Conectarse por SSH a Railway (LifeSystemSolution-API-SUNAT)

Esta guía sirve para cuando la consola web de Railway falla (WebSocket
desconectado) o simplemente prefieres usar la terminal de tu PC. Úsala
también si formateas o reinstalas Windows y necesitas configurar todo
desde cero.

---

## Datos del proyecto (no cambian)

```
Project:     facc1dee-c443-42ed-b152-572da191be2a
Environment: 97bab540-9895-479e-8627-634d99360e73
Service:     554559ab-0a4c-44a2-9039-f4b8c03eb0e8
```

---

## Paso 1 — Instalar Node.js (si no lo tienes)

Railway CLI necesita Node.js. Descárgalo de https://nodejs.org (versión
LTS) e instálalo con las opciones por defecto.

Verifica en PowerShell o CMD:
```powershell
node --version
npm --version
```

## Paso 2 — Instalar Railway CLI

```powershell
npm install -g @railway/cli
```

Como en Windows a veces el PATH no reconoce `railway` directamente,
usa siempre el comando con `npx` delante (funciona igual, sin
depender de configurar el PATH):

```powershell
npx railway --version
```

Si te muestra un número de versión (ej. `railway 5.35.2`), ya está listo.

## Paso 3 — Iniciar sesión en Railway

```powershell
npx railway login
```

Se abrirá el navegador. Inicia sesión con la cuenta
`johannsebastian789@gmail.com` (o la que uses) y confirma el acceso.
Vuelve a la terminal cuando veas "Signed in as...".

## Paso 4 — Generar una llave SSH (solo la primera vez en esta PC)

```powershell
ssh-keygen -t ed25519
```

Presiona **Enter** en las tres preguntas que aparecen (ubicación por
defecto, sin passphrase). Esto crea las llaves en
`C:\Users\<tu_usuario>\.ssh\`.

## Paso 5 — Conectarse al contenedor

```powershell
npx railway ssh --project=facc1dee-c443-42ed-b152-572da191be2a --environment=97bab540-9895-479e-8627-634d99360e73 --service=554559ab-0a4c-44a2-9039-f4b8c03eb0e8
```

La primera vez te preguntará:
- **"Register this SSH key with Railway?"** → responde `Yes`.
- **"Are you sure you want to continue connecting?"** → escribe `yes`.

Después de eso, quedarás dentro del contenedor con un prompt como:
```
fa1456909c85:/app#
```

Ya puedes correr comandos de Linux/Laravel directamente ahí (ej.
`php artisan tinker`, `cat /etc/ssl/openssl.cnf`, etc.).

---

## Uso diario (una vez todo está instalado)

Solo necesitas repetir el **Paso 5** cada vez que quieras conectarte.
Si la sesión de login expira, repite el **Paso 3** primero.

### Tip: crea un script de acceso rápido

Crea un archivo llamado `conectar-railway.ps1` en tu escritorio con
este contenido:

```powershell
npx railway ssh --project=facc1dee-c443-42ed-b152-572da191be2a --environment=97bab540-9895-479e-8627-634d99360e73 --service=554559ab-0a4c-44a2-9039-f4b8c03eb0e8
```

Para ejecutarlo, clic derecho → "Ejecutar con PowerShell", o desde una
terminal:
```powershell
./conectar-railway.ps1
```

Así evitas escribir o copiar el comando largo cada vez.

---

## Notas importantes

- **Un solo comando a la vez**: al pegar comandos en la sesión SSH,
  pega uno completo, espera el resultado, y luego el siguiente. Pegar
  varios mezclados (por ejemplo, texto de PowerShell junto con
  comandos de bash) puede dejar la terminal en un estado raro. Si eso
  pasa, presiona `Ctrl + C` para cancelar la línea y vuelve a intentar.
- **Salir de la sesión SSH**: escribe `exit` y presiona Enter.
- **La consola web de Railway** (Dashboard → Console) debería seguir
  funcionando normalmente la mayoría de las veces; el SSH es un
  respaldo confiable para cuando falle el WebSocket.

---

## Referencia: qué resolvimos con esta conexión

Usamos esta conexión SSH para confirmar que el fix de OpenSSL 3.x
(activar el "legacy provider" en el Dockerfile) permitió leer
correctamente certificados `.p12` antiguos cifrados con algoritmos
legacy (como RC2-40-CBC), comunes en certificados digitales peruanos.
La prueba de verificación fue:

```bash
cat /etc/ssl/openssl.cnf | grep -A3 "legacy_sect"
```

Y la prueba funcional del certificado:

```bash
php artisan tinker --execute="
\$tenant = App\Models\Tenant::first();
try {
    \$service = new App\Services\Greenter\GreenterService(\$tenant);
    \$ref = new ReflectionMethod(\$service, 'resolveCertificatePem');
    \$ref->setAccessible(true);
    \$pem = \$ref->invoke(\$service);
    echo 'EXITO: PEM resuelto, ' . strlen(\$pem) . ' bytes' . PHP_EOL;
    echo str_starts_with(\$pem, '-----BEGIN') ? 'Empieza con -----BEGIN (formato correcto)' : 'AVISO: no empieza con -----BEGIN';
} catch (\Throwable \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
"
```
