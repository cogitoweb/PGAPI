<?php

include_once('PGAPI.php');

$crawlerObject = new PGAPI\crawler('lavanderie%20industriali%20e%20noleggio%20biancheria');

print($crawlerObject->getAllResults());