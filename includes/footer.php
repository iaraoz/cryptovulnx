<?php if ($isLoggedIn): ?>
</div><!-- /.main-content -->
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>var TOKEN = "<?php echo isset($_SESSION['token']) ? $_SESSION['token'] : ''; ?>";</script>
<script src="assets/js/app.js"></script>
<?php if (isset($extraJS)) echo $extraJS; ?>
</body>
</html>
