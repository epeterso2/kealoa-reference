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
                confirmMessage += ' This will also remove all person associations.';
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
         * Handle "Insert After" confirmation for rounds
         */
        $(document).on('click', '.kealoa-insert-after-link', function (e) {
            e.preventDefault();

            var $link = $(this);
            var gameNumber = $link.data('game-number');
            var nonce = $link.data('nonce');

            if (!confirm('Insert a new round after Game #' + gameNumber + '? This will shift all higher game numbers up by one.')) {
                return;
            }

            var $form = $('<form>', {
                method: 'POST',
                action: ''
            });

            $form.append($('<input>', { type: 'hidden', name: 'kealoa_action', value: 'insert_round_after' }));
            $form.append($('<input>', { type: 'hidden', name: 'game_number', value: gameNumber }));
            $form.append($('<input>', { type: 'hidden', name: 'kealoa_nonce', value: nonce }));

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
         * Image URL: https://www.xwordinfo.com/images/cons/{name with punctuation and spaces removed, hyphens kept}.jpg
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
                    var imageName = fullName.replace(/[^A-Za-z0-9\-]/g, '');
                    var imageUrl = 'https://www.xwordinfo.com/images/cons/' + encodeURIComponent(imageName) + '.jpg';
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
                        '<img src="' + imageUrl + '" alt="Person photo" style="max-width: 150px;" />' +
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
         * Refresh persons list when returning from Add Person page
         * Track when the Add Person link is clicked and refresh on window focus
         */
        var waitingForPersonRefresh = false;
        var personSelects = $('#puzzle_constructors, #constructors');
        
        // When the Add new person link is clicked, set flag
        $(document).on('click', 'a[href*="page=kealoa-persons&action=add"]', function () {
            waitingForPersonRefresh = true;
        });
        
        // On window focus, check if we need to refresh the persons list
        $(window).on('focus', function () {
            if (waitingForPersonRefresh && personSelects.length > 0) {
                waitingForPersonRefresh = false;
                refreshPersonsDropdown();
            }
        });
        
        /**
         * Fetch updated persons list via AJAX and update dropdowns
         */
        function refreshPersonsDropdown() {
            if (typeof kealoaAdmin === 'undefined') {
                return;
            }
            
            $.ajax({
                url: kealoaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kealoa_get_persons',
                    nonce: kealoaAdmin.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        updatePersonSelects(response.data);
                    }
                }
            });
        }
        
        /**
         * Update all person select elements with new persons data
         */
        function updatePersonSelects(persons) {
            personSelects.each(function () {
                var $select = $(this);
                var selectedValues = $select.val() || [];
                
                // Clear and rebuild options
                $select.empty();
                
                persons.forEach(function (person) {
                    var isSelected = selectedValues.indexOf(String(person.id)) !== -1;
                    $select.append(
                        $('<option>', {
                            value: person.id,
                            text: person.full_name,
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
         * Puzzle group management for clue forms (multi-puzzle clues)
         */

        // Clear All Puzzles button
        $(document).on('click', '.kealoa-clear-all-puzzles', function () {
            $('#kealoa-puzzle-groups').empty();
        });

        // Add Puzzle button
        $(document).on('click', '.kealoa-add-puzzle', function () {
            var $container = $('#kealoa-puzzle-groups');
            var nextIndex = $container.children('.kealoa-puzzle-group').length;

            // Clone from the first group if available, otherwise build from scratch
            var $firstGroup = $container.find('.kealoa-puzzle-group').first();
            var $newGroup;

            if ($firstGroup.length) {
                $newGroup = $firstGroup.clone();
                // Clear values
                $newGroup.find('input').val('');
                $newGroup.find('select').each(function () {
                    if ($(this).prop('multiple')) {
                        $(this).val([]);
                    } else {
                        $(this).val('');
                    }
                });
            } else {
                // Build a minimal template (reuses server-rendered structure)
                $newGroup = $('<div class="kealoa-puzzle-group">' +
                    '<fieldset style="border:1px solid #ccd0d4; padding:10px 15px; margin-bottom:10px;">' +
                    '<legend style="font-weight:600;">Puzzle 1</legend>' +
                    '<table class="form-table" style="margin:0;">' +
                    '<tr><th><label>NYT Puzzle Date</label></th>' +
                    '<td><input type="date" name="puzzles[0][date]" class="regular-text kealoa-puzzle-date" />' +
                    '<p class="description">If a puzzle with this date already exists, it will be used automatically.</p></td></tr>' +
                    '<tr><th><label>Constructors</label></th>' +
                    '<td><select name="puzzles[0][constructors][]" multiple class="kealoa-multi-select kealoa-puzzle-constructors" style="width: 100%; min-height: 120px;">' +
                    '</select><p class="description">Hold Ctrl/Cmd to select multiple. Only used when creating a new puzzle.</p></td></tr>' +
                    '<tr><th><label>Puzzle Clue Number</label></th>' +
                    '<td><input type="number" name="puzzles[0][clue_number]" min="1" class="kealoa-puzzle-clue-number" /></td></tr>' +
                    '<tr><th><label>Direction</label></th>' +
                    '<td><select name="puzzles[0][direction]" class="kealoa-puzzle-direction">' +
                    '<option value="">— None —</option><option value="A">Across</option><option value="D">Down</option>' +
                    '</select></td></tr>' +
                    '<tr><th><label>Clue Text *</label></th>' +
                    '<td><textarea name="puzzles[0][clue_text]" rows="2" class="large-text kealoa-puzzle-clue-text" required></textarea>' +
                    '<p class="description">The clue text as it appears in this puzzle.</p></td></tr>' +
                    '</table>' +
                    '<p><button type="button" class="button kealoa-remove-puzzle">Remove Puzzle</button></p>' +
                    '</fieldset></div>');
            }

            // Update data-index and field names
            $newGroup.attr('data-index', nextIndex);
            $newGroup.find('legend').text('Puzzle ' + (nextIndex + 1));
            $newGroup.find('[name]').each(function () {
                var name = $(this).attr('name');
                $(this).attr('name', name.replace(/puzzles\[\d+\]/, 'puzzles[' + nextIndex + ']'));
            });

            $container.append($newGroup);
        });

        // Remove Puzzle button
        $(document).on('click', '.kealoa-remove-puzzle', function () {
            $(this).closest('.kealoa-puzzle-group').remove();

            // Renumber remaining groups
            $('#kealoa-puzzle-groups .kealoa-puzzle-group').each(function (idx) {
                $(this).attr('data-index', idx);
                $(this).find('legend').text('Puzzle ' + (idx + 1));
                $(this).find('[name]').each(function () {
                    var name = $(this).attr('name');
                    $(this).attr('name', name.replace(/puzzles\[\d+\]/, 'puzzles[' + idx + ']'));
                });
            });
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

        // =================================================================
        // ALIAS GROUP FORM — enable/disable submit based on selection count
        // =================================================================

        var $aliasSelect = $('#alias-person-ids');
        var $aliasSubmit = $('#alias-submit');

        if ($aliasSelect.length) {
            $aliasSelect.on('change', function () {
                var count = $aliasSelect.find('option:selected').length;
                $aliasSubmit.prop('disabled', count < 2);
            });

            // Initial state check
            $aliasSubmit.prop('disabled', $aliasSelect.find('option:selected').length < 2);
        }

        // Alias form submit validation
        $('#kealoa-alias-form').on('submit', function (e) {
            var count = $aliasSelect.find('option:selected').length;
            if (count < 2) {
                e.preventDefault();
                alert('Please select at least two persons for the alias group.');
            }
        });

        // =================================================================
        // ALIAS GROUP LIST — delete confirmation
        // =================================================================

        $(document).on('click', '[data-delete-alias]', function (e) {
            e.preventDefault();
            var groupIndex = $(this).data('delete-alias');
            if (!confirm('Are you sure you want to delete this alias group?')) {
                return;
            }
            $('#delete-alias-group-index').val(groupIndex);
            $('#kealoa-delete-alias-form').submit();
        });

        // =================================================================
        // DATA CHECK PAGE — select-all and repair button wiring
        // =================================================================

        // "Select All" checkbox toggles all items in the same group
        $(document).on('change', '.kealoa-check-all', function () {
            var group = $(this).data('group');
            var checked = $(this).prop('checked');
            $('.kealoa-check-item[data-group="' + group + '"]').prop('checked', checked);
        });

        // Uncheck "Select All" when any item is unchecked
        $(document).on('change', '.kealoa-check-item', function () {
            var group = $(this).data('group');
            var allChecked = $('.kealoa-check-item[data-group="' + group + '"]').length ===
                             $('.kealoa-check-item[data-group="' + group + '"]:checked').length;
            $('.kealoa-check-all[data-group="' + group + '"]').prop('checked', allChecked);
        });

        // Populate hidden selected_ids field and confirm before submit
        $(document).on('click', '.kealoa-repair-btn', function (e) {
            var group = $(this).data('group');
            var $hidden = $('.kealoa-selected-ids[data-group="' + group + '"]');

            // For forms without selection (e.g. "Renumber All"), just confirm
            if (!$hidden.length) {
                if (!confirm('Are you sure you want to proceed?')) {
                    e.preventDefault();
                }
                return;
            }

            var ids = [];
            $('.kealoa-check-item[data-group="' + group + '"]:checked').each(function () {
                ids.push($(this).val());
            });

            if (ids.length === 0) {
                e.preventDefault();
                alert('Please select at least one record.');
                return;
            }

            $hidden.val(ids.join(','));

            if (!confirm('Are you sure you want to proceed with ' + ids.length + ' selected record(s)?')) {
                e.preventDefault();
            }
        });

    });

})(jQuery);
