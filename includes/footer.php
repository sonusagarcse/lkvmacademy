<?php
$site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');
$contact_email = lms_get_setting($con, 'contact_email', 'info@lkvm.org');
$contact_phone = lms_get_setting($con, 'contact_phone', '+91 9801664500');
?>
<footer class="lms-footer mt-auto bg-dark text-white pt-5 pb-4" style="background-color: #0f172a !important;">
    <div class="container">
        <div class="row g-4 mb-5">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="bg-primary text-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <i class="fa fa-graduation-cap"></i>
                    </span>
                    <span class="fs-4 fw-800 text-white">LKVM <span class="text-primary">Academy</span></span>
                </div>
                <p class="text-white-50 mb-4" style="font-size: 0.95rem; line-height: 1.7;">
                    Empowering students and professionals with industry-relevant computer applications, accounting, and software skills since inception.
                </p>
                <div class="d-flex gap-2">
                    <a href="#" class="btn btn-outline-light rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="btn btn-outline-light rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fab fa-youtube"></i></a>
                    <a href="#" class="btn btn-outline-light rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            
            <div class="col-6 col-lg-2 offset-lg-1">
                <h6 class="fw-800 text-uppercase text-white mb-3" style="letter-spacing: 0.8px;">Explore</h6>
                <ul class="list-unstyled footer-links d-grid gap-2" style="font-size: 0.92rem;">
                    <li><a href="index" class="text-white-50 text-decoration-none">All Courses</a></li>
                    <li><a href="categories" class="text-white-50 text-decoration-none">Categories</a></li>
                    <li><a href="dashboard" class="text-white-50 text-decoration-none">My Learning</a></li>
                    <li><a href="certificates" class="text-white-50 text-decoration-none">My Certificates</a></li>
                    <li><a href="login" class="text-white-50 text-decoration-none">Student Sign In</a></li>
                </ul>
            </div>
            
            <div class="col-6 col-lg-2">
                <h6 class="fw-800 text-uppercase text-white mb-3" style="letter-spacing: 0.8px;">Portals</h6>
                <ul class="list-unstyled footer-links d-grid gap-2" style="font-size: 0.92rem;">
                    <li><a href="../index.php" target="_blank" class="text-white-50 text-decoration-none">Main LKVM School</a></li>
                    <li><a href="../verification.php" target="_blank" class="text-white-50 text-decoration-none">Certificate Verify</a></li>
                    <li><a href="admin/login" class="text-white-50 text-decoration-none"><i class="fa fa-lock me-1"></i> LMS Admin Panel</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3">
                <h6 class="fw-800 text-uppercase text-white mb-3" style="letter-spacing: 0.8px;">Helpline</h6>
                <p class="text-white-50 mb-2" style="font-size: 0.92rem;"><i class="fa fa-envelope me-2 text-primary"></i> <?php echo htmlspecialchars($contact_email); ?></p>
                <p class="text-white-50 mb-2" style="font-size: 0.92rem;"><i class="fa fa-phone me-2 text-primary"></i> <?php echo htmlspecialchars($contact_phone); ?></p>
                <p class="text-white-50 mb-0" style="font-size: 0.92rem;"><i class="fa fa-map-marker-alt me-2 text-primary"></i> Bihar, India</p>
            </div>
        </div>
        
        <div class="border-top border-secondary border-opacity-25 pt-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2" style="font-size: 0.85rem;">
            <p class="text-white-50 mb-0">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.</p>
            <div class="d-flex gap-3 text-white-50">
                <a href="../privacy_policy.php" target="_blank" class="text-white-50 text-decoration-none">Privacy Policy</a>
                <a href="../terms_conditions.php" target="_blank" class="text-white-50 text-decoration-none">Terms of Service</a>
                <a href="admin/login" class="text-primary text-decoration-none fw-semibold">Admin Login</a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
