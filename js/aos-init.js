/**
 * @file
 * Initializes AOS (Animate On Scroll) as a Drupal behavior.
 *
 * AOS is loaded via the varbase_components/aos library, which is attached
 * only when a component has an animation selected. This file runs after
 * aos.js is available on window.AOS.
 */
(function (Drupal, once) {
  "use strict";

  Drupal.behaviors.varbaseComponentsAos = {
    attach: function (context, settings) {
      once("varbase-aos-init", "html", context).forEach(function () {
        if (typeof AOS !== "undefined") {
          AOS.init({
            once: true,
            disable: window.matchMedia("(prefers-reduced-motion: reduce)")
              .matches,
          });
        }
      });
    },
  };
})(Drupal, once);
