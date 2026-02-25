/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
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
         * Convert HH:MM:SS to seconds
         */
        function timeToSeconds(time) {
            if (!time) return 0;
            time = time.toString().trim();
            if (!isNaN(time)) return parseInt(time, 10);
            var parts = time.split(':');
            if (parts.length === 3) {
                return (parseInt(parts[0], 10) * 3600) + (parseInt(parts[1], 10) * 60) + parseInt(parts[2], 10);
            } else if (parts.length === 2) {
                return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
            }
            return 0;
        }

        /**
         * Episode link preview
         */
        $('#episode_url, #episode_start_time').on('change keyup', function () {
            var episodeUrl = $('#episode_url').val();
            var startSeconds = timeToSeconds($('#episode_start_time').val());
            
            if (episodeUrl) {
                var url = episodeUrl + '?t=' + startSeconds;
                
                if (!$('#episode-preview').length) {
                    $('#episode_url').closest('td').append(
                        '<p id="episode-preview" class="description" style="margin-top: 10px;">' +
                        '<a href="' + url + '" target="_blank">Preview Episode Link</a>' +
                        '</p>'
                    );
                } else {
                    $('#episode-preview a').attr('href', url);
                }
            } else {
                $('#episode-preview').remove();
            }
        });

        // Trigger episode preview on page load if values exist
        if ($('#episode_url').val()) {
            $('#episode_url').trigger('change');
        }

        /**
         * Auto-populate XWordInfo fields from constructor name
         * Profile URL: https://www.xwordinfo.com/Author/{name with spaces as underscores}
         * Image URL: https://www.xwordinfo.com/images/cons/{name with spaces removed}.jpg
         */
        $('#full_name').on('change blur', function () {
            var fullName = $(this).val().trim();
            var $profileField = $('#xwordinfo_profile_name');
            var $imageField = $('#xwordinfo_image_url');
            
            // Only auto-populate if on constructor form (both fields exist) and they're empty
            if ($profileField.length && fullName) {
                if (!$profileField.val()) {
                    var profileName = fullName.replace(/ /g, '_');
                    $profileField.val(profileName);
                    $profileField.trigger('change');
                }
            }
            
            if ($imageField.length && fullName) {
                if (!$imageField.val()) {
                    var imageName = fullName.replace(/ /g, '');
                    var imageUrl = 'https://www.xwordinfo.com/images/cons/' + imageName + '.jpg';
                    $imageField.val(imageUrl);
                    $imageField.trigger('change');
                }
            }
        });

        /**
         * XWordInfo profile link preview
         */
        $('#xwordinfo_profile_name').on('change blur', function () {
            var profileName = $(this).val();
            
            if (profileName) {
                // Profile name already has underscores, use as-is
                var url = 'https://www.xwordinfo.com/Author/' + profileName;
                
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
         * XWordInfo image preview
         */
        $('#xwordinfo_image_url').on('change blur', function () {
            var imageUrl = $(this).val();
            
            if (imageUrl) {
                if (!$('#xwordinfo-image-preview').length) {
                    $(this).closest('td').find('.description').first().after(
                        '<p id="xwordinfo-image-preview" style="margin-top: 10px;">' +
                        '<img src="' + imageUrl + '" alt="Constructor photo" style="max-width: 150px;" />' +
                        '</p>'
                    );
                } else {
                    $('#xwordinfo-image-preview img').attr('src', imageUrl);
                }
            } else {
                $('#xwordinfo-image-preview').remove();
            }
        });

        // Trigger XWordInfo image preview on page load if value exists
        if ($('#xwordinfo_image_url').val()) {
            $('#xwordinfo_image_url').trigger('change');
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

        /**
         * Refresh constructors list when returning from Add Constructor page
         * Track when the Add Constructor link is clicked and refresh on window focus
         */
        var waitingForConstructorRefresh = false;
        var constructorSelects = $('#puzzle_constructors, #new_puzzle_constructors, #constructors');
        
        // When the Add new constructor link is clicked, set flag
        $(document).on('click', 'a[href*="page=kealoa-constructors&action=add"]', function () {
            waitingForConstructorRefresh = true;
        });
        
        // On window focus, check if we need to refresh the constructors list
        $(window).on('focus', function () {
            if (waitingForConstructorRefresh && constructorSelects.length > 0) {
                waitingForConstructorRefresh = false;
                refreshConstructorsDropdown();
            }
        });
        
        /**
         * Fetch updated constructors list via AJAX and update dropdowns
         */
        function refreshConstructorsDropdown() {
            if (typeof kealoaAdmin === 'undefined') {
                return;
            }
            
            $.ajax({
                url: kealoaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kealoa_get_constructors',
                    nonce: kealoaAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        updateConstructorSelects(response.data);
                    }
                }
            });
        }
        
        /**
         * Update all constructor select elements with new constructors data
         */
        function updateConstructorSelects(constructors) {
            constructorSelects.each(function () {
                var $select = $(this);
                var selectedValues = $select.val() || [];
                
                // Clear and rebuild options
                $select.empty();
                
                constructors.forEach(function (constructor) {
                    var isSelected = selectedValues.indexOf(String(constructor.id)) !== -1;
                    $select.append(
                        $('<option>', {
                            value: constructor.id,
                            text: constructor.full_name,
                            selected: isSelected
                        })
                    );
                });
                
                // Flash the select to indicate it was updated
                $select.css('background-color', '#e7f5e7');
                setTimeout(function () {
                    $select.css('background-color', '');
                }, 1500);
            });
        }

        /**
         * Clear Puzzle Details button on clue edit form
         */
        $(document).on('click', '.kealoa-clear-puzzle-details', function () {
            $('#puzzle_date').val('');
            $('#puzzle_constructors').val([]).trigger('change');
            $('#puzzle_clue_number').val('');
            $('#puzzle_clue_direction').val('');
        });

        /**
         * Media Library picker for person/constructor forms
         */
        $(document).on('click', '.kealoa-select-media', function (e) {
            e.preventDefault();
            var $button = $(this);
            var targetInput = $button.data('target');
            var previewDiv = $button.data('preview');

            var frame = wp.media({
                title: 'Select Photo',
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $(targetInput).val(attachment.id);
                var imgUrl = attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;
                $(previewDiv).html('<img src="' + imgUrl + '" style="max-width:150px;" />');
                $button.siblings('.kealoa-remove-media').show();
            });

            frame.open();
        });

        $(document).on('click', '.kealoa-remove-media', function (e) {
            e.preventDefault();
            var $button = $(this);
            var targetInput = $button.data('target');
            var previewDiv = $button.data('preview');
            $(targetInput).val('');
            $(previewDiv).html('');
            $button.hide();
        });

    });

})(jQuery);
