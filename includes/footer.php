<?php
// Footer fails - satur lapas beigu HTML (aizverošos tagus un globālo JS iekļaušanu)
?>
    </main>
    <footer>
        <p>&copy; 2025 xAuto Serviss</p>
    </footer>
    <script src="assets/js/main.js"></script> <!-- Galvenais JS fails visām lapām -->
    <?php if (isset($page) && $page === 'registration'): ?>
    <script src="assets/js/reg.js"></script>   <!-- Papildus JS tikai reģistrācijas lapai -->
    <?php endif; ?>
</body>
</html>
