/**
 * WebHub.uz - Main JavaScript
 * Handles theme switching, scroll animations, and common interactions
 */

(function() {
    'use strict';
    
    // Theme Management
    const ThemeManager = {
        init() {
            this.loadTheme();
            this.bindEvents();
        },
        
        loadTheme() {
            const saved = localStorage.getItem('webhub_theme');
            if (saved) {
                document.documentElement.setAttribute('data-theme', saved);
            } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        },
        
        toggle() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('webhub_theme', next);
        },
        
        bindEvents() {
            document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
                btn.addEventListener('click', () => this.toggle());
            });
        }
    };
    
    // Scroll Animations
    const ScrollReveal = {
        init() {
            this.elements = document.querySelectorAll('.scroll-reveal');
            if (this.elements.length === 0) return;
            
            this.observer = new IntersectionObserver(
                entries => this.handleIntersection(entries),
                { threshold: 0.1, rootMargin: '50px' }
            );
            
            this.elements.forEach(el => this.observer.observe(el));
        },
        
        handleIntersection(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    this.observer.unobserve(entry.target);
                }
            });
        }
    };
    
    // Mobile Navigation
    const MobileNav = {
        init() {
            this.toggle = document.querySelector('[data-mobile-nav-toggle]');
            this.menu = document.querySelector('[data-mobile-nav-menu]');
            this.overlay = document.querySelector('[data-mobile-nav-overlay]');
            
            if (!this.toggle || !this.menu) return;
            
            this.bindEvents();
        },
        
        bindEvents() {
            this.toggle.addEventListener('click', () => this.open());
            
            if (this.overlay) {
                this.overlay.addEventListener('click', () => this.close());
            }
            
            document.querySelectorAll('[data-mobile-nav-close]').forEach(btn => {
                btn.addEventListener('click', () => this.close());
            });
        },
        
        open() {
            this.menu.classList.add('active');
            if (this.overlay) this.overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        },
        
        close() {
            this.menu.classList.remove('active');
            if (this.overlay) this.overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    };
    
    // Smooth Scroll for Anchor Links
    const SmoothScroll = {
        init() {
            document.querySelectorAll('a[href^="#"]').forEach(link => {
                link.addEventListener('click', e => {
                    const href = link.getAttribute('href');
                    if (href === '#') return;
                    
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        }
    };
    
    // Form Validation
    const FormValidation = {
        init() {
            document.querySelectorAll('form[data-validate]').forEach(form => {
                form.addEventListener('submit', e => {
                    if (!this.validateForm(form)) {
                        e.preventDefault();
                    }
                });
            });
        },
        
        validateForm(form) {
            let isValid = true;
            
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            form.querySelectorAll('[type="email"]').forEach(field => {
                if (field.value && !this.isValidEmail(field.value)) {
                    isValid = false;
                    field.classList.add('error');
                }
            });
            
            form.querySelectorAll('[type="tel"]').forEach(field => {
                if (field.value && !this.isValidPhone(field.value)) {
                    isValid = false;
                    field.classList.add('error');
                }
            });
            
            return isValid;
        },
        
        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
        
        isValidPhone(phone) {
            return /^\+998\d{9}$/.test(phone.replace(/[^\d+]/g, ''));
        }
    };
    
    // Page Transition Effect
    const PageTransition = {
        init() {
            document.body.classList.add('page-loaded');
            
            // Add fade effect to page links
            document.querySelectorAll('a[href]:not([target="_blank"]):not([download])').forEach(link => {
                link.addEventListener('click', e => {
                    const href = link.getAttribute('href');
                    if (href.startsWith('/') || !href.includes('://')) {
                        // Internal link - could add transition here
                    }
                });
            });
        }
    };
    
    // Initialize all modules on DOM ready
    function init() {
        ThemeManager.init();
        ScrollReveal.init();
        MobileNav.init();
        SmoothScroll.init();
        FormValidation.init();
        PageTransition.init();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Expose to global scope for external use
    window.WebHub = {
        ThemeManager,
        ScrollReveal,
        MobileNav
    };
})();
