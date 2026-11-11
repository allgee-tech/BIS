<?php
declare(strict_types=1);

function handlePost(PDO $pdo, ?array $user): never
{
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'login':
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $ipHash = hash('sha256', 'login:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            if (isAuthRateLimited($pdo, $ipHash)) {
                flash('Too many sign-in attempts. Please wait 15 minutes before trying again.', 'error');
                redirectTo('login');
            }

            $stmt = $pdo->prepare(
                'SELECT id, email, full_name, password_hash, role, status
                 FROM c2c_users WHERE email = :email LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $account = $stmt->fetch();
            $valid = $account !== false
                && $account['status'] === 'active'
                && password_verify($password, $account['password_hash']);
            recordAuthAttempt($pdo, $ipHash, $valid);
            if (!$valid) {
                flash('The email or password was not recognized.', 'error');
                redirectTo('login');
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $account['id'];
            $_SESSION['authenticated_at'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            flash('Welcome back, ' . $account['full_name'] . '.');
            redirectTo('dashboard');

        case 'register':
            if ($user !== null) {
                redirectTo('dashboard');
            }
            $ipHash = hash('sha256', 'register:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            if (isAuthRateLimited($pdo, $ipHash)) {
                flash('Too many registration attempts. Please wait 15 minutes before trying again.', 'error');
                redirectTo('register');
            }
            recordAuthAttempt($pdo, $ipHash, false);

            $role = (string) ($_POST['role'] ?? 'student');
            if (!in_array($role, ['student', 'employer'], true)) {
                throw new InvalidArgumentException('Choose a valid account type.');
            }
            $name = requiredText('full_name', 'Full name', 160);
            $email = strtolower(requiredText('email', 'Email address', 190));
            if (!validEmail($email)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirmation = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
            if (strlen($password) < 12 || strlen($password) > 72) {
                throw new InvalidArgumentException('Choose a password with at least 12 characters and no more than 72 bytes.');
            }
            if (!hash_equals($password, $confirmation)) {
                throw new InvalidArgumentException('The passwords do not match.');
            }

            $pdo->beginTransaction();
            try {
                $createUser = $pdo->prepare(
                    'INSERT INTO c2c_users (email, full_name, password_hash, role)
                     VALUES (:email, :full_name, :password_hash, :role)'
                );
                $createUser->execute([
                    'email' => $email,
                    'full_name' => $name,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                ]);
                $userId = (int) $pdo->lastInsertId();

                if ($role === 'student') {
                    $institutionValue = trim((string) ($_POST['institution_id'] ?? ''));
                    $institutionId = $institutionValue === '' ? null : filter_var($institutionValue, FILTER_VALIDATE_INT);
                    if ($institutionValue !== '' && ($institutionId === false || $institutionId <= 0)) {
                        throw new InvalidArgumentException('Choose a valid institution.');
                    }
                    if ($institutionId !== null) {
                        $institutionCheck = $pdo->prepare('SELECT id FROM c2c_institutions WHERE id = :id AND is_active = 1');
                        $institutionCheck->execute(['id' => $institutionId]);
                        if ($institutionCheck->fetchColumn() === false) {
                            throw new InvalidArgumentException('Choose an active institution.');
                        }
                    }

                    $yearInput = trim((string) ($_POST['graduation_year'] ?? ''));
                    $year = $yearInput === '' ? null : filter_var($yearInput, FILTER_VALIDATE_INT);
                    if ($yearInput !== '' && ($year === false || $year < (int) date('Y') || $year > (int) date('Y') + 12)) {
                        throw new InvalidArgumentException('Enter a valid graduation year.');
                    }
                    $profile = $pdo->prepare(
                        'INSERT INTO c2c_student_profiles
                            (user_id, institution_id, programme, academic_level, graduation_year)
                         VALUES (:user_id, :institution_id, :programme, :academic_level, :graduation_year)'
                    );
                    $profile->execute([
                        'user_id' => $userId,
                        'institution_id' => $institutionId,
                        'programme' => optionalText('programme', 'Programme', 180),
                        'academic_level' => optionalText('academic_level', 'Academic level', 80),
                        'graduation_year' => $year,
                    ]);
                } else {
                    $organizationName = requiredText('organization_name', 'Organization name', 180);
                    $organizationType = requiredText('organization_type', 'Organization type', 100);
                    $country = requiredText('country', 'Country', 100);
                    $website = optionalText('website', 'Website', 255);
                    if ($website !== null && !validWebUrl($website)) {
                        throw new InvalidArgumentException('Enter a valid organization website starting with http:// or https://.');
                    }
                    $organization = $pdo->prepare(
                        'INSERT INTO c2c_organizations
                            (owner_user_id, name, organization_type, country, website, phone, description)
                         VALUES (:owner, :name, :type, :country, :website, :phone, :description)'
                    );
                    $organization->execute([
                        'owner' => $userId,
                        'name' => $organizationName,
                        'type' => $organizationType,
                        'country' => $country,
                        'website' => $website,
                        'phone' => optionalText('phone', 'Phone number', 60),
                        'description' => optionalText('description', 'Organization description', 4000),
                    ]);
                }

                auditEvent($pdo, $userId, 'registered', 'user', $userId, ['role' => $role]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                    throw new InvalidArgumentException('An account with that email address already exists.');
                }
                throw $exception;
            }

            $emailReady = sendVerificationEmail($pdo, $userId, $email, $name);
            flash($emailReady
                ? 'Your account is ready. Check your email to verify your address.'
                : 'Your account is ready. Email verification is pending email-service configuration.');
            redirectTo('login');

        case 'logout':
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            flash('You have signed out.');
            redirectTo('home');

        case 'request_password_reset':
            $ipHash = hash('sha256', 'password-reset:' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            if (isAuthRateLimited($pdo, $ipHash)) {
                flash('If that account exists, reset instructions will be sent when email delivery is configured.');
                redirectTo('forgot-password');
            }
            recordAuthAttempt($pdo, $ipHash, false);
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (validEmail($email)) {
                $stmt = $pdo->prepare("SELECT id FROM c2c_users WHERE email = :email AND status = 'active'");
                $stmt->execute(['email' => $email]);
                $userId = $stmt->fetchColumn();
                if ($userId !== false) {
                    sendPasswordResetEmail($pdo, (int) $userId, $email);
                }
            }
            flash('If an active account exists and email delivery is configured, reset instructions will be sent.');
            redirectTo('forgot-password');

        case 'reset_password':
            $token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (strlen($password) < 12 || strlen($password) > 72) {
                throw new InvalidArgumentException('Choose a password with at least 12 characters and no more than 72 bytes.');
            }
            $pdo->beginTransaction();
            try {
                $lookup = $pdo->prepare(
                    'SELECT id, user_id FROM c2c_password_resets
                     WHERE token_hash = :token AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP
                     FOR UPDATE'
                );
                $lookup->execute(['token' => hash('sha256', $token)]);
                $reset = $lookup->fetch();
                if ($reset === false) {
                    throw new InvalidArgumentException('This reset link is invalid or has expired.');
                }
                $update = $pdo->prepare('UPDATE c2c_users SET password_hash = :hash WHERE id = :id');
                $update->execute([
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => (int) $reset['user_id'],
                ]);
                $markUsed = $pdo->prepare('UPDATE c2c_password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = :id');
                $markUsed->execute(['id' => (int) $reset['id']]);
                $pdo->commit();
                flash('Your password has been updated. Sign in with your new password.');
                redirectTo('login');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

        case 'toggle_save':
            $student = requireRole(['student'], $pdo);
            $opportunityId = validId($_POST['opportunity_id'] ?? null, 'Opportunity');
            $open = $pdo->prepare(
                "SELECT p.id FROM c2c_opportunities p
                 INNER JOIN c2c_student_profiles sp ON sp.user_id = :student
                   AND (p.institution_id IS NULL OR p.institution_id = sp.institution_id)
                 WHERE p.id = :id AND p.status = 'published'
                   AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)"
            );
            $open->execute(['id' => $opportunityId, 'student' => (int) $student['id']]);
            if ($open->fetchColumn() === false) {
                throw new InvalidArgumentException('That opportunity is no longer available.');
            }
            $exists = $pdo->prepare('SELECT 1 FROM c2c_saved_opportunities WHERE user_id = :user AND opportunity_id = :opportunity');
            $exists->execute(['user' => (int) $student['id'], 'opportunity' => $opportunityId]);
            if ($exists->fetchColumn() !== false) {
                $remove = $pdo->prepare('DELETE FROM c2c_saved_opportunities WHERE user_id = :user AND opportunity_id = :opportunity');
                $remove->execute(['user' => (int) $student['id'], 'opportunity' => $opportunityId]);
                flash('Opportunity removed from your saved list.');
            } else {
                $save = $pdo->prepare('INSERT INTO c2c_saved_opportunities (user_id, opportunity_id) VALUES (:user, :opportunity)');
                $save->execute(['user' => (int) $student['id'], 'opportunity' => $opportunityId]);
                flash('Opportunity saved.');
            }
            redirectTo('opportunity', ['id' => $opportunityId]);

        case 'update_profile':
            $profileUser = requireRole(['student'], $pdo);
            $institutionValue = trim((string) ($_POST['institution_id'] ?? ''));
            $institutionId = $institutionValue === '' ? null : filter_var($institutionValue, FILTER_VALIDATE_INT);
            if ($institutionValue !== '' && ($institutionId === false || $institutionId <= 0)) {
                throw new InvalidArgumentException('Choose a valid institution.');
            }
            if ($institutionId !== null) {
                $check = $pdo->prepare('SELECT id FROM c2c_institutions WHERE id = :id AND is_active = 1');
                $check->execute(['id' => $institutionId]);
                if ($check->fetchColumn() === false) {
                    throw new InvalidArgumentException('Choose an active institution.');
                }
            }
            $yearValue = trim((string) ($_POST['graduation_year'] ?? ''));
            $year = $yearValue === '' ? null : filter_var($yearValue, FILTER_VALIDATE_INT);
            if ($yearValue !== '' && ($year === false || $year < (int) date('Y') || $year > (int) date('Y') + 12)) {
                throw new InvalidArgumentException('Enter a valid graduation year.');
            }
            $name = requiredText('full_name', 'Full name', 160);
            $pdo->beginTransaction();
            try {
                $updateUser = $pdo->prepare('UPDATE c2c_users SET full_name = :name WHERE id = :id');
                $updateUser->execute(['name' => $name, 'id' => (int) $profileUser['id']]);
                $updateProfile = $pdo->prepare(
                    'UPDATE c2c_student_profiles
                     SET institution_id = :institution_id, programme = :programme,
                         academic_level = :level, graduation_year = :year,
                         skills = :skills, career_interests = :interests
                     WHERE user_id = :user_id'
                );
                $updateProfile->execute([
                    'institution_id' => $institutionId,
                    'programme' => optionalText('programme', 'Programme', 180),
                    'level' => optionalText('academic_level', 'Academic level', 80),
                    'year' => $year,
                    'skills' => optionalText('skills', 'Skills', 2000),
                    'interests' => optionalText('career_interests', 'Career interests', 2000),
                    'user_id' => (int) $profileUser['id'],
                ]);
                auditEvent($pdo, (int) $profileUser['id'], 'profile_updated', 'student_profile', (int) $profileUser['id']);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash('Your profile has been updated.');
            redirectTo('profile');

        case 'upload_profile_cv':
            $student = requireRole(['student'], $pdo);
            $file = storeUploadedDocument($_FILES['cv'] ?? [], 'cv', (int) $student['id']);
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO c2c_documents
                        (owner_user_id, kind, original_name, stored_name, mime_type, file_size)
                     VALUES (:owner, :kind, :original, :stored, :mime, :size)'
                );
                $insert->execute([
                    'owner' => (int) $student['id'],
                    'kind' => 'cv',
                    'original' => $file['original_name'],
                    'stored' => $file['stored_name'],
                    'mime' => $file['mime_type'],
                    'size' => $file['file_size'],
                ]);
                auditEvent($pdo, (int) $student['id'], 'cv_uploaded', 'document', (int) $pdo->lastInsertId());
            } catch (Throwable $exception) {
                if (is_file($file['path'])) {
                    unlink($file['path']);
                }
                throw $exception;
            }
            flash('Your CV has been saved privately to your profile.');
            redirectTo('profile');

        case 'change_password':
            $account = requireAuth($pdo);
            $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
            $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            $lookup = $pdo->prepare('SELECT password_hash FROM c2c_users WHERE id = :id');
            $lookup->execute(['id' => (int) $account['id']]);
            if (!password_verify($currentPassword, (string) $lookup->fetchColumn())) {
                throw new InvalidArgumentException('The current password is incorrect.');
            }
            if (strlen($newPassword) < 12 || strlen($newPassword) > 72) {
                throw new InvalidArgumentException('Choose a password with at least 12 characters and no more than 72 bytes.');
            }
            $update = $pdo->prepare('UPDATE c2c_users SET password_hash = :hash WHERE id = :id');
            $update->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'id' => (int) $account['id']]);
            auditEvent($pdo, (int) $account['id'], 'password_changed', 'user', (int) $account['id']);
            session_regenerate_id(true);
            $_SESSION['authenticated_at'] = time();
            $_SESSION['last_activity'] = time();
            flash('Your password has been changed.');
            redirectTo('profile');

        case 'resend_verification':
            $account = requireAuth($pdo);
            if ($account['email_verified_at'] !== null) {
                flash('Your email address is already verified.');
                redirectTo('profile');
            }
            $sent = sendVerificationEmail($pdo, (int) $account['id'], $account['email'], $account['full_name']);
            flash($sent ? 'A verification link has been sent.' : 'Email delivery is not configured. Contact the platform administrator.');
            redirectTo('profile');

        case 'request_verification':
            $employer = requireRole(['employer'], $pdo);
            $details = requiredText('verification_details', 'Verification details', 2000);
            $update = $pdo->prepare(
                "UPDATE c2c_organizations SET verification_details = :details, verification_status = 'pending'
                 WHERE owner_user_id = :owner AND verification_status IN ('unverified','rejected')"
            );
            $update->execute(['details' => $details, 'owner' => (int) $employer['id']]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Your organization profile cannot be submitted for verification right now.');
            }
            auditEvent($pdo, (int) $employer['id'], 'organization_verification_requested', 'organization', null);
            flash('Organization verification request submitted.');
            redirectTo('employer');

        case 'update_organization_profile':
            $employer = requireRole(['employer'], $pdo);
            $website = optionalText('website', 'Website', 255);
            if ($website !== null && !validWebUrl($website)) {
                throw new InvalidArgumentException('Enter a valid website URL.');
            }
            $updateUser = $pdo->prepare('UPDATE c2c_users SET full_name = :name WHERE id = :id');
            $updateUser->execute(['name' => requiredText('full_name', 'Contact name', 160), 'id' => (int) $employer['id']]);
            $updateOrg = $pdo->prepare(
                'UPDATE c2c_organizations SET name = :name, organization_type = :type, country = :country,
                    website = :website, phone = :phone, description = :description,
                    verification_details = :details
                 WHERE owner_user_id = :owner'
            );
            $updateOrg->execute([
                'name' => requiredText('organization_name', 'Organization name', 180),
                'type' => requiredText('organization_type', 'Organization type', 100),
                'country' => requiredText('country', 'Country', 100),
                'website' => $website,
                'phone' => optionalText('phone', 'Phone number', 60),
                'description' => optionalText('description', 'Description', 4000),
                'details' => optionalText('verification_details', 'Verification details', 2000),
                'owner' => (int) $employer['id'],
            ]);
            auditEvent($pdo, (int) $employer['id'], 'organization_profile_updated', 'organization', null);
            flash('Organization profile updated.');
            redirectTo('profile');

        case 'submit_application':
            $student = requireRole(['student'], $pdo);
            $opportunityId = validId($_POST['opportunity_id'] ?? null, 'Opportunity');
            $coverLetter = optionalText('cover_letter', 'Cover letter', 10000);
            $lookup = $pdo->prepare(
                "SELECT p.id, p.application_method FROM c2c_opportunities p
                 INNER JOIN c2c_student_profiles sp ON sp.user_id = :student
                   AND (p.institution_id IS NULL OR p.institution_id = sp.institution_id)
                 WHERE p.id = :id AND p.status = 'published'
                   AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)"
            );
            $lookup->execute(['id' => $opportunityId, 'student' => (int) $student['id']]);
            $opportunity = $lookup->fetch();
            if ($opportunity === false || $opportunity['application_method'] !== 'platform') {
                throw new InvalidArgumentException('This opportunity is no longer accepting applications through Campus2Career.');
            }

            $existing = $pdo->prepare(
                'SELECT id FROM c2c_applications WHERE opportunity_id = :opportunity AND student_user_id = :student'
            );
            $existing->execute(['opportunity' => $opportunityId, 'student' => (int) $student['id']]);
            if ($existing->fetchColumn() !== false) {
                throw new InvalidArgumentException('You have already applied to this opportunity.');
            }

            $uploaded = [];
            try {
                $cvUpload = $_FILES['cv'] ?? [];
                if ((int) ($cvUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $existingCvId = validId($_POST['existing_cv_id'] ?? null, 'CV');
                    $existingCvQuery = $pdo->prepare(
                        "SELECT * FROM c2c_documents
                         WHERE id = :id AND owner_user_id = :owner AND kind = 'cv' AND application_id IS NULL"
                    );
                    $existingCvQuery->execute(['id' => $existingCvId, 'owner' => (int) $student['id']]);
                    $existingCv = $existingCvQuery->fetch();
                    $existingCvPath = privateStoragePath() . DIRECTORY_SEPARATOR . basename((string) ($existingCv['stored_name'] ?? ''));
                    if ($existingCv === false || !is_file($existingCvPath)) {
                        throw new InvalidArgumentException('Choose a saved CV or upload a new one.');
                    }
                    $uploaded[] = [
                        'owner_id' => (int) $student['id'],
                        'kind' => 'cv',
                        'original_name' => $existingCv['original_name'],
                        'stored_name' => $existingCv['stored_name'],
                        'mime_type' => $existingCv['mime_type'],
                        'file_size' => (int) $existingCv['file_size'],
                        'path' => $existingCvPath,
                        'reused' => true,
                    ];
                } else {
                    $uploaded[] = storeUploadedDocument($cvUpload, 'cv', (int) $student['id']);
                }
                $supporting = $_FILES['supporting_documents'] ?? null;
                if (is_array($supporting) && isset($supporting['name']) && is_array($supporting['name'])) {
                    $count = count(array_filter($supporting['name'], static fn ($name): bool => is_string($name) && $name !== ''));
                    if ($count > 3) {
                        throw new InvalidArgumentException('Upload no more than three supporting documents.');
                    }
                    for ($i = 0; $i < count($supporting['name']); $i++) {
                        if ($supporting['name'][$i] === '') {
                            continue;
                        }
                        $file = [];
                        foreach (['name','type','tmp_name','error','size'] as $field) {
                            $file[$field] = $supporting[$field][$i] ?? null;
                        }
                        $uploaded[] = storeUploadedDocument($file, 'supporting', (int) $student['id']);
                    }
                }

                $pdo->beginTransaction();
                $insert = $pdo->prepare(
                    'INSERT INTO c2c_applications (opportunity_id, student_user_id, cover_letter)
                     VALUES (:opportunity, :student, :cover_letter)'
                );
                $insert->execute([
                    'opportunity' => $opportunityId,
                    'student' => (int) $student['id'],
                    'cover_letter' => $coverLetter,
                ]);
                $applicationId = (int) $pdo->lastInsertId();
                $history = $pdo->prepare(
                    'INSERT INTO c2c_application_status_history (application_id, status, changed_by_user_id)
                     VALUES (:application, \'submitted\', :actor)'
                );
                $history->execute(['application' => $applicationId, 'actor' => (int) $student['id']]);
                $document = $pdo->prepare(
                    'INSERT INTO c2c_documents
                        (owner_user_id, application_id, kind, original_name, stored_name, mime_type, file_size)
                     VALUES (:owner, :application, :kind, :original, :stored, :mime, :size)'
                );
                foreach ($uploaded as $file) {
                    $document->execute([
                        'owner' => (int) $student['id'],
                        'application' => $applicationId,
                        'kind' => $file['kind'],
                        'original' => $file['original_name'],
                        'stored' => $file['stored_name'],
                        'mime' => $file['mime_type'],
                        'size' => $file['file_size'],
                    ]);
                }

                $employer = $pdo->prepare(
                    'SELECT o.owner_user_id, p.title FROM c2c_opportunities p
                     LEFT JOIN c2c_organizations o ON o.id = p.organization_id WHERE p.id = :id'
                );
                $employer->execute(['id' => $opportunityId]);
                $owner = $employer->fetch();
                if ($owner !== false && $owner['owner_user_id'] !== null) {
                    notifyUser($pdo, (int) $owner['owner_user_id'], 'New application received', $student['full_name'] . ' applied for ' . $owner['title'] . '.', 'employer');
                }
                notifyUser($pdo, (int) $student['id'], 'Application submitted', 'Your application has been received.', 'applications');
                auditEvent($pdo, (int) $student['id'], 'application_submitted', 'application', $applicationId, ['opportunity_id' => $opportunityId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                foreach ($uploaded as $file) {
                    if (empty($file['reused']) && is_file($file['path'])) {
                        unlink($file['path']);
                    }
                }
                if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                    throw new InvalidArgumentException('You have already applied to this opportunity.');
                }
                throw $exception;
            }

            flash('Your application has been submitted.');
            redirectTo('applications');

        case 'withdraw_application':
            $student = requireRole(['student'], $pdo);
            $applicationId = validId($_POST['application_id'] ?? null, 'Application');
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare(
                    "UPDATE c2c_applications SET status = 'withdrawn'
                     WHERE id = :id AND student_user_id = :student
                       AND status IN ('submitted','under_review','shortlisted','interview')"
                );
                $update->execute(['id' => $applicationId, 'student' => (int) $student['id']]);
                if ($update->rowCount() !== 1) {
                    throw new InvalidArgumentException('This application cannot be withdrawn.');
                }
                $history = $pdo->prepare(
                    'INSERT INTO c2c_application_status_history (application_id, status, changed_by_user_id)
                     VALUES (:application, \'withdrawn\', :actor)'
                );
                $history->execute(['application' => $applicationId, 'actor' => (int) $student['id']]);
                auditEvent($pdo, (int) $student['id'], 'application_withdrawn', 'application', $applicationId);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash('Your application has been withdrawn.');
            redirectTo('applications');

        case 'create_opportunity':
        case 'update_opportunity':
            $author = requireRole(['employer','institution_admin'], $pdo);
            $opportunityId = $action === 'update_opportunity' ? validId($_POST['opportunity_id'] ?? null, 'Opportunity') : null;
            $organizationId = null;
            $institutionId = null;
            if ($author['role'] === 'employer') {
                $organizationLookup = $pdo->prepare('SELECT id FROM c2c_organizations WHERE owner_user_id = :owner');
                $organizationLookup->execute(['owner' => (int) $author['id']]);
                $organizationId = $organizationLookup->fetchColumn();
                if ($organizationId === false) {
                    throw new InvalidArgumentException('Complete your organization profile before posting an opportunity.');
                }
            } else {
                $institutionLookup = $pdo->prepare(
                    'SELECT institution_id FROM c2c_institution_admins WHERE user_id = :user'
                );
                $institutionLookup->execute(['user' => (int) $author['id']]);
                $institutionId = $institutionLookup->fetchColumn();
                if ($institutionId === false) {
                    throw new InvalidArgumentException('Your account is not assigned to an institution.');
                }
            }

            $categoryId = validId($_POST['category_id'] ?? null, 'Category');
            $categoryCheck = $pdo->prepare('SELECT id FROM c2c_opportunity_categories WHERE id = :id AND is_active = 1');
            $categoryCheck->execute(['id' => $categoryId]);
            if ($categoryCheck->fetchColumn() === false) {
                throw new InvalidArgumentException('Choose a valid opportunity category.');
            }
            $work = (string) ($_POST['work_arrangement'] ?? 'not_specified');
            if (!in_array($work, ['on_site','hybrid','remote','not_specified'], true)) {
                throw new InvalidArgumentException('Choose a valid work arrangement.');
            }
            $method = (string) ($_POST['application_method'] ?? 'platform');
            if (!in_array($method, ['platform','external'], true)) {
                throw new InvalidArgumentException('Choose a valid application method.');
            }
            $applicationUrl = optionalText('application_url', 'Application URL', 1000);
            if ($method === 'external' && ($applicationUrl === null || !validWebUrl($applicationUrl))) {
                throw new InvalidArgumentException('Enter a valid external application URL starting with http:// or https://.');
            }
            $deadline = validDateInput('application_deadline', 'application deadline');
            if ($deadline !== null && $deadline < date('Y-m-d')) {
                throw new InvalidArgumentException('The application deadline must be today or later.');
            }
            $startDate = validDateInput('start_date', 'start date');
            if ($deadline !== null && $startDate !== null && $startDate < $deadline) {
                throw new InvalidArgumentException('The start date cannot be before the application deadline.');
            }
            $contactEmail = optionalText('contact_email', 'Contact email', 190);
            if ($contactEmail !== null && !validEmail($contactEmail)) {
                throw new InvalidArgumentException('Enter a valid contact email address.');
            }
            $values = [
                'category_id' => $categoryId,
                'title' => requiredText('title', 'Opportunity title', 180),
                'description' => requiredText('description', 'Description', 20000),
                'responsibilities' => optionalText('responsibilities', 'Responsibilities', 10000),
                'requirements' => optionalText('requirements', 'Requirements', 10000),
                'qualifications' => optionalText('qualifications', 'Qualifications', 10000),
                'required_skills' => optionalText('required_skills', 'Required skills', 1000),
                'location' => optionalText('location', 'Location', 180),
                'work_arrangement' => $work,
                'compensation' => optionalText('compensation', 'Compensation', 180),
                'application_deadline' => $deadline,
                'start_date' => $startDate,
                'duration' => optionalText('duration', 'Duration', 100),
                'application_method' => $method,
                'application_url' => $method === 'external' ? $applicationUrl : null,
                'contact_email' => $contactEmail,
            ];

            if ($opportunityId === null) {
                $insert = $pdo->prepare(
                    "INSERT INTO c2c_opportunities
                        (organization_id, institution_id, created_by_user_id, category_id, title, description,
                         responsibilities, requirements, qualifications, required_skills, location, work_arrangement,
                         compensation, application_deadline, start_date, duration, application_method, application_url,
                         contact_email, status)
                     VALUES
                        (:organization_id, :institution_id, :author_id, :category_id, :title, :description,
                         :responsibilities, :requirements, :qualifications, :required_skills, :location, :work_arrangement,
                         :compensation, :application_deadline, :start_date, :duration, :application_method, :application_url,
                         :contact_email, 'draft')"
                );
                $insert->execute([
                    ...$values,
                    'organization_id' => $organizationId,
                    'institution_id' => $institutionId,
                    'author_id' => (int) $author['id'],
                ]);
                $opportunityId = (int) $pdo->lastInsertId();
                $event = 'opportunity_created';
            } else {
                $ownerCheck = $pdo->prepare(
                    "SELECT id FROM c2c_opportunities WHERE id = :id AND created_by_user_id = :author
                     AND status IN ('draft','rejected')"
                );
                $ownerCheck->execute(['id' => $opportunityId, 'author' => (int) $author['id']]);
                if ($ownerCheck->fetchColumn() === false) {
                    throw new InvalidArgumentException('Only your draft or rejected opportunities can be edited.');
                }
                $update = $pdo->prepare(
                    "UPDATE c2c_opportunities SET category_id = :category_id, title = :title, description = :description,
                        responsibilities = :responsibilities, requirements = :requirements, qualifications = :qualifications,
                        required_skills = :required_skills, location = :location, work_arrangement = :work_arrangement,
                        compensation = :compensation, application_deadline = :application_deadline, start_date = :start_date,
                        duration = :duration, application_method = :application_method, application_url = :application_url,
                        contact_email = :contact_email, status = 'draft', rejection_reason = NULL
                     WHERE id = :id AND created_by_user_id = :author"
                );
                $update->execute([...$values, 'id' => $opportunityId, 'author' => (int) $author['id']]);
                $event = 'opportunity_updated';
            }

            auditEvent($pdo, (int) $author['id'], $event, 'opportunity', $opportunityId);
            flash('Opportunity saved as a draft. Submit it for review when it is ready.');
            redirectTo('employer');

        case 'submit_opportunity':
            $author = requireRole(['employer','institution_admin'], $pdo);
            $id = validId($_POST['opportunity_id'] ?? null, 'Opportunity');
            $update = $pdo->prepare(
                "UPDATE c2c_opportunities SET status = 'pending_review'
                 WHERE id = :id AND created_by_user_id = :author AND status IN ('draft','rejected')"
            );
            $update->execute(['id' => $id, 'author' => (int) $author['id']]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Only your draft or rejected opportunity can be submitted for review.');
            }
            auditEvent($pdo, (int) $author['id'], 'opportunity_submitted', 'opportunity', $id);
            flash('Opportunity submitted for review.');
            redirectTo('employer');

        case 'moderate_opportunity':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['opportunity_id'] ?? null, 'Opportunity');
            $decision = (string) ($_POST['decision'] ?? '');
            $allowed = ['approve','publish','reject','suspend','close'];
            if (!in_array($decision, $allowed, true)) {
                throw new InvalidArgumentException('Choose a valid moderation action.');
            }
            $reason = optionalText('reason', 'Reason', 1000);
            if (in_array($decision, ['reject','suspend'], true) && $reason === null) {
                throw new InvalidArgumentException('Provide a reason when rejecting or suspending an opportunity.');
            }
            $status = [
                'approve' => 'approved',
                'publish' => 'published',
                'reject' => 'rejected',
                'suspend' => 'suspended',
                'close' => 'closed',
            ][$decision];
            $allowedCurrentStatus = [
                'approve' => "status = 'pending_review'",
                'publish' => "status = 'approved'",
                'reject' => "status IN ('pending_review','approved')",
                'suspend' => "status = 'published'",
                'close' => "status IN ('published','approved')",
            ][$decision];
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    "UPDATE c2c_opportunities SET status = :status, rejection_reason = :reason,
                        published_at = IF(:publish = 1, CURRENT_TIMESTAMP, published_at)
                     WHERE id = :id AND {$allowedCurrentStatus}"
                );
                $stmt->execute([
                    'status' => $status,
                    'reason' => in_array($decision, ['reject','suspend'], true) ? $reason : null,
                    'publish' => $decision === 'publish' ? 1 : 0,
                    'id' => $id,
                ]);
                if ($stmt->rowCount() !== 1) {
                    throw new InvalidArgumentException('No opportunity was changed. It may no longer be awaiting review.');
                }
                $owner = $pdo->prepare('SELECT created_by_user_id, title FROM c2c_opportunities WHERE id = :id');
                $owner->execute(['id' => $id]);
                $opportunity = $owner->fetch();
                if ($opportunity !== false) {
                    notifyUser($pdo, (int) $opportunity['created_by_user_id'], 'Opportunity review update',
                        $decision === 'reject' ? 'Your opportunity was returned for changes: ' . $reason : 'Your opportunity status is now ' . $status . '.',
                        'employer');
                }
                if ($decision === 'publish') {
                    notifyMatchingStudents($pdo, $id);
                }
                auditEvent($pdo, (int) $admin['id'], 'opportunity_' . $decision, 'opportunity', $id, ['reason' => $reason]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash($decision === 'approve' ? 'Opportunity approved. Publish it when it is ready.' : 'Opportunity review decision recorded.');
            redirectTo('admin', ['tab' => 'opportunities']);

        case 'update_application_status':
            $employer = requireRole(['employer'], $pdo);
            $applicationId = validId($_POST['application_id'] ?? null, 'Application');
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, ['under_review','shortlisted','interview','accepted','rejected'], true)) {
                throw new InvalidArgumentException('Choose a valid application status.');
            }
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare(
                    'UPDATE c2c_applications a
                     INNER JOIN c2c_opportunities p ON p.id = a.opportunity_id
                     INNER JOIN c2c_organizations o ON o.id = p.organization_id
                     SET a.status = :status
                     WHERE a.id = :application
                       AND (o.owner_user_id = :owner OR EXISTS (
                           SELECT 1 FROM c2c_organization_representatives r
                           WHERE r.organization_id = o.id AND r.user_id = :rep_user AND r.is_active = 1
                       ))
                       AND a.status IN (\'submitted\',\'under_review\',\'shortlisted\',\'interview\')'
                );
                $update->execute([
                    'status' => $status,
                    'application' => $applicationId,
                    'owner' => (int) $employer['id'],
                    'rep_user' => (int) $employer['id'],
                ]);
                if ($update->rowCount() !== 1) {
                    throw new InvalidArgumentException('This application is not available to your organization or cannot be changed.');
                }
                $history = $pdo->prepare(
                    'INSERT INTO c2c_application_status_history (application_id, status, changed_by_user_id)
                     VALUES (:application, :status, :actor)'
                );
                $history->execute([
                    'application' => $applicationId,
                    'status' => $status,
                    'actor' => (int) $employer['id'],
                ]);
                $student = $pdo->prepare('SELECT student_user_id FROM c2c_applications WHERE id = :id');
                $student->execute(['id' => $applicationId]);
                $studentId = (int) $student->fetchColumn();
                notifyUser($pdo, $studentId, 'Application status updated',
                    'Your application status is now ' . str_replace('_', ' ', $status) . '.',
                    'applications');
                auditEvent($pdo, (int) $employer['id'], 'application_status_changed', 'application', $applicationId, ['status' => $status]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash('Application status updated.');
            $organizationId = filter_var($_GET['organization_id'] ?? null, FILTER_VALIDATE_INT);
            redirectTo('employer', $organizationId !== false && $organizationId !== null && $organizationId > 0
                ? ['organization_id' => $organizationId]
                : []);

        case 'add_organization_representative':
            $employer = requireRole(['employer'], $pdo);
            $email = strtolower(requiredText('email', 'Representative email', 190));
            if (!validEmail($email)) {
                throw new InvalidArgumentException('Enter a valid representative email.');
            }
            $organizationQuery = $pdo->prepare('SELECT id FROM c2c_organizations WHERE owner_user_id = :owner');
            $organizationQuery->execute(['owner' => (int) $employer['id']]);
            $organizationId = $organizationQuery->fetchColumn();
            if ($organizationId === false) {
                throw new InvalidArgumentException('Only the organization owner can add representatives.');
            }
            $representativeQuery = $pdo->prepare(
                "SELECT id FROM c2c_users
                 WHERE email = :email AND role = 'employer' AND status = 'active' AND id <> :owner"
            );
            $representativeQuery->execute(['email' => $email, 'owner' => (int) $employer['id']]);
            $representativeId = $representativeQuery->fetchColumn();
            if ($representativeId === false) {
                throw new InvalidArgumentException('A representative must already have an active employer account with a different email.');
            }
            $add = $pdo->prepare(
                'INSERT INTO c2c_organization_representatives (organization_id, user_id, role_title, is_active)
                 VALUES (:organization, :user, :title, 1)
                 ON DUPLICATE KEY UPDATE role_title = VALUES(role_title), is_active = 1'
            );
            $add->execute([
                'organization' => (int) $organizationId,
                'user' => (int) $representativeId,
                'title' => optionalText('role_title', 'Role title', 100),
            ]);
            auditEvent($pdo, (int) $employer['id'], 'organization_representative_added', 'organization', (int) $organizationId, ['representative_user_id' => (int) $representativeId]);
            flash('Organization representative added.');
            redirectTo('employer');

        case 'remove_organization_representative':
            $employer = requireRole(['employer'], $pdo);
            $representativeId = validId($_POST['user_id'] ?? null, 'User');
            $organizationQuery = $pdo->prepare('SELECT id FROM c2c_organizations WHERE owner_user_id = :owner');
            $organizationQuery->execute(['owner' => (int) $employer['id']]);
            $organizationId = $organizationQuery->fetchColumn();
            if ($organizationId === false) {
                throw new InvalidArgumentException('Only the organization owner can manage representatives.');
            }
            $remove = $pdo->prepare(
                'DELETE FROM c2c_organization_representatives
                 WHERE organization_id = :organization AND user_id = :user'
            );
            $remove->execute(['organization' => (int) $organizationId, 'user' => $representativeId]);
            if ($remove->rowCount() !== 1) {
                throw new InvalidArgumentException('That representative is not assigned to your organization.');
            }
            auditEvent($pdo, (int) $employer['id'], 'organization_representative_removed', 'organization', (int) $organizationId, ['representative_user_id' => $representativeId]);
            flash('Organization representative removed.');
            redirectTo('employer');

        case 'create_institution':
            $admin = requireRole(['admin'], $pdo);
            $name = requiredText('name', 'Institution name', 180);
            $code = optionalText('code', 'Institution code', 40);
            $website = optionalText('website', 'Website', 255);
            if ($website !== null && !validWebUrl($website)) {
                throw new InvalidArgumentException('Enter a valid website URL.');
            }
            $contact = optionalText('contact_email', 'Contact email', 190);
            if ($contact !== null && !validEmail($contact)) {
                throw new InvalidArgumentException('Enter a valid contact email.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO c2c_institutions (name, code, location, country, website, description, contact_email, is_verified)
                 VALUES (:name, :code, :location, :country, :website, :description, :email, 1)'
            );
            $insert->execute([
                'name' => $name,
                'code' => $code,
                'location' => optionalText('location', 'Location', 160),
                'country' => requiredText('country', 'Country', 100),
                'website' => $website,
                'description' => optionalText('description', 'Description', 4000),
                'email' => $contact,
            ]);
            $id = (int) $pdo->lastInsertId();
            auditEvent($pdo, (int) $admin['id'], 'institution_created', 'institution', $id);
            flash('Institution added.');
            redirectTo('admin', ['tab' => 'institutions']);

        case 'toggle_institution':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['institution_id'] ?? null, 'Institution');
            $update = $pdo->prepare('UPDATE c2c_institutions SET is_active = 1 - is_active WHERE id = :id');
            $update->execute(['id' => $id]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Institution not found.');
            }
            auditEvent($pdo, (int) $admin['id'], 'institution_status_toggled', 'institution', $id);
            flash('Institution status updated.');
            redirectTo('admin', ['tab' => 'institutions']);

        case 'verify_organization':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['organization_id'] ?? null, 'Organization');
            $status = (string) ($_POST['verification_status'] ?? '');
            if (!in_array($status, ['verified','rejected'], true)) {
                throw new InvalidArgumentException('Choose a valid verification status.');
            }
            $update = $pdo->prepare('UPDATE c2c_organizations SET verification_status = :status WHERE id = :id');
            $update->execute(['status' => $status, 'id' => $id]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Organization not found.');
            }
            $owner = $pdo->prepare('SELECT owner_user_id FROM c2c_organizations WHERE id = :id');
            $owner->execute(['id' => $id]);
            notifyUser($pdo, (int) $owner->fetchColumn(), 'Organization verification update', 'Your organization verification status is now ' . $status . '.', 'employer');
            auditEvent($pdo, (int) $admin['id'], 'organization_' . $status, 'organization', $id);
            flash('Organization verification updated.');
            redirectTo('admin', ['tab' => 'organizations']);

        case 'create_institution_admin':
            $admin = requireRole(['admin'], $pdo);
            $email = strtolower(requiredText('email', 'Email address', 190));
            if (!validEmail($email)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $institutionId = validId($_POST['institution_id'] ?? null, 'Institution');
            $userId = validId($_POST['user_id'] ?? null, 'User');
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare("UPDATE c2c_users SET role = 'institution_admin' WHERE id = :id AND email = :email AND role IN ('student','institution_admin')");
                $update->execute(['id' => $userId, 'email' => $email]);
                if ($update->rowCount() !== 1) {
                    throw new InvalidArgumentException('No matching student account was found for that email.');
                }
                $assign = $pdo->prepare('INSERT INTO c2c_institution_admins (institution_id, user_id) VALUES (:institution, :user)');
                $assign->execute(['institution' => $institutionId, 'user' => $userId]);
                auditEvent($pdo, (int) $admin['id'], 'institution_admin_assigned', 'institution', $institutionId, ['user_id' => $userId]);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash('Institution representative assigned.');
            redirectTo('admin', ['tab' => 'institutions']);

        case 'update_user_status':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['user_id'] ?? null, 'User');
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, ['active','suspended'], true) || $id === (int) $admin['id']) {
                throw new InvalidArgumentException('That account cannot be changed.');
            }
            $update = $pdo->prepare('UPDATE c2c_users SET status = :status WHERE id = :id');
            $update->execute(['status' => $status, 'id' => $id]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('User not found.');
            }
            auditEvent($pdo, (int) $admin['id'], 'user_status_changed', 'user', $id, ['status' => $status]);
            flash('User status updated.');
            redirectTo('admin', ['tab' => 'users']);

        case 'create_resource':
            $admin = requireRole(['admin'], $pdo);
            $title = requiredText('title', 'Resource title', 180);
            $category = requiredText('category', 'Category', 100);
            $summary = requiredText('summary', 'Summary', 500);
            $content = requiredText('content', 'Resource content', 30000);
            $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
            $published = isset($_POST['is_published']) ? 1 : 0;
            $insert = $pdo->prepare(
                'INSERT INTO c2c_resources (author_user_id, title, slug, category, summary, content, is_published, published_at)
                 VALUES (:author, :title, :slug, :category, :summary, :content, :published, IF(:published_at = 1, CURRENT_TIMESTAMP, NULL))'
            );
            $insert->execute([
                'author' => (int) $admin['id'],
                'title' => $title,
                'slug' => $slug . '-' . bin2hex(random_bytes(3)),
                'category' => $category,
                'summary' => $summary,
                'content' => $content,
                'published' => $published,
                'published_at' => $published,
            ]);
            $id = (int) $pdo->lastInsertId();
            auditEvent($pdo, (int) $admin['id'], 'resource_created', 'resource', $id, ['published' => (bool) $published]);
            flash('Career resource saved.');
            redirectTo('admin', ['tab' => 'resources']);

        case 'update_resource':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['resource_id'] ?? null, 'Resource');
            $update = $pdo->prepare(
                'UPDATE c2c_resources SET title = :title, category = :category,
                    summary = :summary, content = :content WHERE id = :id'
            );
            $update->execute([
                'title' => requiredText('title', 'Resource title', 180),
                'category' => requiredText('category', 'Category', 100),
                'summary' => requiredText('summary', 'Summary', 500),
                'content' => requiredText('content', 'Resource content', 30000),
                'id' => $id,
            ]);
            if ($update->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM c2c_resources WHERE id = :id');
                $exists->execute(['id' => $id]);
                if ($exists->fetchColumn() === false) {
                    throw new InvalidArgumentException('Resource not found.');
                }
            }
            auditEvent($pdo, (int) $admin['id'], 'resource_updated', 'resource', $id);
            flash('Career resource updated.');
            redirectTo('admin', ['tab' => 'resources']);

        case 'report_opportunity':
            $reporter = $user === null ? null : requireAuth($pdo);
            $id = validId($_POST['opportunity_id'] ?? null, 'Opportunity');
            $reason = requiredText('reason', 'Report reason', 1000);
            $exists = $pdo->prepare("SELECT id FROM c2c_opportunities WHERE id = :id AND status = 'published'");
            $exists->execute(['id' => $id]);
            if ($exists->fetchColumn() === false) {
                throw new InvalidArgumentException('This opportunity cannot be reported.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO c2c_reports (reporter_user_id, opportunity_id, reason)
                 VALUES (:reporter, :opportunity, :reason)'
            );
            $insert->execute([
                'reporter' => $reporter === null ? null : (int) $reporter['id'],
                'opportunity' => $id,
                'reason' => $reason,
            ]);
            flash('Thank you. The report has been sent for review.');
            redirectTo('opportunity', ['id' => $id]);

        case 'review_report':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['report_id'] ?? null, 'Report');
            $decision = (string) ($_POST['decision'] ?? '');
            if (!in_array($decision, ['reviewed','dismissed','suspend'], true)) {
                throw new InvalidArgumentException('Choose a valid report action.');
            }
            $pdo->beginTransaction();
            try {
                $lookup = $pdo->prepare("SELECT opportunity_id FROM c2c_reports WHERE id = :id AND status = 'open' FOR UPDATE");
                $lookup->execute(['id' => $id]);
                $opportunityId = $lookup->fetchColumn();
                if ($opportunityId === false) {
                    throw new InvalidArgumentException('This report has already been reviewed.');
                }
                if ($decision === 'suspend') {
                    $suspend = $pdo->prepare("UPDATE c2c_opportunities SET status = 'suspended' WHERE id = :id AND status = 'published'");
                    $suspend->execute(['id' => (int) $opportunityId]);
                }
                $update = $pdo->prepare(
                    'UPDATE c2c_reports SET status = :status, reviewed_by_user_id = :reviewer,
                        reviewed_at = CURRENT_TIMESTAMP WHERE id = :id'
                );
                $update->execute([
                    'status' => $decision === 'suspend' ? 'reviewed' : $decision,
                    'reviewer' => (int) $admin['id'],
                    'id' => $id,
                ]);
                auditEvent($pdo, (int) $admin['id'], 'report_' . $decision, 'report', $id);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            flash('Report review recorded.');
            redirectTo('admin', ['tab' => 'reports']);

        case 'create_announcement':
            $author = requireRole(['admin','institution_admin'], $pdo);
            $institutionId = null;
            if ($author['role'] === 'institution_admin') {
                $assigned = $pdo->prepare('SELECT institution_id FROM c2c_institution_admins WHERE user_id = :user');
                $assigned->execute(['user' => (int) $author['id']]);
                $institutionId = $assigned->fetchColumn();
                if ($institutionId === false) {
                    throw new InvalidArgumentException('Your account is not assigned to an institution.');
                }
            }
            $published = isset($_POST['is_published']) ? 1 : 0;
            $insert = $pdo->prepare(
                'INSERT INTO c2c_announcements (author_user_id, institution_id, title, body, is_published, published_at)
                 VALUES (:author, :institution, :title, :body, :published, IF(:published_at = 1, CURRENT_TIMESTAMP, NULL))'
            );
            $insert->execute([
                'author' => (int) $author['id'],
                'institution' => $institutionId,
                'title' => requiredText('title', 'Announcement title', 180),
                'body' => requiredText('body', 'Announcement message', 5000),
                'published' => $published,
                'published_at' => $published,
            ]);
            $id = (int) $pdo->lastInsertId();
            auditEvent($pdo, (int) $author['id'], 'announcement_created', 'announcement', $id);
            flash('Announcement saved.');
            redirectTo($author['role'] === 'admin' ? 'admin' : 'institution');

        case 'toggle_announcement':
            $account = requireRole(['admin','institution_admin'], $pdo);
            $id = validId($_POST['announcement_id'] ?? null, 'Announcement');
            if ($account['role'] === 'admin') {
                $update = $pdo->prepare(
                    'UPDATE c2c_announcements SET published_at = IF(is_published = 0, CURRENT_TIMESTAMP, NULL),
                        is_published = 1 - is_published WHERE id = :id AND institution_id IS NULL'
                );
                $update->execute(['id' => $id]);
            } else {
                $assignment = $pdo->prepare('SELECT institution_id FROM c2c_institution_admins WHERE user_id = :user');
                $assignment->execute(['user' => (int) $account['id']]);
                $institutionId = $assignment->fetchColumn();
                if ($institutionId === false) {
                    throw new InvalidArgumentException('Your account is not assigned to an institution.');
                }
                $update = $pdo->prepare(
                    'UPDATE c2c_announcements SET published_at = IF(is_published = 0, CURRENT_TIMESTAMP, NULL),
                        is_published = 1 - is_published
                     WHERE id = :id AND institution_id = :institution'
                );
                $update->execute(['id' => $id, 'institution' => (int) $institutionId]);
            }
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Announcement not found or not assigned to your account.');
            }
            auditEvent($pdo, (int) $account['id'], 'announcement_publication_toggled', 'announcement', $id);
            flash('Announcement publication status updated.');
            redirectTo($account['role'] === 'admin' ? 'admin' : 'institution');

        case 'update_institution_profile':
            $account = requireRole(['institution_admin'], $pdo);
            $assignment = $pdo->prepare('SELECT institution_id FROM c2c_institution_admins WHERE user_id = :user');
            $assignment->execute(['user' => (int) $account['id']]);
            $institutionId = $assignment->fetchColumn();
            if ($institutionId === false) {
                throw new InvalidArgumentException('Your account is not assigned to an institution.');
            }
            $website = optionalText('website', 'Website', 255);
            if ($website !== null && !validWebUrl($website)) {
                throw new InvalidArgumentException('Enter a valid website URL.');
            }
            $contact = optionalText('contact_email', 'Contact email', 190);
            if ($contact !== null && !validEmail($contact)) {
                throw new InvalidArgumentException('Enter a valid contact email address.');
            }
            $update = $pdo->prepare(
                'UPDATE c2c_institutions SET location = :location, website = :website,
                    description = :description, contact_email = :contact
                 WHERE id = :institution AND is_active = 1'
            );
            $update->execute([
                'location' => optionalText('location', 'Location', 160),
                'website' => $website,
                'description' => optionalText('description', 'Description', 4000),
                'contact' => $contact,
                'institution' => (int) $institutionId,
            ]);
            auditEvent($pdo, (int) $account['id'], 'institution_profile_updated', 'institution', (int) $institutionId);
            flash('Institution profile updated.');
            redirectTo('institution');

        case 'create_category':
            $admin = requireRole(['admin'], $pdo);
            $name = requiredText('name', 'Category name', 100);
            $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
            if ($slug === '') {
                throw new InvalidArgumentException('Enter a category name containing letters or numbers.');
            }
            $insert = $pdo->prepare('INSERT INTO c2c_opportunity_categories (name, slug, sort_order) VALUES (:name, :slug, 99)');
            $insert->execute(['name' => $name, 'slug' => $slug]);
            $id = (int) $pdo->lastInsertId();
            auditEvent($pdo, (int) $admin['id'], 'category_created', 'category', $id);
            flash('Opportunity category added.');
            redirectTo('admin', ['tab' => 'categories']);

        case 'toggle_category':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['category_id'] ?? null, 'Category');
            $update = $pdo->prepare('UPDATE c2c_opportunity_categories SET is_active = 1 - is_active WHERE id = :id');
            $update->execute(['id' => $id]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Category not found.');
            }
            auditEvent($pdo, (int) $admin['id'], 'category_status_toggled', 'category', $id);
            flash('Category status updated.');
            redirectTo('admin', ['tab' => 'categories']);

        case 'toggle_resource':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['resource_id'] ?? null, 'Resource');
            $update = $pdo->prepare(
                'UPDATE c2c_resources SET published_at = IF(is_published = 0, CURRENT_TIMESTAMP, NULL),
                    is_published = 1 - is_published WHERE id = :id'
            );
            $update->execute(['id' => $id]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Resource not found.');
            }
            auditEvent($pdo, (int) $admin['id'], 'resource_publication_toggled', 'resource', $id);
            flash('Resource publication status updated.');
            redirectTo('admin', ['tab' => 'resources']);

        case 'mark_notification_read':
            $account = requireAuth($pdo);
            $id = validId($_POST['notification_id'] ?? null, 'Notification');
            $read = $pdo->prepare('UPDATE c2c_notifications SET read_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user');
            $read->execute(['id' => $id, 'user' => (int) $account['id']]);
            flash('Notification marked as read.');
            redirectTo('notifications');

        case 'mark_all_notifications_read':
            $account = requireAuth($pdo);
            $read = $pdo->prepare('UPDATE c2c_notifications SET read_at = CURRENT_TIMESTAMP WHERE user_id = :user AND read_at IS NULL');
            $read->execute(['user' => (int) $account['id']]);
            flash('Notifications marked as read.');
            redirectTo('notifications');

        case 'request_institution':
            $requester = requiredText('institution_name', 'Institution name', 180);
            $email = strtolower(requiredText('contact_email', 'Contact email', 190));
            if (!validEmail($email)) {
                throw new InvalidArgumentException('Enter a valid contact email address.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO c2c_institution_requests (requested_by_user_id, institution_name, location, country, contact_email)
                 VALUES (:user, :name, :location, :country, :email)'
            );
            $insert->execute([
                'user' => $user === null ? null : (int) $user['id'],
                'name' => $requester,
                'location' => optionalText('location', 'Location', 160),
                'country' => requiredText('country', 'Country', 100),
                'email' => $email,
            ]);
            flash('Your institution request has been sent for review.');
            redirectTo('institution-request');

        case 'contact_message':
            $name = $user === null ? requiredText('sender_name', 'Your name', 160) : $user['full_name'];
            $email = $user === null ? strtolower(requiredText('sender_email', 'Email address', 190)) : $user['email'];
            if (!validEmail($email)) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO c2c_contact_messages (user_id, sender_name, sender_email, subject, message)
                 VALUES (:user, :name, :email, :subject, :message)'
            );
            $insert->execute([
                'user' => $user === null ? null : (int) $user['id'],
                'name' => $name,
                'email' => $email,
                'subject' => requiredText('subject', 'Subject', 180),
                'message' => requiredText('message', 'Message', 5000),
            ]);
            flash('Your message has been received.');
            redirectTo('contact');

        case 'handle_contact_message':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['message_id'] ?? null, 'Message');
            $update = $pdo->prepare(
                "UPDATE c2c_contact_messages SET status = 'handled', handled_by_user_id = :admin,
                    handled_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'new'"
            );
            $update->execute(['admin' => (int) $admin['id'], 'id' => $id]);
            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('This contact message is not available.');
            }
            auditEvent($pdo, (int) $admin['id'], 'contact_message_handled', 'contact_message', $id);
            flash('Contact message marked as handled.');
            redirectTo('admin', ['tab' => 'contact']);

        case 'review_institution_request':
            $admin = requireRole(['admin'], $pdo);
            $id = validId($_POST['request_id'] ?? null, 'Institution request');
            $decision = (string) ($_POST['decision'] ?? '');
            if (!in_array($decision, ['approve','reject'], true)) {
                throw new InvalidArgumentException('Choose a valid review decision.');
            }
            $note = optionalText('review_note', 'Review note', 1000);
            $pdo->beginTransaction();
            try {
                $lookup = $pdo->prepare(
                    "SELECT * FROM c2c_institution_requests WHERE id = :id AND status = 'pending' FOR UPDATE"
                );
                $lookup->execute(['id' => $id]);
                $request = $lookup->fetch();
                if ($request === false) {
                    throw new InvalidArgumentException('This institution request is no longer pending.');
                }
                if ($decision === 'approve') {
                    $create = $pdo->prepare(
                        'INSERT IGNORE INTO c2c_institutions (name, location, country, contact_email, is_verified)
                         VALUES (:name, :location, :country, :email, 0)'
                    );
                    $create->execute([
                        'name' => $request['institution_name'],
                        'location' => $request['location'],
                        'country' => $request['country'],
                        'email' => $request['contact_email'],
                    ]);
                }
                $update = $pdo->prepare(
                    'UPDATE c2c_institution_requests SET status = :status, reviewed_by_user_id = :admin,
                        review_note = :note, reviewed_at = CURRENT_TIMESTAMP WHERE id = :id'
                );
                $update->execute([
                    'status' => $decision === 'approve' ? 'approved' : 'rejected',
                    'admin' => (int) $admin['id'],
                    'note' => $note,
                    'id' => $id,
                ]);
                auditEvent($pdo, (int) $admin['id'], 'institution_request_' . $decision, 'institution_request', $id);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
                    throw new InvalidArgumentException('An institution with that name already exists. Review the request and add details manually.');
                }
                throw $exception;
            }
            flash('Institution request reviewed.');
            redirectTo('admin', ['tab' => 'institutions']);

        default:
            throw new InvalidArgumentException('This action is not available.');
    }
}

function validId(mixed $value, string $label): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        throw new InvalidArgumentException('Choose a valid ' . strtolower($label) . '.');
    }

    return $id;
}

function validWebUrl(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http','https'], true);
}
