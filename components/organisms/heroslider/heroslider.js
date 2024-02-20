/**
 * @file
 * JavaScript behaviors for Varbase Hero Slider.
 */

(function (Drupal, once, bootstrap) {

  'use strict';

  /**
   * Varbase Hero Slider Pause administration.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.varbaseHeroSliderPause = {
    attach: function (context) {
      once("pause-heroslider", ".carousel-control-pause", context).forEach((element => {
        element.addEventListener("click", (e => {
          bootstrap.Carousel.getInstance(document.querySelector(element.dataset.bsTarget)).pause();
        }));
      }));
    }
  };
  
  /**
   * Varbase Hero Slider integration with drimage.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.varbaseHeroSliderDrimage= {
    attach: function (context) {
      document.querySelector(".carousel.varbase-heroslider", context).addEventListener("slid.bs.carousel", (event => {
        Drupal.drimage.init(document.querySelector(".carousel.varbase-heroslider", context));
      }));
    }
  };
})(Drupal, once, bootstrap);