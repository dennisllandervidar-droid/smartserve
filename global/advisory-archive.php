<?php
// The calling role page has already checked the session.
$archiveRole = [1=>'admin', 2=>'office', 3=>'student'][(int)$_SESSION['access']];
$isAdmin = $archiveRole === 'admin';
$archiveRows = $model->getArchivedAdvisories((int)$_SESSION['sess'], $isAdmin);
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Archived Advisories | SmartServe</title>
<?php require_once __DIR__ . '/form-assets.php'; ?>
<link rel="icon" href="../assets/img/icon.png">
<link rel="stylesheet" href="../dashboard/assets/css/assets.css">
<link rel="stylesheet" href="../dashboard/assets/css/typography.css">
<link rel="stylesheet" href="../dashboard/assets/css/style.css">
<link rel="stylesheet" href="../dashboard/assets/css/dashboard.css">
<link rel="stylesheet" href="../assets/css/advisories.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/advisories.css'); ?>">
</head><body class="ttr-opened-sidebar ttr-pinned-sidebar ss-advisories">
<?php $page='announcements'; $secondnav='announcements'; include __DIR__ . '/../' . $archiveRole . '/main/admin-nav.php'; ?>
<main class="ttr-wrapper"><div class="container-fluid">
    <div class="db-breadcrumb">
        <h4 class="breadcrumb-title"><i class="ti ti-announcement" aria-hidden="true"></i> Advisories</h4>
        <ul class="db-breadcrumb-list"><li>Archived Advisories</li></ul>
    </div>
    <div class="row">
    <?php if (!$archiveRows): ?>
        <div class="ss-empty-advisories"><h5>No archived advisories yet</h5><p>Older advisories will appear here when they are archived.</p></div>
    <?php endif; ?>
    <?php foreach ($archiveRows as $archive): ?>
        <div class="col-lg-12 m-b30">
            <article class="advisory-card">
                <div class="row no-gutters">
                    <?php if (!empty($archive['image_unique'])): ?>
                    <div class="col-md-5">
                        <a href="../assets/img/announcement/<?php echo htmlspecialchars($archive['image_unique'], ENT_QUOTES); ?>.jpg" target="_blank" rel="noopener">
                            <img src="../assets/img/announcement/<?php echo htmlspecialchars($archive['image_unique'], ENT_QUOTES); ?>.jpg" class="advisory-img" alt="Archived advisory attachment" loading="lazy">
                        </a>
                    </div>
                    <?php endif; ?>
                    <div class="<?php echo empty($archive['image_unique']) ? 'col-12' : 'col-md-7'; ?>">
                        <div class="card-body d-flex flex-column h-100">
                            <div><span class="ss-archived-badge"><i class="ti-archive" aria-hidden="true"></i> Archived</span></div>
                            <h4 class="m-b10"><?php echo htmlspecialchars($archive['title']); ?></h4>
                            <div class="ss-archive-meta">
                                <span>Published <?php echo date('M d, Y', strtotime($archive['published_at'] ?? $archive['date'])); ?></span>
                                <span><?php echo !empty($archive['archived_at']) ? 'Archived ' . date('M d, Y', strtotime($archive['archived_at'])) : 'Manually archived'; ?></span>
                                <span><?php echo ($archive['archive_reason'] ?? '') === 'expired' ? 'One-month expiration' : 'Manual archive'; ?></span>
                            </div>
                            <div class="flex-grow-1 m-b20"><?php echo nl2br(htmlspecialchars(trim(strip_tags($archive['details'])))); ?></div>
                            <div class="action-buttons pt-3" style="border-top:1px solid #e6eee9"><small>Posted by <?php echo htmlspecialchars($archive['fname'].' '.$archive['lname']); ?></small></div>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
    </div>
    <footer class="ss-advisory-footer"><p>Return to your current advisories.</p><a href="announcement" class="ss-back-link"><i class="ti-arrow-left" aria-hidden="true"></i> Back to My Advisories</a></footer>
</div></main><div class="ttr-overlay"></div>
<script src="../dashboard/assets/js/jquery.min.js"></script>
<script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
<script src="../dashboard/assets/js/admin.js"></script>
</body></html>
