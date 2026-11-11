<section class="auth-section">
    <div class="auth-decoration"><span class="auth-orb auth-orb-one"></span><span class="auth-orb auth-orb-two"></span><span class="auth-grid"></span></div>
    <div class="container auth-container">
        <div class="auth-aside"><span class="eyebrow">CAMPUS2CAREER</span><h1><?= $mode === 'register' ? 'Make room for what’s next.' : 'Welcome back to your next step.' ?></h1><p><?= $mode === 'register' ? 'A thoughtful profile is the start of finding meaningful opportunities.' : 'Your next opportunity could be closer than you think.' ?></p><div class="auth-aside-note"><span>✦</span><span>Opportunities built around your ambitions.</span></div></div>
        <div class="auth-card">
            <?php if ($mode === 'login'): ?>
                <span class="eyebrow">SIGN IN</span><h2>Welcome back</h2><p class="form-intro">Sign in to continue to your Campus2Career account.</p>
                <form method="post" action="<?= e(pageUrl('login')) ?>" class="form-stack" data-auth-submit>
                    <?= formToken() ?><input type="hidden" name="action" value="login">
                    <label class="field"><span>Email address</span><input type="email" name="email" required maxlength="190" autocomplete="email"></label>
                    <label class="field"><span>Password</span><span class="password-control"><input type="password" name="password" required autocomplete="current-password"><button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">Show</button></span></label>
                    <button class="button button-primary button-full" type="submit">Sign in</button>
                </form>
                <p class="auth-switch">New to Campus2Career? <a href="<?= e(pageUrl('register')) ?>">Create an account</a></p>
                <p class="auth-switch"><a href="<?= e(pageUrl('forgot-password')) ?>">Forgot your password?</a></p>
            <?php elseif ($mode === 'register'): ?>
                <span class="eyebrow">GET STARTED</span><h2>Create your account</h2><p class="form-intro">Choose the account type that best describes you.</p>
                <form method="post" action="<?= e(pageUrl('register')) ?>" class="form-stack" id="register-form" data-role-toggle data-auth-wizard data-auth-submit>
                    <?= formToken() ?><input type="hidden" name="action" value="register">
                    <div class="auth-wizard-progress" data-auth-progress hidden>
                        <div class="auth-wizard-progress-copy"><span data-auth-step-label>Step 1 of 3</span><span data-auth-progress-title>Account details</span></div>
                        <div class="auth-wizard-track" role="progressbar" aria-label="Sign-up progress" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1"><span data-auth-progress-bar></span></div>
                        <ol class="auth-wizard-steps" aria-label="Sign-up steps">
                            <li aria-current="step"><span>1</span>About you</li>
                            <li><span>2</span>Your profile</li>
                            <li><span>3</span>Secure account</li>
                        </ol>
                    </div>
                    <div class="auth-wizard-step" data-auth-step="1" aria-live="polite">
                        <label class="field"><span>Account type</span><select name="role" id="account-role"><option value="student" <?= ($selectedRole ?? 'student') === 'student' ? 'selected' : '' ?>>Student or graduate</option><option value="employer" <?= ($selectedRole ?? '') === 'employer' ? 'selected' : '' ?>>Employer or opportunity provider</option></select></label>
                        <label class="field"><span>Full name</span><input name="full_name" required maxlength="160" autocomplete="name"></label>
                        <label class="field"><span>Email address</span><input type="email" name="email" required maxlength="190" autocomplete="email"></label>
                    </div>
                    <div class="auth-wizard-step" data-auth-step="2" aria-live="polite">
                        <div class="student-fields" id="student-fields">
                            <label class="field"><span>Institution</span><select name="institution_id"><option value="">Select an institution</option><?php foreach ($institutions as $institution): ?><option value="<?= e($institution['id']) ?>"><?= e($institution['name']) ?></option><?php endforeach; ?></select></label>
                            <p class="form-help">Don't see yours? <a href="<?= e(pageUrl('institution-request')) ?>">Request that it be added.</a></p>
                            <label class="field"><span>Programme or course</span><input name="programme" maxlength="180"></label>
                            <div class="field-row"><label class="field"><span>Academic level</span><input name="academic_level" maxlength="80" placeholder="e.g. Level 300"></label><label class="field"><span>Graduation year</span><input name="graduation_year" type="number" min="<?= e(date('Y')) ?>" max="<?= e(date('Y') + 12) ?>"></label></div>
                        </div>
                        <div class="employer-fields" id="employer-fields" hidden>
                            <label class="field"><span>Organization name</span><input name="organization_name" maxlength="180"></label>
                            <label class="field"><span>Organization type</span><select name="organization_type"><option value="">Select type</option><?php foreach (['Private company','Public institution','NGO','Startup','Training organization','Educational institution','Development organization','Recruitment agency','Other'] as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></label>
                            <div class="field-row"><label class="field"><span>Country</span><input name="country" value="Ghana" maxlength="100"></label><label class="field"><span>Phone</span><input name="phone" maxlength="60" autocomplete="tel"></label></div>
                            <label class="field"><span>Organization website</span><input name="website" type="url" maxlength="255" placeholder="https://"></label>
                            <label class="field"><span>Organization description</span><textarea name="description" rows="3" maxlength="4000"></textarea></label>
                        </div>
                    </div>
                    <div class="auth-wizard-step" data-auth-step="3" aria-live="polite">
                        <label class="field"><span>Password</span><span class="password-control"><input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">Show</button></span><small>Use at least 12 characters.</small></label>
                        <label class="field"><span>Confirm password</span><span class="password-control"><input type="password" name="confirm_password" required minlength="12" maxlength="72" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">Show</button></span></label>
                    </div>
                    <div class="auth-wizard-controls" data-auth-controls hidden>
                        <button class="button button-outline" type="button" data-auth-previous>Back</button>
                        <button class="button button-primary" type="button" data-auth-next>Continue <span aria-hidden="true">→</span></button>
                    </div>
                    <button class="button button-primary button-full" type="submit" data-auth-create>Create account</button>
                </form>
                <p class="auth-switch">Already have an account? <a href="<?= e(pageUrl('login')) ?>">Sign in</a></p>
            <?php else: ?>
                <span class="eyebrow">RESET PASSWORD</span><h2><?= $mode === 'reset' ? 'Choose a new password' : 'Request a reset link' ?></h2>
                <?php if ($mode === 'reset'): ?><form method="post" action="<?= e(pageUrl('reset-password')) ?>" class="form-stack"><?= formToken() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="token" value="<?= e($token ?? '') ?>"><label class="field"><span>New password</span><span class="password-control"><input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"><button type="button" class="password-toggle" data-password-toggle aria-label="Show password" aria-pressed="false">Show</button></span></label><button class="button button-primary button-full" type="submit">Update password</button></form>
                <?php else: ?><p class="form-intro">If email delivery is configured, we’ll send a one-time reset link to your address.</p><form method="post" action="<?= e(pageUrl('forgot-password')) ?>" class="form-stack"><?= formToken() ?><input type="hidden" name="action" value="request_password_reset"><label class="field"><span>Email address</span><input type="email" name="email" required maxlength="190" autocomplete="email"></label><button class="button button-primary button-full" type="submit">Request reset link</button></form><?php endif; ?>
                <p class="auth-switch"><a href="<?= e(pageUrl('login')) ?>">Return to sign in</a></p>
            <?php endif; ?>
        </div>
    </div>
</section>
