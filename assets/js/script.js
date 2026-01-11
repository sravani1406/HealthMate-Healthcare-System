/ HealthHive Main JavaScript File

// Global App Object
const HealthHive = {
    init: function () {
        this.setupEventListeners();
        this.initComponents();
        this.loadNotifications();
    },

    setupEventListeners: function () {
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const navMenu = document.querySelector('.nav-menu');

        if (mobileToggle && navMenu) {
            mobileToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
            });
        }

        // Form validation
        this.setupFormValidation();

        // Real-time search
        this.setupSearch();

        // Tab functionality
        this.setupTabs();

        // Modal functionality
        this.setupModals();
    },

    setupFormValidation: function () {
        const forms = document.querySelectorAll('form[data-validate]');

        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    },

    validateForm: function (form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');

        requiredFields.forEach(field => {
            const value = field.value.trim();
            const fieldName = field.name || field.id;

            // Remove existing error messages
            this.removeFieldError(field);

            if (!value) {
                this.showFieldError(field, `${fieldName} is required`);
                isValid = false;
            } else {
                // Specific validation
                if (field.type === 'email' && !this.isValidEmail(value)) {
                    this.showFieldError(field, 'Please enter a valid email address');
                    isValid = false;
                }

                if (field.type === 'password' && value.length < 6) {
                    this.showFieldError(field, 'Password must be at least 6 characters');
                    isValid = false;
                }

                if (field.name === 'confirm_password') {
                    const passwordField = form.querySelector('[name="password"]');
                    if (passwordField && value !== passwordField.value) {
                        this.showFieldError(field, 'Passwords do not match');
                        isValid = false;
                    }
                }
            }
        });

        return isValid;
    },

    showFieldError: function (field, message) {
        field.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    },

    removeFieldError: function (field) {
        field.classList.remove('error');
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    },

    isValidEmail: function (email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    },

    setupSearch: function () {
        const searchInputs = document.querySelectorAll('[data-search]');

        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                const targetSelector = input.getAttribute('data-search');
                const items = document.querySelectorAll(targetSelector);

                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    },

    setupTabs: function () {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const targetTab = button.getAttribute('data-tab');

                // Remove active class from all tabs
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                button.classList.add('active');
                const targetContent = document.getElementById(targetTab);
                if (targetContent) {
                    targetContent.classList.add('active');
                }
            });
        });
    },

    setupModals: function () {
        const modalTriggers = document.querySelectorAll('[data-modal]');
        const modals = document.querySelectorAll('.modal');
        const closeButtons = document.querySelectorAll('.modal-close');

        modalTriggers.forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.getAttribute('data-modal');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                    document.body.classList.add('modal-open');
                }
            });
        });

        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                modals.forEach(modal => modal.classList.remove('active'));
                document.body.classList.remove('modal-open');
            });
        });

        // Close modal on background click
        modals.forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    document.body.classList.remove('modal-open');
                }
            });
        });
    },

    loadNotifications: function () {
        if (window.location.pathname.includes('dashboard')) {
            fetch('/healthhive/api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.notifications) {
                        this.displayNotifications(data.notifications);
                        this.updateNotificationBadge(data.notifications);
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }
    },

    displayNotifications: function (notifications) {
        const container = document.querySelector('.notifications-container');
        if (!container) return;

        container.innerHTML = '';
        notifications.slice(0, 3).forEach(notification => {
            const notificationElement = this.createNotificationElement(notification);
            container.appendChild(notificationElement);
        });
    },

    createNotificationElement: function (notification) {
        const div = document.createElement('div');
        div.className = `notification-item ${notification.is_read ? 'read' : 'unread'}`;
        div.innerHTML = `
            

                
${this.escapeHtml(notification.message)}


                ${this.formatDate(notification.created_at)}
            

            ${!notification.is_read ? '
' : ''}
            `;
        return div;
    },

    updateNotificationBadge: function(notifications) {
        const unreadCount = notifications.filter(n => !n.is_read).length;
        const badge = document.querySelector('.notification-badge');
        
        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    },

    // Symptom Analysis Functions
    analyzeSymptoms: function(symptomsData) {
        this.showLoading('Analyzing symptoms...');
        
        fetch('/healthhive/api/ai_analysis.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                symptoms: symptomsData,
                additional_info: document.getElementById('additional_info')?.value
            })
        })
        .then(response => response.json())
        .then(data => {
            this.hideLoading();
            if (data.error) {
                this.showMessage(data.error, 'error');
            } else {
                this.displayAnalysisResults(data);
                // If high risk, show emergency alert
                if (data.risk_level === 'high' || data.risk_level === 'critical') {
                    this.showEmergencyAlert(data);
                }
            }
        })
        .catch(error => {
            this.hideLoading();
            this.showMessage('Failed to analyze symptoms. Please try again.', 'error');
            console.error('Analysis error:', error);
        });
    },

    displayAnalysisResults: function(results) {
        const resultsContainer = document.getElementById('analysisResults');
        if (!resultsContainer) return;
        
        resultsContainer.innerHTML = `




        Dismiss
                    Call Emergency




            `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 10000);
    },

    // Utility Functions
    showLoading: function(message = 'Loading...') {
        const loader = document.createElement('div');
        loader.id = 'globalLoader';
        loader.className = 'global-loader';
        loader.innerHTML = `
            

                

                
${ message }




        `;
        document.body.appendChild(loader);
    },

    hideLoading: function() {
        const loader = document.getElementById('globalLoader');
        if (loader) {
            loader.remove();
        }
    },

    showMessage: function(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${ type } -message`;
        messageDiv.innerHTML = `
            
${ this.escapeHtml(message) }


            ×
`;
        
        // Insert at top of main content
        const main = document.querySelector('main') || document.body;
        main.insertBefore(messageDiv, main.firstChild);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    },

    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    formatDate: function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    },

    // Chart Functions (for analytics dashboards)
    createChart: function(containerId, data, type = 'line') {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        // Simple chart implementation (you can replace with Chart.js or similar)
        this.renderSimpleChart(container, data, type);
    },

    renderSimpleChart: function(container, data, type) {
        const maxValue = Math.max(...data.values);
        const width = container.offsetWidth;
        const height = 200;
        
        let svg = `
${ data.labels[index] }
';
container.innerHTML = svg;
    }
};

