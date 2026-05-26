# El Gringo Cotizador — CLAUDE.md

Sistema de cotización web para empresas de catering y food truck. Construido en PHP puro + MySQL, desplegado en hosting cPanel compartido.

---

## Negocio

- **Empresa:** El Gringo Burger Joint — Lima, Perú
- **Operación:** 2 dark kitchens + food truck
- **Productos principales:** Smash burgers, pollos crispy, salchipapas
- **Moneda:** Soles peruanos (S/)
- **IGV:** Seleccionable por cotización — none / 10.5% / 18%
- **Precios:** Por persona / por evento / precio libre (según producto)
- **Usuarios:** Admin (Daniel) + Asistente

---

## Stack técnico

- **Backend:** PHP 8.0+ puro, sin frameworks
- **Base de datos:** MySQL 5.7+ / MariaDB 10.3+ via PDO
- **Frontend:** HTML + CSS propio + JS vanilla — sin Bootstrap ni jQuery
- **PDF:** HTML imprimible con CSS @media print (TCPDF disponible como fallback)
- **Hosting:** cPanel compartido — /home/ebakxdhm/elgringo/cotizador/
- **URL producción:** https://elgringo.pe/cotizador
- **Repo:** https://github.com/danielcuesta12/gringo-cotizador

---

## Flujo de trabajo

```
Editar en Claude Code (Mac)
  → git add . && git commit -m "descripción"
  → git push origin main
  → SSH al servidor → cd /home/ebakxdhm/elgringo/cotizador && git pull
```

---

## Estructura de carpetas

```
gringo-cotizador/
├── .env                          ← Variables de entorno (NO en git)
├── .env.example                  ← Plantilla (SÍ en git)
├── .gitignore
├── CLAUDE.md
├── .htaccess                     ← URLs sin .php + no-cache + security headers
├── solicitud.php                 ← Formulario público de solicitud de cotización
├── config/
│   ├── config.php                ← Carga .env, define constantes globales
│   └── database.php              ← Clase PDO Singleton
├── includes/
│   └── helpers.php               ← Funciones globales
├── auth/
│   ├── login.php
│   └── logout.php
├── admin/
│   ├── layout-top.php            ← Header HTML, sidebar, topbar, flash messages
│   ├── layout-bottom.php         ← Cierre HTML + scripts
│   ├── dashboard.php             ← Stats + calendario mes/lista con tooltip inline
│   ├── calendar.php              ← Calendario independiente con tooltip y vista lista
│   ├── events/
│   │   └── create.php            ← Nuevo evento directo (EV- prefix, status aceptada)
│   ├── clients/                  ← index.php, form.php
│   ├── categories/               ← index.php, form.php
│   ├── products/                 ← index.php, form.php
│   ├── packages/                 ← index.php, form.php
│   ├── requests/
│   │   ├── index.php             ← Bandeja de solicitudes con filtros
│   │   └── detail.php            ← Detalle con botones WA/llamar/email + aceptar/rechazar
│   ├── users/                    ← index.php, form.php
│   └── settings/
│       └── index.php             ← Config empresa, logos A/B, tipos de evento, cuentas bancarias, colores PDF
├── quotes/
│   ├── create.php                ← Nueva cotización (EG- prefix)
│   ├── edit.php                  ← Ver + botón seguimiento por estado + WA/email/llamar
│   ├── list.php                  ← Lista con filtros y cards mobile
│   ├── pdf.php                   ← Vista HTML imprimible (pública y privada)
│   ├── send-email.php            ← Envío de email con asunto/cuerpo por estado
│   └── view.php                  ← Vista pública por token (sin login)
├── api/
│   └── quotes.php                ← Endpoints AJAX (search_products, search_clients, etc.)
├── assets/
│   ├── css/
│   │   ├── style.css             ← Design system completo
│   │   └── quoter.css            ← Estilos específicos del cotizador
│   ├── js/
│   │   ├── app.js                ← Sidebar, modales, utilidades globales
│   │   └── quoter.js             ← Motor JS del cotizador
│   └── img/
│       ├── favicon.ico
│       ├── favicon-32.png
│       ├── favicon-180.png
│       └── uploads/
│           └── logos/            ← Logos subidos desde Configuración
└── install/
    ├── schema.sql                ← Schema completo de la BD
    ├── bank_accounts.sql         ← Tabla de cuentas bancarias
    └── update-events.sql         ← Columnas origin, event_time, event_duration, event_location
```

