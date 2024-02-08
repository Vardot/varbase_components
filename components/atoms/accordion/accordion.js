
/**
 * @file
 * Behaviors for the Varbase Accordion.
 */

(function ($, Drupal) {

  /**
   * Toggle all behavior for all accordion items in an Accordion component.
   */
  Drupal.behaviors.varbaseAccordionToggleAll = {
    attach() {
      $(".accordion-toggle-all").click(function () {
        if ($(this).data("toggle-status") === "collapsed") {

          $("#" + $(this).data("accordion-id") + " .accordion-item .accordion-collapse")
            .collapse({parent: false, toggle: false})
            .collapse("show")
            .collapse({parent: $(this).data("accordion-id"), toggle: true});

          $(this).data("toggle-status", "expanded");
          $(this).text($(this).data("collapsed-text"));
        } else {

          $("#" + $(this).data("accordion-id") + " .accordion-item .accordion-collapse").collapse("hide");

          $(this).data("toggle-status", "collapsed");
          $(this).text($(this).data("expanded-text"));
        }

      });

    },
  };
})(jQuery, Drupal);
