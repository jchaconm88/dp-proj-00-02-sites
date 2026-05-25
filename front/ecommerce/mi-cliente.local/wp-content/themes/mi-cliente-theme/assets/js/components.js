/**
 * Mi Cliente Theme - Interactive Components
 *
 * Vanilla JavaScript for accordions, tabs, modals, and product filters.
 * No jQuery dependency. Lightweight and accessible.
 * Graceful degradation: content accessible in static format if JS fails.
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

(function () {
    'use strict';

    /* ======================================================================
       Utility: Remove no-js class (indicates JS is available)
       ====================================================================== */

    document.documentElement.classList.remove('no-js');
    document.documentElement.classList.add('js');

    /* ======================================================================
       Accordions
       ====================================================================== */

    function initAccordions() {
        var accordions = document.querySelectorAll('.accordion');

        accordions.forEach(function (accordion) {
            var triggers = accordion.querySelectorAll('.accordion__trigger');

            triggers.forEach(function (trigger) {
                var panel = document.getElementById(trigger.getAttribute('aria-controls'));

                if (!panel) return;

                // Set initial state: collapse all panels
                trigger.setAttribute('aria-expanded', 'false');
                panel.setAttribute('aria-hidden', 'true');

                trigger.addEventListener('click', function () {
                    var isExpanded = trigger.getAttribute('aria-expanded') === 'true';

                    // Close other panels in the same accordion (optional single-open mode)
                    if (accordion.hasAttribute('data-single-open')) {
                        triggers.forEach(function (otherTrigger) {
                            var otherPanel = document.getElementById(
                                otherTrigger.getAttribute('aria-controls')
                            );
                            if (otherTrigger !== trigger && otherPanel) {
                                otherTrigger.setAttribute('aria-expanded', 'false');
                                otherPanel.setAttribute('aria-hidden', 'true');
                            }
                        });
                    }

                    // Toggle current panel
                    trigger.setAttribute('aria-expanded', String(!isExpanded));
                    panel.setAttribute('aria-hidden', String(isExpanded));
                });
            });
        });
    }

    /* ======================================================================
       Tabs
       ====================================================================== */

    function initTabs() {
        var tabGroups = document.querySelectorAll('.tabs');

        tabGroups.forEach(function (tabGroup) {
            var tabList = tabGroup.querySelector('.tabs__list');
            var tabs = tabGroup.querySelectorAll('.tabs__tab');
            var panels = tabGroup.querySelectorAll('.tabs__panel');

            if (!tabList || tabs.length === 0) return;

            // Set initial state: activate first tab
            tabs.forEach(function (tab, index) {
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
                tab.setAttribute('tabindex', index === 0 ? '0' : '-1');
            });

            panels.forEach(function (panel, index) {
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('aria-hidden', index === 0 ? 'false' : 'true');
            });

            tabList.setAttribute('role', 'tablist');

            function activateTab(selectedTab) {
                tabs.forEach(function (tab) {
                    tab.setAttribute('aria-selected', 'false');
                    tab.setAttribute('tabindex', '-1');
                });

                panels.forEach(function (panel) {
                    panel.setAttribute('aria-hidden', 'true');
                });

                selectedTab.setAttribute('aria-selected', 'true');
                selectedTab.setAttribute('tabindex', '0');
                selectedTab.focus();

                var targetPanel = document.getElementById(
                    selectedTab.getAttribute('aria-controls')
                );
                if (targetPanel) {
                    targetPanel.setAttribute('aria-hidden', 'false');
                }
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    activateTab(tab);
                });

                tab.addEventListener('keydown', function (e) {
                    var tabArray = Array.from(tabs);
                    var currentIndex = tabArray.indexOf(tab);
                    var nextIndex;

                    switch (e.key) {
                        case 'ArrowRight':
                            nextIndex = (currentIndex + 1) % tabArray.length;
                            activateTab(tabArray[nextIndex]);
                            e.preventDefault();
                            break;
                        case 'ArrowLeft':
                            nextIndex = (currentIndex - 1 + tabArray.length) % tabArray.length;
                            activateTab(tabArray[nextIndex]);
                            e.preventDefault();
                            break;
                        case 'Home':
                            activateTab(tabArray[0]);
                            e.preventDefault();
                            break;
                        case 'End':
                            activateTab(tabArray[tabArray.length - 1]);
                            e.preventDefault();
                            break;
                    }
                });
            });
        });
    }

    /* ======================================================================
       Modals
       ====================================================================== */

    function initModals() {
        var modalTriggers = document.querySelectorAll('[data-modal-target]');
        var activeModal = null;
        var previousFocus = null;

        function openModal(overlay) {
            if (!overlay) return;

            previousFocus = document.activeElement;
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            activeModal = overlay;

            // Focus the close button or first focusable element
            var closeBtn = overlay.querySelector('.modal__close');
            if (closeBtn) {
                closeBtn.focus();
            }

            // Trap focus within modal
            overlay.addEventListener('keydown', trapFocus);
        }

        function closeModal(overlay) {
            if (!overlay) return;

            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            activeModal = null;

            overlay.removeEventListener('keydown', trapFocus);

            // Restore focus
            if (previousFocus) {
                previousFocus.focus();
                previousFocus = null;
            }
        }

        function trapFocus(e) {
            if (e.key !== 'Tab') return;

            var modal = activeModal.querySelector('.modal');
            if (!modal) return;

            var focusable = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            var firstFocusable = focusable[0];
            var lastFocusable = focusable[focusable.length - 1];

            if (e.shiftKey) {
                if (document.activeElement === firstFocusable) {
                    lastFocusable.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === lastFocusable) {
                    firstFocusable.focus();
                    e.preventDefault();
                }
            }
        }

        // Open modal triggers
        modalTriggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var targetId = trigger.getAttribute('data-modal-target');
                var overlay = document.getElementById(targetId);
                openModal(overlay);
            });
        });

        // Close modal buttons
        document.querySelectorAll('.modal__close, [data-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var overlay = btn.closest('.modal-overlay');
                closeModal(overlay);
            });
        });

        // Close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeModal(overlay);
                }
            });
        });

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && activeModal) {
                closeModal(activeModal);
            }
        });
    }

    /* ======================================================================
       Dynamic Product Filters
       ====================================================================== */

    function initProductFilters() {
        var filterContainers = document.querySelectorAll('.product-filters');

        filterContainers.forEach(function (container) {
            var buttons = container.querySelectorAll('.product-filter__btn');
            var targetGridId = container.getAttribute('data-filter-target');
            var grid = targetGridId ? document.getElementById(targetGridId) : null;

            if (!grid) return;

            var items = grid.querySelectorAll('.filterable-item');

            buttons.forEach(function (btn) {
                btn.setAttribute('aria-pressed', btn.classList.contains('product-filter__btn--active') ? 'true' : 'false');

                btn.addEventListener('click', function () {
                    var filter = btn.getAttribute('data-filter');

                    // Update active state
                    buttons.forEach(function (b) {
                        b.setAttribute('aria-pressed', 'false');
                        b.classList.remove('product-filter__btn--active');
                    });
                    btn.setAttribute('aria-pressed', 'true');
                    btn.classList.add('product-filter__btn--active');

                    // Filter items
                    items.forEach(function (item) {
                        var categories = item.getAttribute('data-category') || '';
                        var matches = filter === 'all' || categories.indexOf(filter) !== -1;

                        item.setAttribute('aria-hidden', String(!matches));
                    });
                });
            });
        });
    }

    /* ======================================================================
       Carousel (optional JS enhancement)
       ====================================================================== */

    function initCarousels() {
        var carousels = document.querySelectorAll('.carousel:not(.carousel--snap)');

        carousels.forEach(function (carousel) {
            var track = carousel.querySelector('.carousel__track');
            var slides = carousel.querySelectorAll('.carousel__slide');
            var prevBtn = carousel.querySelector('.carousel__arrow--prev');
            var nextBtn = carousel.querySelector('.carousel__arrow--next');
            var dots = carousel.querySelectorAll('.carousel__dot');
            var currentIndex = 0;
            var totalSlides = slides.length;

            if (!track || totalSlides === 0) return;

            function goToSlide(index) {
                if (index < 0) index = totalSlides - 1;
                if (index >= totalSlides) index = 0;

                currentIndex = index;
                track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';

                // Update dots
                dots.forEach(function (dot, i) {
                    dot.classList.toggle('carousel__dot--active', i === currentIndex);
                    dot.setAttribute('aria-current', i === currentIndex ? 'true' : 'false');
                });

                // Update aria-live for screen readers
                slides.forEach(function (slide, i) {
                    slide.setAttribute('aria-hidden', i !== currentIndex ? 'true' : 'false');
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', function () {
                    goToSlide(currentIndex - 1);
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    goToSlide(currentIndex + 1);
                });
            }

            dots.forEach(function (dot, i) {
                dot.addEventListener('click', function () {
                    goToSlide(i);
                });
            });

            // Initialize first slide
            goToSlide(0);
        });
    }

    /* ======================================================================
       Mega Menu
       ====================================================================== */

    function initMegaMenus() {
        var menus = document.querySelectorAll('.mega-menu');

        menus.forEach(function (menu) {
            var trigger = menu.querySelector('.mega-menu__trigger');
            var panel = menu.querySelector('.mega-menu__panel');

            if (!trigger || !panel) return;

            trigger.setAttribute('aria-expanded', 'false');
            panel.setAttribute('aria-hidden', 'true');

            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                var isExpanded = trigger.getAttribute('aria-expanded') === 'true';
                trigger.setAttribute('aria-expanded', String(!isExpanded));
                panel.setAttribute('aria-hidden', String(isExpanded));
            });

            // Close on click outside
            document.addEventListener('click', function (e) {
                if (!menu.contains(e.target)) {
                    trigger.setAttribute('aria-expanded', 'false');
                    panel.setAttribute('aria-hidden', 'true');
                }
            });

            // Close on Escape
            menu.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    trigger.setAttribute('aria-expanded', 'false');
                    panel.setAttribute('aria-hidden', 'true');
                    trigger.focus();
                }
            });
        });
    }

    /* ======================================================================
       Initialize all components on DOMContentLoaded
       ====================================================================== */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        initAccordions();
        initTabs();
        initModals();
        initProductFilters();
        initCarousels();
        initMegaMenus();
    }
})();
