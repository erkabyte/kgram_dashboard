<?php

namespace App\Http\Controllers\Backend\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\MobileSetting;
use Modules\Entertainment\Models\Entertainment;
use Modules\Banner\Models\Banner;
use Modules\Entertainment\Models\ContinueWatch;
use Modules\Banner\Transformers\SliderResource;
use Modules\Entertainment\Transformers\ContinueWatchResource;
use Modules\LiveTV\Models\LiveTvChannel;
use Modules\LiveTV\Transformers\LiveTvChannelResource;
use Modules\CastCrew\Models\CastCrew;
use Modules\CastCrew\Transformers\CastCrewListResource;
use Modules\Genres\Transformers\GenresResource;
use Modules\Genres\Models\Genres;
use Modules\Video\Models\Video;
use App\Services\RecommendationService;
use App\Services\RecommendationServiceV2;
use Modules\Entertainment\Transformers\MoviesResource;
use Modules\Entertainment\Transformers\CommanResource;
use Modules\Entertainment\Transformers\TvshowResource;
use Modules\Constant\Models\Constant;
use Modules\Video\Transformers\VideoResource;
use Carbon\Carbon;
use function PHPSTORM_META\type;
use Illuminate\Support\Facades\Cache;
use Modules\Banner\Transformers\SliderResourceV2;
use Modules\Entertainment\Transformers\ContinueWatchResourceV2;

