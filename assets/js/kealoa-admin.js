/**
 * KEALOA Reference - Admin JavaScript
 *
 * Handles admin interface functionality.
 *
 * @package KEALOA_Reference
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        /**
         * Handle delete confirmations
         */
        $(document).on('click', '.kealoa-delete-link', function (e) {
            e.preventDefault();

            var $link = $(this);
            var type = $link.data('type');
            var id = $link.data('id');
            var nonce = $link.data('nonce');

            var confirmMessage = 'Are you sure you want to delete this ' + type + '?';
            
            if (type === 'round') {
                confirmMessage += ' This will also delete all associated clues and guesses.';
            } else if (type === 'puzzle') {
                confirmMessage += ' This will also remove all constructor associations.';
            }

            if (!confirm(confirmMessage)) {
                return;
            }

            // Create a form and submit it
            var $form = $('<form>', {
                method: 'POST',
                action: ''
            });

            $form.append($('<input>', {
                type: 'hidden',
                name: 'kealoa_action',
                value: 'delete_' + type
            }));

            $form.append($('<input>', {
                type: 'hidden',
                name: 'id',
                value: id
            }));

            $form.append($('<input>', {
                type: 'hidden',
                name: 'kealoa_nonce',
                value: nonce
            }));

            $('body').append($form);
            $form.submit();
        });

        /**
         * Handle inline clue editing (placeholder for future implementation)
         */
        $(document).on('click', '.kealoa-edit-clue', function (e) {
            e.preventDefault();
            var clueId = $(this).data('clue-id');
            // For now, redirect to a clue edit page or show a modal
            alert('Clue editing will be implemented in a future update. Clue ID: ' + clueId);
        });

        /**
         * Auto-uppercase solution words input
         */
        $('#solution_words').on('blur', function () {
            $(this).val($(this).val().toUpperCase());
        });

        /**
         * Auto-uppercase correct answer selection
         */
        $('#correct_answer').on('change', function () {
            // Value is already uppercase from the options
        });

        /**
         * Episode link preview
         */
        $('#episode_number, #episode_start_seconds').on('change keyup', function () {
            var episodeNumber = $('#episode_number').val();
            var startSeconds = $('#episode_start_seconds').val() || 0;
            
            if (episodeNumber) {
                var url = 'https://bemoresmarter.libsyn.com/player?episode=' + episodeNumber + '&startTime=' + startSeconds;
                
                if (!$('#episode-preview').length) {
                    $('#episode_number').closest('td').append(
                        '<p id="episode-preview" class="description" style="margin-top: 10px;">' +
                        '<a href="' + url + '" target="_blank">Preview Episode Link</a>' +
                        '</p>'
                    );
                } else {
                    $('#episode-preview a').attr('href', url);
                }
            }
        });

        // Trigger episode preview on page load if values exist
        if ($('#episode_number').val()) {
            $('#episode_number').trigger('change');
        }

        /**
         * XWordInfo profile link preview
         */
        $('#xwordinfo_profile_name').on('change blur', function () {
            var profileName = $(this).val();
            
            if (profileName) {
                var urlName = profileName.replace(/ /g, '_');
                var url = 'https://www.xwordinfo.com/Author/' + urlName;
                
                if (!$('#xwordinfo-preview').length) {
                    $(this).closest('td').find('.description').first().after(
                        '<p id="xwordinfo-preview" class="description" style="margin-top: 5px;">' +
                        '<a href="' + url + '" target="_blank">Preview XWordInfo Profile</a>' +
                        '</p>'
                    );
                } else {
                    $('#xwordinfo-preview a').attr('href', url);
                }
            } else {
                $('#xwordinfo-preview').remove();
            }
        });

        // Trigger XWordInfo preview on page load if value exists
        if ($('#xwordinfo_profile_name').val()) {
            $('#xwordinfo_profile_name').trigger('change');
        }

        /**
         * Form validation
         */
        $('.kealoa-form').on('submit', function (e) {
            var $form = $(this);
            var isValid = true;

            // Check required fields
            $form.find('[required]').each(function () {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        /**
         * Clear error class on input
         */
        $('.kealoa-form input, .kealoa-form select, .kealoa-form textarea').on('change keyup', function () {
            $(this).removeClass('error');
        });

        /**
         * Date validation for puzzles
         */
        $('#publication_date').on('change', function () {
            var date = new Date($(this).val());
            var dayName = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][date.getDay()];
            
            if (!$('#day-of-week-display').length) {
                $(this).after('<span id="day-of-week-display" style="margin-left: 10px; color: #666;"></span>');
            }
            
            $('#day-of-week-display').text('(' + dayName + ')');
        });

        // Trigger day display on page load if value exists
        if ($('#publication_date').val()) {
            $('#publication_date').trigger('change');
        }

    });

})(jQuery);
