<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/image.php';

$pdo = db();
$user = current_user();
$isAdmin = $user && $user['role'] === 'admin';
$galleryCategories = ['Awards Ceremony', 'Community Outreach', 'Clinical Training', 'Hospital Projects', 'Staff Wellness', 'Rehabilitation', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        set_flash('error', 'Only admin can manage gallery media.');
        redirect('gallery.php');
    }

    try {
        $action = normalize_text($_POST['action'] ?? 'upload');

        if ($action === 'delete_image') {
            $imageId = (int) ($_POST['image_id'] ?? 0);
            if ($imageId <= 0) {
                throw new InvalidArgumentException('Invalid image selected.');
            }

            $imageRowStmt = $pdo->prepare('SELECT image FROM gallery WHERE id = :id LIMIT 1');
            $imageRowStmt->execute([':id' => $imageId]);
            $imageRow = $imageRowStmt->fetch();
            if (!$imageRow) {
                throw new InvalidArgumentException('Image not found.');
            }

            $deleteImageStmt = $pdo->prepare('DELETE FROM gallery WHERE id = :id');
            $deleteImageStmt->execute([':id' => $imageId]);
            delete_image_file('gallery', (string) $imageRow['image']);

            set_flash('success', 'Image deleted successfully.');
            redirect('gallery.php');
        }

        if ($action === 'delete_video') {
            $videoId = (int) ($_POST['video_id'] ?? 0);
            if ($videoId <= 0) {
                throw new InvalidArgumentException('Invalid video selected.');
            }

            $videoRowStmt = $pdo->prepare('SELECT video FROM event_video WHERE id = :id LIMIT 1');
            $videoRowStmt->execute([':id' => $videoId]);
            $videoRow = $videoRowStmt->fetch();
            if (!$videoRow) {
                throw new InvalidArgumentException('Video not found.');
            }

            $deleteVideoStmt = $pdo->prepare('DELETE FROM event_video WHERE id = :id');
            $deleteVideoStmt->execute([':id' => $videoId]);
            delete_image_file('videos', (string) $videoRow['video']);

            set_flash('success', 'Video deleted successfully.');
            redirect('gallery.php');
        }

        $mediaType = normalize_text($_POST['media_type'] ?? 'image');
        $title = normalize_text($_POST['title'] ?? '');
        $category = normalize_text($_POST['category'] ?? 'Other');
        if (!in_array($category, $galleryCategories, true)) $category = 'Other';
        if ($title === '') {
            throw new InvalidArgumentException('Please enter a title.');
        }

        if ($mediaType === 'video') {
            $videoName = upload_video_clip($_FILES['video'] ?? [], 'videos');
            if ($videoName === null) {
            throw new InvalidArgumentException('Please choose a video file to upload.');
            }

            $videoStmt = $pdo->prepare(
                'INSERT INTO event_video (title, video, uploaded_at) VALUES (:title, :video, :uploaded_at)'
            );
            $videoStmt->execute([
                ':title' => $title,
                ':video' => $videoName,
                ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ]);
            set_flash('success', 'Video added successfully.');
            redirect('gallery.php');
        }

        $imageFiles = $_FILES['image'] ?? [];
        $hasMultipleFiles = isset($imageFiles['name']) && is_array($imageFiles['name']);
        $uploadedCount = 0;

        if ($hasMultipleFiles) {
            foreach ($imageFiles['name'] as $index => $unusedName) {
                $singleFile = [
                    'name' => $imageFiles['name'][$index] ?? '',
                    'type' => $imageFiles['type'][$index] ?? '',
                    'tmp_name' => $imageFiles['tmp_name'][$index] ?? '',
                    'error' => $imageFiles['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $imageFiles['size'][$index] ?? 0,
                ];

                $imageName = upload_and_resize_image($singleFile, 'gallery', 1400, 1400);
                if ($imageName === null) {
                    continue;
                }

                $imageStmt = $pdo->prepare(
                    'INSERT INTO gallery (title, image, category, uploaded_at) VALUES (:title, :image, :category, :uploaded_at)'
                );
                $imageStmt->execute([
                    ':title' => $title,
                    ':image' => $imageName,
                    ':category' => $category,
                    ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ]);
                $uploadedCount++;
            }
        } else {
            $imageName = upload_and_resize_image($imageFiles, 'gallery', 1400, 1400);
            if ($imageName !== null) {
                $imageStmt = $pdo->prepare(
                    'INSERT INTO gallery (title, image, category, uploaded_at) VALUES (:title, :image, :category, :uploaded_at)'
                );
                $imageStmt->execute([
                    ':title' => $title,
                    ':image' => $imageName,
                    ':category' => $category,
                    ':uploaded_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
                ]);
                $uploadedCount = 1;
            }
        }

        if ($uploadedCount === 0) {
            throw new InvalidArgumentException('Please choose an image file to upload.');
        }
        set_flash('success', $uploadedCount === 1
            ? 'Image uploaded to gallery successfully.'
            : $uploadedCount . ' images uploaded to gallery successfully.');
        redirect('gallery.php');
    } catch (Throwable $exception) {
        set_flash('error', $exception->getMessage());
        redirect('gallery.php');
    }
}