---

## Base de datos

### Tablas principales

| Tabla | Propósito |
|-------|-----------|
| users | Usuarios del sistema (admin / asistente) |
| company_settings | Config empresa en formato key/value |
| categories | Categorías de productos |
| products | Productos con precio_per_person y precio_per_event |
| packages | Combos de productos |
| package_products | Relación N:N paquete-producto |
| clients | Clientes (empresa con RUC / persona con DNI) |
| quotes | Cotizaciones y eventos directos |
| quote_items | Ítems de cada cotización |
| quote_status_log | Historial de cambios de estado |
| quote_requests | Solicitudes públicas de cotización |
| bank_accounts | Cuentas bancarias para mostrar en el PDF |
| quote_templates | Plantillas de T&C reutilizables |

### Campos clave de quotes

```sql
status         ENUM('borrador','enviada','aceptada','rechazada')
origin         ENUM('quote','event') DEFAULT 'quote'
event_time     VARCHAR(10)    -- hora de inicio
event_duration VARCHAR(50)   -- duración estimada
event_location VARCHAR(255)
public_token   VARCHAR(64)   -- acceso público sin login
igv_type       ENUM('none','10.5','18')
accepted_at    TIMESTAMP     -- usado para calcular facturado del mes
```

### Prefijos de numeración

- EG-2026-XXXX — cotizaciones normales (origin: quote)
- EV-2026-XXXX — eventos directos (origin: event, status aceptada inmediato)

### Tabla bank_accounts

```sql
id, bank_name, account_holder, tax_id,
account_type   -- 'ahorros' | 'corriente'
currency, account_number, cci, active, sort_order
```

---

## Clase Database (config/database.php)

```php
Database::fetch($sql, $params)       // array|null — un registro
Database::fetchAll($sql, $params)    // array — todos los registros
Database::insert($sql, $params)      // int — último ID insertado
Database::execute($sql, $params)     // int — filas afectadas
Database::getInstance()              // PDO — para transacciones
```

Nunca concatenar variables en SQL — siempre usar prepared statements con ?

---

## Funciones helpers (includes/helpers.php)

```php
// Autenticación
isLoggedIn()           // bool
requireLogin()         // redirige a login si no hay sesión
requireAdmin()         // redirige si no es admin
isAdmin()              // bool
currentUser()          // array [id, name, email, role]

// Seguridad
verifyCsrf()           // lanza 403 si token inválido — usar en TODO POST
csrfToken()            // string
csrfField()            // string — <input type="hidden" name="csrf_token" ...>

// Sanitización — usar SIEMPRE en inputs
clean($value)          // htmlspecialchars + trim → string
cleanInt($value)       // → int
cleanFloat($value)     // → float
cleanEmail($value)     // → string|false

// Formateo
formatMoney($amount)   // → "S/ 1,234.50"
formatDate($date)      // → "25/12/2024"
formatDatetime($dt)    // → "25/12/2024 14:30"

// Utilidades
redirect($path)                  // redirect y exit — NO incluir .php en la ruta
flashMessage($type, $message)    // 'success'|'error'|'info'
getFlashMessages()               // array — consume y devuelve los mensajes
generateQuoteNumber()            // → "EG-2026-0001"
generateToken($bytes)            // → hex string para public_token
getSetting($key, $default)       // leer company_settings (cacheado)
setSetting($key, $value)         // escribir company_settings
uploadImage($file, $folder)      // → 'logos/img_xxx.jpg' | false — SIN prefijo uploads/
igvRate($type)                   // '18'→0.18, '10.5'→0.105, 'none'→0.0
quoteStatusBadge($status)        // → HTML badge
quoteStatusLabel($status)        // → 'Borrador'|'Enviada'|etc.
paginate($total, $perPage, $page)// → array con offset, has_prev, has_next
```

---

## Constantes disponibles en toda la app

```php
APP_URL      // https://elgringo.pe/cotizador  (sin slash final)
APP_PATH     // /home/ebakxdhm/elgringo/cotizador
UPLOAD_URL   // https://elgringo.pe/cotizador/assets/img/uploads/  (con slash final)
UPLOAD_PATH  // /home/ebakxdhm/elgringo/cotizador/assets/img/uploads/  (con slash final)
MAX_FILE_SIZE // 2097152 (2MB)
DEBUG_MODE   // false en producción
```

