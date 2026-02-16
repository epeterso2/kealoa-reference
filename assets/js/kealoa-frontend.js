/**
 * KEALOA Reference - Frontend JavaScript
 *
 * Handles interactive table sorting on the frontend.
 *
 * @package KEALOA_Reference
 */

(function () {
    'use strict';

    /**
     * Weekday order map for sorting
     */
    var WEEKDAY_ORDER = {
        'sunday': 0, 'sun': 0,
        'monday': 1, 'mon': 1,
        'tuesday': 2, 'tue': 2,
        'wednesday': 3, 'wed': 3,
        'thursday': 4, 'thu': 4,
        'friday': 5, 'fri': 5,
        'saturday': 6, 'sat': 6
    };

    /**
     * Parse a date string in M/D/YYYY format to a sortable timestamp.
     *
     * @param {string} text Cell text content
     * @return {number} Timestamp for sorting
     */
    function parseDateValue(text) {
        text = text.trim();

        // Match M/D/YYYY
        var match = text.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
        if (match) {
            return new Date(
                parseInt(match[3], 10),
                parseInt(match[1], 10) - 1,
                parseInt(match[2], 10)
            ).getTime();
        }

        // Fallback: try native Date parsing
        var d = new Date(text);
        if (!isNaN(d.getTime())) {
            return d.getTime();
        }

        return 0;
    }

    /**
     * Parse a numeric value from cell text, stripping %, commas, etc.
     *
     * @param {string} text Cell text content
     * @return {number} Numeric value
     */
    function parseNumericValue(text) {
        text = text.trim().replace(/[,%$]/g, '');
        var val = parseFloat(text);
        return isNaN(val) ? 0 : val;
    }

    /**
     * Parse a clue reference like "42D" or "1A" into a sortable numeric value.
     * Sorts by number first, then direction (A before D).
     *
     * @param {string} text Cell text content
     * @return {number} Sortable value
     */
    function parseClueValue(text) {
        text = text.trim();
        var match = text.match(/^(\d+)\s*([A-Za-z]?)/);
        if (match) {
            var num = parseInt(match[1], 10);
            var dir = (match[2] || '').toUpperCase();
            // A=0, D=1, anything else=2
            var dirOrder = dir === 'A' ? 0 : (dir === 'D' ? 1 : 2);
            return num * 10 + dirOrder;
        }
        return 0;
    }

    /**
     * Get the sortable value from a cell based on sort type.
     *
     * @param {HTMLTableCellElement} cell The table cell
     * @param {string} sortType The sort type (date, number, weekday, text)
     * @return {*} Sortable value
     */
    function getSortValue(cell, sortType) {
        var text = (cell.textContent || '').trim();

        switch (sortType) {
            case 'date':
                return parseDateValue(text);

            case 'number':
                return parseNumericValue(text);

            case 'clue':
                return parseClueValue(text);

            case 'weekday':
                var lower = text.toLowerCase();
                return (lower in WEEKDAY_ORDER) ? WEEKDAY_ORDER[lower] : 99;

            case 'text':
            default:
                return text.toLowerCase();
        }
    }

    /**
     * Compare two sort values.
     *
     * @param {*} a First value
     * @param {*} b Second value
     * @param {boolean} ascending Sort direction
     * @return {number} Comparison result
     */
    function compareValues(a, b, ascending) {
        var result;

        if (typeof a === 'number' && typeof b === 'number') {
            result = a - b;
        } else {
            var strA = String(a);
            var strB = String(b);
            result = strA.localeCompare(strB);
        }

        return ascending ? result : -result;
    }

    /**
     * Sort a table by the given column index.
     *
     * @param {HTMLTableElement} table The table to sort
     * @param {number} colIndex Column index to sort by
     * @param {string} sortType Data type for sorting
     * @param {boolean} ascending Sort direction
     */
    function sortTable(table, colIndex, sortType, ascending) {
        var tbody = table.querySelector('tbody');
        if (!tbody) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

        rows.sort(function (rowA, rowB) {
            var cellA = rowA.cells[colIndex];
            var cellB = rowB.cells[colIndex];

            if (!cellA || !cellB) {
                return 0;
            }

            var valA = getSortValue(cellA, sortType);
            var valB = getSortValue(cellB, sortType);

            return compareValues(valA, valB, ascending);
        });

        // Re-append rows in sorted order
        for (var i = 0; i < rows.length; i++) {
            tbody.appendChild(rows[i]);
        }
    }

    /**
     * Update sort indicator classes on table headers.
     *
     * @param {HTMLTableElement} table The table
     * @param {number} activeIndex Active sorted column index
     * @param {boolean} ascending Current sort direction
     */
    function updateSortIndicators(table, activeIndex, ascending) {
        var headers = table.querySelectorAll('thead th[data-sort]');

        for (var i = 0; i < headers.length; i++) {
            var th = headers[i];
            th.classList.remove('kealoa-sort-asc', 'kealoa-sort-desc', 'kealoa-sort-active');
        }

        var allHeaders = table.querySelectorAll('thead th');
        if (allHeaders[activeIndex] && allHeaders[activeIndex].hasAttribute('data-sort')) {
            allHeaders[activeIndex].classList.add('kealoa-sort-active');
            allHeaders[activeIndex].classList.add(ascending ? 'kealoa-sort-asc' : 'kealoa-sort-desc');
        }
    }

    /**
     * Initialize sorting for all kealoa tables on the page.
     */
    function initTableSorting() {
        var tables = document.querySelectorAll('.kealoa-table');

        for (var t = 0; t < tables.length; t++) {
            (function (table) {
                var headers = table.querySelectorAll('thead th[data-sort]');

                if (headers.length === 0) {
                    return;
                }

                // Store sort state per table
                table._kealoaSortCol = -1;
                table._kealoaSortAsc = true;

                for (var h = 0; h < headers.length; h++) {
                    (function (th) {
                        th.style.cursor = 'pointer';
                        th.setAttribute('title', 'Click to sort');

                        th.addEventListener('click', function () {
                            var allHeaders = table.querySelectorAll('thead th');
                            var colIndex = Array.prototype.indexOf.call(allHeaders, th);
                            var sortType = th.getAttribute('data-sort');

                            // Toggle direction if same column clicked again
                            if (table._kealoaSortCol === colIndex) {
                                table._kealoaSortAsc = !table._kealoaSortAsc;
                            } else {
                                table._kealoaSortCol = colIndex;
                                table._kealoaSortAsc = true;
                            }

                            sortTable(table, colIndex, sortType, table._kealoaSortAsc);
                            updateSortIndicators(table, colIndex, table._kealoaSortAsc);
                        });
                    })(headers[h]);
                }
            })(tables[t]);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableSorting);
    } else {
        initTableSorting();
    }

    /**
     * Tab switching for person view
     */
    function initTabs() {
        var tabContainers = document.querySelectorAll('.kealoa-tabs');
        tabContainers.forEach(function(container) {
            var buttons = container.querySelectorAll('.kealoa-tab-button');
            var panels = container.querySelectorAll('.kealoa-tab-panel');
            
            buttons.forEach(function(button) {
                button.addEventListener('click', function() {
                    var tabName = this.getAttribute('data-tab');
                    
                    buttons.forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                    panels.forEach(function(panel) {
                        panel.classList.remove('active');
                    });
                    
                    this.classList.add('active');
                    var targetPanel = container.querySelector('.kealoa-tab-panel[data-tab="' + tabName + '"]');
                    if (targetPanel) {
                        targetPanel.classList.add('active');
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        initTabs();
    }

    /**
     * Round Picker - navigate to rounds matching an aggregate value
     *
     * Usage: Add class "kealoa-round-picker-link" to an element with
     * data-rounds='[{"id":1,"date":"1/1/2024","words":"FOO BAR","score":"8/10"}]'
     *
     * If there is only one round, navigates directly.
     * If there are multiple, shows a picker modal.
     */
    function initRoundPicker() {
        // Create the modal overlay once
        var overlay = document.createElement('div');
        overlay.className = 'kealoa-round-picker-overlay';
        overlay.innerHTML =
            '<div class="kealoa-round-picker-modal">' +
                '<div class="kealoa-round-picker-header">' +
                    '<h3>Select a Round</h3>' +
                    '<button class="kealoa-round-picker-close" aria-label="Close">&times;</button>' +
                '</div>' +
                '<ul class="kealoa-round-picker-list"></ul>' +
            '</div>';
        document.body.appendChild(overlay);

        var listEl = overlay.querySelector('.kealoa-round-picker-list');
        var closeBtn = overlay.querySelector('.kealoa-round-picker-close');

        function closeModal() {
            overlay.classList.remove('active');
        }

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        /**
         * Open the round picker for a set of rounds.
         * @param {Array} rounds - Array of {id, date, words, score} objects
         */
        window.kealoaOpenRoundPicker = function(rounds) {
            if (!rounds || rounds.length === 0) {
                return;
            }

            if (rounds.length === 1) {
                window.location.href = rounds[0].url;
                return;
            }

            listEl.innerHTML = '';
            rounds.forEach(function(r) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.href = r.url;
                a.innerHTML =
                    '<span class="kealoa-round-picker-date">' + escapeHtml(r.date) + '</span>' +
                    '<span class="kealoa-round-picker-words">' + escapeHtml(r.words) + '</span>' +
                    (r.score ? '<span class="kealoa-round-picker-score">' + escapeHtml(r.score) + '</span>' : '');
                li.appendChild(a);
                listEl.appendChild(li);
            });

            overlay.classList.add('active');
        };

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }

        // Delegate click on all round picker links
        document.addEventListener('click', function(e) {
            var link = e.target.closest('.kealoa-round-picker-link');
            if (!link) return;

            e.preventDefault();
            var roundsData = link.getAttribute('data-rounds');
            if (roundsData) {
                try {
                    var rounds = JSON.parse(roundsData);
                    window.kealoaOpenRoundPicker(rounds);
                } catch (ex) {
                    // ignore parse errors
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRoundPicker);
    } else {
        initRoundPicker();
    }
})();

/**
 * Frontend Media Picker (admin only)
 *
 * Allows admins to select/remove photos from the WordPress media library
 * directly on the person, constructor, and editor views.
 */
(function () {
    'use strict';

    // Only available for logged-in admins (kealoaMedia is localized only for them)
    if (typeof kealoaMedia === 'undefined' || typeof wp === 'undefined' || typeof wp.media === 'undefined') {
        // Hide picker buttons if media library is not available
        document.querySelectorAll('.kealoa-media-picker').forEach(function (el) {
            el.style.display = 'none';
        });
        return;
    }

    function initMediaPicker() {
        document.querySelectorAll('.kealoa-media-picker').forEach(function (picker) {
            var entityType = picker.getAttribute('data-entity-type');
            var entityId = picker.getAttribute('data-entity-id');
            var selectBtn = picker.querySelector('.kealoa-media-select-btn');
            var removeBtn = picker.querySelector('.kealoa-media-remove-btn');

            // Find the image container (sibling before the picker)
            var parentInfo = picker.closest('.kealoa-person-info, .kealoa-constructor-info, .kealoa-editor-info');
            var imageClass = 'kealoa-' + entityType + '-image';

            selectBtn.addEventListener('click', function () {
                var frame = wp.media({
                    title: 'Select Photo',
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var mediaId = attachment.id;
                    var imgUrl = attachment.sizes && attachment.sizes.medium
                        ? attachment.sizes.medium.url
                        : attachment.url;

                    // Save via AJAX
                    var formData = new FormData();
                    formData.append('action', 'kealoa_save_media');
                    formData.append('nonce', kealoaMedia.nonce);
                    formData.append('entity_type', entityType);
                    formData.append('entity_id', entityId);
                    formData.append('media_id', mediaId);

                    fetch(kealoaMedia.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    }).then(function (response) {
                        return response.json();
                    }).then(function (data) {
                        if (data.success) {
                            // Update or create the image element
                            var imageDiv = parentInfo ? parentInfo.querySelector('.' + imageClass) : null;
                            if (!imageDiv) {
                                imageDiv = document.createElement('div');
                                imageDiv.className = imageClass;
                                if (parentInfo) {
                                    parentInfo.insertBefore(imageDiv, parentInfo.firstChild);
                                }
                            }
                            imageDiv.innerHTML = '<img src="' + imgUrl + '" alt="" class="kealoa-entity-image" />';
                            removeBtn.style.display = '';
                        }
                    });
                });

                frame.open();
            });

            removeBtn.addEventListener('click', function () {
                var formData = new FormData();
                formData.append('action', 'kealoa_save_media');
                formData.append('nonce', kealoaMedia.nonce);
                formData.append('entity_type', entityType);
                formData.append('entity_id', entityId);
                formData.append('media_id', 0);

                fetch(kealoaMedia.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                }).then(function (response) {
                    return response.json();
                }).then(function (data) {
                    if (data.success) {
                        // Reload page to show fallback image or no image
                        window.location.reload();
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMediaPicker);
    } else {
        initMediaPicker();
    }
})();
