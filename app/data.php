<?php
declare(strict_types=1);

function fetchCategories(PDO $pdo): array
{
    return $pdo->query(
        "SELECT c.id, c.name, c.slug,
                (SELECT COUNT(*) FROM c2c_opportunities p
                 WHERE p.category_id = c.id AND p.status = 'published'
                   AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)
                ) AS opportunity_count
         FROM c2c_opportunity_categories c
         WHERE c.is_active = 1
         ORDER BY c.sort_order, c.name"
    )->fetchAll();
}

function fetchInstitutions(PDO $pdo, bool $includeInactive = false): array
{
    $sql = 'SELECT id, name, code, location, country, website, is_verified, is_active FROM c2c_institutions';
    if (!$includeInactive) {
        $sql .= ' WHERE is_active = 1';
    }
    return $pdo->query($sql . ' ORDER BY name')->fetchAll();
}

function opportunityStats(PDO $pdo): array
{
    $counts = [];
    $counts['opportunities'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM c2c_opportunities
         WHERE status = 'published' AND (application_deadline IS NULL OR application_deadline >= CURRENT_DATE)"
    )->fetchColumn();
    $counts['organizations'] = (int) $pdo->query("SELECT COUNT(*) FROM c2c_organizations WHERE verification_status = 'verified'")->fetchColumn();
    $counts['institutions'] = (int) $pdo->query('SELECT COUNT(*) FROM c2c_institutions WHERE is_active = 1')->fetchColumn();
    $counts['students'] = (int) $pdo->query("SELECT COUNT(*) FROM c2c_users WHERE role = 'student' AND status = 'active'")->fetchColumn();
    return $counts;
}

function publicOpportunitySelect(): string
{
    return 'SELECT p.id, p.title, p.description, p.location, p.work_arrangement, p.compensation,
                   p.application_deadline, p.start_date, p.duration, p.application_method, p.application_url,
                   p.contact_email, p.featured, p.required_skills, p.status, p.created_at, p.updated_at, p.published_at,
                   c.id AS category_id, c.name AS category_name, c.slug AS category_slug,
                   o.name AS organization_name, o.verification_status,
                   i.name AS institution_name, p.institution_id,
                   u.full_name AS author_name
            FROM c2c_opportunities p
            INNER JOIN c2c_opportunity_categories c ON c.id = p.category_id
            LEFT JOIN c2c_organizations o ON o.id = p.organization_id
            LEFT JOIN c2c_institutions i ON i.id = p.institution_id
            INNER JOIN c2c_users u ON u.id = p.created_by_user_id';
}

function fetchPublicOpportunities(PDO $pdo, array $filters, ?array $user, int $pageNo = 1, int $pageSize = 9): array
{
    $where = [
        "p.status = 'published'",
        '(p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)',
        '(p.institution_id IS NULL OR p.institution_id = :institution_id)',
    ];
    $params = ['institution_id' => (int) ($user['institution_id'] ?? 0)];
    $query = trim((string) ($filters['q'] ?? ''));
    if ($query !== '') {
        $where[] = '(p.title LIKE :q_title OR p.description LIKE :q_description
            OR p.required_skills LIKE :q_skills OR p.location LIKE :q_location OR o.name LIKE :q_org)';
        $like = '%' . $query . '%';
        foreach (['q_title','q_description','q_skills','q_location','q_org'] as $name) {
            $params[$name] = $like;
        }
    }
    if (($filters['category'] ?? '') !== '') {
        $where[] = 'c.slug = :category';
        $params['category'] = $filters['category'];
    }
    if (($filters['location'] ?? '') !== '') {
        $where[] = 'p.location LIKE :location';
        $params['location'] = '%' . $filters['location'] . '%';
    }
    if (in_array(($filters['work'] ?? ''), ['remote','hybrid','on_site'], true)) {
        $where[] = 'p.work_arrangement = :work';
        $params['work'] = $filters['work'];
    }
    if (($filters['compensated'] ?? '') === 'yes') {
        $where[] = "p.compensation IS NOT NULL AND p.compensation <> ''";
    }
    if (in_array(($filters['deadline'] ?? ''), ['7','30'], true)) {
        $where[] = 'p.application_deadline BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL ' . (int) $filters['deadline'] . ' DAY)';
    }
    $condition = implode(' AND ', $where);
    $count = $pdo->prepare(
        'SELECT COUNT(*) FROM c2c_opportunities p
         INNER JOIN c2c_opportunity_categories c ON c.id = p.category_id
         LEFT JOIN c2c_organizations o ON o.id = p.organization_id
         WHERE ' . $condition
    );
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $order = ($filters['sort'] ?? 'newest') === 'deadline'
        ? 'p.application_deadline IS NULL, p.application_deadline ASC, p.published_at DESC'
        : 'p.published_at DESC, p.id DESC';
    $pageNo = max(1, $pageNo);
    $offset = ($pageNo - 1) * $pageSize;
    $statement = $pdo->prepare(
        publicOpportunitySelect() . ' WHERE ' . $condition . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();

    return ['rows' => $statement->fetchAll(), 'total' => $total];
}

