<!Doctype html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Media Player</title>
  <script src="//ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
  <script src="//cdn.jsdelivr.net/npm/js-cookie@3.0.1/dist/js.cookie.min.js"></script>
  <script type="text/javascript"
          src="https://cdnjs.cloudflare.com/ajax/libs/iframe-resizer/4.2.11/iframeResizer.contentWindow.min.js"
  ></script>
  <!-- Bootstrap CSS -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    rel="stylesheet"
  />

  <!-- Video.js -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/video.js/7.21.5/video-js.min.css" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/video.js/7.21.5/video.min.js"></script>
  <!-- Video.js Hotkeys Plugin (keyboard accessibility) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/videojs-hotkeys/0.2.27/videojs.hotkeys.min.js"></script>

  <script>
    //how far the skip buttons (shown only when $show_skip_controls is set, i.e. discuss_it) and the
    //left/right arrow keys (always) move the playback position
    var SKIP_SECONDS = 10;

    function configureAudioPlayer(audioId, captionId, startTime, vttUrl) {
      var audio = document.getElementById(audioId);
      var display = document.getElementById(captionId);

      if (startTime) {
        audio.currentTime = startTime;
      }

      if (vttUrl && display) {
        var track = audio.addTextTrack('captions', 'Captions', 'en');
        track.mode = 'hidden';

        fetch(vttUrl)
          .then(function(r) { return r.text(); })
          .then(function(vtt) {
            var cues = parseVTT(vtt);
            cues.forEach(function(cue) {
              var c = new VTTCue(cue.start, cue.end, cue.text);
              track.addCue(c);
            });

            track.oncuechange = function() {
              var active = this.activeCues;
              if (active && active.length > 0) {
                display.textContent = active[0].text;
              } else {
                display.textContent = '';
              }
            };
          });
      }
    }

    /**
     * Moves the playback position by `seconds` (negative goes back), staying within the recording.
     * media: { getTime(), setTime(seconds), getDuration() }
     */
    function skipMedia(media, seconds) {
      var duration = media.getDuration();
      var target = Math.max(0, (media.getTime() || 0) + seconds);
      if (duration && isFinite(duration)) {
        target = Math.min(duration, target);
      }
      media.setTime(target);
    }

    //wires up the "Back 10s" / "Forward 10s" buttons inside the given container
    function configureSkipControls(containerId, media) {
      var container = document.getElementById(containerId);
      if (!container) return;
      container.querySelectorAll('[data-skip]').forEach(function (button) {
        button.addEventListener('click', function () {
          skipMedia(media, +button.getAttribute('data-skip'));
        });
      });
    }

    //the browser's own audio controls jump 5 seconds on the arrow keys; match the 10-second buttons instead
    function configureAudioArrowKeys(audio) {
      var media = {
        getTime: function () { return audio.currentTime; },
        setTime: function (seconds) { audio.currentTime = seconds; },
        getDuration: function () { return audio.duration; }
      };
      //listen in the capture phase so this runs before the browser's own control handlers, then stop them
      audio.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
          event.preventDefault();
          event.stopImmediatePropagation();
          skipMedia(media, event.key === 'ArrowLeft' ? -SKIP_SECONDS : SKIP_SECONDS);
        }
      }, true);
      return media;
    }

    function parseVTT(vtt) {
      var cues = [];
      var blocks = vtt.split('\n\n');
      blocks.forEach(function(block) {
        var lines = block.trim().split('\n');
        for (var i = 0; i < lines.length; i++) {
          if (lines[i].indexOf('-->') !== -1) {
            var times = lines[i].split('-->');
            var text = lines.slice(i + 1).join('\n');
            cues.push({
              start: parseTime(times[0].trim()),
              end: parseTime(times[1].trim()),
              text: text.trim()
            });
            break;
          }
        }
      });
      return cues;
    }

    function parseTime(str) {
      var parts = str.split(':');
      if (parts.length === 3) {
        return parseFloat(parts[0]) * 3600 + parseFloat(parts[1]) * 60 + parseFloat(parts[2]);
      }
      return parseFloat(parts[0]) * 60 + parseFloat(parts[1]);
    }
  </script>
  @if($in_modal ?? false)
    <style>
    .video-js {
      width: 100%;
      height: auto;
      aspect-ratio: 16 / 9;
    }
    .vjs-default-skin {
      width: 100%;
    }
    .video-js .vjs-text-track-display > div {
      position: absolute !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      bottom: 0 !important;
      margin: 0 !important;
      display: flex !important;
      flex-direction: column !important;
      justify-content: flex-end !important;
      align-items: center !important;
      padding-bottom: 0px !important;
    }

    .video-js .vjs-text-track-display > div > div {
      position: static !important;
      width: 100% !important;
    }

    .video-js .vjs-text-track-display > div > div > div {
      position: static !important;
      inset: unset !important;
      width: 100% !important;
      font-size: 24px !important;
    }
    </style>
  @endif
  <style>
    .video-js .vjs-text-track-cue {
      margin-bottom: 2% !important; /* optional spacing from bottom */
    }
    .audio-player-wrapper {
      border: 1px solid #333;
      border-radius: 8px;
      overflow: hidden;
      max-width: 100%;
    }
    .audio-caption-display {
      background: #000;
      color: #fff;
      min-height: 60px;
      padding: 12px 15px;
      font-size: 18px;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .native-audio-player {
      width: 100%;
      display: block;
      margin: 0;
      padding: 0;
      background: #f1f3f4;
    }
    /* the Back 10s / Forward 10s buttons under each player */
    .skip-controls {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.5rem 0;
    }
    .skip-controls .btn {
      min-width: 110px;
    }
  </style>
</head>
<body>
<div>
  @if($is_phone)
    @if($type === 'audio')
      <div style="max-width: 100%;">
        @if ($vtt_file)
          <div id="mobile-caption-display" class="audio-caption-display"></div>
        @endif
        <audio id="mobile-audio-player" class="native-audio-player" controls preload="auto">
          <source type="audio/mpeg" src="{{ $temporary_url }}"/>
        </audio>
        @if($show_skip_controls ?? false)
        <div id="mobile-audio-skip-controls" class="skip-controls" role="group" aria-label="Move back or forward 10 seconds">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="-10" aria-label="Back 10 seconds">
            <i class="fas fa-backward" aria-hidden="true"></i> Back 10s
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="10" aria-label="Forward 10 seconds">
            Forward 10s <i class="fas fa-forward" aria-hidden="true"></i>
          </button>
        </div>
        @endif
      </div>
      <script>
        configureAudioPlayer('mobile-audio-player', {!! $vtt_file ? "'mobile-caption-display'" : 'null' !!}, {{ $start_time ?? 0 }}, {!! $vtt_file ? json_encode($vtt_file) : 'null' !!});
        (function () {
          var mediaId = "{{ $media_id ?? '' }}";
          var audio = document.getElementById('mobile-audio-player');
          configureSkipControls('mobile-audio-skip-controls', configureAudioArrowKeys(audio));
          //report the playback position so the parent page can show/capture it (Transcript, timestamped replies);
          //loadedmetadata reports it before playback starts, so "Reply at 0:00" is available right away
          function postTime() {
            window.parent.postMessage({
              type: 'videoTimeUpdate',
              mediaId: mediaId,
              currentTime: audio.currentTime
            }, '*');
          }
          ['loadedmetadata', 'timeupdate', 'pause', 'seeked'].forEach(function (eventName) {
            audio.addEventListener(eventName, postTime);
          });
          window.addEventListener('message', function(event) {
            if (!event.data || event.data.mediaId !== mediaId) return
            if (event.data.type === 'mediaPlayerSeekTo') audio.currentTime = event.data.time
            if (event.data.type === 'mediaPlayerPause') audio.pause()
          });
        })();</script>

    @elseif($type === 'video')
      @php
        $use_hls = !empty($stream_hls_url);
        $has_video = $use_hls || !empty($mp4_temporary_url);
      @endphp

      @if ($has_video)
        <video
          id="mobile-video-player"
          class="video-js vjs-default-skin vjs-big-play-centered"
          controls
          preload="auto"
          playsinline
        >
          @if ($use_hls)
            <source src="{{ $stream_hls_url }}" type="application/x-mpegURL">
          @else
            <source src="{{ $mp4_temporary_url }}" type="video/mp4">
            @if (!empty($temporary_url))
              <source src="{{ $temporary_url }}" type="video/webm">
            @endif
          @endif
          @if ($vtt_file)
            <track kind="captions" src="{{ $vtt_file }}" />
          @endif
        </video>
        @if($show_skip_controls ?? false)
        <div id="mobile-video-skip-controls" class="skip-controls" role="group" aria-label="Move back or forward 10 seconds">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="-10" aria-label="Back 10 seconds">
            <i class="fas fa-backward" aria-hidden="true"></i> Back 10s
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="10" aria-label="Forward 10 seconds">
            Forward 10s <i class="fas fa-forward" aria-hidden="true"></i>
          </button>
        </div>
        @endif

        @if (!$use_hls && isset($show_buttons) && $show_buttons)
          <div class="controls" style="display:flex;justify-content:center;gap:0.5rem;margin-bottom:50px;margin-top:0.5rem">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rotateLeft()">
              <i class="fas fa-rotate-left"></i> Rotate Left
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rotateRight()">
              <i class="fas fa-rotate-right"></i> Rotate Right
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetRotation()">
              <i class="fas fa-undo"></i> Reset
            </button>
          </div>
        @endif

        <script>
          (function () {
            const isHls = {{ $use_hls ? 'true' : 'false' }};

            const defaultOptions = {
              fluid: true,
              playbackRates: [0.5, 1, 1.5, 2],
              plugins: {
                hotkeys: {
                  volumeStep: 0.1,
                  seekStep: SKIP_SECONDS,
                  enableMute: true,
                  enableFullscreen: true,
                }
              }
            };

            if (isHls) {
              defaultOptions.html5 = {
                vhs: { overrideNative: true },
                nativeAudioTracks: false,
                nativeVideoTracks: false,
              };
            }

            const extraOptions = @json($player_options ?? []);
            const options = Object.assign({}, defaultOptions, extraOptions);

            const player = videojs('mobile-video-player', options);
            configureSkipControls('mobile-video-skip-controls', {
              getTime: function () { return player.currentTime(); },
              setTime: function (seconds) { player.currentTime(seconds); },
              getDuration: function () { return player.duration(); }
            });
            player.ready(function () {
              const startTime = {{ $start_time ?? 0 }};
              if (startTime) {
                //open at the requested moment (a reply's timestamp). Seeking before the video has loaded doesn't
                //stick (it ends up back at 0), so it's applied again once the video can play; after that the
                //person is in control.
                const goToStartTime = function () {
                  if (Math.abs(player.currentTime() - startTime) > 0.5) {
                    player.currentTime(startTime);
                  }
                };
                goToStartTime();
                //HLS streams may not be seekable until the metadata loads
                player.one('loadedmetadata', goToStartTime);
                player.one('canplay', goToStartTime);
              }
              //report the playback position so the parent page can show/capture it (Transcript, timestamped replies);
              //it's also reported once the video has loaded, so "Reply at 0:00" is available before playback starts
              function postTime() {
                window.parent.postMessage({
                  type: 'videoTimeUpdate',
                  mediaId: "{{ $media_id ?? '' }}",  // pass your media ID into the blade template
                  currentTime: player.currentTime()
                }, '*');
              }
              player.on(['loadedmetadata', 'timeupdate', 'pause', 'seeked'], postTime);
              //the metadata may have loaded before this player was ready, in which case loadedmetadata has already fired
              if (player.readyState() >= 1 && !startTime) {
                postTime();
              }
              window.addEventListener('message', function(event) {
                if (!event.data || event.data.mediaId !== "{{ $media_id ?? '' }}") return
                if (event.data.type === 'mediaPlayerSeekTo') player.currentTime(event.data.time)
                if (event.data.type === 'mediaPlayerPause') player.pause()
              });
            });
          })();
        </script>
      @else
        <div style="height: 300px">
          <div id="processing-message" style="
              max-width: 400px;
              margin: 2rem auto;
              padding: 1.5rem;
              border: 2px solid #ccc;
              border-radius: 12px;
              background-color: #f9f9f9;
              font-family: sans-serif;
              text-align: center;
              box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            "
          >
            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Your video is being optimized for mobile view...</p>
            <p style="font-size: 0.95rem; color: #555;">This may take a few minutes. In the meantime, you may view your
              video in a browser.</p>
            <p style="font-size: 0.95rem; color: #555; margin-bottom: 0;">Once it's ready, you'll be able to reply to a
              specific moment in it.</p>
          </div>
        </div>
      @endif
    @endif

  @else
    @if($type === 'audio')
      <div style="max-width: 100%;">
        <div class="audio-player-wrapper">
          @if ($vtt_file)
            <div id="caption-display" class="audio-caption-display"></div>
          @endif
          <audio id="audio-player" class="native-audio-player" controls preload="auto">
            <source type="audio/mpeg" src="{{ $temporary_url }}"/>
          </audio>
        </div>
        @if($show_skip_controls ?? false)
        <div id="audio-skip-controls" class="skip-controls" role="group" aria-label="Move back or forward 10 seconds">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="-10" aria-label="Back 10 seconds">
            <i class="fas fa-backward" aria-hidden="true"></i> Back 10s
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="10" aria-label="Forward 10 seconds">
            Forward 10s <i class="fas fa-forward" aria-hidden="true"></i>
          </button>
        </div>
        @endif
      </div>
      <script>
        configureAudioPlayer('audio-player', {!! $vtt_file ? "'caption-display'" : 'null' !!}, {{ $start_time ?? 0 }}, {!! $vtt_file ? json_encode($vtt_file) : 'null' !!});
        (function () {
          var mediaId = "{{ $media_id ?? '' }}";
          var audio = document.getElementById('audio-player');
          configureSkipControls('audio-skip-controls', configureAudioArrowKeys(audio));
          //report the playback position so the parent page can show/capture it (Transcript, timestamped replies);
          //loadedmetadata reports it before playback starts, so "Reply at 0:00" is available right away
          function postTime() {
            window.parent.postMessage({
              type: 'videoTimeUpdate',
              mediaId: mediaId,
              currentTime: audio.currentTime
            }, '*');
          }
          ['loadedmetadata', 'timeupdate', 'pause', 'seeked'].forEach(function (eventName) {
            audio.addEventListener(eventName, postTime);
          });
          window.addEventListener('message', function(event) {
            if (!event.data || event.data.mediaId !== mediaId) return
            if (event.data.type === 'mediaPlayerSeekTo') audio.currentTime = event.data.time
            if (event.data.type === 'mediaPlayerPause') audio.pause()
          });
        })();</script>

    @elseif($type === 'video')
      @php
        $use_hls = !empty($stream_hls_url);
        $has_video = $use_hls || !empty($mp4_temporary_url);
      @endphp

      @if ($has_video)
        <video
          id="video1"
          class="video-js vjs-default-skin vjs-big-play-centered"
          controls
          preload="auto"
          playsinline
        >
          @if ($use_hls)
            <source src="{{ $stream_hls_url }}" type="application/x-mpegURL">
          @else
            <source src="{{ $mp4_temporary_url }}" type="video/mp4">
            @if (!empty($temporary_url))
              <source src="{{ $temporary_url }}" type="video/webm">
            @endif
          @endif
          @if ($vtt_file)
            <track kind="captions" src="{{ $vtt_file }}" />
          @endif
        </video>
        @if($show_skip_controls ?? false)
        <div id="video-skip-controls" class="skip-controls" role="group" aria-label="Move back or forward 10 seconds">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="-10" aria-label="Back 10 seconds">
            <i class="fas fa-backward" aria-hidden="true"></i> Back 10s
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" data-skip="10" aria-label="Forward 10 seconds">
            Forward 10s <i class="fas fa-forward" aria-hidden="true"></i>
          </button>
        </div>
        @endif

        @if (!$use_hls && isset($show_buttons) && $show_buttons)
          <div class="controls" style="display:flex;justify-content:center;gap:0.5rem;margin-bottom:50px;margin-top:0.5rem">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rotateLeft()">
              <i class="fas fa-rotate-left"></i> Rotate Left
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="rotateRight()">
              <i class="fas fa-rotate-right"></i> Rotate Right
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetRotation()">
              <i class="fas fa-undo"></i> Reset
            </button>
          </div>
        @endif

        <script>
          (function () {
            const isHls = {{ $use_hls ? 'true' : 'false' }};

            const defaultOptions = {
              fluid: true,
              playbackRates: [0.5, 1, 1.5, 2],
              plugins: {
                hotkeys: {
                  volumeStep: 0.1,
                  seekStep: SKIP_SECONDS,
                  enableMute: true,
                  enableFullscreen: true,
                }
              }
            };

            if (isHls) {
              defaultOptions.html5 = {
                vhs: { overrideNative: true },
                nativeAudioTracks: false,
                nativeVideoTracks: false,
              };
            }
            const extraOptions = @json($player_options ?? []);
            const options = Object.assign({}, defaultOptions, extraOptions);

            const player = videojs('video1', options);
            configureSkipControls('video-skip-controls', {
              getTime: function () { return player.currentTime(); },
              setTime: function (seconds) { player.currentTime(seconds); },
              getDuration: function () { return player.duration(); }
            });
            player.ready(function () {
              const startTime = {{ $start_time ?? 0 }};
              if (startTime) {
                //open at the requested moment (a reply's timestamp). Seeking before the video has loaded doesn't
                //stick (it ends up back at 0), so it's applied again once the video can play; after that the
                //person is in control.
                const goToStartTime = function () {
                  if (Math.abs(player.currentTime() - startTime) > 0.5) {
                    player.currentTime(startTime);
                  }
                };
                goToStartTime();
                //HLS streams may not be seekable until the metadata loads
                player.one('loadedmetadata', goToStartTime);
                player.one('canplay', goToStartTime);
              }
              const ro = new ResizeObserver(() => {
                this.trigger('resize');
                // Re-apply after VJS recalculates
                console.error('resized');

              });
              ro.observe(this.el());
              //report the playback position so the parent page can show/capture it (Transcript, timestamped replies);
              //it's also reported once the video has loaded, so "Reply at 0:00" is available before playback starts
              function postTime() {
                window.parent.postMessage({
                  type: 'videoTimeUpdate',
                  mediaId: "{{ $media_id ?? '' }}",  // pass your media ID into the blade template
                  currentTime: player.currentTime()
                }, '*');
              }
              player.on(['loadedmetadata', 'timeupdate', 'pause', 'seeked'], postTime);
              //the metadata may have loaded before this player was ready, in which case loadedmetadata has already fired
              if (player.readyState() >= 1 && !startTime) {
                postTime();
              }
              window.addEventListener('message', function(event) {
                if (!event.data || event.data.mediaId !== "{{ $media_id ?? '' }}") return
                if (event.data.type === 'mediaPlayerSeekTo') player.currentTime(event.data.time)
                if (event.data.type === 'mediaPlayerPause') player.pause()
              });
            });
          })();
        </script>
      @else
        <div style="height: 300px">
          <div id="processing-message" style="
              max-width: 400px;
              margin: 2rem auto;
              padding: 1.5rem;
              border: 2px solid #ccc;
              border-radius: 12px;
              background-color: #f9f9f9;
              font-family: sans-serif;
              text-align: center;
              box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            "
          >
            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Your video is being optimized...</p>
            <p style="font-size: 0.95rem; color: #555;">This may take a few minutes. Please check back shortly.</p>
            <p style="font-size: 0.95rem; color: #555; margin-bottom: 0;">Once it's ready, you'll be able to reply to a
              specific moment in it.</p>
          </div>
        </div>
      @endif
    @endif
  @endif
</div>

<script>
  let rotation = 0;
  const videoEl = document.getElementById('video1') || document.getElementById('mobile-video-player');

  function applyRotation() {
    if (videoEl) videoEl.style.transform = `rotate(${rotation}deg)`;
  }

  function rotateLeft() {
    rotation -= 90;
    applyRotation();
  }

  function rotateRight() {
    rotation += 90;
    applyRotation();
  }

  function resetRotation() {
    rotation = 0;
    applyRotation();
  }
</script>

</body>
</html>
