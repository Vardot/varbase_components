/**
 * @file
 * Behaviors of Vimeo player in the Heroslider OEmbed iframe.
 */

function ready(fn) {
  if (document.readyState !== 'loading') {
    fn();
  } else if (document.addEventListener) {
    document.addEventListener('DOMContentLoaded', fn);
  } else {
    document.attachEvent('onreadystatechange', function () {
      if (document.readyState !== 'loading') {
        fn();
      }
    });
  }
}

// Load the Vimeo API library.
const tag = document.createElement('script');
tag.src = '//player.vimeo.com/api/player.js';
const firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

ready(function () {
  const mediaIframe = document.querySelector('iframe');
  mediaIframe.setAttribute('id', 'media-oembed-iframe');

  let playerConfgured = false;
  let videoLoop = false;
  let vimeoPlayer;

  function actionProcessor(evt) {
    // Manage Vimeo video.
    if (evt.data === 'play') {
      if (!playerConfgured) {
        const vimeoIframe = document.querySelector('iframe[src*="vimeo.com"]');

        const vimeoOptions = {
          background: true,
          autoplay: true,
          muted: true,
          controls: false,
        };

        vimeoPlayer = new window.Vimeo.Player(vimeoIframe, vimeoOptions);
        vimeoPlayer.setVolume(0);
        vimeoPlayer.setLoop(videoLoop);
        vimeoPlayer.on('ended', function () {
          window.parent.postMessage('endedVimeo', '*');
          vimeoPlayer.pause();
        });

        vimeoPlayer.on('play', function () {
          window.parent.postMessage('playingVimeo', '*');
        });
        playerConfgured = true;
      }

      vimeoPlayer.ready().then(function () {
        vimeoPlayer.getPaused().then(function (paused) {
          if (paused) {
            vimeoPlayer.play();
          }
        });
      });
    } else if (evt.data === 'pause') {
      if (playerConfgured) {
        vimeoPlayer.pause();
      }
    } else if (evt.data === 'loop') {
      videoLoop = true;
    }
  }

  // Setup the event listener for messaging.
  if (window.addEventListener) {
    window.addEventListener('message', actionProcessor, false);
  } else {
    window.attachEvent('onmessage', actionProcessor);
  }
});
