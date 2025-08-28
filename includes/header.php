<?php
// Header fails - satur lapas sākuma HTML (HEAD daļu, atvērto <body>, lapas virsrakstu un navigāciju)
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <title>xAuto - Autoservisa sistēma</title>
    <link rel="stylesheet" href="assets/css/main.css"> <!-- Galvenais CSS visām lapām -->
    <?php if (isset($page) && $page === 'registration'): ?>
    <link rel="stylesheet" href="assets/css/reg.css">   <!-- Papildus CSS tikai reģistrācijas lapai -->
    <?php elseif (isset($page) && $page === 'planner'): ?>
    <link rel="stylesheet" href="assets/css/planner.css"> <!-- Papildus CSS tikai plānotājam -->
    <?php endif; ?>
</head>
<body>
<header>
    <h2>xAuto servisa pārvaldība</h2>
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
    <!-- Navigācija starp sadaļām, redzama tikai pieslēgtam lietotājam -->
    <nav>
        <a href="dashboard.php?page=registration" <?php echo ($page === 'registration' ? 'class="active"': ''); ?>>Reģistrācija</a> |
        <a href="dashboard.php?page=jobs" <?php echo ($page === 'jobs' ? 'class="active"': ''); ?>>Darbi</a> |
        <a href="dashboard.php?page=invoices" <?php echo ($page === 'invoices' ? 'class="active"': ''); ?>>Rēķini</a> |
        <a href="dashboard.php?page=archive" <?php echo ($page === 'archive' ? 'class="active"': ''); ?>>Arhīvs</a> |
        <a href="dashboard.php?page=planner" <?php echo ($page === 'planner' ? 'class="active"': ''); ?>>Plānotājs</a> |
        <a href="dashboard.php?action=logout">Iziet</a>
    </nav>
    <?php endif; ?>
</header>
<main>
