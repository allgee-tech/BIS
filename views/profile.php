<section class="page-hero page-hero-compact"><div class="container"><span class="eyebrow">YOUR ACCOUNT</span><h1>Profile & settings</h1><p>Keep your professional information current and manage your sign-in details.</p></div></section>
<section class="section"><div class="container settings-grid">
    <?php if ($currentUser['role'] === 'student'): ?>
    <form class="panel form-stack" method="post" action="<?= e(pageUrl('profile')) ?>">
        <?= formToken() ?><input type="hidden" name="action" value="update_profile"><span class="eyebrow">STUDENT PROFILE</span><h2>Professional information</h2>
        <label class="field"><span>Full name</span><input name="full_name" required maxlength="160" value="<?= e($currentUser['full_name']) ?>"></label>
        <label class="field"><span>Email</span><input value="<?= e($currentUser['email']) ?>" disabled><small>Email address changes require account verification.</small></label>
        <label class="field"><span>Institution</span><select name="institution_id"><option value="">Select an institution</option><?php foreach ($institutions as $institution): ?><option value="<?= e($institution['id']) ?>" <?= (int) ($profile['institution_id'] ?? 0) === (int) $institution['id'] ? 'selected' : '' ?>><?= e($institution['name']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Programme or course</span><input name="programme" maxlength="180" value="<?= e($profile['programme'] ?? '') ?>"></label>
        <div class="field-row"><label class="field"><span>Academic level</span><input name="academic_level" maxlength="80" value="<?= e($profile['academic_level'] ?? '') ?>"></label><label class="field"><span>Graduation year</span><input name="graduation_year" type="number" min="<?= e(date('Y')) ?>" max="<?= e(date('Y') + 12) ?>" value="<?= e($profile['graduation_year'] ?? '') ?>"></label></div>
        <label class="field"><span>Skills</span><textarea name="skills" rows="3" maxlength="2000" placeholder="Comma-separated skills"><?= e($profile['skills'] ?? '') ?></textarea></label>
        <label class="field"><span>Career interests</span><textarea name="career_interests" rows="3" maxlength="2000" placeholder="Roles, sectors or opportunity types you are interested in"><?= e($profile['career_interests'] ?? '') ?></textarea></label>
        <button class="button button-primary" type="submit">Save profile</button>
    </form>
    <?php if ($currentUser['role'] === 'student'): ?><section class="panel cv-panel"><span class="eyebrow">PRIVATE DOCUMENTS</span><h2>CV or resume</h2><p class="muted small">Documents are kept outside the public web directory and only shown to you and the opportunity owner when attached to an application.</p><form class="form-stack" method="post" enctype="multipart/form-data" action="<?= e(pageUrl('profile')) ?>"><?= formToken() ?><input type="hidden" name="action" value="upload_profile_cv"><label class="field"><span>Upload a current CV</span><input type="file" name="cv" required accept=".pdf,.doc,.docx"><small>PDF, DOC or DOCX, up to 5 MB.</small></label><button class="button button-outline" type="submit">Save CV privately</button></form><?php if ($latestCv !== null): ?><p class="saved-cv"><a href="<?= e(pageUrl('document', ['id' => (int) $latestCv['id']])) ?>"><?= e($latestCv['original_name']) ?></a><small>Updated <?= e(date('M j, Y', strtotime($latestCv['created_at']))) ?></small></p><?php else: ?><p class="muted small">No CV saved to your profile yet.</p><?php endif; ?></section><?php endif; ?>
    <?php elseif ($currentUser['role'] === 'employer'): ?>
    <form class="panel form-stack" method="post" action="<?= e(pageUrl('profile')) ?>">
        <?= formToken() ?><input type="hidden" name="action" value="update_organization_profile"><span class="eyebrow">ORGANIZATION PROFILE</span><h2>Organization information</h2>
        <label class="field"><span>Contact person's name</span><input name="full_name" required maxlength="160" value="<?= e($currentUser['full_name']) ?>"></label>
        <label class="field"><span>Organization name</span><input name="organization_name" required maxlength="180" value="<?= e($organization['name'] ?? '') ?>"></label>
        <label class="field"><span>Organization type</span><input name="organization_type" required maxlength="100" value="<?= e($organization['organization_type'] ?? '') ?>"></label>
        <div class="field-row"><label class="field"><span>Country</span><input name="country" required maxlength="100" value="<?= e($organization['country'] ?? 'Ghana') ?>"></label><label class="field"><span>Phone</span><input name="phone" maxlength="60" value="<?= e($organization['phone'] ?? '') ?>"></label></div>
        <label class="field"><span>Website</span><input type="url" name="website" maxlength="255" value="<?= e($organization['website'] ?? '') ?>"></label>
        <label class="field"><span>Description</span><textarea name="description" rows="5" maxlength="4000"><?= e($organization['description'] ?? '') ?></textarea></label>
        <label class="field"><span>Verification details</span><textarea name="verification_details" rows="3" maxlength="2000"><?= e($organization['verification_details'] ?? '') ?></textarea></label>
        <button class="button button-primary" type="submit">Save organization profile</button>
    </form>
    <?php else: ?>
    <div class="panel"><span class="eyebrow">ACCOUNT</span><h2><?= e($currentUser['full_name']) ?></h2><p><?= e($currentUser['email']) ?></p><p class="muted">Role: <?= e(str_replace('_', ' ', ucfirst($currentUser['role']))) ?></p></div>
    <?php endif; ?>
    <form class="panel form-stack" method="post" action="<?= e(pageUrl('profile')) ?>">
        <?= formToken() ?><input type="hidden" name="action" value="change_password"><span class="eyebrow">SECURITY</span><h2>Change password</h2>
        <label class="field"><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label>
        <label class="field"><span>New password</span><input type="password" name="new_password" required minlength="12" maxlength="72" autocomplete="new-password"><small>At least 12 characters; maximum 72 bytes.</small></label>
        <button class="button button-outline" type="submit">Update password</button>
    </form>
    <div class="panel verification-card"><strong>Email verification</strong><p><?= $currentUser['email_verified_at'] ? 'Your email address is verified.' : 'Your email address has not been verified.' ?></p><?php if (!$currentUser['email_verified_at']): ?><form method="post" action="<?= e(pageUrl('profile')) ?>"><?= formToken() ?><input type="hidden" name="action" value="resend_verification"><button class="button button-text" type="submit">Send verification email</button></form><?php endif; ?></div>
</div></section>
