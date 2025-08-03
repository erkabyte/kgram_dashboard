<?php

namespace Modules\Frontend\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Entertainment\Transformers\TvshowDetailResource;
use Modules\Entertainment\Transformers\TvshowResource;
use Modules\Entertainment\Models\Watchlist;
use Modules\Entertainment\Models\Like;
use Illuminate\Support\Facades\Cache;
use Modules\Entertainment\Models\Entertainment;
use Modules\Episode\Models\Episode;
use Modules\Entertainment\Models\ContinueWatch;
use Modules\Entertainment\Models\EntertainmentDownload;
use Modules\Genres\Models\Genres;
use Modules\Entertainment\Transformers\EpisodeDetailResource;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;
use App\Models\UserSearchHistory;
use Modules\Season\Models\Season;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\MobileSetting;
use Google\Service\CloudSearch\Id;
use Modules\CastCrew\Models\CastCrew;
use Modules\Banner\Models\Banner;
use Carbon\Carbon;
use Modules\Banner\Transformers\SliderResource;
use App\Services\RecommendationService;
use Modules\Entertainment\Transformers\MoviesResource;

class TvShowController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    public function index()
    {
        return view('frontend::index');
    }

    public function tvShowList($language = null)
    {
        $user_id = auth()->id();
        $user = Auth::user();

        $featured_tvshow = Banner::where('banner_for', 'tv_show')
            ->where('status', 1)
            ->limit(5)
            ->get();
        $sliders = SliderResource::collection($featured_tvshow);
        $sliders =  $sliders->toArray(request());

        // Get popular TV shows
        $popularTVShowIds = MobileSetting::getValueBySlug('popular-tvshows');
        $popular_tvshows = !empty($popularTVShowIds)
            ? Entertainment::whereIn('id', json_decode($popularTVShowIds))
                ->where('status', 1)
                ->where('type', 'tvshow')
                ->get()
            : Entertainment::where('status', 1)
                ->where('type', 'tvshow')
                ->limit(10)
                ->get();

        // Initialize variables
        $recommended_shows = collect([]);
        $trending_movies = collect([]);

        // Get recommended shows only for authenticated users
        if ($user) {
            $watchedShows = ContinueWatch::where('user_id', $user_id)
                ->where('entertainment_type', 'tvshow')
                ->latest()
                ->limit(5)
                ->pluck('entertainment_id');

            if ($watchedShows->isNotEmpty()) {
                $genres = Entertainment::whereIn('id', $watchedShows)
                    ->with('entertainmentGenerMappings')
                    ->get()
                    ->pluck('entertainmentGenerMappings.*.genre_id')
                    ->flatten()
                    ->unique();

                $recommended_shows = Entertainment::where('type', 'tvshow')
                    ->where('status', 1)
                    ->whereNotIn('id', $watchedShows)
                    ->whereHas('entertainmentGenerMappings', function($query) use ($genres) {
                        $query->whereIn('genre_id', $genres);
                    })
                    ->with('entertainmentGenerMappings')
                    ->limit(6)
                    ->get();
            }

            // Get trending shows for authenticated users
            $trendingData = $this->recommendationService->getTrendingTvShowByCountry($user);
            if ($trendingData) {
                $trending_movies = MoviesResource::collection($trendingData);
            }
        } else {

            $trending_movies = Entertainment::where('type', 'tvshow')
                ->where('status', 1)
                ->whereDate('release_date', '<=', Carbon::now())
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            $trending_movies = MoviesResource::collection($trending_movies);
        }

        // Get personalities
        $personalities = Cache::remember('personality', 3600, function() {
            $castIds = MobileSetting::getValueBySlug('your-favorite-personality');
            if ($castIds != null) {
                $casts = CastCrew::whereIn('id', json_decode($castIds))->get();
                return $casts->map(function($value) {
                    return [
                        'id' => $value->id,
                        'name' => $value->name,
                        'type' => $value->type,
                        'profile_image' => setBaseUrlWithFileName($value->file_url),
                    ];
                });
            }
            return collect([]);
        });

        return view('frontend::tvShow', compact(
            'popular_tvshows',
            'recommended_shows',
            'personalities',
            'featured_tvshow',
            'sliders',
            'trending_movies',
            'user_id',
            'language'
        ));
    }

    public function tvshowDetail(Request $request, $id)
    {
        $tvshow_id = $id;

        $userId = auth()->id();

        $cacheKey = 'tvshow_' . $tvshow_id;

        $responseData = Cache::get($cacheKey);

        if (!$responseData) {
            $tvshow = Entertainment::where('id', $tvshow_id)
                ->with('entertainmentGenerMappings', 'plan', 'entertainmentReviews', 'entertainmentTalentMappings', 'season', 'episode')
                ->first();

            if (!$tvshow) {
                return abort(404, 'TV show not found.');
            }

            $tvshow['reviews'] = $tvshow->entertainmentReviews ?? null;

            // Encrypt the trailer URL
            if (!empty($tvshow->trailer_url) &&  $tvshow->trailer_url_type != 'Local') {
                $tvshow['trailer_url'] = Crypt::encryptString($tvshow->trailer_url);
            }

            if ($userId) {
                $tvshow['user_id'] = $userId;
                $tvshow['is_watch_list'] = WatchList::where('entertainment_id', $tvshow_id)
                    ->where('user_id', $userId)
                    ->exists();
                $tvshow['is_likes'] = Like::where('entertainment_id', $tvshow_id)
                    ->where('user_id', $userId)
                    ->where('is_like', 1)
                    ->exists();
                $tvshow['your_review'] = $tvshow->entertainmentReviews ? $tvshow->entertainmentReviews->where('user_id', $userId)->first() : null;

                if ($tvshow['your_review']) {
                    $tvshow['reviews'] = $tvshow['reviews']->where('user_id', '!=', $userId);
                }
            }

            // Use TvshowDetailResource to format the response
            $responseData = new TvshowDetailResource($tvshow);

            Cache::put($cacheKey, $responseData);
        }

        // Convert response data to array
        $data = $responseData->toArray(request());

        $season_id = Season::where('entertainment_id', $tvshow_id)->value('id');

        $episode = Episode::where('entertainment_id', $tvshow_id)->where('season_id', $season_id)->with('entertainmentdata', 'plan', 'EpisodeStreamContentMapping', 'episodeDownloadMappings')->first();

        if ($episode == null) {
            abort(404);
        }

        $genre_ids = $episode && $episode->entertainmentData
            ? $episode->entertainmentData->entertainmentGenerMappings->pluck('genre_id')
            : collect();

        $episode['genre_data'] = Genres::whereIn('id', $genre_ids)->get();

        $episode['moreItems'] = Entertainment::where('type', 'tvshow')
            ->whereHas('entertainmentGenerMappings', function ($query) use ($genre_ids) {
                $query->whereIn('genre_id', $genre_ids);
            })
            ->where('id', '!=', $episode->id)
            ->orderBy('id', 'desc')
            ->get();

        $episodeData = new EpisodeDetailResource($episode);
        $data['episodeData'] = $episodeData->toArray(request());

        if ($request->has('is_search') && $request->is_search == 1) {
            $user_id = auth()->user()->id ?? $request->user_id;

            if ($user_id) {
                $currentprofile = GetCurrentprofile($user_id, $request);

                if ($currentprofile) {
                    $existingSearch = UserSearchHistory::where('user_id', $user_id)
                        ->where('profile_id', $currentprofile)
                        ->where('search_query', $data['name'])
                        ->first();

                    if (!$existingSearch) {
                        UserSearchHistory::create([
                            'user_id' => $user_id,
                            'profile_id' => $currentprofile,
                            'search_query' => $data['name'],
                            'search_id' => $data['id'],
                            'type' => $data['type']
                        ]);
                    }
                }
            }
        }

        return view('frontend::tvshowDetail', compact('data'));
    }

    public function episodeDetail(Request $request, $id)
    {
        $user_id = auth()->id();
        $episode_id = $id;

        $cacheKey = 'episode_' . $episode_id;

        $responseData = Cache::get($cacheKey);

        if (!$responseData) {
            $episode = Episode::where('id', $episode_id)->with('entertainmentdata', 'plan', 'EpisodeStreamContentMapping', 'episodeDownloadMappings')->first();

            $genre_ids = $episode->entertainmentData->entertainmentGenerMappings->pluck('genre_id');

            // Encrypt the trailer URL
            if (!empty($episode->trailer_url) &&  $episode->trailer_url_type != 'Local') {
                $episode['trailer_url'] = Crypt::encryptString($episode->trailer_url);
            }

            if (!empty($episode->video_url_input) &&  $episode->video_upload_type != 'Local') {
                $episode['video_url_input'] = Crypt::encryptString($episode->video_url_input);
            }

            $episode['moreItems'] = Entertainment::where('type', 'tvshow')
                ->whereHas('entertainmentGenerMappings', function ($query) use ($genre_ids) {
                    $query->whereIn('genre_id', $genre_ids);
                })
                ->where('id', '!=', $episode->id)
                ->orderBy('id', 'desc')
                ->get();

            $episode['genre_data'] = Genres::whereIn('id', $genre_ids)->get();

            if ($user_id) {
                $continueWatch = ContinueWatch::where('entertainment_id', $episode->id)->where('user_id', $user_id)->where('entertainment_type', 'episode')->first();
                $episode['continue_watch'] = $continueWatch;

                $episode['is_download'] = EntertainmentDownload::where('entertainment_id', $episode->id)->where('user_id', $user_id)->where('entertainment_type', 'episode')->where('is_download', 1)->exists();
            }

            $responseData = new EpisodeDetailResource($episode);

            Cache::put($cacheKey, $responseData);
        }

        $data = $responseData->toArray(request());

        if ($request->has('is_search') && $request->is_search == 1) {
            $user_id = auth()->user()->id ?? $request->user_id;

            if ($user_id) {
                $currentprofile = GetCurrentprofile($user_id, $request);

                if ($currentprofile) {
                    $existingSearch = UserSearchHistory::where('user_id', $user_id)
                        ->where('profile_id', $currentprofile)
                        ->where('search_query', $data['name'])
                        ->first();

                    if (!$existingSearch) {
                        UserSearchHistory::create([
                            'user_id' => $user_id,
                            'profile_id' => $currentprofile,
                            'search_query' => $data['name'],
                            'search_id' => $data['id'],
                            'type' => $data['type']
                        ]);
                    }
                }
            }
        }

        return view('frontend::episode_detail', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('frontend::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        //
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('frontend::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('frontend::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): RedirectResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //
    }

    public function stream($encryptedUrl)
    {
        $result = decryptVideoUrl($encryptedUrl);

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json($result, 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function streamLocal($encryptedUrl, HttpRequest $request)
    {
        $url = Crypt::decryptString($encryptedUrl);

        if (!Storage::disk('local')->exists('test.mp4')) {
            abort(404, 'Video not found.');
        }

        return response()->stream(function () {
            $stream = Storage::disk('local')->readStream('test.mp4');

            fpassthru($stream);
            fclose($stream);
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => Storage::disk('local')->size('test.mp4'),
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="test.mp4"'
        ]);
    }
}