function findOpportunity(PDO $pdo, int $id, ?array $user): ?array
{
    $sql = publicOpportunitySelect() . " WHERE p.id = :id
        AND (
            (p.status = 'published' AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)
             AND (p.institution_id IS NULL OR p.institution_id = :institution_id))
            OR p.created_by_user_id = :user_id
            OR :is_admin = 1
        ) LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id' => $id,
        'institution_id' => (int) ($user['institution_id'] ?? 0),
        'user_id' => (int) ($user['id'] ?? 0),
        'is_admin' => ($user['role'] ?? '') === 'admin' ? 1 : 0,
    ]);

    return $stmt->fetch() ?: null;
}

function fetchRecommendations(PDO $pdo, int $studentId, ?array $profile): array
{
    if ($profile === null) {
        return [];
    }
    $statement = $pdo->prepare(
        publicOpportunitySelect() . " WHERE p.status = 'published'
            AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)
            AND (p.institution_id IS NULL OR p.institution_id = :institution_id)
            AND NOT EXISTS (
                SELECT 1 FROM c2c_applications a
                WHERE a.opportunity_id = p.id AND a.student_user_id = :student_id
            )
            ORDER BY p.published_at DESC LIMIT 100"
    );
    $statement->execute([
        'institution_id' => (int) ($profile['institution_id'] ?? 0),
        'student_id' => $studentId,
    ]);
    $opportunities = $statement->fetchAll();

    $profileTerms = array_merge(
        preg_split('/[,\s;]+/u', mb_strtolower((string) ($profile['skills'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        preg_split('/[,\s;]+/u', mb_strtolower((string) ($profile['career_interests'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        preg_split('/[,\s;]+/u', mb_strtolower((string) ($profile['programme'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: []
    );
    $terms = array_values(array_unique(array_filter($profileTerms, static fn (string $term): bool => mb_strlen($term) >= 3)));
    $scored = [];
    foreach ($opportunities as $opportunity) {
        $text = mb_strtolower(implode(' ', [
            $opportunity['title'],
            $opportunity['description'],
            $opportunity['required_skills'],
            $opportunity['category_name'],
            $opportunity['location'],
        ]));
        $score = 0;
        foreach ($terms as $term) {
            if (str_contains($text, $term)) {
                $score += 1;
            }
        }
        if ((int) ($profile['institution_id'] ?? 0) > 0
            && (int) ($opportunity['institution_id'] ?? 0) === (int) $profile['institution_id']) {
            $score += 2;
        }
        if ($score > 0) {
            $opportunity['match_score'] = $score;
            $scored[] = $opportunity;
        }
    }
    usort($scored, static fn (array $a, array $b): int => ($b['match_score'] <=> $a['match_score']) ?: strcmp((string) $b['published_at'], (string) $a['published_at']));
    return array_slice($scored, 0, 3);
}

function fetchStudentDashboard(PDO $pdo, array $user): array
{
    $profileQuery = $pdo->prepare('SELECT * FROM c2c_student_profiles WHERE user_id = :id');
    $profileQuery->execute(['id' => (int) $user['id']]);
    $profile = $profileQuery->fetch() ?: [];
    $filled = 0;
    foreach (['institution_id','programme','academic_level','graduation_year','skills','career_interests'] as $field) {
        if (!empty($profile[$field])) {
            $filled++;
        }
    }
    $profileCompletion = (int) round($filled / 6 * 100);

    $statsQuery = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM c2c_applications WHERE student_user_id = :user_apps) AS applications,
            (SELECT COUNT(*) FROM c2c_saved_opportunities WHERE user_id = :user_saved) AS saved'
    );
    $statsQuery->execute(['user_apps' => (int) $user['id'], 'user_saved' => (int) $user['id']]);
    $counts = $statsQuery->fetch();
    $openQuery = $pdo->prepare(
        "SELECT COUNT(*) FROM c2c_opportunities WHERE status = 'published'
         AND (application_deadline IS NULL OR application_deadline >= CURRENT_DATE)
         AND (institution_id IS NULL OR institution_id = :institution)"
    );
    $openQuery->execute(['institution' => (int) ($profile['institution_id'] ?? 0)]);
    $open = (int) $openQuery->fetchColumn();

    $applicationQuery = $pdo->prepare(
        'SELECT a.id, a.opportunity_id, a.status, a.submitted_at, p.title, o.name AS organization_name
         FROM c2c_applications a INNER JOIN c2c_opportunities p ON p.id = a.opportunity_id
         LEFT JOIN c2c_organizations o ON o.id = p.organization_id
         WHERE a.student_user_id = :user ORDER BY a.submitted_at DESC LIMIT 5'
    );
    $applicationQuery->execute(['user' => (int) $user['id']]);

    $deadlineQuery = $pdo->prepare(
        "SELECT p.id, p.title, p.application_deadline, o.name AS organization_name
         FROM c2c_saved_opportunities s
         INNER JOIN c2c_opportunities p ON p.id = s.opportunity_id
         INNER JOIN c2c_student_profiles sp ON sp.user_id = s.user_id
         LEFT JOIN c2c_organizations o ON o.id = p.organization_id
         WHERE s.user_id = :user AND p.status = 'published'
           AND (p.institution_id IS NULL OR p.institution_id = sp.institution_id)
           AND p.application_deadline BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY)
         ORDER BY p.application_deadline ASC LIMIT 5"
    );
    $deadlineQuery->execute(['user' => (int) $user['id']]);
    $notification = $pdo->prepare('SELECT COUNT(*) FROM c2c_notifications WHERE user_id = :user AND read_at IS NULL');
    $notification->execute(['user' => (int) $user['id']]);

    return [
        'profile' => $profile,
        'profileCompletion' => $profileCompletion,
        'stats' => ['open' => $open, 'applications' => (int) $counts['applications'], 'saved' => (int) $counts['saved']],
        'applications' => $applicationQuery->fetchAll(),
        'deadlines' => $deadlineQuery->fetchAll(),
        'recommendations' => fetchRecommendations($pdo, (int) $user['id'], $profile),
        'unreadNotifications' => (int) $notification->fetchColumn(),
    ];
}

function fetchApplications(PDO $pdo, int $studentId): array
{
    $stmt = $pdo->prepare(
        'SELECT a.id, a.opportunity_id, a.status, a.submitted_at, p.title, o.name AS organization_name
         FROM c2c_applications a INNER JOIN c2c_opportunities p ON p.id = a.opportunity_id
         LEFT JOIN c2c_organizations o ON o.id = p.organization_id
         WHERE a.student_user_id = :user ORDER BY a.submitted_at DESC'
    );
    $stmt->execute(['user' => $studentId]);
    $applications = $stmt->fetchAll();
    if ($applications === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($applications), '?'));
    $historyQuery = $pdo->prepare(
        'SELECT h.application_id, h.status, h.created_at, u.full_name AS changed_by
         FROM c2c_application_status_history h
         LEFT JOIN c2c_users u ON u.id = h.changed_by_user_id
         WHERE h.application_id IN (' . $placeholders . ')
         ORDER BY h.created_at DESC, h.id DESC'
    );
    $historyQuery->execute(array_map(static fn (array $application): int => (int) $application['id'], $applications));
    $historyByApplication = [];
    foreach ($historyQuery->fetchAll() as $event) {
        $historyByApplication[(int) $event['application_id']][] = $event;
    }
    foreach ($applications as &$application) {
        $application['status_history'] = $historyByApplication[(int) $application['id']] ?? [];
    }
    unset($application);
    return $applications;
}

function fetchSavedOpportunities(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        publicOpportunitySelect() . ' INNER JOIN c2c_saved_opportunities s ON s.opportunity_id = p.id
         INNER JOIN c2c_student_profiles sp ON sp.user_id = s.user_id
         WHERE s.user_id = :user AND p.status = \'published\'
           AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)
           AND (p.institution_id IS NULL OR p.institution_id = sp.institution_id)
         ORDER BY s.created_at DESC'
    );
    $stmt->execute(['user' => $userId]);
    return $stmt->fetchAll();
}

function fetchEmployerData(PDO $pdo, array $user, ?int $selectedOrganizationId = null): array
{
    $organizationQuery = $pdo->prepare(
        'SELECT o.*, (o.owner_user_id = :owner) AS is_owner, r.role_title AS representative_role
         FROM c2c_organizations o
         LEFT JOIN c2c_organization_representatives r ON r.organization_id = o.id
         WHERE o.owner_user_id = :owner_lookup OR (r.user_id = :rep_user AND r.is_active = 1)
         ORDER BY (o.owner_user_id = :owner_order) DESC'
    );
    $organizationQuery->execute([
        'owner' => (int) $user['id'],
        'owner_lookup' => (int) $user['id'],
        'rep_user' => (int) $user['id'],
        'owner_order' => (int) $user['id'],
    ]);
    $organizations = $organizationQuery->fetchAll();
    $organization = null;
    foreach ($organizations as $availableOrganization) {
        if ($selectedOrganizationId !== null && (int) $availableOrganization['id'] === $selectedOrganizationId) {
            $organization = $availableOrganization;
            break;
        }
        if ($selectedOrganizationId === null && $organization === null) {
            $organization = $availableOrganization;
        }
    }
    if ($organization === null && $organizations !== []) {
        $organization = $organizations[0];
    }

    $opportunities = [];
    $applications = [];
    $stats = ['published' => 0, 'pending' => 0, 'applications' => 0];
    if ($organization !== null) {
        $opportunityQuery = $pdo->prepare(
            "SELECT p.id, p.title,
                    IF(p.status = 'published' AND p.application_deadline < CURRENT_DATE, 'expired', p.status) AS status,
                    p.application_deadline, p.rejection_reason, c.name AS category_name,
                    (SELECT COUNT(*) FROM c2c_applications a WHERE a.opportunity_id = p.id) AS application_count
             FROM c2c_opportunities p INNER JOIN c2c_opportunity_categories c ON c.id = p.category_id
             WHERE p.organization_id = :organization ORDER BY p.created_at DESC"
        );
        $opportunityQuery->execute(['organization' => (int) $organization['id']]);
        $opportunities = $opportunityQuery->fetchAll();
        $applicationQuery = $pdo->prepare(
            'SELECT a.id, a.status, a.submitted_at, a.student_user_id, p.title,
                    u.full_name, u.email
             FROM c2c_applications a
             INNER JOIN c2c_opportunities p ON p.id = a.opportunity_id
             INNER JOIN c2c_users u ON u.id = a.student_user_id
             WHERE p.organization_id = :organization ORDER BY a.submitted_at DESC LIMIT 100'
        );
        $applicationQuery->execute(['organization' => (int) $organization['id']]);
        $applications = $applicationQuery->fetchAll();
        foreach ($applications as &$application) {
            $documents = $pdo->prepare(
                'SELECT id, kind, original_name FROM c2c_documents WHERE application_id = :application ORDER BY kind, id'
            );
            $documents->execute(['application' => (int) $application['id']]);
            $application['documents'] = $documents->fetchAll();
        }
        unset($application);
        $countQuery = $pdo->prepare(
            "SELECT
                SUM(status = 'published') AS published,
                SUM(status = 'pending_review') AS pending
            FROM c2c_opportunities WHERE organization_id = :organization"
        );
        $countQuery->execute(['organization' => (int) $organization['id']]);
        $opportunityCounts = $countQuery->fetch();
        $stats['published'] = (int) ($opportunityCounts['published'] ?? 0);
        $stats['pending'] = (int) ($opportunityCounts['pending'] ?? 0);
        $applicationCount = $pdo->prepare(
            'SELECT COUNT(*) FROM c2c_applications a
             INNER JOIN c2c_opportunities p ON p.id = a.opportunity_id
             WHERE p.organization_id = :organization'
        );
        $applicationCount->execute(['organization' => (int) $organization['id']]);
        $stats['applications'] = (int) $applicationCount->fetchColumn();
    }

    $representatives = [];
    if ($organization !== null && (bool) $organization['is_owner']) {
        $representativeQuery = $pdo->prepare(
            'SELECT u.id, u.full_name, u.email, r.role_title
             FROM c2c_organization_representatives r
             INNER JOIN c2c_users u ON u.id = r.user_id
             WHERE r.organization_id = :organization AND r.is_active = 1
             ORDER BY u.full_name'
        );
        $representativeQuery->execute(['organization' => (int) $organization['id']]);
        $representatives = $representativeQuery->fetchAll();
    }

    return compact('organization','organizations','opportunities','applications','stats','representatives');
}

function fetchAdminData(PDO $pdo): array
{
    $stats = [
        'Total users' => (int) $pdo->query('SELECT COUNT(*) FROM c2c_users')->fetchColumn(),
        'Students & graduates' => (int) $pdo->query("SELECT COUNT(*) FROM c2c_users WHERE role = 'student'")->fetchColumn(),
        'Recent graduates' => (int) $pdo->query("SELECT COUNT(*) FROM c2c_student_profiles WHERE graduation_year < YEAR(CURRENT_DATE)")->fetchColumn(),
        'Employer accounts' => (int) $pdo->query("SELECT COUNT(*) FROM c2c_users WHERE role = 'employer'")->fetchColumn(),
        'Verified organizations' => (int) $pdo->query("SELECT COUNT(*) FROM c2c_organizations WHERE verification_status = 'verified'")->fetchColumn(),
        'Institutions' => (int) $pdo->query('SELECT COUNT(*) FROM c2c_institutions WHERE is_active = 1')->fetchColumn(),
        'Published opportunities' => (int) $pdo->query("SELECT COUNT(*) FROM c2c_opportunities WHERE status = 'published' AND (application_deadline IS NULL OR application_deadline >= CURRENT_DATE)")->fetchColumn(),
        'Pending opportunities' => (int) $pdo->query("SELECT COUNT(*) FROM c2c_opportunities WHERE status = 'pending_review'")->fetchColumn(),
        'Applications' => (int) $pdo->query('SELECT COUNT(*) FROM c2c_applications')->fetchColumn(),
    ];
    $pending = $pdo->query(
        publicOpportunitySelect() . " WHERE p.status = 'pending_review' ORDER BY p.created_at ASC LIMIT 50"
    )->fetchAll();
    $approved = $pdo->query(
        publicOpportunitySelect() . " WHERE p.status = 'approved' ORDER BY p.updated_at ASC LIMIT 50"
    )->fetchAll();
    $published = $pdo->query(
        publicOpportunitySelect() . " WHERE p.status = 'published'
         ORDER BY p.published_at DESC, p.id DESC LIMIT 100"
    )->fetchAll();
    $organizations = $pdo->query(
        'SELECT o.*, u.full_name, u.email FROM c2c_organizations o
         INNER JOIN c2c_users u ON u.id = o.owner_user_id ORDER BY o.created_at DESC LIMIT 100'
    )->fetchAll();
    $users = $pdo->query('SELECT id, email, full_name, role, status, created_at FROM c2c_users ORDER BY created_at DESC LIMIT 100')->fetchAll();
    $institutions = fetchInstitutions($pdo, true);
    $allCategories = $pdo->query('SELECT id, name, slug, is_active FROM c2c_opportunity_categories ORDER BY sort_order, name')->fetchAll();
    $institutionRequests = $pdo->query(
        "SELECT * FROM c2c_institution_requests WHERE status = 'pending' ORDER BY created_at ASC LIMIT 100"
    )->fetchAll();
    $reports = $pdo->query(
        "SELECT r.id, r.opportunity_id, r.reason, r.created_at, p.title, u.email AS reporter_email
         FROM c2c_reports r INNER JOIN c2c_opportunities p ON p.id = r.opportunity_id
         LEFT JOIN c2c_users u ON u.id = r.reporter_user_id
         WHERE r.status = 'open' ORDER BY r.created_at ASC LIMIT 100"
    )->fetchAll();
    $audit = $pdo->query(
        'SELECT a.id, a.actor_user_id, a.action_key, a.entity_type, a.entity_id, a.after_data, a.created_at,
                u.full_name AS actor_name
         FROM c2c_audit_logs a LEFT JOIN c2c_users u ON u.id = a.actor_user_id
         ORDER BY a.created_at DESC, a.id DESC LIMIT 100'
    )->fetchAll();
    $announcements = $pdo->query(
        'SELECT id, title, body, is_published, published_at, created_at
         FROM c2c_announcements WHERE institution_id IS NULL ORDER BY created_at DESC LIMIT 10'
    )->fetchAll();
    $contactMessages = $pdo->query(
        'SELECT id, sender_name, sender_email, subject, message, status, created_at
         FROM c2c_contact_messages ORDER BY status = \'new\' DESC, created_at DESC LIMIT 100'
    )->fetchAll();
    $categories = $pdo->query('SELECT id, name, slug, is_active FROM c2c_opportunity_categories ORDER BY sort_order, name')->fetchAll();
    $resources = $pdo->query('SELECT id, title, category, summary, content, is_published FROM c2c_resources ORDER BY created_at DESC LIMIT 100')->fetchAll();

    return compact('stats','pending','approved','published','organizations','users','institutions','allCategories','categories','institutionRequests','reports','audit','announcements','contactMessages','resources');
}

function fetchUnreadNotificationCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM c2c_notifications WHERE user_id = :user AND read_at IS NULL');
    $stmt->execute(['user' => $userId]);
    return (int) $stmt->fetchColumn();
}

function fetchDocument(PDO $pdo, array $user, int $documentId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT d.*, a.opportunity_id, p.created_by_user_id AS opportunity_author, p.institution_id
         FROM c2c_documents d
         LEFT JOIN c2c_applications a ON a.id = d.application_id
         LEFT JOIN c2c_opportunities p ON p.id = a.opportunity_id
         WHERE d.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $documentId]);
    $document = $stmt->fetch();
    if ($document === false) {
        return null;
    }

    $authorized = (int) $document['owner_user_id'] === (int) $user['id'] || $user['role'] === 'admin';
    if ($user['role'] === 'employer' && $document['application_id'] !== null) {
        $ownerCheck = $pdo->prepare(
            'SELECT 1 FROM c2c_applications a
             INNER JOIN c2c_opportunities p ON p.id = a.opportunity_id
             INNER JOIN c2c_organizations o ON o.id = p.organization_id
             LEFT JOIN c2c_organization_representatives r
               ON r.organization_id = o.id AND r.user_id = :rep_user AND r.is_active = 1
             WHERE a.id = :application AND (o.owner_user_id = :owner OR r.user_id IS NOT NULL)'
        );
        $ownerCheck->execute([
            'application' => (int) $document['application_id'],
            'owner' => (int) $user['id'],
            'rep_user' => (int) $user['id'],
        ]);
        $authorized = $authorized || $ownerCheck->fetchColumn() !== false;
    }
    if ($user['role'] === 'institution_admin' && $document['institution_id'] !== null) {
        $institutionCheck = $pdo->prepare(
            'SELECT 1 FROM c2c_institution_admins WHERE user_id = :user AND institution_id = :institution'
        );
        $institutionCheck->execute([
            'user' => (int) $user['id'],
            'institution' => (int) $document['institution_id'],
        ]);
        $authorized = $authorized || $institutionCheck->fetchColumn() !== false;
    }

    return $authorized ? $document : null;
}

function fetchInstitutionData(PDO $pdo, array $user): array
{
    $assignment = $pdo->prepare(
        'SELECT i.* FROM c2c_institution_admins a
         INNER JOIN c2c_institutions i ON i.id = a.institution_id
         WHERE a.user_id = :user AND i.is_active = 1 LIMIT 1'
    );
    $assignment->execute(['user' => (int) $user['id']]);
    $institution = $assignment->fetch();
    if ($institution === false) {
        throw new RuntimeException('Your account is not assigned to an active institution.');
    }

    $studentCount = $pdo->prepare('SELECT COUNT(*) FROM c2c_student_profiles WHERE institution_id = :institution');
    $studentCount->execute(['institution' => (int) $institution['id']]);
    $opportunityCount = $pdo->prepare(
        "SELECT COUNT(*) FROM c2c_opportunities
         WHERE institution_id = :institution AND status = 'published'
           AND (application_deadline IS NULL OR application_deadline >= CURRENT_DATE)"
    );
    $opportunityCount->execute(['institution' => (int) $institution['id']]);
    $applicationCount = $pdo->prepare(
        'SELECT COUNT(*) FROM c2c_applications a INNER JOIN c2c_opportunities p ON p.id = a.opportunity_id
         WHERE p.institution_id = :institution'
    );
    $applicationCount->execute(['institution' => (int) $institution['id']]);

    $opportunities = $pdo->prepare(
        publicOpportunitySelect() . " WHERE p.institution_id = :institution AND p.status = 'published'
         AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)
         ORDER BY p.published_at DESC LIMIT 6"
    );
    $opportunities->execute(['institution' => (int) $institution['id']]);
    $announcements = $pdo->prepare(
        "SELECT id, title, body, is_published, published_at, created_at FROM c2c_announcements
         WHERE institution_id = :institution
         ORDER BY created_at DESC, id DESC LIMIT 20"
    );
    $announcements->execute(['institution' => (int) $institution['id']]);

    return [
        'institution' => $institution,
        'stats' => [
            'students' => (int) $studentCount->fetchColumn(),
            'opportunities' => (int) $opportunityCount->fetchColumn(),
            'applications' => (int) $applicationCount->fetchColumn(),
        ],
        'opportunities' => $opportunities->fetchAll(),
        'announcements' => $announcements->fetchAll(),
    ];
}
