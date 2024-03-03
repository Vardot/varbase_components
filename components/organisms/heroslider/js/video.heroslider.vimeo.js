/**
 * @file
 * Behaviors of Varbase heroslider for vimeo embedded videos scripts.
 */

(function ($, Drupal) {
  Drupal.behaviors.varbaseHeroSliderVimeoVideo = {
    attach(context) {

      const mediaSliders = $('.js-varbase-heroslider', context);

      // On before slide change.
      mediaSliders.on('slide.bs.carousel',function (event) {
          const currentVideo = $(this)
            .find('.carousel-item.active', context)
            .find('.varbase-video-player iframe[src*="vimeo.com"]', context,
          );
          if (currentVideo.length > 0) {
            currentVideo.get(0).contentWindow.postMessage('pause', '*');
          }
        },
      );

      // On after slide change.
      mediaSliders.on('slid.bs.carousel', function (event) {
        const currentVideo = $(this)
        .find('.carousel-item.active', context)
        .find('.varbase-video-player iframe[src*="vimeo.com"]', context);

        if (currentVideo.length > 0) {
          currentVideo.get(0).contentWindow.postMessage('play', '*');
        } else {
          mediaSliders.carousel('cycle');
        }
      });

      // On first slide load.
      const firstIframeVideo = mediaSliders
        .find('.carousel-item', context)
        .first()
        .find('.varbase-video-player iframe[src*="vimeo.com"]', context);
      if (firstIframeVideo.length > 0) {
        firstIframeVideo.on('load', function () {
          if (!firstIframeVideo.hasClass('first-slide-played')) {
            mediaSliders.carousel('pause');

            if ($('.js-varbase-heroslider .carousel-item').length === 1) {
              firstIframeVideo.get(0).contentWindow.postMessage('loop', '*');
            }

            $(this).get(0).contentWindow.postMessage('play', '*');
            firstIframeVideo.addClass('first-slide-played');
          }
        });
      }

      function vimeoActionProcessor(e) {
        if (e.data === 'endedVimeo' || e.message === 'endedVimeo') {
          if ($('.js-varbase-heroslider .slide').length > 1) {
            // When having 2 or more slides.
            mediaSliders.carousel('next');
          } else {
            // When only having one Vimeo slide.
            firstIframeVideo.get(0).contentWindow.postMessage('play', '*');
          }
        } else if (e.data === 'playingVimeo' || e.message === 'playingVimeo') {
          mediaSliders.carousel('pause');
        }
      }

      // Setup the event listener for messaging.
      if (window.addEventListener) {
        window.addEventListener('message', vimeoActionProcessor, false);
      } else {
        window.attachEvent('onmessage', vimeoActionProcessor);
      }
    },
  };
})(jQuery, Drupal);
