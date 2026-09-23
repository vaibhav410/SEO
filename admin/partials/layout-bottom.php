        </main>
    </div>
</div>

<div class="toasts" aria-live="polite">
    <?php foreach (flash_messages() as $f): ?>
        <div class="toast toast-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
            <span class="toast-icon" aria-hidden="true"><?= $f['type'] === 'error' ? '!' : ($f['type'] === 'success' ? '✓' : 'i') ?></span>
            <p><?= e($f['message']) ?></p>
            <button type="button" class="toast-close" aria-label="Dismiss">×</button>
        </div>
    <?php endforeach; ?>
</div>

<dialog id="confirm-dialog" class="modal" aria-labelledby="confirm-title">
    <form method="dialog">
        <div class="modal-icon" aria-hidden="true">!</div>
        <h2 id="confirm-title">Are you sure?</h2>
        <p id="confirm-message">This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn btn-outline" value="cancel" type="submit">Cancel</button>
            <button class="btn btn-danger-solid" value="confirm" type="submit" id="confirm-ok">Confirm</button>
        </div>
    </form>
</dialog>
</body>
</html>
