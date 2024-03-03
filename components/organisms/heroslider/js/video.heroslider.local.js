/**
 * @file
 * Behaviors of heroslider for local video scripts.
 */

(function ($, Drupal) {
  Drupal.behaviors.varbaseHeroSliderLocalVideo = {
    attach(context) {
      $(window).on('load', function () {
        const mediaSliders = $('.js-varbase-heroslider', context);

        // On before slide change.
        mediaSliders.on('slide.bs.carousel', function (event) {

            const currentSlideObject = $(this).find('.carousel-item.active');
            const nextSlideObject = $(this).find('.carousel-item:not(.active)');
            const currentVideo = currentSlideObject.find(
              '.varbase-video-player video',
              context,
            );
            const nextVideo = nextSlideObject.find(
              '.varbase-video-player video',
              context,
            );

            if (currentVideo.length > 0) {
              const currentPlayer = currentVideo.get(0);
              currentPlayer.pause();
            }

            if (nextVideo.length > 0) {
              const nextPlayer = nextVideo.get(0);
              nextPlayer.muted = true;
              nextPlayer.onpause = onPause;
              nextPlayer.onended = onFinish;
              nextPlayer.onplay = onPlayProgress;

              // DOMException - The play() request was interrupted.
              // https://developer.chrome.com/blog/play-request-was-interrupted
              let nextPlayPromise;
              nextPlayPromise = nextPlayer.play();
              if (nextPlayPromise && Object.keys(nextPlayPromise).length === 0 && nextPlayPromise.constructor === Object) {
                nextPlayPromise.then(_ => {
                  // Automatic playback started!
                  // Show playing UI.
                  // We can now safely pause video...
                  nextPlayer.pause();
                })
                .catch(error => {
                  // Auto-play was prevented
                  // Show paused UI.
                });
              }
            } else {
              mediaSliders.carousel('cycle');
            }
          },
        );

        // When first slide has a video (Pause the slider and play the video).
        $('js-varbase-heroslider', context).each(function () {
          const firstVideo = $(this)
            .find('.carousel-item.active')
            .find('.varbase-video-player video', context);

          if (firstVideo.length > 0) {
            mediaSliders.carousel('pause');

            const firstVideoPlayer = firstVideo.get(0);
            firstVideoPlayer.muted = true;

            // DOMException - The play() request was interrupted.
            // https://developer.chrome.com/blog/play-request-was-interrupted
            let firstVideoPlayPromise;
            firstVideoPlayPromise = firstVideoPlayer.play();
            if (firstVideoPlayPromise && Object.keys(firstVideoPlayPromise).length === 0 && firstVideoPlayPromise.constructor === Object) {
              firstVideoPlayPromise.then(_ => {
                // Automatic playback started!
                // Show playing UI.
                // We can now safely pause video...
                firstVideoPlayer.pause();
              })
              .catch(error => {
                // Auto-play was prevented
                // Show paused UI.
              });
            }

            firstVideo.on('ended', function () {
              mediaSliders.carousel('cycle');
            });
          }
        });

        $('.js-varbase-heroslider', context).each(function () {
          const firstVideo = $(this)
            .find('.carousel-item')
            .find('.varbase-video-player video', context);

          if (firstVideo.length > 0) {

            const firstVideoPlayer = firstVideo.get(0);
            firstVideoPlayer.muted = true;
            firstVideoPlayer.loop = true;

            // DOMException - The play() request was interrupted.
            // https://developer.chrome.com/blog/play-request-was-interrupted
            let firstVideoPlayPromise;
            firstVideoPlayPromise = firstVideoPlayer.play();
            if (firstVideoPlayPromise && Object.keys(firstVideoPlayPromise).length === 0 && firstVideoPlayPromise.constructor === Object) {
              firstVideoPlayPromise.then(_ => {
                // Automatic playback started!
                // Show playing UI.
                // We can now safely pause video...
                firstVideoPlayer.pause();
              })
              .catch(error => {
                // Auto-play was prevented
                // Show paused UI.
              });
            }
          }
        });

        // Local Video variable.
        if ($('js-varbase-heroslider .varbase-video-player video', context).length > 0) {
          const player = $('js-varbase-heroslider .varbase-video-player video', context).get(0);

          // When the player is ready, add listeners for pause, finish,
          // and playProgress.
          player.onpause = onPause;
          player.onended = onFinish;
          player.onplay = onPlayProgress;
        }

        // Play when paused.
        function onPause() {
          mediaSliders.carousel('next');
        }

        // Play when finished.
        function onFinish() {
          mediaSliders.carousel('cycle');
        }

        // Pause on play progress.
        function onPlayProgress() {
          mediaSliders.carousel('pause');
        }
      });
    },
  };
})(jQuery, Drupal);