$activeCategory = normalize_text((string) ($_GET['category'] ?? 'All'));
if (!in_array($activeCategory, $galleryCategories, true)) $activeCategory = 'All';
$galleryStmt = $pdo->prepare('SELECT id, image, title, category, uploaded_at FROM gallery WHERE (:category_filter = "All" OR category = :category_match) ORDER BY uploaded_at DESC LIMIT 80');
$galleryStmt->execute([':category_filter' => $activeCategory, ':category_match' => $activeCategory]);
$galleryImages = $galleryStmt->fetchAll();
$eventVideos = $pdo->query('SELECT id, video, title, uploaded_at FROM event_video ORDER BY uploaded_at DESC LIMIT 50')->fetchAll();

render_header('Gallery');
?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Event Gallery</h1>
            <p class="text-muted mb-0">Browse event photos and video highlights from FNPH Kaduna.</p>
        </div>
        <nav class="d-flex gap-2" aria-label="Gallery sections">
            <a class="btn btn-outline-primary btn-sm" href="#event-photos">Photos</a>
            <a class="btn btn-outline-primary btn-sm" href="#event-videos">Videos</a>
        </nav>
    </div>
    <?php if ($isAdmin): ?>
        <div class="card card-soft responsive-upload-card">
            <div class="card-body">
                <h2 class="h5 mb-3">Add Media</h2>
                <form method="post" enctype="multipart/form-data" class="row g-3 mb-4">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="media_type" value="image">
                    <div class="col-md-6">
                        <label class="form-label">Picture Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach ($galleryCategories as $categoryOption): ?><option value="<?= e($categoryOption) ?>"><?= e($categoryOption) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6">
                        <label class="form-label">Choose Picture</label>
                        <input type="file" name="image[]" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif" multiple required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Upload Picture</button>
                    </div>
                </form>

                <form method="post" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="media_type" value="video">
                    <div class="col-md-6">
                        <label class="form-label">Video Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Choose Video</label>
                        <input type="file" name="video" class="form-control" accept=".mp4,.webm,.ogv,.mov" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Upload Video</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mb-0">Everyone can view the gallery. Only admin can add new pictures and videos.</div>
    <?php endif; ?>
</section>

<section class="mb-4" id="event-photos">
    <h2 class="section-title">Award ceremonies, projects &amp; hospital life</h2>
    <nav class="gallery-category-filters" aria-label="Filter gallery by category">
        <?php foreach (array_merge(['All'], $galleryCategories) as $categoryOption): ?><a class="gallery-category-chip <?= $activeCategory === $categoryOption ? 'is-active' : '' ?>" href="<?= e(app_url('gallery.php?category=' . rawurlencode($categoryOption))) ?>" <?= $activeCategory === $categoryOption ? 'aria-current="true"' : '' ?>><?= e($categoryOption) ?></a><?php endforeach; ?>
    </nav>
    <?php if ($galleryImages): ?>
        <div class="gallery-reference-grid">
            <?php foreach ($galleryImages as $image): ?>
                <article class="gallery-reference-card">
                    <a href="<?= e(upload_url('gallery', $image['image'])) ?>" class="js-glightbox" data-gallery="event-gallery" data-title="<?= e($image['title']) ?>"><img src="<?= e(upload_url('gallery', $image['image'])) ?>" alt="<?= e($image['title']) ?>" class="gallery-reference-image"></a>
                    <div class="gallery-reference-card-body"><span><?= e(strtoupper($image['category'] ?: 'OTHER')) ?></span><h3><?= e($image['title']) ?></h3><time><?= e((new DateTimeImmutable($image['uploaded_at']))->format('n/j/Y')) ?></time>
                    <?php if ($isAdmin): ?><form method="post" class="mt-2"><input type="hidden" name="action" value="delete_image"><input type="hidden" name="image_id" value="<?= e((string) $image['id']) ?>"><button type="submit" class="btn btn-outline-danger btn-sm w-100 js-confirm" data-confirm="Delete this picture?">Delete Picture</button></form><?php endif; ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No pictures have been added yet.</div>
    <?php endif; ?>
</section>

<section id="event-videos">
    <h2 class="section-title">Event Video Highlights</h2>
    <?php if ($eventVideos): ?>
        <div class="row g-3">
            <?php foreach ($eventVideos as $video): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card card-soft h-100 anim-lift p-2">
                        <video controls preload="metadata" class="event-video-player mb-2">
                            <source src="<?= e(upload_url('videos', $video['video'])) ?>">
                            Your browser does not support video playback.
                        </video>
                        <h3 class="h6 mb-1"><?= e($video['title']) ?></h3>
                        <p class="small text-muted mb-0"><?= e(format_datetime_human($video['uploaded_at'])) ?></p>
                        <?php if ($isAdmin): ?>
                            <form method="post" class="mt-2">
                                <input type="hidden" name="action" value="delete_video">
                                <input type="hidden" name="video_id" value="<?= e((string) $video['id']) ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100 js-confirm" data-confirm="Delete this video?">Delete Video</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary">No videos have been added yet.</div>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
