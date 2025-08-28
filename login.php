<?php
// Pieslēgšanās lapa. Šeit lietotājs ievada savus akreditācijas datus (lietotājvārds/parole).

require 'config.php'; // iekļaujam konfigurāciju (uzsāk sesiju, ielādē konstantes utt.)

// Pārbaudām, vai lietotājs jau ir pieslēdzies. Ja jā, tad pāradresējam uz dashboard.
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

// Definējam fiktīvus lietotājus (lietotājvārds => parole un loma)
$users = array(
    'xxx'      => array('password' => 'xxx',      'role' => ROLE_ADMIN),
    'darbinieks' => array('password' => 'darbinieks123', 'role' => ROLE_STAFF)
);

// Mainīgais pieteikšanās rezultāta ziņojumam
$errorMessage = '';

// Apstrādājam iesniegto pieteikšanās formu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Pārbaudām, vai lietotājvārds eksistē un parole sakrīt
    if (isset($users[$username]) && $users[$username]['password'] === $password) {
        // Pieslēgšanās veiksmīga - iestata sesijas mainīgos
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['user_role'] = $users[$username]['role'];

        // Pāradresē uz galveno darba (dashboard) lapu
        header("Location: dashboard.php");
        exit;
    } else {
        // Nepareizs lietotājvārds vai parole
        $errorMessage = "Nepareizs lietotājvārds vai parole!";
    }
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <title>xAuto - Pieslēgšanās</title>
    <link rel="stylesheet" href="assets/css/main.css"> <!-- Ielādējam galveno CSS stilu -->
</head>
<body>
    <div class="login-container">
        <h2>Pieslēgšanās</h2>
        <?php if ($errorMessage): ?>
            <p class="error"><?php echo $errorMessage; ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <label>Lietotājvārds: <input type="text" name="username" required></label><br>
            <label>Parole: <input type="password" name="password" required></label><br>
            <button type="submit">Ienākt</button>
        </form>
    </div>
</body>
</html>
