/**
 * @file
 * Behaviors of Youtube player in the Heroslider OEmbed iframe.
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

const tag = document.createElement('script');
tag.src = '//www.youtube.com/player_api';
const firstScriptTag = document.getElementsByTagName('script')[0];
firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

ready(function () {
  const mediaIframe = document.querySelector('iframe');
  mediaIframe.setAttribute('id', 'media-oembed-iframe');
  mediaIframe.style.opacity = '0';
  document.body.style.backgroundColor = '#000000';

  let playerConfgured = false;
  let youtubePlayer;

  function actionProcessor(evt) {
    // Manage Youtube video.
    if (evt.data === 'play') {
      const youtubeIframe = document.querySelector(
        'iframe[src*="youtube.com"]',
      );
      if (youtubeIframe !== undefined && youtubeIframe.src !== undefined) {
        if (!playerConfgured) {
          let youtubeURL = String(youtubeIframe.src);
          youtubeURL = youtubeURL.replace(/autoplay=0/gi, 'autoplay=1');
          youtubeURL = youtubeURL.replace(/controls=1/gi, 'controls=0');
          youtubeURL += '&controls=0';
          youtubeURL += '&enablejsapi=1';
          youtubeURL += '&showinfo=0'; // Hide the video title.
          youtubeURL += '&modestbranding=1'; // Hide the Youtube Logo.
          youtubeURL += '&loop=1'; // Run the video in a loop.
          youtubeURL += '&fs=1'; // Hide the full screen button.
          youtubeURL += '&cc_load_policy=1'; // Hide closed captions.
          youtubeURL += '&iv_load_policy=1'; // Hide the Video Annotations.
          youtubeURL += '&volume=0';
          youtubeURL += '&rel=0';
          youtubeIframe.src = youtubeURL;
          youtubeURL = undefined;

          youtubePlayer = new window.YT.Player(youtubeIframe.id, {
            playerVars: {
              autoplay: 1, // Auto-play the video on load.
              controls: 0, // Show pause/play buttons in player.
              showinfo: 0, // Hide the video title.
              modestbranding: 1, // Hide the Youtube Logo.
              loop: 1, // Run the video in a loop.
              fs: 0, // Hide the full screen button.
              autohide: 0, // Hide video controls when playing.
              rel: 0, // Hide related videos.
            },
            events: {
              onReady: onPlayerReady,
              onStateChange: onPlayerStateChange,
            },
          });

          playerConfgured = true;
        } else if (typeof youtubePlayer.playVideo === 'function') {
          youtubePlayer.playVideo();
        }
      }
    } else if (evt.data === 'pause') {
      if (playerConfgured) {
        youtubePlayer.pauseVideo();
        youtubePlayer.isPlaying = false;
      }
    }
  }

  function onPlayerReady(event) {
    event.target.mute();
    event.target.setVolume(0);
    event.target.playVideo();
  }

  function onPlayerStateChange(event) {
    if (event.data === window.YT.PlayerState.BUFFERING) {
      mediaIframe.style.opacity = '0';
    } else {
      mediaIframe.style.opacity = '1';
    }

    if (event.data === window.YT.PlayerState.PLAYING) {
      youtubePlayer.isPlaying = true;
    } else {
      youtubePlayer.isPlaying = false;
    }

    if (event.data === window.YT.PlayerState.ENDED) {
      window.parent.postMessage('endedYoutube', '*');
      youtubePlayer.pauseVideo();
      youtubePlayer.seekTo(0);
    } else {
      window.parent.postMessage('playingYoutube', '*');
    }
  }

  // Setup the event listener for messaging.
  if (window.addEventListener) {
    window.addEventListener('message', actionProcessor, false);
  } else {
    window.attachEvent('onmessage', actionProcessor);
  }
});
