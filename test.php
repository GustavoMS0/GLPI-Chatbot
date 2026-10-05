<?php
define('GLPI_ROOT', '/var/www/glpi');
include GLPI_ROOT . '/inc/includes.php';

global $DB;
$res = $DB->request(['FROM' => 'glpi_tickets', 'LIMIT' => 1]);
foreach ($res as $row) {
    print_r(array_keys($row));
}
