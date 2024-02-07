
/**
 * @file
 * Behaviors for the Varbase Accordion.
 */

(function ($, Drupal) {

  /**
   * The Toggle all behavior for all accordion item in the accordion component.
   */
  Drupal.behaviors.varbaseAccordionToggleAll = {
    attach() {
      $('.accordion-toggle-all').click(function () {
        if ($(this).data('accordion-toggle-status') === 'collapsed') {

          $('#' + $(this).data('accordion-id') + ' .accordion-item .accordion-collapse.collapse')
            .collapse({parent: false, show: true})
            .collapse('show')
            .collapse({parent: $(this).data('accordion-id'), show: true});

          $(this).data('accordion-toggle-status', 'expanded');
          $(this).text($(this).data('collapsed-text'));
        } else {

          $('#' + $(this).data('accordion-id') + ' .accordion-item .accordion-collapse.collapse')
          .collapse({parent: false, hide: true})
          .collapse('hide')
          .collapse({parent: $(this).data('accordion-id'), hide: true});

          $(this).data('accordion-toggle-status', 'collapsed');
          $(this).text($(this).data('expanded-text'));
        }

      });

    },
  };
})(jQuery, Drupal);
