<?php
//error_reporting(-1);
require_once dirname(__FILE__).'/ErplyWoo.class.php';

$ErplyWoo = new ErplyWoo();

//Sync Inventory
$ErplyWoo->syncErplyInventory();

//Sync Customers
$ErplyWoo->syncErplyCustomers();

//Create New Invoices
$ErplyWoo->createErplyInvoices();

//Sync New Products From Erply
$ErplyWoo->syncErplyProducts();

//Get New Users
$ErplyWoo->syncErplyUsers();

echo "done";
?>
