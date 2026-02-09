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
        'sunday': 0,
        'monday': 1,
        'tuesday': 2,
        'wednesday': 3,
        'thursday': 4,
        'friday': 5,
        'saturday': 6
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
})();
