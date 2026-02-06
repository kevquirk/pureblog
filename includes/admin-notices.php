<?php
// Expects: $notice (string) and $errors (array)
?>
<?php if (!empty($notice)): ?>
    <p class="notice" data-auto-dismiss><?= e($notice) ?></p>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="notice">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
