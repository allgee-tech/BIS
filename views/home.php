<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> A better start to your career</span>
            <h1>Your Future Starts With the <em>Right Opportunity.</em></h1>
            <p>Discover internships, jobs, scholarships, graduate programmes and career development opportunities designed to move your career forward.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="<?= e(pageUrl('opportunities')) ?>">Explore opportunities <span aria-hidden="true">→</span></a>
                <a class="button button-secondary" href="<?= e(pageUrl('register')) ?>">Create your account</a>
            </div>
            <form class="hero-search" method="get" action="<?= e(basePath()) ?>/index.php">
                <input type="hidden" name="page" value="opportunities">
                <label class="sr-only" for="hero-query">Search opportunities</label>
                <input id="hero-query" name="q" type="search" placeholder="Job title, skill or organization">
                <button class="button button-primary" type="submit">Search</button>
            </form>
            <p class="hero-footnote">For students, recent graduates and the organizations that champion them.</p>
        </div>
        <div class="hero-art" aria-label="Opportunity highlights">
            <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
            <div class="hero-art-panel">
                <span class="art-label">YOUR NEXT STEP</span>
                <div class="art-steps"><span class="step-number">01</span><span>Discover your path</span></div>
                <div class="art-steps"><span class="step-number">02</span><span>Build your profile</span></div>
                <div class="art-steps"><span class="step-number">03</span><span>Make your move</span></div>
                <div class="art-decoration"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
            </div>
            <div class="floating-note"><span class="floating-icon">↗</span><span><strong><?= e($stats['opportunities']) ?></strong><small>open opportunities</small></span></div>
        </div>
    </div>
</section>

<?php if ($announcements !== []): ?>
<section class="section announcement-section"><div class="container">
    <div class="section-heading"><div><span class="eyebrow">CAMPUS2CAREER UPDATES</span><h2>Latest announcements</h2></div></div>
    <div class="announcement-list"><?php foreach ($announcements as $announcement): ?><article><h3><?= e($announcement['title']) ?></h3><p><?= e($announcement['body']) ?></p><time><?= e(date('M j, Y', strtotime($announcement['published_at']))) ?></time></article><?php endforeach; ?></div>
</div></section>
<?php endif; ?>

<section class="stats-strip">
    <div class="container stats-grid">
        <div><strong><?= e($stats['opportunities']) ?></strong><span>Open opportunities</span></div>
        <div><strong><?= e($stats['organizations']) ?></strong><span>Organizations</span></div>
        <div><strong><?= e($stats['institutions']) ?></strong><span>Institutions</span></div>
        <div><strong><?= e($stats['students']) ?></strong><span>Talent profiles</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">EXPLORE BY INTEREST</span><h2>Find the opportunity that fits.</h2><p>From your first internship to your next big step, discover what's possible.</p></div><a class="text-link" href="<?= e(pageUrl('opportunities')) ?>">See all opportunities <span aria-hidden="true">→</span></a></div>
        <?php
        $categoryPresentation = [
            'internships' => ['icon' => '✳', 'description' => 'Build practical experience with teams in your field.'],
            'full-time-jobs' => ['icon' => '↗', 'description' => 'Find your next step into a full-time career.'],
            'part-time-jobs' => ['icon' => '◷', 'description' => 'Balance paid work with study and other priorities.'],
            'remote-jobs' => ['icon' => '⌘', 'description' => 'Explore location-flexible roles and teams.'],
            'graduate-programmes' => ['icon' => '✦', 'description' => 'Start strong with structured early-career pathways.'],
            'scholarships' => ['icon' => '◇', 'description' => 'Discover support for your education and ambitions.'],
            'fellowships' => ['icon' => '◎', 'description' => 'Grow your expertise through focused programmes.'],
            'volunteering' => ['icon' => '♡', 'description' => 'Make a difference while developing new skills.'],
            'training-programmes' => ['icon' => '⌁', 'description' => 'Strengthen the skills employers are looking for.'],
            'career-development-programmes' => ['icon' => '↗', 'description' => 'Build confidence and prepare for what comes next.'],
            'practical-projects' => ['icon' => '▧', 'description' => 'Put your knowledge to work on real projects.'],
            'industry-challenges' => ['icon' => '✧', 'description' => 'Solve practical problems alongside industry.'],
            'competitions' => ['icon' => '♢', 'description' => 'Showcase your ideas, creativity and talent.'],
            'other-opportunities' => ['icon' => '◈', 'description' => 'Explore more ways to learn, contribute and grow.'],
        ];
        ?>
        <div class="category-grid">
            <?php foreach ($categories as $index => $category): ?>
                <?php $presentation = $categoryPresentation[$category['slug']] ?? ['icon' => '◈', 'description' => 'Explore new ways to learn, contribute and grow.']; ?>
                <a class="category-card category-tone-<?= e(($index % 4) + 1) ?>" href="<?= e(pageUrl('opportunities', ['category' => $category['slug']])) ?>">
                    <span class="category-symbol" aria-hidden="true"><?= e($presentation['icon']) ?></span>
                    <span class="category-copy"><strong><?= e($category['name']) ?></strong><span class="category-description"><?= e($presentation['description']) ?></span></span>
                    <span class="category-count"><span><strong><?= e($category['opportunity_count']) ?></strong> open</span><span class="category-arrow" aria-hidden="true">→</span></span>
                </a>
            <?php endforeach; ?>
            <?php if ($categories === []): ?><div class="empty-state">Opportunity categories will appear here once they are configured.</div><?php endif; ?>
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">FEATURED</span><h2>Opportunities worth a closer look.</h2><p>Curated, live listings from organizations and institutions.</p></div><a class="text-link" href="<?= e(pageUrl('opportunities')) ?>">Browse all <span aria-hidden="true">→</span></a></div>
        <div class="opportunity-grid">
            <?php foreach ($featured as $opportunity): ?><?= opportunityCard($opportunity) ?><?php endforeach; ?>
        </div>
        <?php if ($featured === []): ?><div class="empty-state empty-inline"><strong>New opportunities are on the way.</strong><span>Check back soon or browse the full opportunity board.</span><a class="button button-outline" href="<?= e(pageUrl('opportunities')) ?>">Explore opportunities</a></div><?php endif; ?>
    </div>
