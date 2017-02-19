# transfiguration
**A php library to transfiguration live html**


```
<?php
require '../lib/transfiguration.php';
#creating transfiguration object
$transfiguration = new transfiguration();

#load html
$transfiguration->loadHtml(file_get_contents("template.html"));
```
