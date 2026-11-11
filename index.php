<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/actions.php';

$page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
$allowedPages = [
    'home','about','login','register','forgot-password','reset-password','verify-email',
    'opportunities','opportunity','apply','applications','saved','dashboard','employer',
    'opportunity-form','profile','notifications','resources','resource','admin','institution',
    'institution-request','contact','document',
];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'not-found';
}

$pageTitle = 'Connecting Talent to Opportunity';
$activePage = $page;
$currentUser = null;
$unreadNotifications = 0;

try {
    $pdo = database();
    $currentUser = currentUser($pdo);
    if ($currentUser !== null) {
        $unreadNotifications = fetchUnreadNotificationCount($pdo, (int) $currentUser['id']);
    }

    if (requestMethod() === 'POST') {
        handlePost($pdo, $currentUser);
    }

    if ($page === 'verify-email') {
        $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
        $pdo->beginTransaction();
        try {
            $lookup = $pdo->prepare(
                'SELECT id, user_id FROM c2c_email_verifications
                 WHERE token_hash = :token AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP
                 FOR UPDATE'
            );
            $lookup->execute(['token' => hash('sha256', $token)]);
            $verification = $lookup->fetch();
            if ($verification === false) {
                throw new InvalidArgumentException('This verification link is invalid or has expired.');
            }
            $updateUser = $pdo->prepare('UPDATE c2c_users SET email_verified_at = CURRENT_TIMESTAMP WHERE id = :id');
            $updateUser->execute(['id' => (int) $verification['user_id']]);
            $used = $pdo->prepare('UPDATE c2c_email_verifications SET used_at = CURRENT_TIMESTAMP WHERE id = :id');
            $used->execute(['id' => (int) $verification['id']]);
            $pdo->commit();
            flash('Your email address has been verified.');
            redirectTo('login');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    if ($page === 'document') {
        $account = requireAuth($pdo);
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $document = $id === false || $id === null ? null : fetchDocument($pdo, $account, (int) $id);
        if ($document === null) {
            http_response_code(404);
            throw new RuntimeException('The requested document is not available.');
        }
        $path = privateStoragePath() . DIRECTORY_SEPARATOR . basename($document['stored_name']);
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The document is temporarily unavailable.');
        }
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Length: ' . (int) filesize($path));
        header('Content-Disposition: attachment; filename="' . str_replace(['"',"\r","\n"], '', basename($document['original_name'])) . '"; filename*=UTF-8\'\'' . rawurlencode($document['original_name']));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    if ($page === 'home') {
        $pageTitle = 'Your future starts with the right opportunity';
        $activePage = 'home';
        $stats = opportunityStats($pdo);
        $categories = fetchCategories($pdo);
        $institutions = $pdo->query('SELECT name, location, country FROM c2c_institutions WHERE is_active = 1 ORDER BY name LIMIT 8')->fetchAll();
        $featuredQuery = $pdo->prepare(
            publicOpportunitySelect() . " WHERE p.status = 'published'
             AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)
             AND (p.institution_id IS NULL OR p.institution_id = :institution_id)
             ORDER BY p.featured DESC, p.published_at DESC LIMIT 3"
        );
        $featuredQuery->execute(['institution_id' => (int) ($currentUser['institution_id'] ?? 0)]);
        $featured = $featuredQuery->fetchAll();
        $resources = $pdo->query(
            "SELECT id, title, category, summary FROM c2c_resources
             WHERE is_published = 1 ORDER BY published_at DESC, id DESC LIMIT 3"
        )->fetchAll();
        $announcements = $pdo->query(
            "SELECT title, body, published_at FROM c2c_announcements
             WHERE institution_id IS NULL AND is_published = 1
             ORDER BY published_at DESC, id DESC LIMIT 3"
        )->fetchAll();
        render('home', compact('pageTitle','activePage','currentUser','unreadNotifications','stats','categories','featured','resources','institutions','announcements'));
        exit;
    }

    if ($page === 'about') {
        $pageTitle = 'About Campus2Career';
        render('about', compact('pageTitle','activePage','currentUser','unreadNotifications'));
        exit;
    }

    if (in_array($page, ['login','register','forgot-password','reset-password'], true)) {
        if ($currentUser !== null) {
            redirectTo('dashboard');
        }
        $pageTitle = match ($page) {
            'register' => 'Create your account',
            'forgot-password' => 'Reset your password',
            'reset-password' => 'Choose a new password',
            default => 'Sign in',
        };
        $mode = $page === 'forgot-password' ? 'forgot' : ($page === 'reset-password' ? 'reset' : $page);
        $institutions = fetchInstitutions($pdo);
        $selectedRole = ($_GET['type'] ?? '') === 'employer' ? 'employer' : 'student';
        $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
        if ($mode === 'reset') {
            $check = $pdo->prepare(
                'SELECT 1 FROM c2c_password_resets WHERE token_hash = :token
                 AND used_at IS NULL AND expires_at > CURRENT_TIMESTAMP'
            );
            $check->execute(['token' => hash('sha256', $token)]);
            if ($check->fetchColumn() === false) {
                throw new InvalidArgumentException('This reset link is invalid or has expired.');
            }
        }
        render('auth', compact('pageTitle','activePage','currentUser','unreadNotifications','mode','institutions','selectedRole','token'));
        exit;
    }

    if ($page === 'opportunities') {
        $pageTitle = 'Explore opportunities';
        $filters = [
            'q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '',
            'category' => is_string($_GET['category'] ?? null) ? mb_substr(trim($_GET['category']), 0, 110) : '',
            'location' => is_string($_GET['location'] ?? null) ? mb_substr(trim($_GET['location']), 0, 100) : '',
            'work' => is_string($_GET['work'] ?? null) ? $_GET['work'] : '',
            'compensated' => is_string($_GET['compensated'] ?? null) ? $_GET['compensated'] : '',
            'deadline' => is_string($_GET['deadline'] ?? null) ? $_GET['deadline'] : '',
            'sort' => is_string($_GET['sort'] ?? null) && $_GET['sort'] === 'deadline' ? 'deadline' : 'newest',
        ];
        $pageNo = max(1, min(10000, (int) ($_GET['page_no'] ?? 1)));
        $result = fetchPublicOpportunities($pdo, $filters, $currentUser, $pageNo);
        $opportunities = $result['rows'];
        $total = $result['total'];
        $pages = max(1, (int) ceil($total / 9));
        $categories = fetchCategories($pdo);
        render('opportunities', compact('pageTitle','activePage','currentUser','unreadNotifications','filters','opportunities','total','pages','pageNo','categories'));
        exit;
    }

    if ($page === 'opportunity') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $opportunity = $id === false || $id === null ? null : findOpportunity($pdo, (int) $id, $currentUser);
        if ($opportunity === null) {
            http_response_code(404);
            throw new RuntimeException('This opportunity is unavailable or no longer open.');
        }
        $pageTitle = $opportunity['title'];
        $hasApplied = false;
        $isSaved = false;
        if ($currentUser !== null && $currentUser['role'] === 'student') {
            $applied = $pdo->prepare('SELECT 1 FROM c2c_applications WHERE student_user_id = :user AND opportunity_id = :opportunity');
            $applied->execute(['user' => (int) $currentUser['id'], 'opportunity' => (int) $opportunity['id']]);
            $hasApplied = $applied->fetchColumn() !== false;
            $saved = $pdo->prepare('SELECT 1 FROM c2c_saved_opportunities WHERE user_id = :user AND opportunity_id = :opportunity');
            $saved->execute(['user' => (int) $currentUser['id'], 'opportunity' => (int) $opportunity['id']]);
            $isSaved = $saved->fetchColumn() !== false;
        }
        $similarQuery = $pdo->prepare(
            publicOpportunitySelect() . " WHERE p.status = 'published' AND p.id <> :id
             AND p.category_id = :category AND (p.application_deadline IS NULL OR p.application_deadline >= CURRENT_DATE)
             AND (p.institution_id IS NULL OR p.institution_id = :institution_id)
             ORDER BY p.published_at DESC LIMIT 3"
        );
        $similarQuery->execute([
            'id' => (int) $opportunity['id'],
            'category' => (int) $opportunity['category_id'],
            'institution_id' => (int) ($currentUser['institution_id'] ?? 0),
        ]);
        $similar = $similarQuery->fetchAll();
        render('opportunity', compact('pageTitle','activePage','currentUser','unreadNotifications','opportunity','hasApplied','isSaved','similar'));
        exit;
    }

    if ($page === 'apply') {
        $student = requireRole(['student'], $pdo);
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $opportunity = $id === false || $id === null ? null : findOpportunity($pdo, (int) $id, $student);
        if ($opportunity === null || $opportunity['status'] !== 'published'
            || $opportunity['application_method'] !== 'platform'
            || ($opportunity['application_deadline'] !== null && $opportunity['application_deadline'] < date('Y-m-d'))) {
            throw new InvalidArgumentException('This opportunity is not accepting applications through Campus2Career.');
        }
        $duplicate = $pdo->prepare('SELECT 1 FROM c2c_applications WHERE student_user_id = :student AND opportunity_id = :opportunity');
        $duplicate->execute(['student' => (int) $student['id'], 'opportunity' => (int) $opportunity['id']]);
        if ($duplicate->fetchColumn() !== false) {
            flash('You have already applied to this opportunity.', 'error');
            redirectTo('opportunity', ['id' => (int) $opportunity['id']]);
        }
        $pageTitle = 'Apply: ' . $opportunity['title'];
        $cvQuery = $pdo->prepare(
            "SELECT id, original_name, created_at FROM c2c_documents
             WHERE owner_user_id = :user AND kind = 'cv' AND application_id IS NULL
             ORDER BY created_at DESC LIMIT 1"
        );
        $cvQuery->execute(['user' => (int) $student['id']]);
        $latestCv = $cvQuery->fetch() ?: null;
        render('apply', compact('pageTitle','activePage','currentUser','unreadNotifications','opportunity','latestCv'));
        exit;
    }

    if (in_array($page, ['dashboard','applications','saved'], true)) {
        if ($page === 'dashboard' && $currentUser !== null) {
            $roleDashboard = match ($currentUser['role']) {
                'admin' => 'admin',
                'employer' => 'employer',
                'institution_admin' => 'institution',
                default => null,
            };
            if ($roleDashboard !== null) {
                redirectTo($roleDashboard);
            }
        }
        $student = requireRole(['student'], $pdo);
        if ($page === 'dashboard') {
            $data = fetchStudentDashboard($pdo, $student);
            $pageTitle = 'Student dashboard';
            render('dashboard', [
                'pageTitle' => $pageTitle, 'activePage' => $page, 'currentUser' => $student,
                'unreadNotifications' => $data['unreadNotifications'],
                'profileCompletion' => $data['profileCompletion'], 'stats' => $data['stats'],
                'applications' => $data['applications'], 'deadlines' => $data['deadlines'],
                'recommendations' => $data['recommendations'],
            ]);
            exit;
        }
        if ($page === 'applications') {
            $pageTitle = 'My applications';
            $applications = fetchApplications($pdo, (int) $student['id']);
            render('applications', compact('pageTitle','activePage','currentUser','unreadNotifications','applications'));
            exit;
        }
        $pageTitle = 'Saved opportunities';
        $opportunities = fetchSavedOpportunities($pdo, (int) $student['id']);
        render('saved', compact('pageTitle','activePage','currentUser','unreadNotifications','opportunities'));
        exit;
    }

    if ($page === 'employer') {
        $employer = requireRole(['employer'], $pdo);
        $pageTitle = 'Organization dashboard';
        $selectedOrganizationId = filter_var($_GET['organization_id'] ?? null, FILTER_VALIDATE_INT);
        $selectedOrganizationId = $selectedOrganizationId !== false && $selectedOrganizationId !== null && $selectedOrganizationId > 0
            ? $selectedOrganizationId
            : null;
        $data = fetchEmployerData($pdo, $employer, $selectedOrganizationId);
        extract($data, EXTR_SKIP);
        render('employer', compact('pageTitle','activePage','currentUser','unreadNotifications','organization','organizations','opportunities','applications','stats','representatives'));
        exit;
    }

    if ($page === 'institution') {
        $institutionAdmin = requireRole(['institution_admin'], $pdo);
        $pageTitle = 'Institution dashboard';
        $data = fetchInstitutionData($pdo, $institutionAdmin);
        extract($data, EXTR_SKIP);
        render('institution', compact('pageTitle','activePage','currentUser','unreadNotifications','institution','stats','opportunities','announcements'));
        exit;
    }

    if ($page === 'opportunity-form') {
        $author = requireRole(['employer','institution_admin'], $pdo);
        if ($author['role'] === 'employer') {
            $organization = $pdo->prepare('SELECT id FROM c2c_organizations WHERE owner_user_id = :owner');
            $organization->execute(['owner' => (int) $author['id']]);
            if ($organization->fetchColumn() === false) {
                flash('Add your organization profile before creating opportunities.', 'error');
                redirectTo('profile');
            }
        }
        $editing = isset($_GET['id']);
        $item = [];
        if ($editing) {
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            $find = $pdo->prepare(
                "SELECT * FROM c2c_opportunities WHERE id = :id AND created_by_user_id = :author
                 AND status IN ('draft','rejected')"
            );
            $find->execute(['id' => (int) $id, 'author' => (int) $author['id']]);
            $item = $find->fetch();
            if ($item === false) {
                http_response_code(404);
                throw new RuntimeException('This opportunity cannot be edited.');
            }
        }
        $pageTitle = $editing ? 'Edit opportunity' : 'Create opportunity';
        $categories = fetchCategories($pdo);
        render('opportunity-form', compact('pageTitle','activePage','currentUser','unreadNotifications','editing','item','categories'));
        exit;
    }

    if ($page === 'profile') {
        $account = requireAuth($pdo);
        $pageTitle = 'Profile and settings';
        $profile = [];
        $institutions = [];
        $organization = null;
        $latestCv = null;
        if ($account['role'] === 'student') {
            $statement = $pdo->prepare('SELECT * FROM c2c_student_profiles WHERE user_id = :user');
            $statement->execute(['user' => (int) $account['id']]);
            $profile = $statement->fetch() ?: [];
            $institutions = fetchInstitutions($pdo);
            $cvQuery = $pdo->prepare(
                "SELECT id, original_name, created_at FROM c2c_documents
                 WHERE owner_user_id = :user AND kind = 'cv' AND application_id IS NULL
                 ORDER BY created_at DESC LIMIT 1"
            );
            $cvQuery->execute(['user' => (int) $account['id']]);
            $latestCv = $cvQuery->fetch() ?: null;
        } elseif ($account['role'] === 'employer') {
            $statement = $pdo->prepare('SELECT * FROM c2c_organizations WHERE owner_user_id = :owner');
            $statement->execute(['owner' => (int) $account['id']]);
            $organization = $statement->fetch() ?: null;
        }
        render('profile', compact('pageTitle','activePage','currentUser','unreadNotifications','profile','institutions','organization','latestCv'));
        exit;
    }

    if ($page === 'notifications') {
        $account = requireAuth($pdo);
        $pageTitle = 'Notifications';
        $statement = $pdo->prepare('SELECT * FROM c2c_notifications WHERE user_id = :user ORDER BY created_at DESC LIMIT 100');
        $statement->execute(['user' => (int) $account['id']]);
        $notifications = $statement->fetchAll();
        $unreadCount = $unreadNotifications;
        render('notifications', compact('pageTitle','activePage','currentUser','unreadNotifications','notifications','unreadCount'));
        exit;
    }

    if (in_array($page, ['resources','resource'], true)) {
        $pageTitle = 'Career resources';
        if ($page === 'resource') {
            $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
            $statement = $pdo->prepare(
                'SELECT id, title, category, summary, content, published_at, created_at
                 FROM c2c_resources WHERE id = :id AND (is_published = 1 OR :is_admin = 1)'
            );
            $statement->execute([
                'id' => (int) $id,
                'is_admin' => ($currentUser['role'] ?? '') === 'admin' ? 1 : 0,
            ]);
            $resource = $statement->fetch();
            if ($resource === false) {
                http_response_code(404);
                throw new RuntimeException('This resource is unavailable.');
            }
            $pageTitle = $resource['title'];
            render('resources', compact('pageTitle','activePage','currentUser','unreadNotifications','resource'));
            exit;
        }
        $query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
        if (($currentUser['role'] ?? '') === 'admin') {
            if ($query === '') {
                $resources = $pdo->query('SELECT id, title, category, summary, is_published FROM c2c_resources ORDER BY created_at DESC LIMIT 100')->fetchAll();
            } else {
                $find = $pdo->prepare('SELECT id, title, category, summary, is_published FROM c2c_resources WHERE title LIKE :title OR category LIKE :category ORDER BY created_at DESC LIMIT 100');
                $find->execute(['title' => '%' . $query . '%', 'category' => '%' . $query . '%']);
                $resources = $find->fetchAll();
            }
        } else {
            $find = $pdo->prepare('SELECT id, title, category, summary FROM c2c_resources WHERE is_published = 1 AND (:query = \'\' OR title LIKE :title OR category LIKE :category OR summary LIKE :summary) ORDER BY published_at DESC LIMIT 100');
            $find->execute([
                'query' => $query,
                'title' => '%' . $query . '%',
                'category' => '%' . $query . '%',
                'summary' => '%' . $query . '%',
            ]);
            $resources = $find->fetchAll();
        }
        render('resources', compact('pageTitle','activePage','currentUser','unreadNotifications','resources','query'));
        exit;
    }

    if ($page === 'admin') {
        $admin = requireRole(['admin'], $pdo);
        $pageTitle = 'Administration';
        $data = fetchAdminData($pdo);
        $tab = is_string($_GET['tab'] ?? null) ? $_GET['tab'] : 'overview';
        $validTabs = ['overview','opportunities','organizations','users','institutions','categories','resources','reports','contact','audit'];
        if (!in_array($tab, $validTabs, true)) {
            $tab = 'overview';
        }
        $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM c2c_opportunities WHERE status = 'pending_review'")->fetchColumn();
        render('admin', [
            'pageTitle' => $pageTitle, 'activePage' => $page, 'currentUser' => $admin,
            'unreadNotifications' => $unreadNotifications, 'tab' => $tab,
            'pendingCount' => $pendingCount, ...$data,
        ]);
        exit;
    }

    if ($page === 'institution-request') {
        $pageTitle = 'Request an institution';
        render('institution-request', compact('pageTitle','activePage','currentUser','unreadNotifications'));
        exit;
    }

    if ($page === 'contact') {
        $pageTitle = 'Contact Campus2Career';
        render('contact', compact('pageTitle','activePage','currentUser','unreadNotifications'));
        exit;
    }

    if ($page === 'not-found') {
        http_response_code(404);
        render('error', [
            'pageTitle' => 'Page not found',
            'activePage' => '',
            'currentUser' => $currentUser,
            'unreadNotifications' => $unreadNotifications,
            'errorTitle' => 'Page not found',
            'errorMessage' => 'The page you requested could not be found.',
        ]);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    if (http_response_code() < 400) {
        http_response_code(400);
    }
    flash($exception->getMessage(), 'error');
    $returnPage = $page === 'reset-password'
        ? 'forgot-password'
        : (in_array($page, ['login','register','forgot-password','opportunities','opportunity','apply','applications','saved','dashboard','employer','opportunity-form','profile','notifications','resources','admin','institution','institution-request','contact'], true)
            ? $page
            : 'home');
    $params = [];
    foreach (['id','tab','type'] as $key) {
        if (is_scalar($_GET[$key] ?? null)) {
            $params[$key] = (string) $_GET[$key];
        }
    }
    if ($page === 'opportunity' && isset($_POST['opportunity_id'])) {
        $params['id'] = (string) $_POST['opportunity_id'];
    }
    if ($page === 'apply' && isset($_POST['opportunity_id'])) {
        $params['id'] = (string) $_POST['opportunity_id'];
    }
    redirectTo($returnPage, $params);
} catch (Throwable $exception) {
    $isPermissionError = http_response_code() === 403;
    error_log('Campus2Career request failed [' . $page . ']: ' . $exception->getMessage());
    if (!$isPermissionError && http_response_code() < 400) {
        http_response_code(500);
    }
    $errorTitle = $isPermissionError ? 'Access denied' : 'Campus2Career is temporarily unavailable';
    $errorMessage = $isPermissionError
        ? 'Your account does not have permission to access this page.'
        : 'We could not complete your request. Please try again later.';
    $showDatabaseHelp = $exception instanceof PDOException;
    try {
        render('error', compact('pageTitle','activePage','currentUser','unreadNotifications','errorTitle','errorMessage','showDatabaseHelp'));
    } catch (Throwable $renderException) {
        http_response_code(500);
        echo 'Campus2Career is temporarily unavailable.';
    }
    exit;
}

http_response_code(404);
render('error', [
    'pageTitle' => 'Page not found',
    'activePage' => '',
    'currentUser' => $currentUser,
    'unreadNotifications' => $unreadNotifications,
    'errorTitle' => 'Page not found',
    'errorMessage' => 'The page you requested could not be found.',
]);
