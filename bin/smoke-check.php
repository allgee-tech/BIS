<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

function expectSmoke(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = database();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(6));
    $createUser = $pdo->prepare(
        'INSERT INTO c2c_users (email, full_name, password_hash, role)
         VALUES (:email, :name, :password, :role)'
    );
    $newUser = static function (string $role, string $label) use ($createUser, $pdo, $suffix): array {
        $email = $label . '-' . $suffix . '@example.invalid';
        $createUser->execute([
            'email' => $email,
            'name' => ucfirst($label) . ' Smoke Test',
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        return ['id' => (int) $pdo->lastInsertId(), 'email' => $email, 'role' => $role];
    };

    $student = $newUser('student', 'student');
    $employer = $newUser('employer', 'employer');
    $representative = $newUser('employer', 'representative');
    $outsider = $newUser('employer', 'outsider');
    $institutionAdmin = $newUser('institution_admin', 'institution-admin');
    $newUser('admin', 'admin');

    $institutionId = (int) $pdo->query("SELECT id FROM c2c_institutions WHERE code = 'PUC'")->fetchColumn();
    $categoryId = (int) $pdo->query("SELECT id FROM c2c_opportunity_categories WHERE slug = 'internships'")->fetchColumn();
    $profile = $pdo->prepare('INSERT INTO c2c_student_profiles (user_id, institution_id, programme, skills) VALUES (:user, :institution, :programme, :skills)');
    $profile->execute(['user' => $student['id'], 'institution' => $institutionId, 'programme' => 'Computing', 'skills' => 'PHP, research']);
    $assignInstitution = $pdo->prepare('INSERT INTO c2c_institution_admins (institution_id, user_id) VALUES (:institution, :user)');
    $assignInstitution->execute(['institution' => $institutionId, 'user' => $institutionAdmin['id']]);

    $organization = $pdo->prepare(
        'INSERT INTO c2c_organizations (owner_user_id, name, organization_type, country)
         VALUES (:owner, :name, \'Company\', \'Ghana\')'
    );
    $organization->execute(['owner' => $employer['id'], 'name' => 'Smoke Test Organization ' . $suffix]);
    $organizationId = (int) $pdo->lastInsertId();
    $organization->execute(['owner' => $representative['id'], 'name' => 'Representative Home Organization ' . $suffix]);
    $addRepresentative = $pdo->prepare(
        'INSERT INTO c2c_organization_representatives (organization_id, user_id, role_title)
         VALUES (:organization, :user, \'Recruiter\')'
    );
    $addRepresentative->execute(['organization' => $organizationId, 'user' => $representative['id']]);

    $opportunity = $pdo->prepare(
        "INSERT INTO c2c_opportunities
            (organization_id, created_by_user_id, category_id, title, description, location, status, published_at)
         VALUES (:organization, :author, :category, :title, 'Smoke test opportunity description', 'Accra', 'published', CURRENT_TIMESTAMP)"
    );
    $opportunity->execute([
        'organization' => $organizationId,
        'author' => $employer['id'],
        'category' => $categoryId,
        'title' => 'Smoke Test Opportunity ' . $suffix,
    ]);
    $opportunityId = (int) $pdo->lastInsertId();
    $save = $pdo->prepare('INSERT INTO c2c_saved_opportunities (user_id, opportunity_id) VALUES (:user, :opportunity)');
    $save->execute(['user' => $student['id'], 'opportunity' => $opportunityId]);
    $applicationInsert = $pdo->prepare('INSERT INTO c2c_applications (opportunity_id, student_user_id, cover_letter) VALUES (:opportunity, :student, :letter)');
    $applicationInsert->execute(['opportunity' => $opportunityId, 'student' => $student['id'], 'letter' => 'Smoke test cover letter']);
    $applicationId = (int) $pdo->lastInsertId();
    $historyInsert = $pdo->prepare(
        "INSERT INTO c2c_application_status_history (application_id, status, changed_by_user_id)
         VALUES (:application, 'submitted', :actor)"
    );
    $historyInsert->execute(['application' => $applicationId, 'actor' => $student['id']]);
    $documentInsert = $pdo->prepare(
        "INSERT INTO c2c_documents (owner_user_id, application_id, kind, original_name, stored_name, mime_type, file_size)
         VALUES (:owner, :application, 'cv', 'smoke.pdf', :stored, 'application/pdf', 1)"
    );
    $documentInsert->execute([
        'owner' => $student['id'],
        'application' => $applicationId,
        'stored' => $suffix . '.pdf',
    ]);
    $documentId = (int) $pdo->lastInsertId();

    $search = fetchPublicOpportunities($pdo, ['q' => $suffix, 'category' => 'internships'], null);
    expectSmoke($search['total'] === 1, 'Opportunity search did not return the matching listing.');
    expectSmoke(findOpportunity($pdo, $opportunityId, null) !== null, 'Public opportunity details could not be loaded.');
    expectSmoke(count(fetchSavedOpportunities($pdo, $student['id'])) === 1, 'Saved opportunities query failed.');
    $applications = fetchApplications($pdo, $student['id']);
    expectSmoke(count($applications) === 1 && count($applications[0]['status_history']) === 1, 'Application history query failed.');
    expectSmoke(fetchStudentDashboard($pdo, $student)['stats']['applications'] === 1, 'Student dashboard query failed.');
    expectSmoke(count(fetchEmployerData($pdo, $employer)['applications']) === 1, 'Organization owner dashboard query failed.');
    expectSmoke(count(fetchEmployerData($pdo, $representative, $organizationId)['applications']) === 1, 'Organization representative access failed.');
    expectSmoke(str_starts_with((string) fetchEmployerData($pdo, $representative)['organization']['name'], 'Representative Home'), 'An employer account could not choose between its own and represented organizations.');
    expectSmoke(fetchEmployerData($pdo, $outsider)['organization'] === null, 'An unrelated employer gained organization access.');
    expectSmoke(fetchAdminData($pdo)['stats']['Applications'] >= 1, 'Admin dashboard query failed.');
    expectSmoke(fetchInstitutionData($pdo, $institutionAdmin)['stats']['students'] >= 1, 'Institution dashboard query failed.');
    expectSmoke(fetchDocument($pdo, $student, $documentId) !== null, 'Document owner could not access the document record.');
    expectSmoke(fetchDocument($pdo, $employer, $documentId) !== null, 'Organization owner could not access the applicant document.');
    expectSmoke(fetchDocument($pdo, $representative, $documentId) !== null, 'Organization representative could not access the applicant document.');
    expectSmoke(fetchDocument($pdo, $outsider, $documentId) === null, 'An unrelated employer gained document access.');

    fwrite(STDOUT, "Smoke checks passed; all temporary database records will be rolled back.\n");
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
