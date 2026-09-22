<?php
/** @var array $state from lead_form_state() @var string $heading @var string $button */
$old = $state['old'];
$errors = $state['errors'];
?>
<div class="form-card" id="lead-form">
    <h2><?= e($heading) ?></h2>
    <?= render_flash() ?>
    <form method="post" action="#lead-form" novalidate data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="_ts" value="<?= e($state['ts']) ?>">
        <div class="hp" aria-hidden="true">
            <label for="lf-website">Leave this field empty</label>
            <input type="text" id="lf-website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-grid two">
            <div class="field">
                <label for="lf-name">Full name</label>
                <input type="text" id="lf-name" name="name" required maxlength="100" autocomplete="name" value="<?= e($old['name'] ?? '') ?>"<?= field_aria($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="field">
                <label for="lf-email">Work email</label>
                <input type="email" id="lf-email" name="email" required maxlength="190" autocomplete="email" value="<?= e($old['email'] ?? '') ?>"<?= field_aria($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
                <label for="lf-phone">Phone <span class="optional">(optional)</span></label>
                <input type="tel" id="lf-phone" name="phone" maxlength="20" autocomplete="tel" inputmode="tel" pattern="\+?[0-9 ()\-]{7,20}" value="<?= e($old['phone'] ?? '') ?>"<?= field_aria($errors, 'phone') ?>>
                <?= field_error($errors, 'phone') ?>
            </div>
            <div class="field">
                <label for="lf-company">Company <span class="optional">(optional)</span></label>
                <input type="text" id="lf-company" name="company" maxlength="150" autocomplete="organization" value="<?= e($old['company'] ?? '') ?>">
            </div>
            <div class="field span-2">
                <label for="lf-interest">Interested in <span class="optional">(optional)</span></label>
                <select id="lf-interest" name="interest">
                    <option value="">Select a service</option>
                    <?php foreach ($state['interests'] as $interest): ?>
                        <option<?= ($old['interest'] ?? '') === $interest ? ' selected' : '' ?>><?= e($interest) ?></option>
                    <?php endforeach; ?>
                    <option<?= ($old['interest'] ?? '') === 'Other' ? ' selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="field span-2">
                <label for="lf-message">How can we help?</label>
                <textarea id="lf-message" name="message" required minlength="10" maxlength="3000"<?= field_aria($errors, 'message') ?>><?= e($old['message'] ?? '') ?></textarea>
                <?= field_error($errors, 'message') ?>
            </div>
        </div>
        <p><button type="submit" class="btn btn-primary"><?= e($button) ?></button></p>
        <p class="form-note">We use your details only to reply to this enquiry.</p>
    </form>
</div>
