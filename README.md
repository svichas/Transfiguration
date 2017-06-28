<div align="center">
	<h1>
	<span>Transfiguration</span>
	<div style="font-size:12px;">A light-weight template engine</div>
	</h1>
</div>

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

```php
$transfiguration->render();
```

<h3>Exporting template</h3>

```php
$transfiguration->export();
```