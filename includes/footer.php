            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS for print functionality and responsive features -->
    <script>
        function printWorkload() {
            window.print();
        }
        
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar) {
                sidebar.classList.toggle('show');
                if (mainContent) {
                    mainContent.classList.toggle('sidebar-open');
                }
            }
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleButton = document.querySelector('.navbar-toggler');
            const isMobile = window.innerWidth < 992;
            
            if (isMobile && sidebar && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
                    sidebar.classList.remove('show');
                    document.querySelector('.main-content').classList.remove('sidebar-open');
                }
            }
        });
        
        // Auto-focus on first input field
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"], input[type="email"], select');
            if (firstInput && window.innerWidth > 768) {
                firstInput.focus();
            }
            
            // Add responsive table classes
            const tables = document.querySelectorAll('table');
            tables.forEach(table => {
                if (!table.parentElement.classList.contains('table-responsive')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'table-responsive';
                    table.parentNode.insertBefore(wrapper, table);
                    wrapper.appendChild(table);
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth >= 992) {
                if (sidebar) {
                    sidebar.classList.remove('show');
                }
                if (mainContent) {
                    mainContent.classList.remove('sidebar-open');
                }
            }
        });
        
        // Form validation helper
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (form) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                return isValid;
            }
            return true;
        }
        
        // Add loading state to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
                    
                    // Re-enable after 10 seconds as fallback
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    }, 10000);
                }
            });
        });
        
        // Initialize Bootstrap tooltips
        if (typeof bootstrap !== 'undefined') {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Add success animation to buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.classList.add('btn-clicked');
                setTimeout(() => {
                    this.classList.remove('btn-clicked');
                }, 200);
            });
        });
    </script>
</body>
</html> 