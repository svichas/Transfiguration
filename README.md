
<p align="center">
  <img src="transfiguration-logo.png" width="325" alt="Transfiguration logo">
</p>

<h1 align="center">Transfiguration</h1>
<p align="center"><strong>A lightweight, secure PHP template engine</strong></p>

<p align="center">
  <a href="https://github.com/svichas/Transfiguration"><img src="https://img.shields.io/travis/svichas/Transfiguration.svg" alt="Build Status"></a>
  <a href="https://github.com/svichas/Transfiguration"><img src="https://img.shields.io/github/license/svichas/Transfiguration.svg" alt="License"></a>
  <a href="https://packagist.org/packages/transfiguration/transfiguration"><img src="https://img.shields.io/packagist/v/Transfiguration/Transfiguration.svg" alt="Packagist"></a>
</p>

---

Transfiguration is a minimal, no-bloat template engine for PHP. It compiles a clean, custom syntax into plain HTML — no heavyweight compilers, no caching layers, just simple and fast rendering.

## ✨ Features

- **Simple syntax** — `{{ }}` code blocks, familiar control flow
- **Safe by default** — template expressions are evaluated through a secure parser; no raw `eval()` on user input
- **Path traversal protection** — `include`/`require` are sandboxed to a configurable base directory
- **Extensible hooks** — register custom tag handlers to extend the engine with your own DSL
- **Zero dependencies** — works as a standalone library or via Composer
- **PHP 7.0+** compatible

---

## 📦 Installation

### Via Composer (recommended)

```bash
composer require transfiguration/transfiguration 1.*
```

```php
require 'vendor/autoload.php';
use Transfiguration\Transfiguration;
```

### Manual download

Download the [latest release](https://github.com/svichas/Transfiguration/releases) and require the entry point:

```php
require 'path/to/Transfiguration/Transfiguration.php';
use Transfiguration\Transfiguration;
```

---

## 🚀 Quick Start

```php
$transfiguration = new Transfiguration();

$transfiguration
    ->html('<h1>{{ echo $title }}</h1>')
    ->data(['title' => 'Hello, World!'])
    ->render();
// Output: <h1>Hello, World!</h1>
```

---

## 📖 Usage Guide

### Setting up a template

```php
$transfiguration = new Transfiguration();

$transfiguration
    ->html($templateHtml)              // raw template string
    ->data($templateVariables)         // key-value bindings
    ->requirePath('/path/to/templates'); // base path for includes
```

### Rendering

Echo the compiled template directly:

```php
$transfiguration->render();
```

Return the compiled template as a string:

```php
$html = $transfiguration->export();
```

---

## 🧩 Template Syntax

### Echo / Print

Output a variable or expression:

```html
{{ echo $username }}
{{ echo "Welcome, " . $name }}
{{ print $item.price }}
```

### Variables

Declare variables inside templates:

```html
{{ var $greeting = "Hello" }}
{{ echo $greeting }}
```

### Conditionals

Full `if` / `elseif` / `else` support:

```html
{{ if $user.loggedIn }}
    <b>Welcome back, {{ echo $user.name }}!</b>
{{ elseif $user.isGuest }}
    <b>Hello, guest!</b>
{{ else }}
    <a href="/login">Log in</a>
{{ endif }}
```

### Loops

Iterate over arrays with optional key:

```html
<ul>
{{ for $item in $items }}
    <li>{{ echo $item }}</li>
{{ endfor }}
</ul>
```

With key-value access:

```html
<ul>
{{ for $id : $product in $products }}
    <li>{{ echo $id }} — {{ echo $product.name }}</li>
{{ endfor }}
</ul>
```

### Includes

Pull in other templates (paths are relative to `requirePath`):

```html
{{ include 'partials/header.html' }}
{{ require 'partials/footer.html' }}
```

### Comments

```html
{# This will not appear in the output #}
```

---

## 🪝 Hooks

Extend the engine by registering custom tag handlers:

```php
$transfiguration->hook('header', function(string $content): string {
    return "<h1 class=\"title\">{$content}</h1>";
});
```

Usage in templates:

```html
{{ header 'Welcome to my site' }}
```

Hooks receive the evaluated content as their argument. The return value replaces the tag in the final output.

---

## 🔒 Security

Transfiguration takes a defense-in-depth approach:

| Concern | Mitigation |
|---|---|
| **Arbitrary code execution** | Template expressions are parsed by a safe recursive-descent evaluator. Only a whitelist of ~30 built-in PHP functions are callable. |
| **Path traversal** | `include`/`require` paths are resolved with `realpath()` and validated to stay within the configured base directory. |
| **Variable pollution** | Template data is kept in a dedicated data store — no `extract()` on global scope. |

> ⚠️ Never pass untrusted user input directly as template source. Hooks and the `requirePath` base directory should be under your control.

---

## 🧪 Testing

```bash
composer install
./vendor/bin/phpunit
```

---

## 📄 License

MIT © [Stefanos Vichas](https://github.com/svichas)

