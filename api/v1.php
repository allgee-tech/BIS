<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/app/data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigins = array_filter(array_map('trim', explode(',', (string) appConfig('C2C_API_ORIGINS', ''))));
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
}
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    header('Allow: GET, OPTIONS');
    echo json_encode(['error' => 'This API only supports read-only GET requests.']);
    exit;
}

try {
    $pdo = database();
    $resource = is_string($_GET['resource'] ?? null) ? $_GET['resource'] : '';
    if ($resource === 'categories') {
        $data = fetchCategories($pdo);
    } elseif ($resource === 'stats') {
        $data = opportunityStats($pdo);
    } elseif ($resource === 'opportunities') {
        $filters = [
            'q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '',
            'category' => is_string($_GET['category'] ?? null) ? mb_substr(trim($_GET['category']), 0, 110) : '',
            'location' => is_string($_GET['location'] ?? null) ? mb_substr(trim($_GET['location']), 0, 100) : '',
            'work' => is_string($_GET['work'] ?? null) ? $_GET['work'] : '',
            'compensated' => is_string($_GET['compensated'] ?? null) ? $_GET['compensated'] : '',
            'deadline' => is_string($_GET['deadline'] ?? null) ? $_GET['deadline'] : '',
            'sort' => is_string($_GET['sort'] ?? null) && $_GET['sort'] === 'deadline' ? 'deadline' : 'newest',
        ];
        $page = max(1, min(10000, (int) ($_GET['page'] ?? 1)));
        $result = fetchPublicOpportunities($pdo, $filters, null, $page, 12);
        $data = [
            'items' => $result['rows'],
            'page' => $page,
            'page_size' => 12,
            'total' => $result['total'],
        ];
    } elseif ($resource === 'opportunity') {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id === null || (int) $id < 1) {
            http_response_code(400);
            echo json_encode(['error' => 'A valid opportunity ID is required.']);
            exit;
        }
        $data = findOpportunity($pdo, (int) $id, null);
        if ($data === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Opportunity not found.']);
            exit;
        }
    } elseif ($resource === 'resources') {
        $query = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : '';
        $statement = $pdo->prepare(
            "SELECT id, title, category, summary, published_at FROM c2c_resources
             WHERE is_published = 1
               AND (:query = '' OR title LIKE :title OR category LIKE :category OR summary LIKE :summary)
             ORDER BY published_at DESC LIMIT 50"
        );
        $statement->execute([
            'query' => $query,
            'title' => '%' . $query . '%',
            'category' => '%' . $query . '%',
            'summary' => '%' . $query . '%',
        ]);
        $data = $statement->fetchAll();
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Unknown API resource.']);
        exit;
    }

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    error_log('Campus2Career public API failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'The API is temporarily unavailable.']);
}
