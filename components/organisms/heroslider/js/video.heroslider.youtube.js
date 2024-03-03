/**
 * @file
 * Behaviors of Varbase heroslider for Youtube video scripts.
 */

(function ($, Drupal) {
  Drupal.behaviors.varbaseHeroSliderYoutubeVideo = {
    attach(context) {

      const mediaSliders = $('.js-varbase-heroslider', context);

      // On before slide change.
      mediaSliders.on('slide.bs.carousel', function (event) {
          const currentVideo = $(this)
           .find('.carousel-item.active', context)
           .find('.varbase-video-player iframe[src*="youtube.com"]', context);

          if (currentVideo.length > 0) {
            currentVideo.get(0).contentWindow.postMessage('pause', '*');
          }
        },
      );

      // On after slide change.
      mediaSliders.on('slid.bs.carousel', function (event) {
        const currentVideo = $(this)
          .find('.carousel-item.active', context)
          .find('.varbase-video-player iframe[src*="youtube.com"]', context);

        if (currentVideo.length > 0) {
          currentVideo.get(0).contentWindow.postMessage('play', '*');
        } else {
          mediaSliders.carousel('cycle');
        }
      });

      // On first slide load.
      const firstIframeVideo = $('.js-varbase-heroslider')
        .find('.carousel-item')
        .first()
        .find('.varbase-video-player iframe[src*="youtube.com"]', context);
      if (firstIframeVideo.length > 0) {
        firstIframeVideo.on('load', function () {
          mediaSliders.carousel('pause');
          $(this).get(0).contentWindow.postMessage('play', '*');
        });
      }

      function youtubeActionProcessor(e) {
        if (e.data === 'endedYoutube' || e.message === 'endedYoutube') {
          if ($('.js-varbase-heroslider').length > 1) {
            // When having 2 or more slides.
            mediaSliders.carousel('next');
          } else {
            // When only having one Youtube slide.
            firstIframeVideo.get(0).contentWindow.postMessage('play', '*');
          }
        } else if (
          e.data === 'playingYoutube' ||
          e.message === 'playingYoutube'
        ) {
          mediaSliders.carousel('pause');
        }
      }

      // Setup the event listener for messaging.
      if (window.addEventListener) {
        window.addEventListener('message', youtubeActionProcessor, false);
      } else {
        window.attachEvent('onmessage', youtubeActionProcessor);
      }
    },
  };
})(jQuery, Drupal);
