(function () {
    'use strict';

    const header = document.querySelector('.site-header');
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    const navLinks = document.querySelectorAll('.nav-menu a');
    const yearEl = document.getElementById('year');

    if (yearEl) {
        yearEl.textContent = new Date().getFullYear();
    }

    // Sticky header shadow
    function onScroll() {
        if (window.scrollY > 10) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Mobile nav toggle
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            const expanded = navToggle.getAttribute('aria-expanded') === 'true';
            navToggle.setAttribute('aria-expanded', String(!expanded));
            navToggle.setAttribute('aria-label', expanded ? 'Open menu' : 'Close menu');
            navMenu.classList.toggle('open');
        });

        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.setAttribute('aria-label', 'Open menu');
                navMenu.classList.remove('open');
            });
        });
    }

    // Scroll reveal (respect reduced motion)
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefersReducedMotion) {
        const revealEls = document.querySelectorAll(
            '.section-header, .about-card, .featured-card, .project-card, .skill-category, .contact-card'
        );

        revealEls.forEach(function (el) {
            el.classList.add('reveal');
        });

        const observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
        );

        revealEls.forEach(function (el) {
            observer.observe(el);
        });
    }

    // Active nav link on scroll
    const sections = document.querySelectorAll('section[id]');

    function setActiveNav() {
        const scrollY = window.scrollY + header.offsetHeight + 20;

        sections.forEach(function (section) {
            const id = section.getAttribute('id');
            const link = document.querySelector('.nav-menu a[href="#' + id + '"]');

            if (!link) return;

            const top = section.offsetTop;
            const height = section.offsetHeight;

            if (scrollY >= top && scrollY < top + height) {
                navLinks.forEach(function (l) { l.removeAttribute('aria-current'); });
                link.setAttribute('aria-current', 'page');
            }
        });
    }

    window.addEventListener('scroll', setActiveNav, { passive: true });
})();
