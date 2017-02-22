# transfiguration
**A lightweight php library to transfigure html**

##Create transfiguration object and load HTML

```php
<?php
require 'lib/transfiguration.php';
#creating transfiguration object
$transfiguration = new transfiguration();

#load html
$transfiguration->loadHtml(file_get_contents("template.html")); #or $transfiguration = new transfiguration(file_get_contents("template.html"));
```

##Replace text with data

```php
#replacing values with data
$values = array(
  "name" => "steve",
  "old" => "19 years old"
);
$transfiguration->replaceValues($values);
```

```
Hello my name is {{name}}, and I'm {{old}}!

Result:

Hello my name is steve, and I'm 19 years old!
```


##Block code

```php
#creating block code
$values = array(
  array(
    [
      "link" => "https://github.com/svichas/transfiguration",
      "text" => "GitHub"
    ],
    [
      "link" => "http://facebook.com/",
      "text" => "Facebook"
    ]
  ),
);
#creating code blocks
$transfiguration->block("links",$values);
```

```html
<ul>{{links}}
  <li><a href="{{link}}">{{text}}</a></li>
{{/links}}</ul>

Result:

<ul>
<li><a href="https://github.com/svichas/transfiguration">GitHub</a></li>
<li><a href="http://facebook.com/">Facebook</a></li>
</ul>
```

##Adding elements

```php
#adding html elements
$elements = array(
  "head" => [
    "tagname" => "link",
    "rel" => "stylesheet",
    "href" => "link/to/css"
  ],
  "body" => [
    "tagname" => "p",
    "style" => "font-weight:bold;",
    "html" => "<i>This was added Later</i>",
  ]
);
$transfiguration->addElement($elements);
```

```html
<html>
<head><head>
<body>
</body>
</html>

result:

<html>
<head><link rel="stylesheet" href="link/to/css"><head>
<body>
  <p style="font-weight:bold;"><i>This was added Later</i></p>
</body>
</html>
```

##Exporting HTML

```php
print $transfiguration->exportHtml();
```

##Minify HTML

```php
$transfiguration->minify();
```

##If block

```php
$show = true;
$transfiguration->isBlock("pageArea", $show);
```
```html
  {%pageArea%}
    <p>This is post area</p>
  {%endblock%}

  result:
  <p>This is post area</p>
```
```php
$show = false;
$transfiguration->isBlock("pageArea", $show);
```
```html
  {%pageArea%}
    <p>This is post area</p>
  {%endblock%}

  result:

```
