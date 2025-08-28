<?php
// -------------------------------------------------------------
// Pamata konfigurācija visam projektam
// -------------------------------------------------------------

// 1) Iestatām laika joslu
date_default_timezone_set('Europe/Riga');

// 2) Sesijas parametri (jābūt pirms session_start())
ini_set('session.gc_maxlifetime', 3600);  // sesijas derīgums 3600s (1 stunda)
// (var papildus uzstādīt arī cookie parametrus, ja nepieciešams)
// session_set_cookie_params(3600);

// 3) Uzsākam sesiju
session_start();

// -------------------------------------------------------------
// Datubāzes pieslēguma konstantes (šobrīd tikai definīcijas)
// -------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'xauto_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// -------------------------------------------------------------
// Lietotāju lomas
// -------------------------------------------------------------
define('ROLE_ADMIN', 'ADMIN');    
define('ROLE_STAFF', 'STAFF');    
$userRoles = [
    ROLE_ADMIN => 'Administrators',
    ROLE_STAFF => 'Darbinieks'
];

// -------------------------------------------------------------
// Šeit var pievienot citas globālās konfigurācijas
// -------------------------------------------------------------