// Patient Dashboard Specific Functions
const PatientDashboard = {
    init: function () {
        this.loadHealthStatus();
        this.setupSymptomForm();
        this.loadRecentAnalyses();
    },

    loadHealthStatus: function () {
        // Update health indicators
        this.updateHealthIndicator();
    },

    updateHealthIndicator: function () {
        const indicator = document.querySelector('.health-indicator');
        if (!indicator) return;

        // Get latest analysis data (this would typically come from an API)
        const riskLevel = indicator.dataset.riskLevel || 'low';
        indicator.className = `health-indicator ${riskLevel}`;
    },

    setupSymptomForm: function () {
        const symptomForm = document.getElementById('symptomsForm');
        if (!symptomForm) return;

        symptomForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const formData = new FormData(symptomForm);
            const symptoms = formData.getAll('symptoms[]');
            const severities = formData.getAll('severity[]');
            const durations = formData.getAll('duration[]');

            const symptomsData = symptoms.map((symptom, index) => ({
                symptom: symptom,
                severity: severities[index] || 'mild',
                duration: durations[index] || 'recent'
            }));

            if (symptomsData.length === 0) {
                HealthHive.showMessage('Please select at least one symptom.', 'error');
                return;
            }

            HealthHive.analyzeSymptoms(symptomsData);
        });
    },

    loadRecentAnalyses: function () {
        // This would typically load from an API
        const analysesContainer = document.querySelector('.recent-analyses');
        if (analysesContainer) {
            // Update with real data
        }
    }
};