### Rutas de archivos subidos

```php
// uploadImage() retorna 'logos/img_xxx.png' — SIN prefijo 'uploads/'
UPLOAD_PATH . $imagen  // ruta en disco
UPLOAD_URL  . $imagen  // URL pública
```

---

## Layout del panel admin

```php
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

requireLogin(); // o requireAdmin()

$pageTitle  = 'Título';
$activePage = 'products';   // marca el nav activo
// $extraHead    = '...';   // CSS adicional
// $extraScripts = '...';   // JS adicional

include __DIR__ . '/../../admin/layout-top.php';
?>
<!-- Contenido aquí -->
<?php include __DIR__ . '/../../admin/layout-bottom.php'; ?>
```

### Valores válidos de $activePage

dashboard | quote-new | event-new | quotes | calendar |
clients | products | categories | packages | requests | users | settings

---

## CSS — Variables del design system

```css
--red:           #C8102E   /* color principal */
--red-dark:      #a80d25
--red-light:     rgba(200,16,46,.1)
--bg-sidebar:    #111111
--bg-page:       #f4f4f5
--bg-card:       #ffffff
--text-primary:  #1a1a1a
--text-secondary:#666666
--text-muted:    #999999
--border:        #e5e5e5
--green:         #16a34a
--blue:          #2563eb
--radius:        10px
--radius-lg:     14px
```

### Variables CSS del PDF

```css
--red          /* fondo de header, tabla, total final, footer */
--text-on-red  /* texto dentro de las cajas de color primario */
```

Generadas desde company_settings: pdf_primary_color y pdf_secondary_color.
Los títulos OBSERVACIONES y TERMINOS Y CONDICIONES son #888 fijo — no cambian.

---

## Configuración empresa (company_settings)

```
company_name, company_ruc, company_address, company_phone, company_email
pdf_primary_color      fondo del PDF (header, tabla, total, footer)
pdf_secondary_color    texto en cajas de color primario
company_logo           Logo A: ruta relativa desde uploads/ (logos/img_xxx.png)
company_logo_b         Logo B: misma estructura
active_logo            'a' o 'b'
event_types            tipos separados por coma: "Corporativo,Boda,Cumpleaños,..."
show_bank_accounts     '1' o '0'
default_terms          términos por defecto
default_observations   observaciones por defecto
quote_prefix           prefijo del número (EG)
quote_validity_days    días de vigencia (15)
whatsapp_number        número WA empresa normalizado: 51XXXXXXXXX
```

---

## Cotizaciones vs Eventos directos

| | Cotización | Evento directo |
|---|---|---|
| Prefix | EG- | EV- |
| origin | 'quote' | 'event' |
| Status inicial | 'borrador' | 'aceptada' |
| accepted_at | cuando se acepta | NOW() al crear |
| Calendario | azul/verde | morado |

### Facturado del mes

```sql
WHERE status = 'aceptada'
  AND MONTH(accepted_at) = MONTH(NOW())
  AND YEAR(accepted_at) = YEAR(NOW())
```

Los eventos directos nacen con status='aceptada' y accepted_at=NOW(), por lo que suman automáticamente.

---

## Botón de seguimiento (edit.php)

| Estado | Título | Color |
|--------|--------|-------|
| borrador | "Enviar cotización al cliente" | neutro |
| enviada | "Hacer seguimiento · N días" | azul |
| aceptada | "Coordinar el evento · N días para evento" | verde |
| rechazada | "Recuperar cliente" | rojo |

Al clicar se despliega panel con 3 botones: WhatsApp, Llamar, Email.
Mensajes WA y email tienen texto y tono diferente por estado.

---

## Emojis en WhatsApp

Usar mb_convert_encoding desde codepoints decimales:

```php
mb_convert_encoding('&#127828;', 'UTF-8', 'HTML-ENTITIES') // 🍔
mb_convert_encoding('&#128075;', 'UTF-8', 'HTML-ENTITIES') // 👋
mb_convert_encoding('&#127881;', 'UTF-8', 'HTML-ENTITIES') // 🎉
```

