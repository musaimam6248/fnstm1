<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$pdo = db();

$currentWinnerStmt = $pdo->query(
    "SELECT w.id, w.month, w.year, w.reasons,
            w.image AS winner_photo,
            s.name AS staff_name, s.photo AS staff_photo, s.rank AS staff_rank,
            d.name AS department_name
     FROM winner w
     INNER JOIN staff s ON s.id = w.staff_id
     INNER JOIN department d ON d.id = s.department_id
     ORDER BY w.year DESC, w.month DESC
     LIMIT 1"
);
$currentWinner = $currentWinnerStmt->fetch() ?: null;
$currentWinnerBackgroundImage = $currentWinner
    ? ($currentWinner['winner_photo'] ? upload_url('winners', $currentWinner['winner_photo']) : upload_url('staff', $currentWinner['staff_photo']))
    : null;

$pastWinnerSql = "SELECT w.id, w.month, w.year, w.reasons,
                         w.image AS winner_photo,
                         s.name AS staff_name, s.photo AS staff_photo, s.rank AS staff_rank,
                         d.name AS department_name
                  FROM winner w
                  INNER JOIN staff s ON s.id = w.staff_id
                  INNER JOIN department d ON d.id = s.department_id";
$pastWinnerParams = [];
if ($currentWinner) {
    $pastWinnerSql .= " WHERE w.id <> :current_id";
    $pastWinnerParams[':current_id'] = (int) $currentWinner['id'];
}
$pastWinnerSql .= " ORDER BY w.year DESC, w.month DESC LIMIT 8";
$pastWinnerStmt = $pdo->prepare($pastWinnerSql);
$pastWinnerStmt->execute($pastWinnerParams);
$pastWinners = $pastWinnerStmt->fetchAll();

$committeeStmt = $pdo->query('SELECT id, name, role, photo FROM committee ORDER BY created_at DESC');
$committeeMembers = $committeeStmt->fetchAll();

