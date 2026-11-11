<?php
$currentUser = $currentUser ?? null;
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? 'Connecting Talent to Opportunity';
$unreadNotifications = (int) ($unreadNotifications ?? 0);
$flashMessage = takeFlash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Find internships, jobs, scholarships and career resources with Campus2Career.">
    <title><?= e($pageTitle) ?> · Campus2Career</title>
    <link rel="stylesheet" href="<?= e(basePath()) ?>/assets/style.css">
</head>
<body class="<?= $activePage === 'dashboard' ? 'dashboard-page' : '' ?>">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(pageUrl('home')) ?>" aria-label="Campus2Career home">
            <span class="brand-mark">C2</span><span class="brand-name">Campus<span>2</span>Career<small>CONNECTING TALENT TO OPPORTUNITY</small></span>
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">Menu</button>
        <nav class="main-nav" id="main-nav" aria-label="Main navigation">
            <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="<?= e(pageUrl('home')) ?>">Home</a>
            <a class="<?= in_array($activePage, ['opportunities','opportunity'], true) ? 'active' : '' ?>" href="<?= e(pageUrl('opportunities')) ?>">Explore opportunities</a>
            <a class="<?= in_array($activePage, ['resources','resource'], true) ? 'active' : '' ?>" href="<?= e(pageUrl('resources')) ?>">Career resources</a>
            <a href="<?= e(pageUrl('about')) ?>">About</a>
            <a href="<?= e(pageUrl('contact')) ?>">Contact</a>
            <?php if ($currentUser !== null): ?>
                <a href="<?= e(pageUrl('dashboard')) ?>">Dashboard</a>
                <a class="notification-link" href="<?= e(pageUrl('notifications')) ?>">Notifications<?php if ($unreadNotifications > 0): ?><span class="notification-count"><?= e($unreadNotifications) ?></span><?php endif; ?></a>
                <span class="nav-user" aria-label="Signed in as <?= e($currentUser['full_name']) ?>"><span class="nav-user-avatar" aria-hidden="true"><?= e($navInitials) ?></span><span class="nav-user-name"><?= e($currentUser['full_name']) ?></span></span>
                <form class="nav-logout" method="post" action="<?= e(pageUrl('home')) ?>">
                    <?= formToken() ?><input type="hidden" name="action" value="logout">
                    <button class="button button-outline button-small" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a class="nav-signin" href="<?= e(pageUrl('login')) ?>">Sign in</a>
                <a class="button button-primary button-small" href="<?= e(pageUrl('register')) ?>">Get started</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main id="main-content">
    <?php if ($flashMessage !== null): ?>
        <div class="container"><div class="alert alert-<?= e($flashMessage['type'] ?? 'success') ?>" role="status"><?= e($flashMessage['message'] ?? '') ?></div></div>
    <?php endif; ?>
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div><a class="brand footer-brand" href="<?= e(pageUrl('home')) ?>"><span class="brand-mark">C2</span><span class="brand-name">Campus<span>2</span>Career<small>CONNECTING TALENT TO OPPORTUNITY</small></span></a><p>Helping students and graduates move from learning to meaningful work.</p></div>
        <div><strong>Explore</strong><a href="<?= e(pageUrl('opportunities')) ?>">Opportunities</a><a href="<?= e(pageUrl('resources')) ?>">Career resources</a></div>
        <div><strong>Campus2Career</strong><a href="<?= e(pageUrl('about')) ?>">About us</a><a href="<?= e(pageUrl('institution-request')) ?>">Add an institution</a></div>
        <div><strong>Get in touch</strong><a href="<?= e(pageUrl('contact')) ?>">Contact us</a><?php if ($currentUser === null): ?><a href="<?= e(pageUrl('login')) ?>">Sign in</a><a href="<?= e(pageUrl('register')) ?>">Create an account</a><?php else: ?><a href="<?= e(pageUrl('dashboard')) ?>">Your dashboard</a><?php endif; ?></div>
    </div>
    <div class="container footer-bottom"><span>© <?= e(date('Y')) ?> Campus2Career</span><span>Built for Ghana's next generation of talent.</span></div>
</footer>
<script src="<?= e(basePath()) ?>/assets/app.js" defer></script>
</body>
</html>
