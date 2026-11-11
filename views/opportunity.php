<section class="section">
    <div class="container detail-layout">
        <article class="detail-main">
            <a class="back-link" href="<?= e(pageUrl('opportunities')) ?>">← Back to opportunities</a>
            <div class="detail-heading"><span class="category-pill"><?= e($opportunity['category_name']) ?></span><h1><?= e($opportunity['title']) ?></h1><p class="organization-name"><?= e($opportunity['organization_name'] ?? 'Institution opportunity') ?></p></div>
            <div class="detail-meta"><span><?= e($opportunity['location'] ?: 'Location not specified') ?></span><span><?= e(str_replace('_', ' ', ucfirst($opportunity['work_arrangement']))) ?></span><?php if ($opportunity['compensation']): ?><span><?= e($opportunity['compensation']) ?></span><?php endif; ?></div>
            <?php foreach (['description' => 'About this opportunity','responsibilities' => 'Responsibilities','requirements' => 'Requirements','qualifications' => 'Qualifications','required_skills' => 'Skills'] as $field => $label): if (!empty($opportunity[$field])): ?>
                <section class="detail-section"><h2><?= e($label) ?></h2><div class="prose"><?= nl2br(e($opportunity[$field])) ?></div></section>
            <?php endif; endforeach; ?>
        </article>
        <aside class="detail-sidebar">
            <div class="panel apply-panel"><h2>Application details</h2><dl class="detail-list">
                <div><dt>Deadline</dt><dd><?= $opportunity['application_deadline'] ? e(date('M j, Y', strtotime($opportunity['application_deadline']))) : 'Not specified' ?></dd></div>
                <div><dt>Start date</dt><dd><?= $opportunity['start_date'] ? e(date('M j, Y', strtotime($opportunity['start_date']))) : 'Not specified' ?></dd></div>
                <div><dt>Duration</dt><dd><?= e($opportunity['duration'] ?: 'Not specified') ?></dd></div>
                <div><dt>Organization status</dt><dd><?= e(ucfirst($opportunity['verification_status'] ?? 'unverified')) ?></dd></div>
            </dl>
            <?php if ($opportunity['application_method'] === 'external'): ?><a class="button button-primary button-full" href="<?= e($opportunity['application_url']) ?>" target="_blank" rel="noopener noreferrer">Apply on organization site <span aria-hidden="true">↗</span></a>
            <?php elseif ($currentUser === null): ?><a class="button button-primary button-full" href="<?= e(pageUrl('login')) ?>">Sign in to apply</a>
            <?php elseif ($currentUser['role'] !== 'student'): ?><p class="muted small">Student accounts can apply to this opportunity.</p>
            <?php elseif ($hasApplied): ?><span class="button button-disabled button-full">Application submitted</span>
            <?php else: ?><a class="button button-primary button-full" href="<?= e(pageUrl('apply', ['id' => (int) $opportunity['id']])) ?>">Apply now</a><?php endif; ?>
            <?php if ($currentUser !== null && $currentUser['role'] === 'student'): ?><form method="post" action="<?= e(pageUrl('opportunity', ['id' => (int) $opportunity['id']])) ?>"><?= formToken() ?><input type="hidden" name="action" value="toggle_save"><input type="hidden" name="opportunity_id" value="<?= e($opportunity['id']) ?>"><button class="button button-outline button-full" type="submit"><?= $isSaved ? 'Remove from saved' : 'Save opportunity' ?></button></form><?php endif; ?>
            <?php if ($currentUser === null || in_array($currentUser['role'], ['student','employer'], true)): ?><details class="report-details"><summary>Report this listing</summary><form class="form-stack" method="post" action="<?= e(pageUrl('opportunity', ['id' => (int) $opportunity['id']])) ?>"><?= formToken() ?><input type="hidden" name="action" value="report_opportunity"><input type="hidden" name="opportunity_id" value="<?= e($opportunity['id']) ?>"><label class="field"><span>Reason for reporting</span><textarea name="reason" rows="3" maxlength="1000" required></textarea></label><button class="button button-text" type="submit">Send report</button></form></details><?php endif; ?>
            </div>
        </aside>
    </div>
</section>
<?php if ($similar !== []): ?><section class="section section-soft"><div class="container"><div class="section-heading"><div><span class="eyebrow">KEEP EXPLORING</span><h2>Similar opportunities</h2></div></div><div class="opportunity-grid"><?php foreach ($similar as $item): ?><?= opportunityCard($item) ?><?php endforeach; ?></div></div></section><?php endif; ?>
