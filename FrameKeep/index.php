<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/php/kickstart.php';
require_once F_HEADER;

?>

<main id="framekeep" class="min-vh-100 bg-body text-body" data-progress="<?php echo htmlspecialchars(webPath(F_PROGRESS_API)); ?>" data-restore="<?php echo htmlspecialchars(webPath(F_RESTORE)); ?>" data-backup="<?php echo htmlspecialchars(webPath(F_BACKUP)); ?>" data-api="<?php echo htmlspecialchars(webPath(F_API)); ?>" data-stream="<?php echo htmlspecialchars(webPath(F_STREAM)); ?>" data-thumbnail="<?php echo htmlspecialchars(webPath(F_THUMBNAIL)); ?>" data-scan-frequency="<?php echo (int) SCAN_FREQUENCY; ?>">
    <div class="container-fluid p-3 p-lg-4">
        <div class="row g-4">
            <?php require_once F_SIDEBAR; ?>

            <div class="col-12 col-lg-9">
                <?php require_once F_PLAYER; ?>

                <?php require_once F_LIBRARY; ?>
            </div>
        </div>
    </div>
</main>

<?php

require_once F_DIALOGS;
require_once F_FOOTER;