class DashboardController extends Controller
{
    protected $recommendationService,$recommendationServiceV2;
    public function __construct(RecommendationService $recommendationService, RecommendationServiceV2 $recommendationServiceV2)
    {
        $this->recommendationService = $recommendationService;
        $this->recommendationServiceV2 = $recommendationServiceV2;

    }
    public function DashboardDetail(Request $request){

        $user_id = !empty($request->user_id) ? $request->user_id : null;
        $continueWatch = [];


        if($request->has('user_id')){
            $continueWatchList = ContinueWatch::where('user_id', $user_id)
            ->where('profile_id',$request->profile_id)->get();
            $continueWatch = ContinueWatchResource::collection($continueWatchList);
        }

        $isBanner = MobileSetting::getValueBySlug('banner');
        $sliderList = $isBanner
            ? Banner::where('banner_for','home')->where('status', 1)->get()
            : collect();

        $sliders = SliderResource::collection(
            $sliderList->map(fn($slider) => new SliderResource($slider, $user_id))
        );


        $topMovieIds = MobileSetting::getValueBySlug('top-10');
        $topMovies = !empty($topMovieIds) ? Entertainment::whereIn('id', json_decode($topMovieIds, true))->with('entertainmentGenerMappings')
            ->where('status', 1)
            ->whereDate('release_date', '<=', Carbon::now())
            ->get() : collect();
        $top_10 = CommanResource::collection($topMovies)->toArray(request());


       $responseData = [
           'slider' => $sliders,
           'continue_watch' => $continueWatch,
           'top_10' => $top_10
       ];

       // Cache::put($cacheKey,$responseData);

       return response()->json([
           'status' => true,
           'data' => $responseData,
           'message' => __('messages.dashboard_detail'),
       ], 200);
    }

public function DashboardDetailData(Request $request){

    $user_id = !empty($request->user_id) ? $request->user_id : null;

         if($request->has('user_id')){
           $continueWatchList = ContinueWatch::where('user_id', $user_id)
           ->where('profile_id',$request->profile_id)->get();
           $continueWatch = ContinueWatchResource::collection($continueWatchList);

           $user = User::where('id',$request->user_id)->first();
           $profile_id=$request->profile_id;

           if( $user_id !=null){
               $user = User::where('id',$user_id)->first();

           $likedMovies = $this->recommendationService->getLikedMovies($user, $profile_id);
           $likedMovies = CommanResource::collection($likedMovies);
           $viewedMovies = $this->recommendationService->getEntertainmentViews($user, $profile_id);
           $viewedMovies = CommanResource::collection($viewedMovies);

           $favorite_gener = $this->recommendationService->getFavoriteGener($user, $profile_id);
           $FavoriteGener = GenresResource::collection($favorite_gener);
           $FavoriteGener = $FavoriteGener->toArray(request());

         

           $favorite_personality = $this->recommendationService->getFavoritePersonality($user, $profile_id);
           $favorite_personality = CastCrewListResource::collection($favorite_personality);
           $favorite_personality = $favorite_personality->toArray(request());

           $trendingMovies = $this->recommendationService->getTrendingMoviesByCountry($user, $request);
           $trendingMovies = CommanResource::collection($trendingMovies);

           }

       }

       $latestMovieIds = MobileSetting::getValueBySlug('latest-movies');
       $latestMovieIdsArray = json_decode($latestMovieIds, true);
       $latest_movie = !empty($latestMovieIdsArray) ? CommanResource::collection(
           Entertainment::whereIn('id', $latestMovieIdsArray)->with('entertainmentGenerMappings')
               ->where('status', 1)
               ->whereDate('release_date', '<=', Carbon::now())
               ->get()
       ) : collect();

       $languageIds = MobileSetting::getValueBySlug('enjoy-in-your-native-tongue');
       $languageIdsArray = json_decode($languageIds, true);
       $popular_language = !empty($languageIdsArray) ? Constant::whereIn('id', $languageIdsArray)->get() : collect();

       $popularMovieIds = MobileSetting::getValueBySlug('popular-movies');
       $popularMovieIdsArray = json_decode($popularMovieIds, true);
       $popular_movie = !empty($popularMovieIdsArray) ? CommanResource::collection(
           Entertainment::whereIn('id', $popularMovieIdsArray)->with('entertainmentGenerMappings')
               ->where('status', 1)
               ->whereDate('release_date', '<=', Carbon::now())
               ->get()
       ) : collect();

       $channelIds = MobileSetting::getValueBySlug('top-channels');
       $channelIdsArray = json_decode($channelIds, true);
       $top_channel = !empty($channelIdsArray) ? LiveTvChannelResource::collection(
           LiveTvChannel::whereIn('id', $channelIdsArray)
               ->where('status', 1)
               ->get()
       ) : collect();

       $castIds = MobileSetting::getValueBySlug('your-favorite-personality');
       $castIdsArray = json_decode($castIds, true);
       $personality = [];
       if (!empty($castIdsArray)) {
           $casts = CastCrew::whereIn('id', $castIdsArray)->get();
           foreach ($casts as $value) {
               $personality[] = [
                   'id' => $value->id,
                   'name' => $value->name,
                   'type' => $value->type,
                   'profile_image' => setBaseUrlWithFileName($value->file_url),
               ];
           }
       }

       $movieIds = MobileSetting::getValueBySlug('500-free-movies');
       $movieIdsArray = json_decode($movieIds, true);
       $free_movie = !empty($movieIdsArray) ? CommanResource::collection(
           Entertainment::whereIn('id', $movieIdsArray)->with('entertainmentGenerMappings')
               ->where('status', 1)
               ->whereDate('release_date', '<=', Carbon::now())
               ->get()
       ) : collect();

       $popular_tvshowIds = MobileSetting::getValueBySlug('popular-tvshows');
       $popular_tvshowIdsArray = json_decode($popular_tvshowIds, true);
       $popular_tvshow = !empty($popular_tvshowIdsArray) ? CommanResource::collection(
           Entertainment::whereIn('id', $popular_tvshowIdsArray)->with('entertainmentGenerMappings')
               ->where('status', 1)
               ->get()
       ) : collect();

       $genreIds = MobileSetting::getValueBySlug('genre');
       $genreIdsArray = json_decode($genreIds, true);
       $genres = !empty($genreIdsArray) ? GenresResource::collection(
           Genres::whereIn('id', $genreIdsArray)
               ->where('status', 1)

               ->get()
       ) : collect();

       $videoIds = MobileSetting::getValueBySlug('popular-videos');
       $videoIdsArray = json_decode($videoIds, true);
       $popular_videos = !empty($videoIdsArray) ? VideoResource::collection(
           Video::whereIn('id', $videoIdsArray)
               ->where('status', 1)
               ->get()
       ) : collect();




       $entertainment_list = Entertainment::with([
           'entertainmentReviews' => function ($query) {
               $query->whereBetween('rating', [4, 5])->take(6);
           }
       ])->where('status', 1)
       ->where('type', 'movie')
       ->whereDate('release_date', '<=', Carbon::now())
       ->get();

       $tranding_movie = CommanResource::collection($entertainment_list);

       $responseData = [

        'latest_movie' => $latest_movie,
        'popular_language' => $popular_language,
        'popular_movie' => $popular_movie,
        'top_channel' => $top_channel,
        'personality' => $personality,
        'tranding_movie'=>$tranding_movie,
        'free_movie' => $free_movie,
        'genres' => $genres,
        'popular_tvshow' => $popular_tvshow,
        'popular_videos' => $popular_videos,
        'likedMovies' => $likedMovies ?? [],
        'viewedMovies' => $viewedMovies ?? [],
        'trendingMovies' => $trendingMovies ?? [],
        'favorite_gener' => $FavoriteGener ?? [],
        'favorite_personality' => $favorite_personality ?? [],
        'base_on_last_watch'=>$Lastwatchrecommendation ?? [],
    ];

    // Cache::put($cacheKey,$responseData);

    return response()->json([
        'status' => true,
        'data' => $responseData,
        'message' => __('messages.dashboard_detail'),
    ], 200);


}

