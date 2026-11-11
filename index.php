<?php
require __DIR__ . '/includes/bootstrap.php';

// Already logged in: go straight to the user's own dashboard.
if ($user = current_user()) {
    redirect(dashboard_for($user['role']));
}

$pageTitle = 'Home';
$wideLayout = true;

// Optional: put a photo you have permission to use at assets/hero.jpg and it becomes the hero background.
$heroStyle = is_file(__DIR__ . '/assets/hero.jpg')
    ? ' style="background-image: linear-gradient(135deg, rgba(14,27,68,.93), rgba(24,40,88,.8)), url(\'assets/hero.jpg\'); background-size: cover; background-position: center;"'
    : '';

require __DIR__ . '/includes/header.php';
?>
<?php if (needs_admin_setup($conn)): ?>
<div class="setup-banner">
    <div class="section-inner setup-inner">
        <div><strong>Welcome! One last step to get started.</strong> This system has no administrator yet. Create the administrator account to begin.</div>
        <a class="btn" href="install.php">Set up the administrator</a>
    </div>
</div>
<?php endif; ?>
<section class="home-hero"<?= $heroStyle ?>>
    <div class="section-inner hero-grid">
        <div>
            <h1>Lodge a complaint. See it through to a resolution.</h1>
            <p class="lead">One place for KCA University students to report a problem, see who is handling it, and rate the service they received.</p>
            <div class="hero-actions">
                <a class="btn btn-gold btn-lg" href="login.php">Log in</a>
                <a class="btn btn-light btn-lg" href="register.php">Register as a student</a>
            </div>
        </div>
        <aside class="journey" aria-label="What happens to a complaint">
            <h2>What happens to a complaint</h2>
            <ol>
                <li>Submitted<small>You send your complaint and get a reference number</small></li>
                <li>Received<small>The department sees it</small></li>
                <li>Assigned<small>A staff member takes it on</small></li>
                <li>In Progress<small>Work is under way</small></li>
                <li>Escalated<small>Passed to senior staff if it takes too long</small></li>
                <li class="goal">Resolved<small>You are told, and you can rate the service</small></li>
                <li>Closed<small>The complaint is finished</small></li>
            </ol>
        </aside>
    </div>
</section>

<section class="portals">
    <div class="section-inner">
        <div class="portal-grid">
            <div class="portal-card">
                <div class="icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 9 12 4 2 9l10 5 10-5z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v6"/></svg>
                </div>
                <h3>Student portal</h3>
                <p>For KCA University students.</p>
                <ul>
                    <li>Submit a complaint with an optional attachment</li>
                    <li>Track it with your reference number</li>
                    <li>Get updates by email and in the system</li>
                    <li>Rate the service once it is resolved</li>
                </ul>
                <div class="actions">
                    <a class="btn" href="login.php?portal=student">Student log in</a>
                    <a class="btn btn-ghost" href="register.php">Register</a>
                </div>
            </div>

            <div class="portal-card">
                <div class="icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="11" r="2.5"/><path d="M5.5 17c.6-2 2-3 3.5-3s2.9 1 3.5 3"/><path d="M15 9h3M15 12h3"/></svg>
                </div>
                <h3>Staff portal</h3>
                <p>For staff and senior staff.</p>
                <ul>
                    <li>Handle the complaints assigned to you</li>
                    <li>Reply to students or leave internal notes</li>
                    <li>Escalate to senior staff when needed</li>
                    <li>Senior staff receive escalated complaints</li>
                </ul>
                <div class="actions">
                    <a class="btn" href="login.php?portal=staff">Staff log in</a>
                </div>
            </div>

            <div class="portal-card">
                <div class="icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.2 7.8 8 9 4.8-1.2 8-4.5 8-9V6l-8-3z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <h3>Administrator portal</h3>
                <p>For system administrators.</p>
                <ul>
                    <li>Assign and reassign complaints</li>
                    <li>Manage users and escalation times</li>
                    <li>Reports with charts and exports</li>
                    <li>Activity log of every action</li>
                </ul>
                <div class="actions">
                    <a class="btn" href="login.php?portal=admin">Administrator log in</a>
                </div>
            </div>
        </div>
        <p class="portal-note">Everyone logs in with the same email and password, and your role decides what you see. Staff and administrator accounts are created by the administrator.</p>
    </div>
</section>

<section class="home-section">
    <div class="section-inner">
        <h2>How it works</h2>
        <p class="section-sub">Three steps from reporting a problem to telling us how it went.</p>
        <div class="steps">
            <div class="step">
                <div class="n">1</div>
                <h3>Submit</h3>
                <p>Choose the department and category, describe the problem and attach a file if it helps. You get a reference number straight away.</p>
            </div>
            <div class="step">
                <div class="n">2</div>
                <h3>Track</h3>
                <p>Follow the status, read replies from staff, and get an email each time something changes.</p>
            </div>
            <div class="step">
                <div class="n">3</div>
                <h3>Rate</h3>
                <p>When the complaint is resolved, rate the service from 1 to 5 and leave a comment.</p>
            </div>
        </div>
    </div>
</section>

<section class="home-section last">
    <div class="section-inner">
        <h2>What the system does</h2>
        <p class="section-sub">Built so that no complaint is forgotten.</p>
        <div class="feature-grid">
            <div class="feature"><h3>Attachments</h3><p>Add a PDF, JPG or PNG of up to 2 MB as evidence.</p></div>
            <div class="feature"><h3>Email and in-app updates</h3><p>Students and staff are told when something changes.</p></div>
            <div class="feature"><h3>Automatic escalation</h3><p>A complaint that waits too long goes to senior staff.</p></div>
            <div class="feature"><h3>Priorities</h3><p>Urgent complaints are escalated sooner than routine ones.</p></div>
            <div class="feature"><h3>Reports</h3><p>Charts and tables by period, department and category, with CSV and PDF export.</p></div>
            <div class="feature"><h3>Audit trail</h3><p>Every login and change is recorded for the administrator.</p></div>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="section-inner cta-inner">
        <div>
            <h2>Have a concern? Tell us today.</h2>
            <p>Lodging a complaint is quick, and you can follow every step.</p>
        </div>
        <div class="hero-actions">
            <a class="btn btn-gold btn-lg" href="login.php?portal=student">Student log in</a>
            <a class="btn btn-light btn-lg" href="register.php">Register</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
