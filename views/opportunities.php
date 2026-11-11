<section class="page-hero page-hero-compact">
    <div class="container"><span class="eyebrow">YOUR NEXT MOVE</span><h1>Explore opportunities</h1><p>Find internships, jobs, scholarships and programmes that match your goals.</p></div>
</section>
<section class="section">
    <div class="container">
        <form class="filter-panel" method="get" action="<?= e(basePath()) ?>/index.php">
            <input type="hidden" name="page" value="opportunities">
            <label class="field filter-search"><span>Search</span><input name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="Title, organization or skill"></label>
            <label class="field"><span>Category</span><select name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= e($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Location</span><input name="location" value="<?= e($filters['location']) ?>" placeholder="e.g. Accra"></label>
            <label class="field"><span>Work arrangement</span><select name="work"><option value="">Any arrangement</option><?php foreach (['remote' => 'Remote','hybrid' => 'Hybrid','on_site' => 'On-site'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $filters['work'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Compensation</span><select name="compensated"><option value="">Any</option><option value="yes" <?= $filters['compensated'] === 'yes' ? 'selected' : '' ?>>Compensation listed</option></select></label>
            <label class="field"><span>Deadline</span><select name="deadline"><option value="">Any time</option><option value="7" <?= $filters['deadline'] === '7' ? 'selected' : '' ?>>Next 7 days</option><option value="30" <?= $filters['deadline'] === '30' ? 'selected' : '' ?>>Next 30 days</option></select></label>
            <label class="field"><span>Sort by</span><select name="sort"><option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option><option value="deadline" <?= $filters['sort'] === 'deadline' ? 'selected' : '' ?>>Closing soon</option></select></label>
            <div class="filter-actions"><button class="button button-primary" type="submit">Apply filters</button><a class="button button-outline" href="<?= e(pageUrl('opportunities')) ?>">Clear</a></div>
        </form>
        <div class="results-heading"><p><strong><?= e($total) ?></strong> open opportunity<?= $total === 1 ? '' : 'ies' ?></p><?php if ($currentUser !== null && $currentUser['role'] === 'student'): ?><a href="<?= e(pageUrl('dashboard')) ?>">View your matches <span aria-hidden="true">→</span></a><?php endif; ?></div>
        <?php if ($opportunities !== []): ?>
            <div class="opportunity-grid"><?php foreach ($opportunities as $opportunity): ?><?= opportunityCard($opportunity) ?><?php endforeach; ?></div>
            <?php if ($pages > 1): ?><nav class="pagination" aria-label="Opportunity pages"><?php for ($number = 1; $number <= $pages; $number++): $query = $filters; $query['page_no'] = $number; ?><a class="<?= $number === $pageNo ? 'current' : '' ?>" href="<?= e(pageUrl('opportunities', $query)) ?>" aria-current="<?= $number === $pageNo ? 'page' : 'false' ?>"><?= e($number) ?></a><?php endfor; ?></nav><?php endif; ?>
        <?php else: ?>
            <div class="empty-state empty-results"><span class="empty-icon">⌕</span><h2>No open opportunities found</h2><p>Try broadening your search or clearing one of the filters.</p><a class="button button-outline" href="<?= e(pageUrl('opportunities')) ?>">Clear filters</a></div>
        <?php endif; ?>
    </div>
</section>
