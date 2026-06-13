</main>
<footer class="glass-footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - by RDZ - Tungkal Punye Dev</p>
    </div>
</footer>
<?php
$base = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : '';
?>
<script src="<?php echo $base; ?>assets/app.js?v=<?php echo time(); ?>"></script>
</body>

</html>