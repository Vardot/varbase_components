/**
 * @file
 * JavaScript behaviors for Varbase Hero Slider.
 */

(function ($, Drupal, once) {

  'use strict';

  /**
   * Varbase Hero Slider Pause behaviors.
   *
   * @type {Drupal~behavior}
   * 
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior for pausing or sliding.
   */
  Drupal.behaviors.varbaseHeroSliderPause = {
    attach: function (context, settings) {

      // When clicking on the pause button.
      $(
        once(
          'varbase-heroslider-pause',
          '.js-carousel-control-pause',
          context,
        ),
      )
      .on('click', (event) => {
        const target_heroslider = $($(event.currentTarget).data('bs-target'));
        target_heroslider.carousel({
          interval: target_heroslider.data('bs-interval'),
          pause: false,
        });
        target_heroslider.carousel('pause');
        event.preventDefault();
        event.stopPropagation();
      });

      // When clicking on next slide or previous slide buttons.
      // reset the pause to on the default data-bs-pause value (hover)
      // and slide.
      $(
        once(
          'varbase-heroslider-carousel',
          '.js-carousel-control-next , .js-carousel-control-prev',
          context,
        ),
      ).on('click', (event) => {
        const target_heroslider = $($(event.currentTarget).data('bs-target'));
        target_heroslider.carousel({
          interval: target_heroslider.data('bs-interval'),
          pause: target_heroslider.data('bs-pause'),
        });
        target_heroslider.carousel($(event.currentTarget).data('bs-slide'));
      });
    }
  };

  /**
   * Varbase Hero Slider integration with drimage behaviors.
   *
   * @type {Drupal~behavior}
   * 
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior for generating an image with view width and height using drimage.
   */
  Drupal.behaviors.varbaseHeroSliderDrimage = {
    attach: function (context, settings) {
      document.querySelector(".carousel.varbase-heroslider", context).addEventListener("slid.bs.carousel", (event => {
        Drupal.drimage.init(document.querySelector(".carousel.varbase-heroslider", context));
      }));
    }
  };
})(jQuery, Drupal, once);