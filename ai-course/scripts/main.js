/**
 * Course Landing Page - JavaScript
 * AI-Ассистент для бизнеса
 *
 * Payments handled by WooCommerce checkout (products 1185, 1186).
 * This file only handles landing page UI: scroll, FAQ, lightbox, analytics.
 */

(function() {
    'use strict';

    // ==========================================================================
    // Smooth Scroll
    // ==========================================================================

    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                var targetId = this.getAttribute('href');
                if (targetId === '#') return;
                var targetEl = document.querySelector(targetId);
                if (!targetEl) return;
                e.preventDefault();
                var offsetPosition = targetEl.getBoundingClientRect().top + window.pageYOffset - 20;
                window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
            });
        });
    }

    // ==========================================================================
    // FAQ Accordion
    // ==========================================================================

    function initFAQ() {
        var faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(function(item) {
            var question = item.querySelector('.faq-question');
            question.addEventListener('click', function() {
                var isActive = item.classList.contains('active');
                faqItems.forEach(function(other) {
                    if (other !== item) {
                        other.classList.remove('active');
                        other.querySelector('.faq-question').setAttribute('aria-expanded', 'false');
                    }
                });
                item.classList.toggle('active');
                question.setAttribute('aria-expanded', !isActive);
            });
        });
    }

    // ==========================================================================
    // Lightbox for Images
    // ==========================================================================

    function initLightbox() {
        var lightbox = document.getElementById('lightbox');
        if (!lightbox) return;

        var lightboxImg = lightbox.querySelector('img');

        var screenshots = document.querySelectorAll('.proof-visual-row .bot-screenshot, .case-image img');
        screenshots.forEach(function(img) {
            img.addEventListener('click', function() {
                lightboxImg.src = this.src;
                lightboxImg.alt = this.alt;
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });

        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox || e.target.classList.contains('lightbox-close')) {
                lightbox.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && lightbox.classList.contains('active')) {
                lightbox.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }

    // ==========================================================================
    // Sticky Mobile CTA
    // ==========================================================================

    function initStickyCTA() {
        var stickyCta = document.getElementById('sticky-cta');
        var hero = document.querySelector('.hero');
        if (!stickyCta || !hero) return;

        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    stickyCta.classList.remove('visible');
                } else {
                    stickyCta.classList.add('visible');
                }
            });
        }, { threshold: 0 });

        observer.observe(hero);
    }

    // ==========================================================================
    // Scroll Depth Tracking (Yandex Metrika)
    // ==========================================================================

    function initScrollTracking() {
        if (typeof ym === 'undefined') return;

        var thresholds = [25, 50, 75, 100];
        var fired = {};

        window.addEventListener('scroll', function() {
            var scrollTop = window.pageYOffset;
            var docHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (docHeight <= 0) return;
            var pct = Math.round((scrollTop / docHeight) * 100);

            thresholds.forEach(function(t) {
                if (pct >= t && !fired[t]) {
                    fired[t] = true;
                    ym(93286516, 'reachGoal', 'scroll_' + t);
                }
            });
        });
    }

    // ==========================================================================
    // Sign-up Form
    // ==========================================================================

    function initSignupForm() {
        var form = document.getElementById('signup-form');
        if (!form) return;

        // Pre-select tariff when clicking "или Записаться" from pricing card
        document.querySelectorAll('.signup-link[data-tariff]').forEach(function(link) {
            link.addEventListener('click', function() {
                var tariff = this.getAttribute('data-tariff');
                var radio = form.querySelector('input[name="tariff"][value="' + tariff + '"]');
                if (radio) radio.checked = true;
            });
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var nameEl = document.getElementById('signup-name');
            var phoneEl = document.getElementById('signup-phone');
            var emailEl = document.getElementById('signup-email');
            var errorEl = document.getElementById('signup-error');
            var submitBtn = document.getElementById('signup-submit');
            var tariffEl = form.querySelector('input[name="tariff"]:checked');

            // Reset error
            errorEl.hidden = true;

            // Validate
            var name = nameEl.value.trim();
            var phone = phoneEl.value.trim();

            if (!name) {
                showError(errorEl, 'Укажите имя');
                nameEl.focus();
                return;
            }
            if (!phone) {
                showError(errorEl, 'Укажите телефон');
                phoneEl.focus();
                return;
            }

            // Disable button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Отправка...';

            var data = {
                name: name,
                contact: phone,
                email: emailEl.value.trim(),
                tariff: tariffEl ? tariffEl.value : 'standard'
            };

            fetch('scripts/submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Server error');
                return response.json();
            })
            .then(function(result) {
                if (result.success) {
                    // Show success
                    form.hidden = true;
                    document.getElementById('signup-success').hidden = false;

                    // Yandex Metrika goal
                    if (typeof ym !== 'undefined') {
                        ym(93286516, 'reachGoal', 'course_signup');
                    }
                } else {
                    throw new Error(result.error || 'Ошибка отправки');
                }
            })
            .catch(function(err) {
                showError(errorEl, 'Не удалось отправить заявку. Попробуйте ещё раз.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Свяжитесь со мной';
            });
        });

        function showError(el, msg) {
            el.textContent = msg;
            el.hidden = false;
        }
    }

    // ==========================================================================
    // Initialize
    // ==========================================================================

    // ==========================================================================
    // Lead Magnet CTA Tracking
    // ==========================================================================

    function initLeadMagnetTracking() {
        var cta = document.getElementById('lead-magnet-cta');
        if (!cta) return;

        cta.addEventListener('click', function() {
            // Fire Yandex Metrika goal
            if (typeof ym === 'function') {
                ym(93286516, 'reachGoal', 'lead_magnet_click');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initSmoothScroll();
        initFAQ();
        initLightbox();
        initStickyCTA();
        initScrollTracking();
        initSignupForm();
        initLeadMagnetTracking();
    });

})();
