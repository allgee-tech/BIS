<div class="career-dashboard">
    <aside class="dashboard-sidebar" aria-label="Dashboard navigation">
        <a class="dashboard-brand" href="<?= e(pageUrl('home')) ?>" aria-label="Campus2Career home">
            <span class="dashboard-brand-mark">C2</span>
            <span>Campus<span>2</span>Career<small>YOUR CAREER SPACE</small></span>
        </a>
        <span class="sidebar-label">WORKSPACE</span>
        <nav class="dashboard-nav" aria-label="Student">
            <a class="is-active" href="<?= e(pageUrl('dashboard')) ?>" aria-current="page"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>Overview</a>
            <a href="<?= e(pageUrl('opportunities')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>Explore opportunities</a>
            <a href="<?= e(pageUrl('applications')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h8l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v6h6M9 14h7M9 18h7"/></svg>Applications</a>
            <a href="<?= e(pageUrl('saved')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18l-6-4-6 4z"/></svg>Saved opportunities</a>
            <a href="<?= e(pageUrl('resources')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z"/><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8M8 10h6"/></svg>Career resources</a>
            <a href="<?= e(pageUrl('notifications')) ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>Notifications<?php if ($unreadNotifications > 0): ?><span class="sidebar-count"><?= e($unreadNotifications) ?></span><?php endif; ?></a>
        </nav>
        <div class="sidebar-bottom">
            <a class="sidebar-profile" href="<?= e(pageUrl('profile')) ?>">
                <span class="sidebar-avatar" aria-hidden="true"><?= e($navInitials) ?></span>
                <span class="sidebar-profile-copy"><strong><?= e($currentUser['full_name']) ?></strong><small><?= e($currentUser['email']) ?></small></span>
                <span class="sidebar-profile-arrow" aria-hidden="true">⌄</span>
            </a>
            <form method="post" action="<?= e(pageUrl('home')) ?>">
                <?= formToken() ?><input type="hidden" name="action" value="logout">
                <button class="sidebar-signout" type="submit">Sign out</button>
            </form>
        </div>
    </aside>

    <div class="dashboard-main">
        <header class="dashboard-topbar">
            <div class="dashboard-greeting">
                <span class="eyebrow">YOUR CAREER SPACE</span>
                <h1>Welcome back, <?= e($currentUser['full_name']) ?> <span aria-hidden="true">👋</span></h1>
                <p>Here’s what’s happening with your career journey today.</p>
            </div>
            <div class="dashboard-tools">
                <form class="dashboard-search" method="get" action="<?= e(basePath()) ?>/index.php">
                    <input type="hidden" name="page" value="opportunities">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                    <input type="search" name="q" placeholder="Search opportunities..." aria-label="Search opportunities">
                    <button type="submit" class="sr-only">Search</button>
                </form>
                <a class="dashboard-notification-button" href="<?= e(pageUrl('notifications')) ?>" aria-label="Notifications<?= $unreadNotifications > 0 ? ', ' . e($unreadNotifications) . ' unread' : '' ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                    <?php if ($unreadNotifications > 0): ?><span class="notification-indicator"><?= e($unreadNotifications) ?></span><?php endif; ?>
                </a>
            </div>
        </header>

        <section class="dashboard-summary" aria-label="Your career overview">
            <article class="summary-card summary-opportunities">
                <span class="summary-label">Open opportunities</span>
                <strong><?= e($stats['open']) ?></strong>
                <a href="<?= e(pageUrl('opportunities')) ?>">Find your next step <span aria-hidden="true">→</span></a>
            </article>
            <article class="summary-card summary-applications">
                <span class="summary-label">Applications</span>
                <strong><?= e($stats['applications']) ?></strong>
                <a href="<?= e(pageUrl('applications')) ?>">Track your progress <span aria-hidden="true">→</span></a>
            </article>
            <article class="summary-card summary-saved">
                <span class="summary-label">Saved opportunities</span>
                <strong><?= e($stats['saved']) ?></strong>
                <a href="<?= e(pageUrl('saved')) ?>">Revisit your shortlist <span aria-hidden="true">→</span></a>
            </article>
            <article class="summary-card summary-profile">
                <span class="summary-label">Profile completion</span>
                <strong><?= e($profileCompletion) ?>%</strong>
                <a href="<?= e(pageUrl('profile')) ?>">Make your profile stand out <span aria-hidden="true">→</span></a>
            </article>
        </section>

        <div class="dashboard-columns">
            <section class="panel dashboard-applications">
                <div class="panel-heading">
                    <div><span class="eyebrow">APPLICATION TRACKER</span><h2>Recent applications</h2></div>
                    <a class="text-link" href="<?= e(pageUrl('applications')) ?>">View all <span aria-hidden="true">→</span></a>
                </div>
                <?php if ($applications === []): ?>
                    <div class="empty-small"><p>You haven’t applied to an opportunity yet.</p><a href="<?= e(pageUrl('opportunities')) ?>">Explore what’s open →</a></div>
                <?php else: ?>
                    <div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Organization</th><th>Status</th><th>Applied</th></tr></thead><tbody><?php foreach ($applications as $application): ?><tr><td><a href="<?= e(pageUrl('opportunity', ['id' => (int) $application['opportunity_id']])) ?>"><?= e($application['title']) ?></a></td><td><?= e($application['organization_name'] ?? 'Institution opportunity') ?></td><td><span class="status status-<?= e($application['status']) ?>"><?= e(str_replace('_', ' ', ucfirst($application['status']))) ?></span></td><td><?= e(date('M j, Y', strtotime($application['submitted_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div>
                <?php endif; ?>
            </section>

            <section class="panel profile-progress-panel">
                <div class="panel-heading"><div><span class="eyebrow">YOUR PROFILE</span><h2>Profile progress</h2></div><a class="dashboard-more" href="<?= e(pageUrl('profile')) ?>" aria-label="Edit profile">↗</a></div>
                <div class="progress-content">
                    <div class="progress-ring" style="--profile-progress: <?= e($profileCompletion) ?>%"><div><strong><?= e($profileCompletion) ?>%</strong><span>Complete</span></div></div>
                    <div class="progress-copy"><strong>Build a stronger profile</strong><p>A complete profile helps match you with more relevant opportunities.</p><a href="<?= e(pageUrl('profile')) ?>">Update profile <span aria-hidden="true">→</span></a></div>
                </div>
            </section>

            <section class="panel dashboard-deadlines">
                <div class="panel-heading"><div><span class="eyebrow">DON’T MISS OUT</span><h2>Upcoming deadlines</h2></div><a class="text-link" href="<?= e(pageUrl('saved')) ?>">Saved <span aria-hidden="true">→</span></a></div>
                <?php if ($deadlines === []): ?>
                    <div class="empty-small"><p>No saved opportunity deadlines to show.</p><a href="<?= e(pageUrl('opportunities')) ?>">Find an opportunity →</a></div>
                <?php else: ?>
                    <div class="deadline-list"><?php foreach ($deadlines as $item): ?><a href="<?= e(pageUrl('opportunity', ['id' => (int) $item['id']])) ?>"><span class="deadline-day"><?= e(date('d', strtotime($item['application_deadline']))) ?><small><?= e(date('M', strtotime($item['application_deadline']))) ?></small></span><span><strong><?= e($item['title']) ?></strong><small><?= e($item['organization_name'] ?? 'Institution opportunity') ?></small></span></a><?php endforeach; ?></div>
                <?php endif; ?>
            </section>

            <section class="dashboard-resource-panel">
                <div class="resource-panel-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5z"/><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8M8 10h6"/></svg></div>
                <div><span class="eyebrow">CAREER RESOURCES</span><h2>Build your confidence, one step at a time.</h2><p>Explore practical guides for CVs, interviews and workplace readiness.</p></div>
                <a class="button button-outline" href="<?= e(pageUrl('resources')) ?>">Browse career guides <span aria-hidden="true">→</span></a>
            </section>
        </div>

        <section class="dashboard-recommendations">
            <div class="section-heading dashboard-section-heading"><div><span class="eyebrow">MATCHED TO YOUR PROFILE</span><h2>Opportunities to explore</h2><p>Suggestions based on your interests, skills, institution and location preferences.</p></div><a class="text-link" href="<?= e(pageUrl('opportunities')) ?>">See all <span aria-hidden="true">→</span></a></div>
            <?php if ($recommendations !== []): ?><div class="opportunity-grid"><?php foreach ($recommendations as $item): ?><?= opportunityCard($item) ?><?php endforeach; ?></div><?php else: ?><div class="empty-state empty-inline"><strong>Recommendations will improve as your profile grows.</strong><span>Add skills and career interests to your profile, or browse all current opportunities.</span><a class="button button-outline" href="<?= e(pageUrl('profile')) ?>">Update profile</a></div><?php endif; ?>
        </section>
    </div>
</div>