// Doctor Dashboard Specific Functions
const DoctorDashboard = {
    init: function () {
        this.loadPatientAlerts();
        this.setupPatientSearch();
        this.loadConsultations();
    },

    loadPatientAlerts: function () {
        fetch('/healthhive/api/patient_alerts.php')
            .then(response => response.json())
            .then(data => {
                this.displayPatientAlerts(data.alerts || []);
            })
            .catch(error => console.error('Error loading alerts:', error));
    },

    displayPatientAlerts: function (alerts) {
        const container = document.querySelector('.patient-alerts');
        if (!container) return;

        container.innerHTML = '';
        alerts.forEach(alert => {
            const alertElement = this.createAlertElement(alert);
            container.appendChild(alertElement);
        });
    },

    createAlertElement: function (alert) {
        const div = document.createElement('div');
        div.className = `alert-item ${alert.risk_level}`;
        div.innerHTML = `
            

                
${HealthHive.escapeHtml(alert.patient_name)}

                ${alert.risk_level}
            

            
${HealthHive.escapeHtml(alert.message)}


            

                
                    Review Patient
                
            

        `;
        return div;
    },

    setupPatientSearch: function () {
        const searchInput = document.getElementById('patientSearch');
        if (!searchInput) return;

        searchInput.addEventListener('input', (e) => {
            this.filterPatients(e.target.value);
        });
    },

    filterPatients: function (searchTerm) {
        const patients = document.querySelectorAll('.patient-card');
        const term = searchTerm.toLowerCase();

        patients.forEach(patient => {
            const name = patient.querySelector('h3').textContent.toLowerCase();
            const visible = name.includes(term);
            patient.style.display = visible ? 'block' : 'none';
        });
    },

    loadConsultations: function () {
        // Load upcoming consultations
        const consultationsContainer = document.querySelector('.upcoming-consultations');
        if (consultationsContainer) {
            // This would load from API
        }
    }
};

// Admin Dashboard Specific Functions
const AdminDashboard = {
    init: function () {
        this.loadSystemStats();
        this.setupUserManagement();
        this.loadSystemActivity();
    },

    loadSystemStats: function () {
        fetch('/healthhive/api/system_stats.php')
            .then(response => response.json())
            .then(data => {
                this.updateStatsDisplay(data.stats || {});
            })
            .catch(error => console.error('Error loading stats:', error));
    },

    updateStatsDisplay: function (stats) {
        Object.keys(stats).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                element.textContent = stats[key];
            }
        });
    },

    setupUserManagement: function () {
        const userActions = document.querySelectorAll('.user-action');
        userActions.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const action = button.dataset.action;
                const userId = button.dataset.userId;

                if (confirm(`Are you sure you want to ${action} this user?`)) {
                    this.performUserAction(action, userId);
                }
            });
        });
    },

    performUserAction: function (action, userId) {
        fetch('/healthhive/api/user_management.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                user_id: userId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    HealthHive.showMessage(`User ${action} successful`, 'success');
                    location.reload(); // Refresh the page
                } else {
                    HealthHive.showMessage(data.error || 'Action failed', 'error');
                }
            })
            .catch(error => {
                HealthHive.showMessage('Action failed', 'error');
                console.error('Action error:', error);
            });
    },

    loadSystemActivity: function () {
        // Load recent system activity
        const activityContainer = document.querySelector('.system-activity');
        if (activityContainer) {
            // This would load from API
        }
    }
};

// Initialize based on page
document.addEventListener('DOMContentLoaded', function () {
    // Initialize base HealthHive functionality
    HealthHive.init();

    // Initialize page-specific functionality
    const currentPage = window.location.pathname;

    if (currentPage.includes('patient/dashboard')) {
        PatientDashboard.init();
    } else if (currentPage.includes('doctor/dashboard')) {
        DoctorDashboard.init();
    } else if (currentPage.includes('admin/dashboard')) {
        AdminDashboard.init();
    }
});

// Global error handling
window.addEventListener('error', function (e) {
    console.error('Global error:', e.error);
    // You might want to send error reports to your server here
});

// Service Worker Registration (for PWA capabilities)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/healthhive/sw.js')
            .then(function (registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function (err) {
                console.log('ServiceWorker registration failed');
            });
    });
}