<?php
error_reporting(-1);
require_once dirname(__FILE__).'/ErplyWoo.class.php';

$ErplyWoo = new ErplyWoo();

$ErplyWoo->syncErplyUsers();

?>
