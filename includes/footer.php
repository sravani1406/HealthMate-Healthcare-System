</main>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-heartbeat me-2"></i>HealthHive</h5>
                    <p class="text-muted">Your smart healthcare assistant for better health management and early risk detection.</p>
                </div>
                <div class="col-md-2">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>" class="text-muted text-decoration-none">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>about.php" class="text-muted text-decoration-none">About</a></li>
                        <li><a href="<?php echo SITE_URL; ?>contact.php" class="text-muted text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6>For Patients</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>patient/register.php" class="text-muted text-decoration-none">Register</a></li>
                        <li><a href="<?php echo SITE_URL; ?>patient/login.php" class="text-muted text-decoration-none">Login</a></li>
                        <li><a href="<?php echo SITE_URL; ?>help.php" class="text-muted text-decoration-none">Help</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6>For Doctors</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo SITE_URL; ?>doctor/register.php" class="text-muted text-decoration-none">Join Us</a></li>
                        <li><a href="<?php echo SITE_URL; ?>doctor/login.php" class="text-muted text-decoration-none">Login</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6>Emergency</h6>
                    <div class="text-danger">
                        <i class="fas fa-phone me-1"></i>
                        <strong><?php echo get_setting('emergency_phone', '911'); ?></strong>
                    </div>
                    <small class="text-muted">Call immediately for emergencies</small>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <small class="text-muted">&copy; <?php echo date('Y'); ?> HealthHive. All rights reserved.</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="<?php echo SITE_URL; ?>privacy.php" class="text-muted text-decoration-none me-3">Privacy Policy</a>
                    <a href="<?php echo SITE_URL; ?>terms.php" class="text-muted text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo SITE_URL; ?>assets/js/script.js"></script>

    <!-- Notification System -->
    <?php if (is_logged_in()): ?>
    <script>
        // Load notifications on page load
        $(document).ready(function() {
            loadNotifications();
            
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });

        function loadNotifications() {
            $.ajax({
                url: '<?php echo SITE_URL; ?>api/notifications.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        updateNotificationUI(response.notifications);
                    }
                },
                error: function() {
                    console.log('Error loading notifications');
                }
            });
        }

        function updateNotificationUI(notifications) {
            const unreadCount = notifications.filter(n => !n.is_read).length;
            const badge = document.getElementById('notificationCount');
            const list = document.getElementById('notificationList');
            
            // Update badge
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
            
            // Update notification list
            if (notifications.length > 0) {
                list.innerHTML = '';
                notifications.slice(0, 5).forEach(notification => {
                    const item = document.createElement('li');
                    item.innerHTML = `
                        <a class="dropdown-item ${!notification.is_read ? 'bg-light' : ''}" 
                           href="#" onclick="markAsRead(${notification.id})">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 ${!notification.is_read ? 'fw-bold' : ''}">${notification.title}</h6>
                                    <p class="mb-1 small text-muted">${notification.message}</p>
                                    <small class="text-muted">${timeAgo(notification.created_at)}</small>
                                </div>
                                ${!notification.is_read ? '<span class="badge bg-primary rounded-pill">New</span>' : ''}
                            </div>
                        </a>
                    `;
                    list.appendChild(item);
                });
            } else {
                list.innerHTML = '<div class="text-center py-3"><small class="text-muted">No notifications</small></div>';
            }
        }

        function markAsRead(notificationId) {
            $.ajax({
                url: '<?php echo SITE_URL; ?>api/notifications.php',
                type: 'POST',
                data: {
                    action: 'mark_read',
                    notification_id: notificationId
                },
                success: function(response) {
                    if (response.success) {
                        loadNotifications();
                    }
                }
            });
        }

        function timeAgo(dateString) {
            const now = new Date();
            const past = new Date(dateString);
            const diffInSeconds = Math.floor((now - past) / 1000);
            
            if (diffInSeconds < 60) return 'just now';
            if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' min ago';
            if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hr ago';
            return Math.floor(diffInSeconds / 86400) + ' days ago';
        }
    </script>
    <?php endif; ?>

    <!-- Emergency Alert Modal -->
    <div class="modal fade" id="emergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Emergency Alert
                    </h5>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-phone text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h4>Call Emergency Services</h4>
                    <h2 class="text-danger"><?php echo get_setting('emergency_phone', '911'); ?></h2>
                    <p class="text-muted">If this is a medical emergency, please call immediately or visit the nearest emergency room.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="tel:<?php echo get_setting('emergency_phone', '911'); ?>" class="btn btn-danger">
                        <i class="fas fa-phone me-1"></i>Call Now
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Analysis Loading Modal -->
    <div class="modal fade" id="aiAnalysisModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5>AI Analysis in Progress</h5>
                    <p class="text-muted">Please wait while we analyze your symptoms...</p>
                </div>
            </div>
        </div>
    </div>

</body>
</html>