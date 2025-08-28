<?php
// Galvenā aplikācijas lapa pēc pieteikšanās. Šeit notiek sadaļu (view) ielāde dinamiski.

require 'config.php';  // Iekļaujam konfigurāciju un uzsākam sesiju

// Pārbaudām, vai lietotājs ir autentificēts. Ja nav, novirzām atpakaļ uz login.php.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// Apstrādājam iziešanas pieprasījumu (logout) 
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy(); // Izbeidzam sesiju (noņems visus sesijas mainīgos)
    header("Location: login.php");
    exit;
}

// Nosakām, kuru sadaļu (view failu) ielādēt, balstoties uz GET parametru ?page=
$page = $_GET['page'] ?? 'registration';  // noklusējumā - "registration"
$allowed_pages = array('registration','jobs','invoices','archive','planner'); // atļauto sadaļu saraksts
if (!in_array($page, $allowed_pages)) {
    $page = 'registration';  // ja padots neatļauts sadaļas nosaukums, ielādē reģistrāciju
}

// Ja no reģistrācijas formas tika iesniegti dati (POST pieprasījums), iekļaujam apstrādes failu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'registration') {
    include 'includes/process_registration.php';
    // Piezīme: process_registration.php iestata mainīgo $message, ko vēlāk izmanto skata fails
}

// Iekļaujam lapas galveni (header) - tajā ielādējas arī CSS/JS un navigācija
include 'includes/header.php';

// Iekļaujam attiecīgo sadaļas skatu failu no views/
include 'views/' . $page . '.php';

// Iekļaujam lapas kājeni (footer) - tajā ielādējas globālie JS (un specifiski JS, ja vajag)
include 'includes/footer.php';
?>