</section>

<section class="section section-soft">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">A GROWING NETWORK</span><h2>Built to connect campuses and careers.</h2><p>Institutions on the platform are listed from current, active records.</p></div><a class="text-link" href="<?= e(pageUrl('institution-request')) ?>">Request an institution <span aria-hidden="true">→</span></a></div>
        <div class="institution-strip"><?php foreach ($institutions as $institution): ?><article><span class="institution-monogram"><?= e(mb_substr($institution['name'], 0, 1)) ?></span><span><strong><?= e($institution['name']) ?></strong><small><?= e($institution['location'] ?: $institution['country']) ?></small></span></article><?php endforeach; ?><?php if ($institutions === []): ?><div class="empty-small">Institutions will appear here as they join the platform.</div><?php endif; ?></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">HOW IT WORKS</span><h2>Make your next move, step by step.</h2><p>A simpler bridge between the classroom and your career.</p></div></div>
        <div class="steps-grid">
            <article class="step-card"><span class="step-index">01</span><h3>Create your account</h3><p>Join Campus2Career as a student or recent graduate and open the door to new possibilities.</p></article>
            <article class="step-card"><span class="step-index">02</span><h3>Build your profile</h3><p>Share your education, skills and interests so employers can see your potential.</p></article>
            <article class="step-card"><span class="step-index">03</span><h3>Discover opportunities</h3><p>Find relevant internships, jobs, scholarships and programmes in one place.</p></article>
            <article class="step-card"><span class="step-index">04</span><h3>Apply and track progress</h3><p>Apply, follow your progress and build the experience that takes you further.</p></article>
        </div>
    </div>
</section>

<section class="section section-soft">
    <div class="container audience-grid">
        <article class="audience-card audience-student"><span class="eyebrow">FOR STUDENTS & GRADUATES</span><h2>Your ambition deserves a clear path.</h2><p>Build a professional profile, find real openings and keep your applications moving.</p><a class="button button-primary" href="<?= e(pageUrl('register')) ?>">Join as a student <span aria-hidden="true">→</span></a></article>
        <article class="audience-card audience-employer"><span class="eyebrow">FOR EMPLOYERS & PARTNERS</span><h2>Meet talent ready to make an impact.</h2><p>Share opportunities with emerging talent from institutions across Ghana.</p><a class="button button-secondary" href="<?= e(pageUrl('register', ['type' => 'employer'])) ?>">Join as an organization <span aria-hidden="true">→</span></a></article>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">CAREER TOOLKIT</span><h2>Skills for the journey ahead.</h2><p>Practical guidance to help you present your strengths and prepare for what's next.</p></div><a class="text-link" href="<?= e(pageUrl('resources')) ?>">Visit the resource hub <span aria-hidden="true">→</span></a></div>
        <div class="resource-grid">
            <?php foreach ($resources as $resource): ?><article class="resource-card"><span class="resource-category"><?= e($resource['category']) ?></span><h3><a href="<?= e(pageUrl('resource', ['id' => (int) $resource['id']])) ?>"><?= e($resource['title']) ?></a></h3><p><?= e($resource['summary']) ?></p><a class="text-link" href="<?= e(pageUrl('resource', ['id' => (int) $resource['id']])) ?>">Read guide <span aria-hidden="true">→</span></a></article><?php endforeach; ?>
        </div>
        <?php if ($resources === []): ?><div class="empty-state empty-inline"><strong>Career guides are being prepared.</strong><span>Published resources will appear here when available.</span></div><?php endif; ?>
    </div>
</section>

<section class="cta-band"><div class="container cta-band-inner"><div><span class="eyebrow">YOUR NEXT CHAPTER STARTS HERE</span><h2>Ready to take the next step?</h2><p>Create your profile and start exploring opportunities tailored to your ambitions.</p></div><a class="button button-white" href="<?= e(pageUrl('register')) ?>">Get started today <span aria-hidden="true">→</span></a></div></section>
