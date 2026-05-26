# 🍔 El Gringo Cotizador — Sistema de Cotización para Eventos

**Versión:** 1.0.0  
**Tecnologías:** PHP 8.0+ · MySQL · Bootstrap 5 · TCPDF  
**Moneda:** Soles peruanos (S/)

---

## 📋 Requisitos del servidor

| Requisito | Versión mínima |
|-----------|---------------|
| PHP       | 8.0+          |
| MySQL     | 5.7+ / MariaDB 10.3+ |
| Extensiones PHP | PDO, pdo_mysql, mbstring, gd |

---

## 🚀 Instalación en cPanel — Paso a paso

### 1. Subir los archivos

1. Entra a **cPanel → Administrador de archivos**
2. Navega a `public_html` (o crea una subcarpeta, ej. `cotizador`)
3. Sube el archivo `.zip` del proyecto
4. Haz clic derecho → **Extraer** en la misma carpeta
5. El resultado debe quedar así:
   ```
   public_html/
   └── cotizador/          ← o directo en public_html/
       ├── .htaccess
       ├── config/
       ├── auth/
       ├── admin/
       └── ...
   ```

### 2. Crear la base de datos

1. En cPanel → **MySQL Databases**
2. Crea una nueva base de datos:  
   → Escribe el nombre (ej: `gringo`) → **Crear base de datos**
3. Crea un nuevo usuario:  
   → Nombre (ej: `dbuser`) → Contraseña fuerte → **Crear usuario**
4. Agrega el usuario a la base de datos:  
   → Selecciona usuario y BD → marca **ALL PRIVILEGES** → **Agregar**
5. Anota los 3 datos:
   - **Nombre BD:** `tuusuariocpanel_gringo`
   - **Usuario BD:** `tuusuariocpanel_dbuser`
   - **Contraseña BD:** `la_que_pusiste`

> 💡 En cPanel el nombre real de la BD y usuario incluye tu usuario de cPanel como prefijo.  
> Ej: si tu usuario es `elgringo`, la BD queda `elgringo_gringo`

### 3. Ejecutar el instalador

1. Abre en el navegador:  
   `https://tudominio.com/cotizador/install/setup.php`
2. El instalador verifica que el servidor cumpla los requisitos
3. Completa el formulario con:
   - Datos de la base de datos (del paso 2)
   - URL del sistema (ej: `https://tudominio.com/cotizador`)
   - Datos de tu cuenta administrador
4. Haz clic en **Instalar sistema**

### 4. Proteger el instalador (IMPORTANTE)

Después de instalar, **elimina o bloquea el instalador**:

**Opción A** — Eliminar desde cPanel Administrador de archivos:  
→ Selecciona `install/setup.php` → Eliminar

**Opción B** — Crear `install/.htaccess` con:
```apache
Deny from all
```

### 5. Primer acceso

Entra a: `https://tudominio.com/cotizador/auth/login.php`  
Usa el email y contraseña que configuraste en el instalador.

---

## ⚙️ Configuración inicial después de instalar

1. **Logo y datos de empresa:**  
   Admin → Configuración → Empresa

2. **Subir tu logo:**  
   Formatos aceptados: JPG, PNG, WebP · Máximo 2MB

3. **Personalizar términos y condiciones:**  
   Admin → Configuración → Plantillas

4. **Crear tu asistente:**  
   Admin → Usuarios → Nuevo usuario

5. **Cargar tus categorías y productos:**  
   Admin → Categorías → Productos

---

## 📁 Estructura de carpetas

