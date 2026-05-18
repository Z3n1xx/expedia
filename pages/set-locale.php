<?php
require_once __DIR__ . '/../includes/config.php';
$currency = $_POST['currency'] ?? null;
$region   = $_POST['region']   ?? null;
global $_CURRENCIES, $_REGIONS;
if ($currency && array_key_exists($currency, $_CURRENCIES)) $_SESSION['currency'] = $currency;
if ($region   && array_key_exists($region,   $_REGIONS))    $_SESSION['region']   = $region;
$back = $_POST['back'] ?? (SITE_URL . '/index.php');
header('Location: ' . $back); exit;
