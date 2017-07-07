<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
    
require_once dirname(__FILE__).'/ErplyWoo.class.php';

$ErplyWoo = new ErplyWoo();

$ErplyWoo->syncErplyProducts();

?>
