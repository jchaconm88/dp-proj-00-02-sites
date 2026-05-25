/**
 * Page Protection Dialog
 *
 * Shows confirmation dialogs when client role users attempt critical
 * page operations on protected pages (inicio, tienda, contacto).
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

(function () {
    'use strict';

    if (typeof miClientePageProtection === 'undefined') {
        return;
    }

    var config = miClientePageProtection;
    var protectedPages = config.protectedPages || [];
    var messages = config.messages || {};

    /**
     * Check if a page slug is in the protected list.
     *
     * @param {string} slug The page slug to check.
     * @return {boolean} True if the page is protected.
     */
    function isProtectedPage(slug) {
        if (!slug) {
            return false;
        }
        return protectedPages.indexOf(slug.toLowerCase()) !== -1;
    }

    /**
     * Get the page slug from a table row in the pages list.
     *
     * @param {HTMLElement} row The table row element.
     * @return {string|null} The page slug or null.
     */
    function getSlugFromRow(row) {
        var editLink = row.querySelector('.row-title');
        if (editLink && editLink.href) {
            // Try to extract slug from the row's inline edit data.
            var slugInput = row.querySelector('.inline-edit-slug input, [name="post_name"]');
            if (slugInput) {
                return slugInput.value;
            }
        }

        // Fallback: check the view link for the slug.
        var viewLink = row.querySelector('.view a');
        if (viewLink && viewLink.href) {
            var url = viewLink.href.replace(/\/$/, '');
            var parts = url.split('/');
            return parts[parts.length - 1];
        }

        // Fallback: use the row ID to find slug via title text.
        var titleEl = row.querySelector('.row-title');
        if (titleEl) {
            var title = titleEl.textContent.toLowerCase().trim();
            for (var i = 0; i < protectedPages.length; i++) {
                if (title === protectedPages[i]) {
                    return protectedPages[i];
                }
            }
        }

        return null;
    }

    /**
     * Intercept delete/trash actions on the pages list screen.
     */
    function protectDeleteActions() {
        document.addEventListener('click', function (e) {
            var target = e.target;

            // Check if clicking a trash/delete link.
            if (target.tagName !== 'A') {
                target = target.closest('a');
            }
            if (!target) {
                return;
            }

            var href = target.getAttribute('href') || '';
            var isTrash = href.indexOf('action=trash') !== -1;
            var isDelete = href.indexOf('action=delete') !== -1;

            if (!isTrash && !isDelete) {
                return;
            }

            // Find the parent row and check if it's a protected page.
            var row = target.closest('tr');
            if (!row) {
                return;
            }

            var slug = getSlugFromRow(row);
            if (isProtectedPage(slug)) {
                var confirmed = window.confirm(messages.deleteWarning);
                if (!confirmed) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }
        }, true);
    }

    /**
     * Intercept template changes in the page editor.
     */
    function protectTemplateChanges() {
        // Watch for template selector changes in the block editor sidebar.
        var observer = new MutationObserver(function () {
            var templateSelect = document.querySelector(
                '.editor-page-attributes__template select, ' +
                '[class*="page-template"] select'
            );

            if (templateSelect && !templateSelect.dataset.miClienteProtected) {
                templateSelect.dataset.miClienteProtected = 'true';
                templateSelect.addEventListener('change', function (e) {
                    // Check if we're editing a protected page.
                    var slug = getEditorPageSlug();
                    if (isProtectedPage(slug)) {
                        var confirmed = window.confirm(messages.templateWarning);
                        if (!confirmed) {
                            e.preventDefault();
                            // Revert the select value.
                            e.target.value = e.target.dataset.previousValue || '';
                        }
                    }
                });
                templateSelect.addEventListener('focus', function (e) {
                    e.target.dataset.previousValue = e.target.value;
                });
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    /**
     * Get the current page slug from the block editor.
     *
     * @return {string|null} The page slug or null.
     */
    function getEditorPageSlug() {
        // Try the permalink/slug input in the editor.
        var slugInput = document.querySelector(
            '.editor-post-slug input, ' +
            '[class*="post-slug"] input, ' +
            '#edit-slug-box #new-post-slug'
        );
        if (slugInput) {
            return slugInput.value;
        }

        // Try the URL in the permalink display.
        var permalinkEl = document.querySelector('.editor-post-link__link');
        if (permalinkEl) {
            var url = permalinkEl.textContent.replace(/\/$/, '');
            var parts = url.split('/');
            return parts[parts.length - 1];
        }

        return null;
    }

    /**
     * Protect homepage settings on the Reading settings page.
     */
    function protectHomepageSettings() {
        var form = document.querySelector('form[action="options.php"]');
        if (!form) {
            return;
        }

        // Check if we're on the reading settings page.
        var pageOnFront = document.querySelector('#page_on_front');
        var pageForPosts = document.querySelector('#page_for_posts');
        var showOnFront = document.querySelectorAll('[name="show_on_front"]');

        if (!pageOnFront && !pageForPosts && showOnFront.length === 0) {
            return;
        }

        form.addEventListener('submit', function (e) {
            var confirmed = window.confirm(messages.homepageWarning);
            if (!confirmed) {
                e.preventDefault();
            }
        });
    }

    // Initialize protections when DOM is ready.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        protectDeleteActions();
        protectTemplateChanges();
        protectHomepageSettings();
    }
})();
