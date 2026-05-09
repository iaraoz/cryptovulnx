<?php
// VULN (API8): phpinfo standalone accesible publicamente
// Esto es lo que usan los devs para "verificar" que el deploy funciono
// Y nunca se acuerdan de borrarlo
phpinfo();
