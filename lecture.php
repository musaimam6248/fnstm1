<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/image.php';

$pdo = db();
$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'Only admin can manage lectures.');
        redirect('lecture.php');
    }

    try {
        $action = normalize_text((string) ($_POST['action'] ?? 'upload'));
        if ($action === 'delete') {
            $lectureId = (int) ($_POST['lecture_id'] ?? 0);
            if ($lectureId <= 0) {
                throw new InvalidArgumentException('Invalid lecture selected.');
            }

            $lectureStmt = $pdo->prepare('SELECT file_name FROM lecture WHERE id = :id LIMIT 1');
            $lectureStmt->execute([':id' => $lectureId]);
            $lecture = $lectureStmt->fetch();
            if (!$lecture) {
                throw new RuntimeException('Lecture not found.');
            }

            $pdo->prepare('DELETE FROM lecture WHERE id = :id')->execute([':id' => $lectureId]);
            delete_image_file('lectures', $lecture['file_name']);
            set_flash('success', 'Lecture removed successfully.');
            redirect('lecture.php');
        }

        if ($action !== 'upload') {
            throw new InvalidArgumentException('Unsupported lecture action.');
        }

        $title = normalize_text($_POST['title'] ?? '');
        $presenter = normalize_text($_POST['presenter'] ?? '');
        $specialty = normalize_text($_POST['specialty'] ?? 'General Psychiatry');
        if ($title === '' || strlen($title) > 180) {
            throw new InvalidArgumentException('Please enter a lecture title of up to 180 characters.');
        }
        if (strlen($presenter) > 180 || strlen($specialty) > 100) {
            throw new InvalidArgumentException('Presenter or specialty text is too long.');
        }

        $fileName = upload_lecture_file($_FILES['lecture_file'] ?? [], 'lectures');
        if ($fileName === null) {
            throw new InvalidArgumentException('Please choose a lecture file to upload.');
        }

        $stmt = $pdo->prepare('INSERT INTO lecture (title, file_name, presenter, specialty, uploaded_at) VALUES (:title, :file_name, :presenter, :specialty, :uploaded_at)');
        $stmt->execute([
            ':title' => $title,
            ':file_name' => $fileName,
            ':presenter' => $presenter,
            ':specialty' => $specialty,
            ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        set_flash('success', 'Lecture uploaded successfully.');
        redirect('lecture.php');
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        redirect('lecture.php');
    }
}

$search = normalize_text((string) ($_GET['q'] ?? ''));
$specialtyFilter = normalize_text((string) ($_GET['specialty'] ?? ''));
$specialties = $pdo->query("SELECT DISTINCT specialty FROM lecture WHERE trim(specialty) <> '' ORDER BY specialty")->fetchAll(PDO::FETCH_COLUMN);
$lectureSql = 'SELECT id, title, file_name, presenter, specialty, uploaded_at FROM lecture WHERE 1 = 1';
$lectureParams = [];
if ($search !== '') {
    $lectureSql .= ' AND (title LIKE :search OR presenter LIKE :presenter_search)';
    $lectureParams[':search'] = '%' . $search . '%';
    $lectureParams[':presenter_search'] = '%' . $search . '%';
}
if ($specialtyFilter !== '') {
    $lectureSql .= ' AND specialty = :specialty';
    $lectureParams[':specialty'] = $specialtyFilter;
}
$lectureSql .= ' ORDER BY uploaded_at DESC LIMIT 100';
$lectureStmt = $pdo->prepare($lectureSql);
$lectureStmt->execute($lectureParams);
$lectures = $lectureStmt->fetchAll();

render_header('Lecture');
?>
<section class="lecture-page mb-4">
    <header class="reference-page-heading">
        <div><p class="reference-kicker">Learning and development</p><h1>Lectures & Resources</h1><p>Access, download, and review specialized psychiatric grand round papers, CME training slides, and clinical research documentation.</p></div>
    </header>
    <?php if ($isAdmin): ?>
        <div class="card card-soft responsive-upload-card">
            <div class="card-body">
                <h2 class="h5 mb-3">Add Lecture</h2>
                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="action" value="upload">
                    <div class="col-md-6">
                        <label class="form-label">Lecture Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6"><label class="form-label">Presenter / Faculty</label><input type="text" name="presenter" class="form-control" placeholder="Presenter name and credentials"></div>
                    <div class="col-md-6"><label class="form-label">Specialty</label><input type="text" name="specialty" class="form-control" placeholder="e.g. Clinical Adult Psychiatry" value="General Psychiatry"></div>
                    <div class="col-md-6">
                        <label class="form-label">Lecture File</label>
                        <input type="file" name="lecture_file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Upload Lecture</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mb-0">Everyone can view and download lectures. Only admin can add new files.</div>
    <?php endif; ?>
</section>

<section class="lecture-results">
    <form method="get" class="lecture-filter-panel mb-4">
        <label class="visually-hidden" for="lecture-search">Search lectures</label>
        <input id="lecture-search" type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search by lecture title or presenter...">
        <label for="lecture-specialty">Specialty:</label>
        <select id="lecture-specialty" name="specialty" class="form-select"><option value="">All Specialties</option><?php foreach ($specialties as $specialty): ?><option value="<?= e((string) $specialty) ?>" <?= $specialtyFilter === $specialty ? 'selected' : '' ?>><?= e((string) $specialty) ?></option><?php endforeach; ?></select>
        <button class="btn btn-outline-primary" type="submit">Search</button>
    </form>
    <?php if ($lectures): ?>
        <div class="table-wrap">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Lecture Title &amp; Faculty Presenter</th><th>Specialty</th><th>Format</th><th>Size</th><th>Date Uploaded</th><th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($lectures as $lecture): ?>
                    <tr>
                        <?php $lecturePath = BASE_PATH . '/uploads/lectures/' . basename((string) $lecture['file_name']); $fileSize = is_file($lecturePath) ? filesize($lecturePath) : false; $extension = strtoupper(pathinfo((string) $lecture['file_name'], PATHINFO_EXTENSION)); ?>
                        <td><strong><?= e($lecture['title']) ?></strong><br><small class="text-muted"><?= e($lecture['presenter'] ?: 'FNPH Faculty') ?></small></td>
                        <td><?= e($lecture['specialty'] ?: 'General Psychiatry') ?></td>
                        <td><span class="lecture-format-badge"><?= e($extension ?: 'FILE') ?></span></td>
                        <td><?= $fileSize !== false ? e(number_format($fileSize / 1048576, 1) . ' MB') : '—' ?></td>
                        <td><?= e((new DateTimeImmutable($lecture['uploaded_at']))->format('m/d/Y')) ?></td>
                        <td class="text-nowrap"><a class="btn btn-outline-primary btn-sm" href="<?= e(upload_url('lectures', $lecture['file_name'])) ?>" target="_blank" rel="noopener">Download</a>
                        <?php if ($isAdmin): ?>
                                <form method="post" class="d-inline ms-1" onsubmit="return confirm('Remove this lecture permanently?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="lecture_id" value="<?= e((string) $lecture['id']) ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                </form>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No lectures uploaded yet.</div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
