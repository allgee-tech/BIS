<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/app/security.php';

function seedUser(PDO $pdo, string $email, string $name, string $role, string $password): int
{
    $lookup = $pdo->prepare('SELECT id FROM c2c_users WHERE email = :email LIMIT 1');
    $lookup->execute(['email' => $email]);
    $existing = $lookup->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }

    $insert = $pdo->prepare(
        'INSERT INTO c2c_users (email, full_name, password_hash, role, email_verified_at)
         VALUES (:email, :name, :password_hash, :role, CURRENT_TIMESTAMP)'
    );
    $insert->execute([
        'email' => $email,
        'name' => $name,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
    return (int) $pdo->lastInsertId();
}

function seedCategory(PDO $pdo, string $name, string $slug, int $sortOrder): int
{
    $lookup = $pdo->prepare('SELECT id FROM c2c_opportunity_categories WHERE slug = :slug LIMIT 1');
    $lookup->execute(['slug' => $slug]);
    $existing = $lookup->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }
    $insert = $pdo->prepare(
        'INSERT INTO c2c_opportunity_categories (name, slug, sort_order) VALUES (:name, :slug, :sort_order)'
    );
    $insert->execute(['name' => $name, 'slug' => $slug, 'sort_order' => $sortOrder]);
    return (int) $pdo->lastInsertId();
}

function seedOpportunity(PDO $pdo, array $values): int
{
    $lookup = $pdo->prepare(
        'SELECT id FROM c2c_opportunities WHERE title = :title AND created_by_user_id = :author LIMIT 1'
    );
    $lookup->execute(['title' => $values['title'], 'author' => $values['created_by_user_id']]);
    $existing = $lookup->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }

    $insert = $pdo->prepare(
        'INSERT INTO c2c_opportunities
         (organization_id, institution_id, created_by_user_id, category_id, title, description,
          responsibilities, requirements, qualifications, required_skills, location, work_arrangement,
          compensation, application_deadline, start_date, duration, application_method, contact_email,
          featured, status, published_at)
         VALUES
         (:organization_id, :institution_id, :created_by_user_id, :category_id, :title, :description,
          :responsibilities, :requirements, :qualifications, :required_skills, :location, :work_arrangement,
          :compensation, :application_deadline, :start_date, :duration, :application_method, :contact_email,
          :featured, :status, IF(:published = 1, CURRENT_TIMESTAMP, NULL))'
    );
    $values['published'] = $values['status'] === 'published' ? 1 : 0;
    $insert->execute($values);
    return (int) $pdo->lastInsertId();
}

