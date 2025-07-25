/**
 * admin\js\pattern-type.js
 * IntelliSend Pattern Type handling
 * 
 * Adds support for Pattern Type configuration in routing rules
 */

jQuery(document).ready(function($) {
    // Add pattern type to the form data when adding a new rule
    $(document).on('click', '.save-new-rule', function() {
        var $row = $(this).closest('tr');
        var patternType = $row.find('.rule-pattern-type').val();
        
        // Store the pattern type in a hidden field to be included in the form data
        if (!$row.find('.rule-pattern-type-hidden').length) {
            $row.append('<input type="hidden" class="rule-pattern-type-hidden" name="pattern_type" value="' + patternType + '">');
        } else {
            $row.find('.rule-pattern-type-hidden').val(patternType);
        }
    });
    
    // Add pattern type to the form data when updating an existing rule
    $(document).on('click', '.save-rule', function() {
        var $row = $(this).closest('tr');
        var patternType = $row.find('.rule-pattern-type').val();
        
        // Store the pattern type in a hidden field to be included in the form data
        if (!$row.find('.rule-pattern-type-hidden').length) {
            $row.append('<input type="hidden" class="rule-pattern-type-hidden" name="pattern_type" value="' + patternType + '">');
        } else {
            $row.find('.rule-pattern-type-hidden').val(patternType);
        }
    });
    
    // Update the view mode display when pattern type is changed
    $(document).on('change', '.rule-pattern-type', function() {
        var $select = $(this);
        var $cell = $select.closest('td');
        var selectedText = $select.find('option:selected').text();
        
        $cell.find('.view-mode').text(selectedText);
    });
});