NO usar bytes \xF0\x9F... literales — se corrompen al guardar en algunos editores.

---

## Cotizador JS (quoter.js)

- parseNum(val, default) — cantidad vacía usa default (1 para qty, 0 para disc)
- Inputs de cantidad y descuento: type="text" inputmode="decimal" (sin flechitas)
- API_URL y CSRF_TOKEN deben definirse ANTES de cargar quoter.js
- recalculate() se dispara en cada cambio de campo

---

## Calendario

### Colores de eventos
- Azul #dbeafe → cotización enviada
- Verde #dcfce7 → cotización aceptada
- Morado #ede9fe → evento directo

### Tooltip
Al clicar una píldora del calendario se muestra un tooltip con:
cliente, hora, lugar, personas, productos, total y botón "Ver cotización/evento →"

---

## Convenciones de código

```php
// Todo archivo protegido empieza con:
requireLogin(); // o requireAdmin()

// Todo POST incluye:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $campo = clean($_POST['campo'] ?? '');
    // validar → ejecutar → flashMessage → redirect
}

// Formularios incluyen:
<form method="post"><?= csrfField() ?></form>

// Redirecciones siempre sin .php:
redirect('/admin/dashboard');      // correcto
redirect('/admin/dashboard.php');  // incorrecto
```

---

## Seguridad — Reglas no negociables

1. Nunca concatenar variables en SQL — siempre ? con PDO
2. Siempre verifyCsrf() al inicio de todo bloque POST
3. Siempre sanitizar con clean() / cleanInt() / cleanFloat()
4. Siempre requireLogin() o requireAdmin() en cada página protegida
5. Nunca mostrar errores PDO en producción (DEBUG_MODE = false)

---

## Bugs resueltos — NO repetir

| Bug | Fix |
|-----|-----|
| Doble /cotizador/ en redirect post-login | requireLogin() extrae parse_url(APP_URL, PHP_URL_PATH) de REQUEST_URI antes de guardar en sesión |
| Logo con doble prefijo uploads/ | uploadImage() retorna 'logos/img_xxx.png' — el consumidor agrega UPLOAD_URL |
| PDF en blanco al imprimir en Mac | Padding movido de body a .doc como margin; @media print cancela body{padding:0} |
| Inputs con flechitas en Chrome | type="text" inputmode="decimal" con -webkit-appearance:none |
| Emojis corruptos en WA desde Mac | mb_convert_encoding('&#CODEPOINT;', 'UTF-8', 'HTML-ENTITIES') |
| Facturado sumaba cotizaciones no aceptadas | Query filtra status='aceptada' AND MONTH(accepted_at) |

---

## Objetivo multi-empresa

Cada instancia solo necesita su propio .env con:
- DB_NAME diferente
- APP_URL y APP_PATH propios
- UPLOAD_URL y UPLOAD_PATH propios

El código fuente es idéntico. La configuración visual (colores, logos, nombre, cuentas) va en company_settings de cada BD.

---

## Notas generales

- Sistema en español — textos de UI, mensajes, labels en español peruano
- Formato de moneda: siempre S/ 1,234.50 — usar formatMoney()
- Fechas: dd/mm/yyyy para mostrar, yyyy-mm-dd para BD — usar formatDate()
- Zona horaria: America/Lima (UTC-5)
- Codificación: UTF-8 / utf8mb4 en toda la BD
- Asistentes solo ven sus propias cotizaciones — query con WHERE q.user_id = ? cuando !isAdmin()
- public_token = link que se comparte con el cliente sin login
- Teléfonos guardados normalizados: 51XXXXXXXXX (con código de país, sin +)

---

## Prompts de ejemplo para Claude Code

```
> Agrega en el cotizador la opción de cargar paquetes además de productos individuales
> Crea admin/reports/index.php con ventas por mes y top 5 productos
> Agrega validación de dígito verificador del RUC peruano en clients/form.php
> Agrega en quotes/list.php la opción de exportar a CSV
> Crea una vista admin/clients/history.php con el historial de cotizaciones de un cliente
> Implementa búsqueda global en el topbar que busque en cotizaciones y clientes
> Crea un dashboard de reportes con gráficas de ventas mensuales
> Agrega un módulo de notificaciones cuando llegan solicitudes nuevas
```