try {
    $pdo = database();
    $pdo->beginTransaction();

    // Demo users only. This script never creates or changes an administrator.
    $studentId = seedUser($pdo, 'student.demo@campus2career.test', 'Ama Mensah', 'student', 'StudentDemo!2026');
    $secondStudentId = seedUser($pdo, 'graduate.demo@campus2career.test', 'Kojo Boateng', 'student', 'GraduateDemo!2026');
    $employerId = seedUser($pdo, 'employer.demo@campus2career.test', 'Nana Owusu', 'employer', 'EmployerDemo!2026');
    $institutionAdminId = seedUser($pdo, 'institution.demo@campus2career.test', 'Akosua Asante', 'institution_admin', 'InstitutionDemo!2026');

    $institutionLookup = $pdo->prepare('SELECT id FROM c2c_institutions WHERE code = :code LIMIT 1');
    $institutionLookup->execute(['code' => 'DEMO-TECH']);
    $institutionId = $institutionLookup->fetchColumn();
    if ($institutionId === false) {
        $insert = $pdo->prepare(
            'INSERT INTO c2c_institutions (name, code, location, country, website, description, contact_email, is_verified, is_active)
             VALUES (:name, :code, :location, :country, :website, :description, :contact_email, 1, 1)'
        );
        $insert->execute([
            'name' => 'Demo Institute of Technology',
            'code' => 'DEMO-TECH',
            'location' => 'Accra',
            'country' => 'Ghana',
            'website' => 'https://example.edu.gh',
            'description' => 'A seeded institution used to demonstrate campus opportunity workflows.',
            'contact_email' => 'info@example.edu.gh',
        ]);
        $institutionId = $pdo->lastInsertId();
    }
    $institutionId = (int) $institutionId;

    $studentProfile = $pdo->prepare(
        'INSERT INTO c2c_student_profiles (user_id, institution_id, programme, academic_level, graduation_year, skills, career_interests)
         VALUES (:user_id, :institution_id, :programme, :academic_level, :graduation_year, :skills, :career_interests)
         ON DUPLICATE KEY UPDATE institution_id = VALUES(institution_id), programme = VALUES(programme),
             academic_level = VALUES(academic_level), graduation_year = VALUES(graduation_year),
             skills = VALUES(skills), career_interests = VALUES(career_interests)'
    );
    foreach ([
        [$studentId, 'Computer Science', 'Level 300', 2027, 'PHP, MySQL, JavaScript, teamwork', 'Backend engineering, product development'],
        [$secondStudentId, 'Business Administration', 'Graduate', 2025, 'Research, Excel, communication, analysis', 'Operations, project management'],
    ] as $profile) {
        $studentProfile->execute([
            'user_id' => $profile[0], 'institution_id' => $institutionId, 'programme' => $profile[1],
            'academic_level' => $profile[2], 'graduation_year' => $profile[3], 'skills' => $profile[4],
            'career_interests' => $profile[5],
        ]);
    }

    $assignAdmin = $pdo->prepare(
        'INSERT IGNORE INTO c2c_institution_admins (institution_id, user_id) VALUES (:institution_id, :user_id)'
    );
    $assignAdmin->execute(['institution_id' => $institutionId, 'user_id' => $institutionAdminId]);

    $organizationLookup = $pdo->prepare('SELECT id FROM c2c_organizations WHERE owner_user_id = :owner LIMIT 1');
    $organizationLookup->execute(['owner' => $employerId]);
    $organizationId = $organizationLookup->fetchColumn();
    if ($organizationId === false) {
        $insert = $pdo->prepare(
            'INSERT INTO c2c_organizations (owner_user_id, name, organization_type, country, website, phone, description, verification_details, verification_status)
             VALUES (:owner, :name, :type, :country, :website, :phone, :description, :verification_details, :verification_status)'
        );
        $insert->execute([
            'owner' => $employerId, 'name' => 'BrightPath Digital Ltd.', 'type' => 'Technology company',
            'country' => 'Ghana', 'website' => 'https://example.com', 'phone' => '+233 20 000 0000',
            'description' => 'A demo employer helping early-career talent gain practical experience.',
            'verification_details' => 'Seeded demonstration organization.', 'verification_status' => 'verified',
        ]);
        $organizationId = $pdo->lastInsertId();
    }
    $organizationId = (int) $organizationId;

    $categories = [
        'internships' => seedCategory($pdo, 'Internships', 'internships', 1),
        'full-time-jobs' => seedCategory($pdo, 'Full-time jobs', 'full-time-jobs', 2),
        'graduate-programmes' => seedCategory($pdo, 'Graduate programmes', 'graduate-programmes', 5),
    ];

    $frontendId = seedOpportunity($pdo, [
        'organization_id' => $organizationId, 'institution_id' => null, 'created_by_user_id' => $employerId,
        'category_id' => $categories['internships'], 'title' => 'Junior Web Developer Internship',
        'description' => 'Join a product team building accessible digital services for Ghanaian businesses.',
        'responsibilities' => 'Build and test web features, participate in reviews, and document solutions.',
        'requirements' => 'Basic PHP or JavaScript experience and a willingness to learn.',
        'qualifications' => 'Current tertiary student or recent graduate.', 'required_skills' => 'PHP, JavaScript, Git, communication',
        'location' => 'Accra', 'work_arrangement' => 'hybrid', 'compensation' => 'Monthly stipend',
        'application_deadline' => date('Y-m-d', strtotime('+30 days')), 'start_date' => date('Y-m-d', strtotime('+45 days')),
        'duration' => '3 months', 'application_method' => 'platform', 'contact_email' => 'careers@example.com',
        'featured' => 1, 'status' => 'published',
    ]);
    $analystId = seedOpportunity($pdo, [
        'organization_id' => $organizationId, 'institution_id' => null, 'created_by_user_id' => $employerId,
        'category_id' => $categories['full-time-jobs'], 'title' => 'Business Operations Analyst',
        'description' => 'Support planning, reporting and process improvement across a growing technology business.',
        'responsibilities' => 'Prepare weekly reports, analyze operational data, and coordinate improvement projects.',
        'requirements' => 'Strong analytical and communication skills.',
        'qualifications' => 'Degree in business, economics, statistics or a related field.', 'required_skills' => 'Excel, reporting, analysis, presentations',
        'location' => 'Accra', 'work_arrangement' => 'on_site', 'compensation' => 'Competitive salary',
        'application_deadline' => date('Y-m-d', strtotime('+45 days')), 'start_date' => date('Y-m-d', strtotime('+60 days')),
        'duration' => 'Full-time', 'application_method' => 'platform', 'contact_email' => 'careers@example.com',
        'featured' => 0, 'status' => 'published',
    ]);
    seedOpportunity($pdo, [
        'organization_id' => null, 'institution_id' => $institutionId, 'created_by_user_id' => $institutionAdminId,
        'category_id' => $categories['graduate-programmes'], 'title' => 'Demo Institute Graduate Innovation Programme',
        'description' => 'A campus programme connecting graduates with mentors and practical innovation projects.',
        'responsibilities' => 'Attend workshops, collaborate on a project and present outcomes.',
        'requirements' => 'Must be a student or graduate of the Demo Institute of Technology.',
        'qualifications' => 'Any discipline with an interest in innovation.', 'required_skills' => 'Research, teamwork, presentation',
        'location' => 'Accra', 'work_arrangement' => 'on_site', 'compensation' => 'Certificate and mentorship',
        'application_deadline' => date('Y-m-d', strtotime('+21 days')), 'start_date' => date('Y-m-d', strtotime('+30 days')),
        'duration' => '8 weeks', 'application_method' => 'platform', 'contact_email' => 'careers@example.edu.gh',
        'featured' => 1, 'status' => 'published',
    ]);

    $application = $pdo->prepare(
        'INSERT IGNORE INTO c2c_applications (opportunity_id, student_user_id, cover_letter, status)
         VALUES (:opportunity, :student, :cover_letter, :status)'
    );
    $application->execute([
        'opportunity' => $frontendId, 'student' => $studentId,
        'cover_letter' => 'I am excited to apply my web development skills to a practical product team.',
        'status' => 'under_review',
    ]);
    $application->execute([
        'opportunity' => $analystId, 'student' => $secondStudentId,
        'cover_letter' => 'My research and reporting experience would help me contribute to this role.',
        'status' => 'submitted',
    ]);

    $reportLookup = $pdo->prepare('SELECT id FROM c2c_reports WHERE opportunity_id = :opportunity AND reason = :reason LIMIT 1');
    $reportLookup->execute(['opportunity' => $analystId, 'reason' => 'Demo report: please verify the compensation details.']);
    if ($reportLookup->fetchColumn() === false) {
        $report = $pdo->prepare('INSERT INTO c2c_reports (reporter_user_id, opportunity_id, reason) VALUES (:reporter, :opportunity, :reason)');
        $report->execute(['reporter' => $studentId, 'opportunity' => $analystId, 'reason' => 'Demo report: please verify the compensation details.']);
    }

    $announcement = $pdo->prepare(
        'INSERT INTO c2c_announcements (author_user_id, institution_id, title, body, is_published, published_at)
         SELECT :author, NULL, :title, :body, 1, CURRENT_TIMESTAMP
         WHERE NOT EXISTS (SELECT 1 FROM c2c_announcements WHERE title = :title_check)'
    );
    $announcement->execute([
        'author' => $institutionAdminId, 'title' => 'Welcome to the Campus2Career demo network',
        'title_check' => 'Welcome to the Campus2Career demo network',
        'body' => 'Explore opportunities, complete your profile and use the report centre to help keep listings trustworthy.',
    ]);
    $announcement->execute([
        'author' => $institutionAdminId, 'title' => 'Demo institute career week',
        'title_check' => 'Demo institute career week',
        'body' => 'Students can connect with employers and mentors through this seeded campus announcement.',
    ]);

    $resource = $pdo->prepare(
        'INSERT INTO c2c_resources (author_user_id, title, slug, category, summary, content, is_published, published_at)
         SELECT :author, :title, :slug, :category, :summary, :content, 1, CURRENT_TIMESTAMP
         WHERE NOT EXISTS (SELECT 1 FROM c2c_resources WHERE slug = :slug_check)'
    );
    $resource->execute([
        'author' => $institutionAdminId, 'title' => 'How to prepare for an internship', 'slug' => 'demo-prepare-for-internship',
        'slug_check' => 'demo-prepare-for-internship', 'category' => 'Career readiness',
        'summary' => 'A practical checklist for students preparing to apply and interview.',
        'content' => "Review your profile, tailor your CV, research the organization, and prepare clear examples of your skills.\n\nUse the application tracker to follow up on every submission.",
    ]);
    $resource->execute([
        'author' => $institutionAdminId, 'title' => 'Writing a strong opportunity report', 'slug' => 'demo-writing-opportunity-report',
        'slug_check' => 'demo-writing-opportunity-report', 'category' => 'Platform safety',
        'summary' => 'What to include when reporting inaccurate or unsafe listing information.',
        'content' => "Explain what looks wrong, include the relevant detail, and avoid personal information. Administrators review every report and record the outcome.",
    ]);

    $contact = $pdo->prepare(
        'INSERT INTO c2c_contact_messages (user_id, sender_name, sender_email, subject, message)
         SELECT :user_id, :name, :email, :subject, :message
         WHERE NOT EXISTS (SELECT 1 FROM c2c_contact_messages WHERE sender_email = :email_check AND subject = :subject_check)'
    );
    $contact->execute([
        'user_id' => $studentId, 'name' => 'Ama Mensah', 'email' => 'student.demo@campus2career.test',
        'email_check' => 'student.demo@campus2career.test', 'subject' => 'Demo support question',
        'subject_check' => 'Demo support question', 'message' => 'Could you share more guidance on preparing for interviews?',
    ]);

    $request = $pdo->prepare(
        'INSERT INTO c2c_institution_requests (requested_by_user_id, institution_name, location, country, contact_email)
         SELECT :user_id, :institution_name, :location, :country, :contact_email
         WHERE NOT EXISTS (SELECT 1 FROM c2c_institution_requests WHERE institution_name = :institution_check)'
    );
    $request->execute([
        'user_id' => $secondStudentId, 'institution_name' => 'Demo Polytechnic of Applied Studies',
        'institution_check' => 'Demo Polytechnic of Applied Studies', 'location' => 'Kumasi', 'country' => 'Ghana',
        'contact_email' => 'registrar@demo-polytechnic.test',
    ]);

    $pdo->commit();
    fwrite(STDOUT, "Demo data populated successfully. No administrator account was created or changed.\n");
    fwrite(STDOUT, "Demo passwords: StudentDemo!2026, GraduateDemo!2026, EmployerDemo!2026, InstitutionDemo!2026\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Campus2Career demo seed failed: ' . $exception->getMessage());
    fwrite(STDERR, "Demo data could not be populated. Check the PHP error log.\n");
    exit(1);
}