    public function getTrandingData(Request $request){


        if ($request->has('is_ajax') && $request->is_ajax == 1) {

            $popularMovieIds = MobileSetting::getValueBySlug(slug: 'popular-movies');
            $movieList = Entertainment::whereIn('id',json_decode($popularMovieIds))->where('status',1)->get();

            $html = '';
            if($request->has('section')&& $request->section == 'tranding_movie'){
                $movieData = (isenablemodule('movie') == 1) ? MoviesResource::collection($movieList) : [];
                if(!empty( $movieData)){

                    foreach( $movieData->toArray(request()) as $index => $movie){
                        $html .= view('frontend::components.card.card_entertainment',['value' => $movie])->render();
                    }
                }
            }


        return response()->json([
                'status' => true,
                'html' => $html,
                'message' => __('movie.tvshow_list'),
            ], 200);
        }



    }

    public function getEntertainmentData(Request $request)
    {
        $type = $request->query('type', 'movie'); // Default to 'movie'
        $user_id = $request->user_id ?? null;


        $isBanner = MobileSetting::getValueBySlug('banner');
        if($request->type == 'tvshow'){
            $request->type = 'tv_show';
        }


        $sliderList = $isBanner
        ? Banner::get_sliderList($request->type)
        : collect();

    // $sliderList =  $sliderList ->where('type', $request->type);

       $sliders = SliderResourceV2::collection($sliderList)->toArray(request());



        return response()->json([
            'status' => true,
            'data' => [
                'slider' => $sliders,
            ],
            'message' => __('messages.' . $type . '_detail'),
        ], 200);
    }




    // public function getMovieData(Request $request)
    // {
    //     return $this->getEntertainmentData($request, 'movie');
    // }

