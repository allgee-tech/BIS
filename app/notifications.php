<?php
declare(strict_types=1);

function notifyUser(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO c2c_notifications (user_id, title, message, link)
         VALUES (:user_id, :title, :message, :link)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'title' => mb_substr($title, 0, 160),
        'message' => mb_substr($message, 0, 1000),
        'link' => $link,
    ]);
}

function notifyMatchingStudents(PDO $pdo, int $opportunityId): void
{
    $query = $pdo->prepare(
        'SELECT p.title, p.required_skills, p.description, p.location, p.institution_id,
                c.name AS category_name
         FROM c2c_opportunities p
         INNER JOIN c2c_opportunity_categories c ON c.id = p.category_id
         WHERE p.id = :id'
    );
    $query->execute(['id' => $opportunityId]);
    $opportunity = $query->fetch();
    if ($opportunity === false) {
        return;
    }

    $text = mb_strtolower(implode(' ', [
        $opportunity['title'],
        $opportunity['required_skills'],
        $opportunity['description'],
        $opportunity['location'],
        $opportunity['category_name'],
    ]));
    $students = $pdo->prepare(
        "SELECT u.id, s.skills, s.career_interests, s.programme, s.institution_id
         FROM c2c_users u INNER JOIN c2c_student_profiles s ON s.user_id = u.id
         WHERE u.role = 'student' AND u.status = 'active'
           AND (:institution_id IS NULL OR s.institution_id = :target_institution)
         ORDER BY u.id DESC LIMIT 5000"
    );
    $institutionId = $opportunity['institution_id'] === null ? null : (int) $opportunity['institution_id'];
    $students->execute([
        'institution_id' => $institutionId,
        'target_institution' => $institutionId ?? 0,
    ]);

    foreach ($students->fetchAll() as $student) {
        $terms = preg_split(
            '/[,\s;]+/u',
            mb_strtolower(implode(' ', [
                (string) $student['skills'],
                (string) $student['career_interests'],
                (string) $student['programme'],
            ])),
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];
        foreach (array_unique($terms) as $term) {
            if (mb_strlen($term) >= 3 && str_contains($text, $term)) {
                notifyUser(
                    $pdo,
                    (int) $student['id'],
                    'A new opportunity may suit you',
                    'A new ' . $opportunity['category_name'] . ' opportunity matches your profile: ' . $opportunity['title'] . '.',
                    'opportunity:' . $opportunityId
                );
                break;
            }
        }
    }
}
