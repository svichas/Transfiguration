<div align="center">
	<h1>
	<span>Transfiguration</span>
	<h5 >A light-weight template engine</h5>
	</h1>
</div>

<p>Transfiguration should be considered an early alpha.</p>

<h3>Installation</h3>

```php
require 'Transfiguration/transfiguration.php';
```

<h3>Usage</h3>

```php
$data = array();
$transfiguration = new Transfiguration(file_get_contents("{template path}"), $data, $include_path);
```

<h3>Rendering template</h3>
<p>This function is printing template html into the page.</p>

```php
$transfiguration->render();
```

<h3>Exporting template</h3>
<p>This function returns executed template html.</p>

```php
$transfiguration->export();
```

<h2>Transfiguration code</h2>

<h3>For loop</h3>

```html
<ul>
{{ for $value in $key : $var }}
	<li>{{ echo $key . " " . $value}}</li>
{{endfor}}
</ul>
```

<h3>If statement</h3>

```html
{{ if $loggedin == true}}
	<b>User logged in!</b>
{{ elseif $guest == true }}
	<b>User is guest!</b>
{{ else }}
 	<b>User not logged in!</b>
{{ endif }}
```

<h3>Print & Echo</h3>

```html
{{echo "string"}}
```
<p>or</p>

```html
{{print "string"}}
```

<h3>Including files</h3>

```html
{{ include 'base/footer.html' }}
```
<p>or</p>

```html
{{ require 'base/footer.html' }}
```