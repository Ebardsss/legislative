<footer class="app-footer">
            <span>
                &copy; <?= date('Y') ?>
                Citizen Engagement and Public Feedback Management System
            </span>

            <span>
                Shared Legislative Management Platform
            </span>
        </footer>
    </main>
</div>

<script
    src="<?= e(vendorAsset(
        'bootstrap/bootstrap.bundle.min.js',
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
    )) ?>"
></script>

<script
    src="<?= e(vendorAsset(
        'sweetalert2/sweetalert2.all.min.js',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11'
    )) ?>"
></script>

<script
    src="<?= e(vendorAsset(
        'chartjs/chart.umd.min.js',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js'
    )) ?>"
></script>

<script src="<?= e(appUrl('assets/js/layout.js?v=' . time())) ?>"></script>

<?php foreach ($extraJs as $js): ?>
    <script src="<?= e($js) ?>"></script>
<?php endforeach; ?>

<?php $flash = getFlash(); ?>

<?php if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: <?= json_encode(
                $flash['type'] === 'danger'
                    ? 'error'
                    : $flash['type']
            ) ?>,
            title: <?= json_encode(
                $flash['type'] === 'success'
                    ? 'Success'
                    : 'Notice'
            ) ?>,
            text: <?= json_encode($flash['message']) ?>
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>
