(function ($) {
    'use strict';

    // Bootstrap only hides a modal. Discard unsaved edits when it closes,
    // restoring the values rendered from the database, including styled selects.
    $(document).on('hidden.bs.modal', '.modal[data-ss-reset-on-close]', function () {
        $(this).find('form').each(function () {
            this.reset();
            delete this.dataset.ssConfirmed;
            $(this).find('select').each(function () {
                if ($(this).data('selectpicker')) $(this).selectpicker('refresh');
            });
        });
    });
})(window.jQuery);