$departmentInfo = $pdo->query('SELECT vision, mission, objectives, image FROM department_info ORDER BY updated_at DESC LIMIT 1')->fetch();
if (!empty($departmentInfo['image'])) {
    $departmentInfoImage = upload_url('gallery', $departmentInfo['image']);
} else {
    $departmentInfoImage = app_url('assets/img/placeholder.svg');
}
$submissionSchedule = $pdo->query(
    'SELECT opens_at, closes_at, is_enabled, note, updated_at FROM submission_schedule WHERE id = 1 LIMIT 1'
)->fetch();
$scheduleEnabled = $submissionSchedule ? ((int) $submissionSchedule['is_enabled'] === 1) : false;
$scheduleState = get_submission_window_state(
    $submissionSchedule['opens_at'] ?? null,
    $submissionSchedule['closes_at'] ?? null,
    $scheduleEnabled
);
$homepageSettings = $pdo->query('SELECT homepage_background_color FROM homepage_settings WHERE id = 1 LIMIT 1')->fetch() ?: [];
$homepageBackgroundImages = $pdo->query('SELECT image FROM homepage_background_image ORDER BY sort_order ASC, id ASC LIMIT 10')->fetchAll();
$homepageBackgroundUrls = array_map(static fn($image) => upload_url('gallery', $image['image']), $homepageBackgroundImages);
$homepageBackgroundColor = normalize_hex_color((string) ($homepageSettings['homepage_background_color'] ?? ''));
$homepageBackgroundOverlay = hex_color_with_alpha($homepageBackgroundColor);
render_header('Home');
?>
<div data-home-page="true" class="homepage-background" data-background-images="<?= e((string) json_encode($homepageBackgroundUrls, JSON_UNESCAPED_SLASHES)) ?>" style="--homepage-background-color: <?= e($homepageBackgroundColor ?? 'transparent') ?>; --homepage-background-overlay: <?= e($homepageBackgroundOverlay ?? 'rgba(244, 246, 249, 0.84)') ?>;<?php if ($homepageBackgroundUrls): ?> --homepage-background-image: url('<?= e($homepageBackgroundUrls[0]) ?>');<?php endif; ?>">
<section class="mb-4 reveal-on-scroll schedule-window-zone">
    <div class="card card-soft anim-lift submission-window-card schedule-state-<?= e($scheduleState) ?>">
        <span class="window-glow window-glow-a" aria-hidden="true"></span>
        <span class="window-glow window-glow-b" aria-hidden="true"></span>
        <div class="card-body submission-window-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Federal Neuro-Psychiatric Hospital, Kaduna · Staff Excellence Portal</h2>
                <span class="badge text-bg-<?= e(get_submission_window_badge_class($scheduleState)) ?> submission-window-badge">
                    <span class="status-ping" aria-hidden="true"></span>
                    Nomination Window: <?= e(strtoupper(get_submission_window_label($scheduleState))) ?>
                </span>
            </div>
            <p class="mb-1"><strong>Opens:</strong> <?= e(format_datetime_human($submissionSchedule['opens_at'] ?? null)) ?></p>
            <p class="mb-1"><strong>Closes:</strong> <?= e(format_datetime_human($submissionSchedule['closes_at'] ?? null)) ?></p>
            <?php if (!empty($submissionSchedule['note']) && $submissionSchedule['note'] !== 'Default monthly submission window'): ?>
                <p class="mb-0 text-muted small"><strong>Note:</strong> <?= e($submissionSchedule['note']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="hero-card winner-hero-card p-4 p-lg-5 mb-4 reveal-on-scroll" data-reveal="right" id="current-winner" aria-labelledby="winner-hero-title">
    <svg class="winner-hero-watermark" viewBox="0 0 180 220" fill="none" aria-hidden="true">
        <path d="M48 12h84v36c0 34-16 58-42 68C64 106 48 82 48 48V12Z" stroke="currentColor" stroke-width="8"/>
        <path d="M48 30H17v18c0 28 13 44 39 48M132 30h31v18c0 28-13 44-39 48M90 116v40m-35 38 35-21 35 21-8-40H63l-8 40Z" stroke="currentColor" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="m90 31 8 17 19 3-14 13 3 19-16-9-16 9 3-19-14-13 19-3 8-17Z" fill="currentColor"/>
    </svg>
    <div class="row align-items-center g-4 home-stagger position-relative">
        <div class="col-lg-5">
            <div class="winner-frame-wrap">
                <div class="winner-ribbon"><span aria-hidden="true">✦</span> Staff of the Month</div>
                <div class="winner-frame">
                    <div class="winner-frame-inner">
                        <img src="<?= e($currentWinnerBackgroundImage ?? app_url('assets/img/placeholder.svg')) ?>"
                             alt="<?= e($currentWinner ? 'Staff of the Month: ' . $currentWinner['staff_name'] : 'Staff of the Month portrait') ?>"
                             class="winner-photo">
                        <?php if ($currentWinner): ?>
                            <div class="winner-photo-overlay" aria-hidden="true"></div>
                            <div class="winner-photo-caption"><span><?= e(strtoupper(format_month_year((int) $currentWinner['month'], (int) $currentWinner['year']))) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <p class="winner-hero-eyebrow mb-3">Federal Neuro-Psychiatric Hospital, Kaduna <span aria-hidden="true">·</span> Award of Excellence</p>
            <?php if ($currentWinner): ?>
                <h1 class="winner-hero-title mb-2" id="winner-hero-title"><?= e($currentWinner['staff_name']) ?></h1>
                <p class="winner-hero-meta mb-3"><?= e($currentWinner['staff_rank']) ?><span aria-hidden="true"> · </span><?= e($currentWinner['department_name']) ?></p>
                <div class="winner-hero-quote">
                    <h2 class="winner-hero-quote-label">Official Committee Citation &amp; Justification</h2>
                    <p class="mb-0"><?= e($currentWinner['reasons']) ?></p>
                </div>
                <div class="winner-hero-actions mt-3">
                    <a class="btn winner-hero-past-link" href="#past-winners">View Past Laureates</a>
                    <a class="winner-hero-gallery-link" href="<?= e(app_url('gallery.php')) ?>">Award Ceremony Photos <span aria-hidden="true">→</span></a>
                </div>
            <?php else: ?>
                <h1 class="winner-hero-title mb-2" id="winner-hero-title">Celebrating Excellence</h1>
                <p class="winner-hero-meta mb-0">No monthly winner has been selected yet. This spotlight will feature the latest laureate once an award is announced.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell committee-section" data-reveal="left" aria-labelledby="committee-title">
    <div class="committee-section-heading">
        <div>
            <h2 class="section-title mb-1" id="committee-title">Award &amp; Honours Committee</h2>
            <p class="committee-section-subtitle mb-0">Distinguished hospital leadership guiding transparent nominations and merit awards.</p>
        </div>
        <span class="committee-member-count"><?= e((string) count($committeeMembers)) ?> Committee <?= count($committeeMembers) === 1 ? 'Member' : 'Members' ?></span>
    </div>
    <?php if ($committeeMembers): ?>
        <div class="committee-grid home-stagger">
            <?php foreach ($committeeMembers as $member): ?>
                <?php
                $committeePhotoName = basename((string) ($member['photo'] ?? ''));
                $committeePhotoPath = UPLOAD_PATH . '/committee/' . $committeePhotoName;
                $committeePhotoUrl = $committeePhotoName !== '' && is_file($committeePhotoPath)
                    ? upload_url('committee', $committeePhotoName)
                    : app_url('assets/img/placeholder.svg');
                ?>
                <article class="committee-card card card-soft anim-lift">
                    <div class="committee-photo-wrap">
                        <img src="<?= e($committeePhotoUrl) ?>" alt="Portrait of <?= e($member['name']) ?>" class="committee-photo">
                    </div>
                    <div class="committee-card-body">
                        <h3 class="committee-card-name"><?= e($member['name']) ?></h3>
                        <p class="committee-card-role mb-0"><?= e($member['role']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">Committee list is currently empty.</div>
    <?php endif; ?>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell" id="past-winners">
    <h2 class="section-title">Past Winners</h2>
    <?php if ($pastWinners): ?>
        <div id="pastWinnersCarousel" class="carousel slide home-stagger" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach ($pastWinners as $index => $winner): ?>
                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                        <div class="card card-soft anim-lift">
                            <div class="card-body p-3 p-md-4">
                                <div class="past-winner-profile">
                                    <div class="past-winner-frame">
                                        <div class="past-winner-frame-inner">
                                            <img src="<?= e($winner['winner_photo'] ? upload_url('winners', $winner['winner_photo']) : upload_url('staff', $winner['staff_photo'])) ?>" alt="<?= e($winner['staff_name']) ?>" class="winner-photo">
                                        </div>
                                    </div>
                                    <div class="past-winner-copy">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                            <span class="badge text-bg-warning">Winner Spotlight</span>
                                            <span class="past-winner-date"><?= e(format_month_year((int) $winner['month'], (int) $winner['year'])) ?></span>
                                        </div>
                                        <h3 class="h4 mb-1"><?= e($winner['staff_name']) ?></h3>
                                        <p class="past-winner-meta mb-3"><?= e($winner['staff_rank']) ?>, <?= e($winner['department_name']) ?></p>
                                        <div class="past-winner-reason">
                                            <p class="mb-0"><?= e($winner['reasons']) ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#pastWinnersCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#pastWinnersCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No past winners yet.</div>
    <?php endif; ?>
</section>

<section class="mb-5 reveal-on-scroll home-section-shell">
    <h2 class="section-title">Department Information</h2>
    <div class="row g-3 home-stagger">
        <div class="col-md-4">
            <div class="card card-soft h-100 anim-lift dept-info-card">
                <div class="card-body dept-info-card-body">
                    <div class="dept-info-image-slot">
                        <img src="<?= e($departmentInfoImage) ?>" alt="Vision illustration" class="dept-info-image">
                    </div>
                    <div class="dept-info-copy">
                        <h3 class="h5 mb-2">Vision</h3>
                        <p class="mb-0"><?= e($departmentInfo['vision'] ?? 'Not set') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-soft h-100 anim-lift dept-info-card">
                <div class="card-body dept-info-card-body">
                    <div class="dept-info-image-slot">
                        <img src="<?= e($departmentInfoImage) ?>" alt="Mission illustration" class="dept-info-image">
                    </div>
                    <div class="dept-info-copy">
                        <h3 class="h5 mb-2">Mission</h3>
                        <p class="mb-0"><?= e($departmentInfo['mission'] ?? 'Not set') ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-soft h-100 anim-lift dept-info-card">
                <div class="card-body dept-info-card-body">
                    <div class="dept-info-image-slot">
                        <img src="<?= e($departmentInfoImage) ?>" alt="Objectives illustration" class="dept-info-image">
                    </div>
                    <div class="dept-info-copy">
                        <h3 class="h5 mb-2">Objectives</h3>
                        <p class="mb-0"><?= e($departmentInfo['objectives'] ?? 'Not set') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="how-to-use" class="mb-4 reveal-on-scroll home-section-shell" data-reveal="right">
    <div class="card card-soft border-0 shadow-sm">
        <div class="card-body p-4">
            <h2 class="section-title mb-3">How to use the portal</h2>
            <div class="row g-3 home-stagger">
                <div class="col-md-4">
                    <div class="info-step-card h-100">
                        <span class="info-step-number">1</span>
                        <h3 class="h6 mt-2">Sign in or register a department</h3>
                        <p class="mb-0 text-muted">Use the login page for access, or register a department if you are new.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-step-card h-100">
                        <span class="info-step-number">2</span>
                        <h3 class="h6 mt-2">Check the submission window</h3>
                        <p class="mb-0 text-muted">Nominations are accepted only during the open period shown above.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-step-card h-100">
                        <span class="info-step-number">3</span>
                        <h3 class="h6 mt-2">Explore winners and updates</h3>
                        <p class="mb-0 text-muted">Review past winners, gallery items, videos, and department information anytime.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="hero-card hero-intro-card p-4 p-lg-5 mb-4 reveal-on-scroll home-section-shell" data-reveal="left">
    <span class="hero-orb hero-orb-a" aria-hidden="true"></span>
    <span class="hero-orb hero-orb-b" aria-hidden="true"></span>
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <span class="badge text-bg-primary mb-2">Welcome</span>
            <h1 class="display-6 mb-3">A simple portal for recognition, submissions, and department updates.</h1>
            <p class="lead mb-3">
                View winners, submit staff of the month nominations, browse gallery highlights, and read department information in one place.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="<?= e(app_url('login.php')) ?>">Login to Continue</a>
                <a class="btn btn-outline-primary" href="#how-it-works">How It Works</a>
                <a class="btn btn-outline-secondary" href="<?= e(app_url('gallery.php')) ?>">View Gallery</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card card-soft border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Who can use this portal?</h2>
                    <ul class="list-unstyled mb-0 portal-audience-list">
                        <li>Admin: manage winners, schedules, reports, and content.</li>
                        <li>Departments: submit nominations and review messages.</li>
                        <li>Everyone: explore winners, gallery, videos, and department vision.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
</div>
<?php
render_footer();
