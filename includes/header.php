<?php
/**
 * Page header and navigation.
 * Expects $conn (from bootstrap) and $pageTitle to be set by the including page.
 * A page can set $wideLayout = true to use the full page width (the home page does).
 */
$navUser = current_user();
$navUnread = $navUser ? unread_count($conn, $navUser['id']) : 0;
$flashMessages = pull_flashes();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
/** Adds class="active" to the link of the page being viewed. */
$active = function (string $file) use ($currentPage): string {
    return $currentPage === $file ? ' class="active"' : '';
};
$cssVersion = (int)@filemtime(BASE_PATH . '/assets/css/style.css');   // makes browsers load the newest stylesheet
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Home') ?> - KCAU Complaint System</title>
<link rel="stylesheet" href="assets/css/style.css?v=<?= $cssVersion ?>">
</head>
<body>
<div class="topbar no-print">
    <div class="topbar-inner">
        <span>Complaint and Feedback Management System</span>
        <nav class="topbar-links" aria-label="University links">
            <a href="https://www.kcau.ac.ke/" target="_blank" rel="noopener">University website</a>
            <a href="https://portal.kcau.ac.ke" target="_blank" rel="noopener">KCAU student portal</a>
            <a href="https://www.kcau.ac.ke/about-library/" target="_blank" rel="noopener">Library</a>
            <a href="https://www.kcau.ac.ke/contact-us/" target="_blank" rel="noopener">Contact us</a>
        </nav>
    </div>
</div>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= $navUser ? e(dashboard_for($navUser['role'])) : 'index.php' ?>">
            <img src="assets/logo.png" alt="KCA University logo">
            <span class="brand-text">
                <strong>KCA University</strong>
                <small>Complaint and Feedback System</small>
            </span>
        </a>
        <nav class="nav" aria-label="Main">
            <?php if ($navUser): ?>
                <a href="<?= e(dashboard_for($navUser['role'])) ?>"<?= $active(dashboard_for($navUser['role'])) ?>>Dashboard</a>
                <?php if ($navUser['role'] === 'admin'): ?>
                    <a href="admin_users.php"<?= $active('admin_users.php') ?>>Users</a>
                    <a href="admin_settings.php"<?= $active('admin_settings.php') ?>>Settings</a>
                    <a href="admin_reports.php"<?= $active('admin_reports.php') ?>>Reports</a>
                    <a href="admin_activity.php"<?= $active('admin_activity.php') ?>>Activity log</a>
                <?php endif; ?>
                <a href="notifications.php"<?= $active('notifications.php') ?>>Notifications<?php if ($navUnread > 0): ?><span class="nav-badge"><?= (int)$navUnread ?></span><?php endif; ?></a>
                <details class="user-menu">
                    <summary>
                        <?php if ($navUser['avatar'] !== ''): ?>
                            <img class="avatar" src="avatar.php?v=<?= e(substr(md5($navUser['avatar']), 0, 8)) ?>" alt="">
                        <?php else: ?>
                            <span class="avatar avatar-initials"><?= e(initials($navUser['name'])) ?></span>
                        <?php endif; ?>
                        <span class="menu-name"><?= e($navUser['name']) ?></span>
                        <span class="caret" aria-hidden="true"></span>
                    </summary>
                    <div class="menu-panel">
                        <div class="menu-head"><strong><?= e($navUser['name']) ?></strong><span><?= e(ucfirst($navUser['role'])) ?></span></div>
                        <a href="profile.php">My profile</a>
                        <a href="change_password.php">Change password</a>
                        <form method="post" action="logout.php">
                            <?= csrf_field() ?>
                            <button type="submit" class="link-btn">Logout</button>
                        </form>
                    </div>
                </details>
            <?php else: ?>
                <a href="index.php"<?= $active('index.php') ?>>Home</a>
                <a href="login.php"<?= $active('login.php') ?>>Log in</a>
                <a href="register.php"<?= $active('register.php') ?>>Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<script>document.addEventListener('click', function (e) { document.querySelectorAll('details.user-menu[open]').forEach(function (d) { if (!d.contains(e.target)) { d.removeAttribute('open'); } }); });</script>
<main class="<?= !empty($wideLayout) ? 'wide' : '' ?>">
<?php if ($flashMessages): ?>
<div class="flash-wrap">
    <?php foreach ($flashMessages as $m): ?>
        <div class="alert alert-<?= e($m['type']) ?>"><?= e($m['text']) ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
