
<div class="detail-page-banner">
        <div class="video-player">

            @if($type=='Local')

            <video id="videoPlayer" class="video-js vjs-default-skin" controls  width="560"
            height="315"
            autoplay="{{ auth()->check() ? 'true' : 'false' }}"
            muted
            data-setup="{}"
              poster="{{$thumbnail_image}}"
                data-setup='{"autoplay": {{ auth()->check() ? 'true' : 'false' }}, "muted": true}'>
            <source src="{{ $data }}" type="video/mp4" id="videoSource">

          </video>


            @else

            <!-- Video.js Player -->
            <video
                id="videoPlayer"
                class="video-js vjs-default-skin"
                controls
                width="560"
                height="315"
                autoplay="{{ auth()->check() ? 'true' : 'false' }}"
                muted
                data-watch-time="{{$watched_time??0}}"
                data-movie-access="{{$dataAccess??''}}"
                data-encrypted="{{ $data }}"
                 poster="{{$thumbnail_image}}"
                data-setup='{"autoplay": {{ auth()->check() ? 'true' : 'false' }}, "muted": true}'>

            </video>
            @endif

        </div>
</div>





<!-- Include the custom JS -->
<script src="{{ asset('js/videoplayer.min.js') }}"></script>
<script>
    var isAuthenticated = {{ auth()->check() ? 'true' : 'false' }};
    var loginUrl = "{{ route('login') }}";  // Update with your actual login route
</script>