```
gringo-cotizador/
├── .htaccess                    # Seguridad Apache
├── config/
│   ├── config.php               # ← Configuración (auto-generado por setup)
│   └── database.php             # Clase PDO
├── includes/
│   └── helpers.php              # Funciones globales
├── auth/
│   ├── login.php                # Pantalla de login
│   └── logout.php
├── admin/
│   ├── dashboard.php            # Panel principal
│   ├── layout.php               # Layout HTML compartido
│   ├── products/                # CRUD productos
│   ├── categories/              # CRUD categorías
│   ├── clients/                 # CRUD clientes
│   ├── packages/                # CRUD paquetes/combos
│   ├── settings/                # Config empresa, T&C
│   └── users/                   # Gestión de usuarios
├── quotes/
│   ├── create.php               # Cotizador (formulario dinámico)
│   ├── edit.php                 # Editar cotización
│   ├── list.php                 # Lista de cotizaciones
│   ├── view.php                 # Vista pública (link compartible)
│   └── pdf.php                  # Generador de PDF
├── api/
│   └── quotes.php               # Endpoints AJAX del cotizador
├── assets/
│   ├── css/
│   │   └── style.css            # Estilos del sistema
│   ├── js/
│   │   └── app.js               # JavaScript principal
│   └── img/
│       └── uploads/             # Imágenes subidas (logo, productos)
├── vendor/
│   └── tcpdf/                   # Librería PDF (se instala con Composer)
└── install/
    ├── schema.sql               # Estructura de la base de datos
    └── setup.php                # Instalador (eliminar después de instalar)
```

---

## 📦 Instalar TCPDF (librería PDF)

### Opción A — Con Composer (recomendado si el hosting lo soporta)

```bash
composer require tecnickcom/tcpdf
```

### Opción B — Descarga manual

1. Descarga desde: https://github.com/tecnickcom/TCPDF/archive/main.zip
2. Extrae y sube la carpeta como `vendor/tcpdf/`
3. El archivo principal debe quedar en: `vendor/tcpdf/tcpdf.php`

---

## 🔒 Seguridad implementada

- Contraseñas hasheadas con bcrypt (cost 12)
- Protección CSRF en todos los formularios POST
- Prepared statements PDO (protección SQL Injection)
- Sanitización de inputs con htmlspecialchars
- Sesiones seguras (HttpOnly, SameSite=Strict)
- Headers de seguridad HTTP via .htaccess
- Acceso a carpetas sensibles bloqueado

---

## 👥 Roles de usuario

| Funcionalidad | Admin | Asistente |
|--------------|-------|-----------|
| Crear cotizaciones | ✓ | ✓ |
| Editar sus cotizaciones | ✓ | ✓ |
| Ver todas las cotizaciones | ✓ | Solo las suyas |
| Generar PDF | ✓ | ✓ |
| CRUD Productos | ✓ | ✗ |
| CRUD Clientes | ✓ | ✓ (crear/editar) |
| CRUD Categorías | ✓ | ✗ |
| CRUD Paquetes | ✓ | ✗ |
| Configuración empresa | ✓ | ✗ |
| Gestión de usuarios | ✓ | ✗ |
| Eliminar registros | ✓ | ✗ |

---

## 💡 Flujo de trabajo recomendado

```
1. Entrar al sistema
2. Admin → Clientes → Buscar o crear cliente
3. Cotizaciones → Nueva cotización
4. Seleccionar cliente, tipo de evento, fecha, N° personas
5. Agregar productos (por persona / por evento / precio libre)
6. Ajustar cantidades, descuentos por ítem
7. Aplicar descuento global si corresponde
8. Seleccionar IGV (ninguno / 10.5% / 18%)
9. Agregar costos extras si hay (movilidad, personal adicional, etc.)
10. Revisar total y precio por persona
11. Guardar como borrador → revisar → cambiar estado a "Enviada"
12. Descargar PDF o compartir por WhatsApp
```

---

## 🆘 Problemas frecuentes

**Error de conexión a la BD:**  
→ Verifica que el nombre de BD y usuario incluyan el prefijo de cPanel

**Imágenes no se suben:**  
→ Verifica permisos de `assets/img/uploads/` (chmod 755)

**PDF no se genera:**  
→ Verifica que TCPDF esté en `vendor/tcpdf/tcpdf.php`

**Sesión expira rápido:**  
→ Usa la opción "Recordarme" en el login (30 días)

---

## 📞 Soporte

Sistema desarrollado para **El Gringo Burger Joint** — Lima, Perú.
