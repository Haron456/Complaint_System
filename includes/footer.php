</main>
<footer class="site-footer">
    <div class="footer-grid">
        <div>
            <div class="footer-brand">
                <img src="assets/logo.png" alt="KCA University logo">
                <span>KCA University<br>Complaint and Feedback System</span>
            </div>
            <p>Lodge a complaint, follow it to a resolution and rate the service you received.</p>
        </div>
        <?php if (empty($navUser)): ?>
        <div>
            <h3>Portals</h3>
            <ul>
                <li><a href="login.php?portal=student">Student portal</a></li>
                <li><a href="login.php?portal=staff">Staff portal</a></li>
                <li><a href="login.php?portal=admin">Administrator portal</a></li>
                <li><a href="register.php">Student registration</a></li>
            </ul>
        </div>
        <?php endif; ?>
        <div>
            <h3>KCA University</h3>
            <ul>
                <li><a href="https://www.kcau.ac.ke/" target="_blank" rel="noopener">University website</a></li>
                <li><a href="https://portal.kcau.ac.ke" target="_blank" rel="noopener">Student portal</a></li>
                <li><a href="https://www.kcau.ac.ke/contact-us/" target="_blank" rel="noopener">Contact us</a></li>
                <li><a href="https://www.kcau.ac.ke/data-protection/" target="_blank" rel="noopener">Data protection</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">&copy; <?= date('Y') ?> KCAU Complaint and Feedback Management System</div>
</footer>
</body>
</html>
