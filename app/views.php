<?php
declare(strict_types=1);

function render(string $view, array $data = []): void
{
    $file = dirname(__DIR__) . '/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('The requested page is unavailable.');
    }

    if (!array_key_exists('navInitials', $data)) {
        $userName = is_array($data['currentUser'] ?? null) && is_string($data['currentUser']['full_name'] ?? null)
            ? trim($data['currentUser']['full_name'])
            : '';
        $nameParts = $userName === '' ? [] : preg_split('/\s+/u', $userName, -1, PREG_SPLIT_NO_EMPTY);
        $data['navInitials'] = $nameParts === false || $nameParts === []
            ? ''
            : mb_strtoupper(
                mb_substr($nameParts[0], 0, 1, 'UTF-8') . (isset($nameParts[1]) ? mb_substr($nameParts[1], 0, 1, 'UTF-8') : ''),
                'UTF-8'
            );
    }

    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $content = (string) ob_get_clean();
    require dirname(__DIR__) . '/views/layout.php';
}

function notificationUrl(string $link): string
{
    if (preg_match('/\Aopportunity:(\d+)\z/', $link, $matches) === 1) {
        return pageUrl('opportunity', ['id' => (int) $matches[1]]);
    }

    $allowed = ['dashboard','applications','employer','admin','institution','profile','opportunities','resources'];
    return in_array($link, $allowed, true) ? pageUrl($link) : pageUrl('dashboard');
}

function opportunityCard(array $opportunity): string
{
    $deadline = $opportunity['application_deadline'] ?? null;
    ob_start();
    ?>
    <article class="opportunity-card">
        <div class="card-topline">
            <span class="category-pill"><?= e($opportunity['category_name']) ?></span>
            <?php if (!empty($opportunity['featured'])): ?><span class="featured-pill">Featured</span><?php endif; ?>
        </div>
        <h3><a href="<?= e(pageUrl('opportunity', ['id' => (int) $opportunity['id']])) ?>"><?= e($opportunity['title']) ?></a></h3>
        <p class="organization-name"><?= e($opportunity['organization_name'] ?? 'Institution opportunity') ?></p>
        <div class="opportunity-meta">
            <?php if (!empty($opportunity['location'])): ?><span><?= e($opportunity['location']) ?></span><?php endif; ?>
            <span><?= e(str_replace('_', ' ', ucfirst($opportunity['work_arrangement']))) ?></span>
            <?php if (!empty($opportunity['compensation'])): ?><span><?= e($opportunity['compensation']) ?></span><?php endif; ?>
        </div>
        <?php if ($deadline !== null): ?><p class="deadline">Apply by <?= e(date('M j, Y', strtotime((string) $deadline))) ?></p><?php endif; ?>
        <a class="text-link" href="<?= e(pageUrl('opportunity', ['id' => (int) $opportunity['id']])) ?>">View opportunity <span aria-hidden="true">→</span></a>
    </article>
    <?php
    return (string) ob_get_clean();
}