    // public function getTvShowData(Request $request)
    // {
    //     return $this->getTvshowAllData($request, 'tvshow');
    // }
    public function DashboardDetailDataV2(Request $request)
    {

        $user_id = !empty($request->user_id) ? $request->user_id : null;

        if (!Cache::has('genres')) {
            $genresData = Genres::get(['id','name'])->keyBy('id')->toArray();
            Cache::put('genres', $genresData);
        }


            if($request->has('user_id'))
            {
            //    $continueWatchList = ContinueWatch::where('user_id', $user_id)
            //    ->where('profile_id',$request->profile_id)->get();
            //    $continueWatch = ContinueWatchResource::collection($continueWatchList);

               $user = User::where('id',$request->user_id)->first();
               $profile_id=$request->profile_id;

               if( $user_id !=null)
               {
                   $user = User::where('id',$user_id)->first();

                    $likedMovies = $this->recommendationServiceV2->getLikedMovies($user, $profile_id);
                    $likedMovies = CommanResource::collection($likedMovies);
                    $viewedMovies = $this->recommendationService->getEntertainmentViews($user, $profile_id);
                    $viewedMovies = CommanResource::collection($viewedMovies);

                    $FavoriteGener = $this->recommendationService->getFavoriteGener($user, $profile_id);
                    $FavoriteGener = GenresResource::collection($FavoriteGener);
            

                    $favorite_personality = $this->recommendationService->getFavoritePersonality($user, $profile_id);
                     $favorite_personality = CastCrewListResource::collection($favorite_personality);
                  
                    $trendingMovies = $this->recommendationService->getTrendingMoviesByCountry($user, $request);
                    $trendingMovies = CommanResource::collection($trendingMovies);
               }

            }

           $latestMovieIds = MobileSetting::getCacheValueBySlug('latest-movies');
           $latestMovieIdsArray = json_decode($latestMovieIds, true);


           $latest_movie = (!empty($latestMovieIdsArray)) ? Entertainment::get_latest_movie($latestMovieIdsArray) : collect();
           $latest_movie = MoviesResource::collection($latest_movie)->toArray(request());


           $languageIds = MobileSetting::getCacheValueBySlug('enjoy-in-your-native-tongue');
           $languageIdsArray = json_decode($languageIds, true);
           $popular_language = !empty($languageIdsArray) ? Constant::whereIn('id', $languageIdsArray)->get() : collect();

           $popularMovieIds = MobileSetting::getCacheValueBySlug('popular-movies');
           
           $popularMovieIdsArray = json_decode($popularMovieIds, true);
           $popular_movie = (!empty($popularMovieIdsArray)) ? Entertainment::get_popular_movie($popularMovieIdsArray) : collect();
           $popular_movie = MoviesResource::collection($popular_movie)->toArray(request());


           $channelIds = MobileSetting::getValueBySlug('top-channels');
           $channelIdsArray = json_decode($channelIds, true);

           $top_channel = (!empty($channelIdsArray)) ? LiveTvChannel::get_top_channel($channelIdsArray) : collect();
           $top_channel = LiveTvChannelResource::collection($top_channel)->toArray(request());

           

           $castIds = MobileSetting::getValueBySlug('your-favorite-personality');
           $castIdsArray = json_decode($castIds, true);
           $personality = [];
            if (!empty($castIdsArray)) {
               $casts = CastCrew::whereIn('id', $castIdsArray)->get();
               foreach ($casts as $value) {
                   $personality[] = [
                       'id' => $value->id,
                       'name' => $value->name,
                       'type' => $value->type,
                       'profile_image' => setBaseUrlWithFileName($value->file_url),
                   ];
               }
            }

           $movieIds = MobileSetting::getValueBySlug('500-free-movies');
           $movieIdsArray = json_decode($movieIds, true);


           $free_movie = !empty($movieIdsArray) ? Entertainment::get_free_movie($movieIdsArray) : collect();
           $free_movie = MoviesResource::collection($free_movie)->toArray(request());


           $popular_tvshowIds = MobileSetting::getValueBySlug('popular-tvshows');
           $popular_tvshowIdsArray = json_decode($popular_tvshowIds, true);

           $popular_tvshow = !empty($popular_tvshowIdsArray) ? Entertainment::get_popular_tvshow($popular_tvshowIdsArray) : collect();
           $popular_tvshow = TvshowResource::collection($popular_tvshow)->toArray(request());


           $genreIds = MobileSetting::getValueBySlug('genre');
           $genreIdsArray = json_decode($genreIds, true);
           $genres = !empty($genreIdsArray) ? GenresResource::collection(
               Genres::whereIn('id', $genreIdsArray)
                   ->where('status', 1)
                   ->get()
           ) : collect();

            $videoIds = MobileSetting::getValueBySlug('popular-videos');
            $videoIdsArray = json_decode($videoIds, true);

            $popular_videos = !empty($videoIdsArray) ? Video::get_popular_videos($videoIdsArray) : collect();
            $popular_videos = VideoResource::collection($popular_videos)->toArray(request());

            $tranding_movie = Entertainment::get_entertainment_list();
            $tranding_movie = MoviesResource::collection($tranding_movie)->toArray(request());


           $responseData = [
               'latest_movie' => $latest_movie,
                'popular_language' => $popular_language,
                'popular_movie' => $popular_movie,
                'top_channel' => $top_channel,
                'personality' => $personality,
                'free_movie' => $free_movie,
                'popular_tvshow' => $popular_tvshow,
                'genres' => $genres,
                'popular_videos' => $popular_videos,
                'likedMovies' => $likedMovies ?? [],
                'viewedMovies' => $viewedMovies ?? [],
                'trendingMovies' => $trendingMovies ?? [],
                'favorite_gener' => $FavoriteGener ?? [],
                'favorite_personality' => $favorite_personality ?? [],
                'base_on_last_watch'=> $Lastwatchrecommendation ?? [],
                'tranding_movie'=>$tranding_movie,
            ];

        return response()->json([
            'status' => true,
            'data' => $responseData,
            'message' => __('messages.dashboard_detail'),
        ], 200);
    }

    public function DashboardDetailV2(Request $request)
    {
        if (!Cache::has('genres')) {
            $genresData = Genres::get(['id','name'])->keyBy('id')->toArray();
            Cache::put('genres', $genresData);
        }

        $user_id = !empty($request->user_id) ? $request->user_id : null;
        $continueWatch = [];

        if($request->has('user_id')){
            $continueWatchList = ContinueWatch::where('user_id', $user_id)
            ->where('profile_id',$request->profile_id)->get();
            $continueWatch = ContinueWatchResourceV2::collection($continueWatchList);
        }

        $isBanner = MobileSetting::getCacheValueBySlug('banner');
        $sliderList = $isBanner
            ? Banner::get_sliderList('home')
            : collect(); 
    
        $sliders = SliderResourceV2::collection($sliderList)->toArray(request());

        $topMovieIds = MobileSetting::getCacheValueBySlug('top-10');

        $top_10 = !empty($topMovieIds) ? Entertainment::get_top_movie(json_decode($topMovieIds, true)) : collect();

        $top_10 = MoviesResource::collection($top_10)->toArray(request());

        $responseData = [
           'slider' => $sliders,
           'continue_watch' => $continueWatch,
           'top_10' => $top_10
        ];

       // Cache::put($cacheKey,$responseData);

       return response()->json([
           'status' => true,
           'data' => $responseData,
           'message' => __('messages.dashboard_detail'),
       ], 200);
    }
}
