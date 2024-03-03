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
        target_heroslider.carousel('pause');
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

      var timer;

      $(
        once(
          'varbase-heroslider-drimage',
          '.js-varbase-heroslider',
          context,
        ),
      )
      .on('slid.bs.carousel', (event) => {
        clearTimeout(timer);
        timer = setTimeout(Drupal.drimage.init, 100, event.currentTarget);
      })
      .on('slide.bs.carousel', (event) => {
        clearTimeout(timer);
        timer = setTimeout(Drupal.drimage.init, 100, event.currentTarget);
      });
    }
  };
})(jQuery, Drupal, once);