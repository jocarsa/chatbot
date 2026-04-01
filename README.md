

# 🎓 Jocarsa Chatbot + CRM + IFTTT

Sistema completo de atención al alumno que combina:

* 💬 Chatbot inteligente (IA + base local)
* 👤 Gestión de usuarios (CRM básico)
* ⚙️ Automatización tipo IFTTT (envío de emails)
* 📊 Panel de administración web
* 🧠 Persistencia de conversaciones y aprendizaje

---

## 🚀 Características principales

### 🧠 Chatbot híbrido

* Respuestas basadas en:

  * Base local de preguntas/respuestas (SQLite)
  * IA remota (fallback)
* Contexto del usuario (nombre, curso, etc.)
* Historial de conversación persistente
* Sistema de scoring para coincidencias

### 👤 Gestión de usuarios

* CRUD completo desde panel admin
* Campos:

  * Nombre
  * Apellidos
  * Email
  * Teléfono
  * Curso matriculado
* Identificación automática por teléfono

👉 Implementado en: 

---

### ⚙️ Automatización tipo IFTTT

* Reglas basadas en texto de conversación
* Matching inteligente con scoring
* Envío automático de emails
* Prevención de duplicados (hash + teléfono)

Ejemplo:

> Si el alumno muestra intención → enviar aviso al tutor

👉 Implementado en: 

---

### 📊 Panel de administración

* Login protegido
* 3 módulos:

  * Usuarios
  * Chatbot Q&A
  * Acciones IFTTT
* Dashboard con contadores
* Interfaz responsive estilo WordPress

👉 Implementado en: 

---

### 💬 Interfaz de usuario (frontend)

* Chat minimalista tipo WhatsApp
* Identificación por teléfono
* Visualización de ficha de usuario
* Efectos glassmorphism

👉 Implementado en: 

---

## 🏗️ Arquitectura

```
/project
│
├── index.html        → Frontend chat
├── back.php          → API chatbot + lógica IA + IFTTT
├── admin.php         → Panel de administración
├── admin.sqlite      → Base de datos SQLite
├── debug_ai_logs/    → Logs de IA
├── fondo.jpg/jpeg    → Background UI
├── blancotrans.png   → Overlay visual
```

---

## 🗄️ Base de datos (SQLite)

Tablas principales:

* `usuarios`
* `chatbot_qa`
* `ifttt_acciones`
* `conversaciones`
* `ifttt_envios`

Incluye:

* Índices únicos para evitar duplicados
* Creación automática si no existen

---

## ⚙️ Configuración

### 🔐 Credenciales admin

```php
define('ADMIN_USER', 'jocarsa');
define('ADMIN_PASS', 'jocarsa');
```

⚠️ **IMPORTANTE:** Cambiar en producción.

---

### 📧 SMTP (envío de correos)

```php
define('SMTP_HOST', 'smtp.ionos.es');
define('SMTP_PORT', 587);
define('SMTP_USER', 'notificaciones@institutotame.es');
define('SMTP_PASS', '******');
define('SMTP_SECURE', 'tls');
```

Soporta:

* TLS / SSL
* Autenticación LOGIN
* Debug detallado

---

### 🤖 IA remota

Actualmente usa endpoint:

```
https://XXX/chat/?q=
```

Puedes sustituir por:

* OpenAI API
* LLM local
* servidor propio

---

## 🔄 Flujo de funcionamiento

1. Usuario introduce teléfono
2. Sistema:

   * Busca en SQLite
   * Muestra ficha
3. Usuario pregunta
4. Backend:

   * Busca coincidencias locales
   * Si score alto → responde local
   * Si no → consulta IA remota
5. Se guarda conversación
6. Se evalúan reglas IFTTT
7. (Opcional) se envía email automático

---

## 🧪 Sistema de scoring

* Similaridad textual
* Coincidencia parcial
* Intersección de palabras
* `similar_text()` de PHP

Permite:

* Priorizar respuestas relevantes
* Activar automatizaciones

---

## 🧾 Logging avanzado

Se generan logs en:

```
/debug_ai_logs/
```

Incluyen:

* Prompt enviado a IA
* Respuesta remota
* Matching local
* Activación IFTTT
* Errores

---

## 🛡️ Seguridad

Incluye:

* Prepared statements (PDO)
* Escape HTML (`htmlspecialchars`)
* Sesiones para admin
* Control de duplicados en IFTTT

⚠️ Mejoras recomendadas:

* Hash de contraseñas
* CSRF tokens
* Rate limiting
* Validación de inputs

---

## 📱 UI / UX

* Diseño tipo glassmorphism
* Responsive
* Animaciones suaves
* Scroll personalizado
* Experiencia tipo chat moderno

---

## 🧩 Posibles mejoras

* Integración con WhatsApp API
* Entrenamiento de modelo propio
* Panel de analítica
* Multiusuario admin
* Roles y permisos
* Exportación de datos
* Webhooks externos

---

## 📦 Requisitos

* PHP 7.4+
* SQLite
* Servidor web (Apache/Nginx)
* Extensión PDO SQLite

---

## ▶️ Instalación

```bash
git clone https://github.com/tu-repo/jocarsa-chatbot
cd jocarsa-chatbot
```

1. Subir a servidor PHP
2. Dar permisos a:

   * `admin.sqlite`
   * `debug_ai_logs/`
3. Acceder a:

```
/admin.php
```

---

## 👨‍💻 Autor

**Jose Vicente Carratala**
Desarrollo de software, automatización e IA aplicada

---

## 📄 Licencia

MIT (o la que prefieras)

