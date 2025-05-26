<?php
if (!isset($default_app_title)) {
    $default_app_title = "โปรแกรมบันทึกค่าใช้จ่ายส่วนตัว";
}
?>
        </main>

    <footer class="py-4 bg-light text-center text-muted border-top mt-auto">
        <div class="container">
            <p class="mb-0 small">&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($default_app_title); ?>. All Rights Reserved.</p>
            <p class="mb-0 small"><em>สร้างสรรค์โดย คุณก๊อต</em></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <?php if (isset($custom_js_files) && is_array($custom_js_files)): ?>
        <?php foreach ($custom_js_files as $js_file): ?>
            <script src="<?php echo htmlspecialchars($js_file); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>