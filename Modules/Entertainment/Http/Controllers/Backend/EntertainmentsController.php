<?php

namespace Modules\Entertainment\Http\Controllers\Backend;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Modules\Entertainment\Models\Entertainment;
use Illuminate\Http\Request;
use Modules\Entertainment\Http\Requests\EntertainmentRequest;
use App\Trait\ModuleTrait;
use App\Services\DropboxService;
use Modules\Constant\Models\Constant;
use Modules\Subscriptions\Models\Plan;
use Modules\Genres\Models\Genres;
use Modules\CastCrew\Models\CastCrew;
use Modules\Entertainment\Services\EntertainmentService;
use Modules\World\Models\Country;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
// use Spatie\Dropbox\Facades\Dropbox;
use Spatie\Dropbox\Client as DropboxClient;
// use App\Services\DropboxService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\DropboxToken;
use Illuminate\Support\Facades\Log;


class EntertainmentsController extends Controller
{
    protected string $exportClass = '\App\Exports\EntertainmentExport';

    public function redirectToDropbox()
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => env('DROPBOX_APP_KEY'),
            'redirect_uri' => env('DROPBOX_REDIRECT_URI'),
            'token_access_type' => 'offline', // ensures refresh token
        ]);

        return redirect("https://www.dropbox.com/oauth2/authorize?$query");
    }

    // Step 2: Handle callback and save tokens
    public function handleCallback(Request $request)
    {
        $code = $request->get('code');
        Log::info('the code: '.$code);

        $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => env('DROPBOX_APP_KEY'),
            'client_secret' => env('DROPBOX_APP_SECRET'),
            'redirect_uri' => env('DROPBOX_REDIRECT_URI'),
        ]);

        $data = $response->json();
        Log::info('the data: '.json_encode($data) );

        // Save tokens in DB
        DropboxToken::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => now()->addSeconds($data['expires_in']),
            ]
        );

        return redirect('/app/movies/create')->with('success', 'Dropbox connected successfully!');
    }

    // Step 3: Refresh token (manual trigger or background job)
    public function refreshToken()
    {
        // $token = DropboxToken::where('user_id', Auth::id())->first();
        // // $token = config('services.dropbox.access_token');
        // if (!$token) {
        //     return redirect('/app/movies/create')->with('error', 'No Dropbox token found');
        // }

        // $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
        //     'grant_type' => 'refresh_token',
        //     'refresh_token' => $token->refresh_token,
        //     'client_id' => env('DROPBOX_APP_KEY'),
        //     'client_secret' => env('DROPBOX_APP_SECRET'),
        // ]);

        // $data = $response->json();

        // $token->update([
        //     'access_token' => $data['access_token'],
        //     'expires_at' => now()->addSeconds($data['expires_in']),
        // ]);
        $token = DropboxToken::where('user_id', Auth::id())->first();

        // if (now()->greaterThanOrEqualTo($token->expires_at)) {
            // refresh
            $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id'     => env('DROPBOX_APP_KEY'),
                'client_secret' => env('DROPBOX_APP_SECRET'),
            ]);

            $data = $response->json();

            $token->update([
                'access_token' => $data['access_token'],
                // 'refresh_token' => $data['refresh_token'],
                'expires_at'   => now()->addSeconds($data['expires_in']),
            ]);
        // }

        return redirect('/app/movies/create')->with('success', 'Dropbox token refreshed!');
    }


    use ModuleTrait {
        initializeModuleTrait as private traitInitializeModuleTrait;
        }

        protected $entertainmentService;

        public function __construct(EntertainmentService $entertainmentService)
        {
            $this->entertainmentService = $entertainmentService;

            $this->traitInitializeModuleTrait(
                'castcrew.castcrew_title',
                'castcrew',

                'fa-solid fa-clipboard-list'
            );
        }


    public function index(Request $request)
    {
        $filter = [
            'status' => $request->status,
        ];

        $module_action = 'List';

        $export_import = true;
        $export_columns = [
            [
                'value' => 'name',
                'text' => ' Name',
            ]
        ];
        $export_url = route('backend.entertainments.export');

        return view('entertainment::backend.entertainment.index', compact('module_action', 'filter', 'export_import', 'export_columns', 'export_url'));
    }

    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;
        $moduleName = 'Entertainment'; // Adjust as necessary for dynamic use

        Cache::flush();


        return $this->performBulkAction(Entertainment::class, $ids, $actionType, $moduleName);
    }

    public function store(EntertainmentRequest $request)
     {

        $data = $request->all();
        Log::info('Form data : '.json_encode($data));
        $data['thumbnail_url'] = !empty($data['tmdb_id']) ? $data['thumbnail_url'] :extractFileNameFromUrl($data['thumbnail_url']);
        $data['poster_url']= !empty( $data['tmdb_id']) ?  $data['poster_url'] : extractFileNameFromUrl($data['poster_url']);
        $data['poster_tv_url']= !empty( $data['tmdb_id']) ?  $data['poster_tv_url'] : extractFileNameFromUrl($data['poster_tv_url']);

        // if (isset($data['IMDb_rating'])) {
        //     // Round the IMDb rating to 1 decimal place
        //     $data['IMDb_rating'] = round($data['IMDb_rating'], 1);
        // }

        if($request->trailer_url_type == 'Local'){
            $data['trailer_video'] = extractFileNameFromUrl($data['trailer_video']);
        }
        if($request->video_upload_type == 'Local'){
            $data['video_file_input'] = extractFileNameFromUrl($data['video_file_input']);
        }
        if($request->trailer_url_type == 'Dropbox'){
            $data['trailer_video'] = extractFileNameFromUrl($data['trailer_url']);
        }
        if($request->video_upload_type == 'Dropbox'){
            $data['video_file_input'] = extractFileNameFromUrl($data['video_url_input']);
        }

        $entertainment = $this->entertainmentService->create($data);
        $type = $entertainment->type;
        $message = trans('messages.create_form', ['type' =>ucfirst($type)]);
        Log::info('Entertainment id :: ', [
            'id ::' => $entertainment->id,
            'dropbox_url :: '=> $entertainment->dropbox_url,
            'dropbox_trailer_url :: '=> $entertainment->dropbox_trailer_url
            // 'file' => $file->getClientOriginalName()
        ]);

        if (!empty($entertainment->dropbox_trailer_url)) {
            $this->convertDropboxVideo($entertainment->dropbox_trailer_url, $entertainment->id,"trailer");
        }
        if (!empty($entertainment->dropbox_url)) {
            $this->convertDropboxVideo($entertainment->dropbox_url, $entertainment->id,"movie");
        }
        

        Cache::flush();

        if($type=='movie'){

            return redirect()->route('backend.movies.index')->with('success', $message);

        }else{

            return redirect()->route('backend.tvshows.index')->with('success', $message);
        }
    }

    public function update_status(Request $request, Entertainment $id)
    {
        $id->update(['status' => $request->status]);

        Cache::flush();

        return response()->json(['status' => true, 'message' => __('messages.status_updated')]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {

        $data = Entertainment::where('id', $id)
            ->with([
                'entertainmentGenerMappings',
                'entertainmentCountryMappings',
                'entertainmentStreamContentMappings',
                'entertainmentTalentMappings'
            ])
            ->first();

        $tmdb_id = $data->tmdb_id;
        $data->thumbnail_url = setBaseUrlWithFileName($data->thumbnail_url);
        $data->poster_url =setBaseUrlWithFileName($data->poster_url);

        $data->poster_tv_url =setBaseUrlWithFileName($data->poster_tv_url);
        if($data->trailer_url_type =='Local'){

            $data->trailer_url = setBaseUrlWithFileName($data->trailer_url);
        }

        if($data->video_upload_type =='Local'){

            $data->video_url_input = setBaseUrlWithFileName($data->video_url_input);
        }


        $constants = Constant::whereIn('type', ['upload_type', 'movie_language', 'video_quality'])->get();
        $upload_url_type = $constants->where('type', 'upload_type');
        $movie_language = $constants->where('type', 'movie_language');
        $video_quality = $constants->where('type', 'video_quality');

        $plan = Plan::where('status', 1)->get();
        $genres = Genres::where('status', 1)->get();
        $actors = CastCrew::where('type', 'actor')->get();
        $directors = CastCrew::where('type', 'director')->get();
        $countries = Country::where('status', 1)->get();
        $mediaUrls = getMediaUrls();
        $assets = ['textarea'];

        if ($data->type === 'tvshow') {
            $module_title = __('tvshow.edit_title');
        } else {
            $module_title = __('movie.edit_title');
        }


        $numberOptions = collect(range(1, 10))->mapWithKeys(function ($number) {
            return [$number => $number];
        });

        $data['genres_data'] = $data->entertainmentGenerMappings->pluck('genre_id')->toArray();
        // $data['countries'] = $data->entertainmentCountryMappings->pluck('country_id')->toArray();
        // $data['actors'] = $data->entertainmentTalentMappings->pluck('talent_id')->toArray();
        // $data['directors'] = $data->entertainmentTalentMappings->pluck('talent_id')->toArray();

        $dropboxStatusOptions = $this->entertainmentService->getDropboxStatusOptions();

        return view('entertainment::backend.entertainment.edit', compact(
            'data',
            'tmdb_id',
            'upload_url_type',
            'plan',
            'movie_language',
            'genres',
            'numberOptions',
            // 'actors',
            // 'directors',
            'countries',
            'video_quality',
            'mediaUrls',
            'assets',
            'module_title',
            'dropboxStatusOptions'

        ));
    }


    public function update(EntertainmentRequest $request, $id)
    {

        Cache::flush();
        $request_data=$request->all();
        $request_data['thumbnail_url'] = !empty($request_data['tmdb_id']) ? $request_data['thumbnail_url'] :extractFileNameFromUrl($request_data['thumbnail_url']);
        $request_data['poster_url'] = !empty($request_data['tmdb_id']) ? $request_data['poster_url'] : extractFileNameFromUrl($request_data['poster_url']);
        $request_data['poster_tv_url'] = !empty($request_data['tmdb_id']) ? $request_data['poster_tv_url'] : extractFileNameFromUrl($request_data['poster_tv_url']);
        $request_data['trailer_video'] = extractFileNameFromUrl($request_data['trailer_video']);
        $request_data['video_file_input'] = isset($request_data['video_file_input'])  ? extractFileNameFromUrl($request_data['video_file_input']) : null;

        if (isset($request_data['IMDb_rating'])) {
            // Round the IMDb rating to 1 decimal place
            $request_data['IMDb_rating'] = round($request_data['IMDb_rating'], 1);
        }

        $entertainment = $this->entertainmentService->getById($id);

        // Handle Poster Image
        if ($request->input('remove_image') == 1) {
            $requestData['poster_url'] = setDefaultImage($request_data['poster_url']);


        } elseif ($request->hasFile('poster_url')) {
            $file = $request->file('poster_url');
            StoreMediaFile($entertainment, $file, 'poster_url');
            $requestData['poster_url'] = $entertainment->getFirstMediaUrl('poster_url');
        } else {
            $requestData['poster_url'] = $entertainment->poster_url;
        }

        // Handle Poster Image
        if ($request->input('remove_image_tv') == 1) {
            $requestData['poster_tv_url'] = setDefaultImage($request_data['poster_tv_url']);


        } elseif ($request->hasFile('poster_tv_url')) {
            $file = $request->file('poster_tv_url');
            StoreMediaFile($entertainment, $file, 'poster_tv_url');
            $requestData['poster_tv_url'] = $entertainment->getFirstMediaUrl('poster_tv_url');
        } else {
            $requestData['poster_tv_url'] = $entertainment->poster_tv_url;
        }

        // Handle Thumbnail Image
        if ($request->input('remove_image_thumbnail') == 1) {
            $requestData['thumbnail_url'] = setDefaultImage($request_data['thumbnail_url']);
        } elseif ($request->hasFile('thumbnail_url')) {
            $file = $request->file('thumbnail_url');
            StoreMediaFile($entertainment, $file, 'thumbnail_url');
            $requestData['thumbnail_url'] = $entertainment->getFirstMediaUrl('thumbnail_url');
        } else {
            $requestData['thumbnail_url'] = $entertainment->thumbnail_url;
        }
        $data = $this->entertainmentService->update($id, $request_data);

        Cache::flush();


        $type = $entertainment->type;
        $message = trans('messages.update_form', ['Form' =>ucfirst($type)]);

        if ($type == 'movie') {
            return redirect()->route('backend.movies.index')
                ->with('success', $message);
        } else if ($type == 'tvshow') {
            return redirect()->route('backend.tvshows.index')
                ->with('success', $message);
        }
    }


    public function destroy($id)
    {
       $entertainment = $this->entertainmentService->getById($id);
       $type=$entertainment->type;
       $entertainment->delete();
       $message = trans('messages.delete_form', ['form' => $type]);
       Cache::flush();
       return response()->json(['message' => $message, 'status' => true], 200);
    }

    public function restore($id)
    {
        $entertainment = $this->entertainmentService->getById($id);
        $type=$entertainment->type;
        $entertainment->restore();
        $message = trans('messages.restore_form', ['form' =>$type]);
        Cache::flush();
        return response()->json(['message' => $message, 'status' => true], 200);

    }

    public function forceDelete($id)
    {
        $entertainment = $this->entertainmentService->getById($id);
        $type=$entertainment->type;
        $entertainment->forceDelete();
        $message = trans('messages.permanent_delete_form', ['form' =>$type]);
        Cache::flush();
        return response()->json(['message' => $message, 'status' => true], 200);
    }

    public function downloadOption(Request $request, $id){

        $data = Entertainment::where('id',$id)->with('entertainmentDownloadMappings')->first();

        $module_title =__('messages.download_movie');

        $upload_url_type=Constant::where('type','upload_type')->get();
        $video_quality=Constant::where('type','video_quality')->get();
        Cache::flush();

        return view('entertainment::backend.entertainment.download', compact('data','module_title','upload_url_type','video_quality'));

    }


   public function storeDownloads(Request $request, $id)
    {
        $data = $request->all();
        $this->entertainmentService->storeDownloads($data, $id);
        $message = trans('messages.set_download_url');
        Cache::flush();

        return redirect()->route('backend.movies.index')->with('success', $message);
    }


    public function details($id)
    {
        $data = Entertainment::with([
            'entertainmentGenerMappings',
            'entertainmentStreamContentMappings',
            'entertainmentTalentMappings',
            'entertainmentReviews',
            'season',

        ])->findOrFail($id);


       foreach ($data->entertainmentTalentMappings as $talentMapping) {
    $talentProfile = $talentMapping->talentprofile;

    if ($talentProfile) {
        if (in_array($talentProfile->type, ['actor', 'director'])) {
            $talentProfile->file_url =  setBaseUrlWithFileName($talentProfile->file_url);
        }
    }
}
        $data->poster_url =setBaseUrlWithFileName($data->poster_url);

        $data->formatted_release_date = Carbon::parse($data->release_date)->format('d M, Y');
        if($data->type == "movie"){
            $module_title = __('movie.title');
            $show_name = $data->name;
            $route = 'backend.movies.index';
        }else{
            $module_title = __('tvshow.title');
            $show_name = $data->name;
            $route = 'backend.tvshows.index';
        }

        return view('entertainment::backend.entertainment.details', compact('data','module_title','show_name','route'));
    }

    
    /**
     * Upload video to Dropbox
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function uploadSmallFile(UploadedFile $file, string $path): array
    {
        $contents = file_get_contents($file->getRealPath());
        
        return $this->client->upload($path, $contents, [
            'autorename' => false,
            'mute' => false,
            'strict_conflict' => false
        ]);
    }


    protected function sendHlsRequest(Request $dropbox_link)
    {
        $videoUrl = $dropbox_link;//$request->input('video_url');

        try {
            // Send to Express.js converter
            $response = Http::timeout(1200) // wait up to 10 minutes
                ->post('http://localhost:3001/convert-and-upload', [
                    'video_url' => $videoUrl,
                    'id' => 2
                ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Conversion failed',
                    'details' => $response->body()
                ], 500);
            }

            $data = $response->json();

            // Save or return the master.m3u8 link
            return response()->json([
                'success' => true,
                'hls_url' => $data['masterUrl'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server error',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload video to Dropbox
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadToDropboxAndConvert(Request $request)
    {
        try {
            $request->validate([
                'video_file' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv' // 2GB max
            ]);







            // Optional: update entertainment dropbox_video_status if provided
            // $entertainmentId = $request->input('entertainment_id');
            // if (!empty($entertainmentId)) {
            //     try {
            //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'uploading');
            //     } catch (\Throwable $t) {
            //         Log::warning('Failed to set uploading status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
            //     }
            // }




            

            $dropboxService = new DropboxService();
            $client = new DropboxClient();
            // if (!$dropboxService->isConfigured()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Dropbox is not configured. Please check your settings.'
            //     ], 400);
            // }

            // Test connection first
            // $connectionTest = $dropboxService->testConnection();
            // if (!$connectionTest['success']) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Dropbox connection failed: ' . $connectionTest['message']
            //     ], 400);
            // }

            // $result = $dropboxService->uploadVideo($request->file('video_file'));
            $result = $dropboxService->uploadWithClient($request->file('video_file'), '/Apps/kgram');

            
            if ($result['success']) {


                // to be modified
                // if (!empty($entertainmentId)) {
                //     try {
                //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'processing');
                //     } catch (\Throwable $t) {
                //         Log::warning('Failed to set processing status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                //     }
                // }



                // sendHlsRequest($payload->dropbox_url);
                $dropbox_url = $result['dropbox_url'];
                $dropbox_url = str_replace("&dl=0", "&dl=1", $dropbox_url);
                Log::info('to convert link : '.$dropbox_url);
                $convert_result = $dropboxService->sendHlsRequest($dropbox_url);
                // $convert_result = json_encode($convert_result);
                Log::info('convert result : '.json_encode($convert_result));
                $convert_result = json_encode($convert_result, true);
                $convert_result = json_decode($convert_result, true);
                $convert_result = $convert_result[0]['original'];
                Log::info('convert result : '.json_encode($convert_result));

                if ($convert_result['success']) {
                    // Log::info('Convert result : '.$convert_result);//['hls_url']);//['hlsOutputDir']);
                    $payload = [
                        'success' => true,
                        'dropbox_url' => $result['dropbox_url'] ?? null,
                        'hls_file'=>$convert_result['hls_url'],
                        'message' => 'Video uploaded to Dropbox successfully',
                        'status' => 'completed'
                    ];
                    // if (!empty($entertainmentId)) {
                    //     try {
                    //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'completed', $result['dropbox_url']);
                    //     } catch (\Throwable $t) {
                    //         Log::warning('Failed to set completed status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                    //     }
                    // }
    
                    return response()->json(
                        $this->ensureUtf8($payload),
                        200,
                        [],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                    );

                }
                else {
                    Log::info('Converting error : '.json_encode($convert_result));//['hls_url']);//['hlsOutputDir']);
                    $payload = [
                        'success' => false,
                        'message' => $result['message'] ?? 'HLS convert failed',
                        // 'status' => 'failed'
                    ];
                    // if (!empty($entertainmentId)) {
                    //     try {
                    //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'failed');
                    //     } catch (\Throwable $t) {
                    //         Log::warning('Failed to set failed status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                    //     }
                    // }
                    return response()->json(
                        $this->ensureUtf8($payload),
                        400,
                        [],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                    );

                }
                // Log::info('Convert response data : '.json_encode($convert_result['data']));//['hls_url']);//['hlsOutputDir']);
                
            } else {
                $payload = [
                    'success' => false,
                    'message' => $result['message'] ?? 'Upload failed',
                    // 'status' => 'failed'
                ];

                // if (!empty($entertainmentId)) {
                //     try {
                //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'failed');
                //     } catch (\Throwable $t) {
                //         Log::warning('Failed to set failed status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                //     }
                // }
                
                return response()->json(
                    $this->ensureUtf8($payload),
                    400,
                    [],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );
            }

        } catch (\Exception $e) {
            \Log::error('Dropbox upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $payload = [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                // 'status' => 'failed'
            ];
            return response()->json(
                $this->ensureUtf8($payload),
                500,
                [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
        }
    }


    /**
     * Upload video to Dropbox
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function convertDropboxVideo(?string $dropboxPath, string $entertainmentId, ?string $videoType)
    {
        try {
            if (empty($dropboxPath)) {
                Log::info('Dropbox path is empty, skipping conversion', [
                    'entertainment_id' => $entertainmentId,
                    'video_type' => $videoType
                ]);
                return;
            }

            $dropboxService = new DropboxService();
                // sendHlsRequest($payload->dropbox_url);
                $dropbox_url = $dropboxPath;
                $dropbox_url = str_replace("&dl=0", "&dl=1", $dropbox_url);
                Log::info('to convert link : '.$dropbox_url);
                Log::info('to convert type : '.$videoType);
                $result = $dropboxService->sendHlsConvertRequest($dropbox_url,$entertainmentId,$videoType);

        } catch (\Exception $e) {
            \Log::error('Dropbox upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // $payload = [
            //     'success' => false,
            //     'message' => 'Upload failed: ' . $e->getMessage(),
            //     // 'status' => 'failed'
            // ];
            // return response()->json(
            //     $this->ensureUtf8($payload),
            //     500,
            //     [],
            //     JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            // );
        }
    }

    /**
     * Upload video to Dropbox
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadToDropbox(Request $request)
    {
        try {
            $request->validate([
                'video_file' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm,mkv' // 2GB max
            ]);







            // Optional: update entertainment dropbox_video_status if provided
            // $entertainmentId = $request->input('entertainment_id');
            // if (!empty($entertainmentId)) {
            //     try {
            //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'uploading');
            //     } catch (\Throwable $t) {
            //         Log::warning('Failed to set uploading status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
            //     }
            // }




            

            $dropboxService = new DropboxService();
            $client = new DropboxClient();
            // if (!$dropboxService->isConfigured()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Dropbox is not configured. Please check your settings.'
            //     ], 400);
            // }

            // Test connection first
            // $connectionTest = $dropboxService->testConnection();
            // if (!$connectionTest['success']) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Dropbox connection failed: ' . $connectionTest['message']
            //     ], 400);
            // }

            // $result = $dropboxService->uploadVideo($request->file('video_file'));
            $result = $dropboxService->uploadWithClient($request->file('video_file'), '/Apps/kgram');

            
            if ($result['success']) {


                // to be modified
                // if (!empty($entertainmentId)) {
                //     try {
                //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'processing');
                //     } catch (\Throwable $t) {
                //         Log::warning('Failed to set processing status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                //     }
                // }



                // sendHlsRequest($payload->dropbox_url);
                $dropbox_url = $result['dropbox_url'];
                $dropbox_url = str_replace("&dl=0", "&dl=1", $dropbox_url);
                Log::info('to convert link : '.$dropbox_url);
                // $convert_result = $dropboxService->sendHlsRequest($dropbox_url);
                // // $convert_result = json_encode($convert_result);
                // Log::info('convert result : '.json_encode($convert_result));
                // $convert_result = json_encode($convert_result, true);
                // $convert_result = json_decode($convert_result, true);
                // $convert_result = $convert_result[0]['original'];
                // Log::info('convert result : '.json_encode($convert_result));

                // if ($convert_result['success']) {
                    // Log::info('Convert result : '.$convert_result);//['hls_url']);//['hlsOutputDir']);
                    $payload = [
                        'success' => true,
                        'dropbox_url' => $result['dropbox_url'] ?? null,
                        // 'hls_file'=>$convert_result['hls_url'],
                        'message' => 'Video uploaded to Dropbox successfully',
                        'status' => 'completed'
                    ];
                    // if (!empty($entertainmentId)) {
                    //     try {
                    //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'completed', $result['dropbox_url']);
                    //     } catch (\Throwable $t) {
                    //         Log::warning('Failed to set completed status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                    //     }
                    // }
    
                    return response()->json(
                        $this->ensureUtf8($payload),
                        200,
                        [],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                    );

                // }
                // else {
                //     Log::info('Converting error : '.json_encode($convert_result));//['hls_url']);//['hlsOutputDir']);
                //     $payload = [
                //         'success' => false,
                //         'message' => $result['message'] ?? 'HLS convert failed',
                //         // 'status' => 'failed'
                //     ];
                //     // if (!empty($entertainmentId)) {
                //     //     try {
                //     //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'failed');
                //     //     } catch (\Throwable $t) {
                //     //         Log::warning('Failed to set failed status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                //     //     }
                //     // }
                //     return response()->json(
                //         $this->ensureUtf8($payload),
                //         400,
                //         [],
                //         JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                //     );

                // }
                // Log::info('Convert response data : '.json_encode($convert_result['data']));//['hls_url']);//['hlsOutputDir']);
                
            } else {
                $payload = [
                    'success' => false,
                    'message' => $result['message'] ?? 'Upload failed',
                    // 'status' => 'failed'
                ];

                // if (!empty($entertainmentId)) {
                //     try {
                //         $this->entertainmentService->updateDropboxStatus($entertainmentId, 'failed');
                //     } catch (\Throwable $t) {
                //         Log::warning('Failed to set failed status', ['id' => $entertainmentId, 'error' => $t->getMessage()]);
                //     }
                // }
                
                return response()->json(
                    $this->ensureUtf8($payload),
                    400,
                    [],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );
            }

        } catch (\Exception $e) {
            \Log::error('Dropbox upload error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $payload = [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
                // 'status' => 'failed'
            ];
            return response()->json(
                $this->ensureUtf8($payload),
                500,
                [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
        }
    }

    public function convertToHls(Request $request)
    {
        // Example: Dropbox file link or storage path
        $videoUrl = $request->input('video_url');

        try {
            // Send to Express.js converter
            $response = Http::timeout(1200) // wait up to 10 minutes
                ->post('http://localhost:3000/convert', [
                    'video_url' => $videoUrl,
                    'id' => 2
                ]);

            if ($response->failed()) {
                return response()->json([
                    'error' => 'Conversion failed',
                    'details' => $response->body()
                ], 500);
            }

            $data = $response->json();

            // Save or return the master.m3u8 link
            return response()->json([
                'success' => true,
                'hls_url' => $data['masterUrl'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server error',
                'details' => $e->getMessage()
            ], 500);
        }
    }   

    private function ensureUtf8($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->ensureUtf8($v);
            }
            return $value;
        }
        if (is_string($value)) {
            if (!mb_check_encoding($value, 'UTF-8')) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                if ($converted !== false) {
                    return $converted;
                }
                return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            return $value;
        }
        return $value;
    }

    /**
     * Test Dropbox connection
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function testDropboxConnection()
    {
        try {
            $dropboxService = new DropboxService();
            
            if (!$dropboxService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dropbox is not configured. Please check your settings.'
                ], 400);
            }

            // Check token format
            if (!$dropboxService->validateTokenFormat()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dropbox access token format is invalid. Please check your configuration.'
                ], 400);
            }

            $result = $dropboxService->testConnection();

            // If the first method fails, try the alternative
            if (!$result['success']) {
                Log::info('Primary Dropbox connection test failed, trying alternative method');
                $result = $dropboxService->testConnectionAlternative();
            }

            return response()->json($result);

        } catch (\Exception $e) {
            \Log::error('Dropbox connection test error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Dropbox configuration status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDropboxConfigStatus()
    {
        try {
            $dropboxService = new DropboxService();
            
            $config = [
                'is_configured' => $dropboxService->isConfigured(),
                'token_format_valid' => $dropboxService->validateTokenFormat(),
                'config_vars' => [
                    'app_key' => !empty(config('services.dropbox.app_key')),
                    'app_secret' => !empty(config('services.dropbox.app_secret')),
                    'access_token' => !empty(config('services.dropbox.access_token')),
                    'redirect_uri' => !empty(config('services.dropbox.redirect_uri'))
                ]
            ];

            return response()->json([
                'success' => true,
                'config' => $config
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration check failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test filename generation for debugging UTF-8 issues
     *
     * @param string $filename
     * @return \Illuminate\Http\JsonResponse
     */
    public function testFilenameGeneration($filename)
    {
        try {
            $dropboxService = new DropboxService();
            $result = $dropboxService->testFilenameGeneration($filename);
            
            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to test filename generation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dropbox status for an entertainment
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDropboxStatus($id)
    {
        try {
            $entertainment = $this->entertainmentService->getById($id);
            
            return response()->json([
                'success' => true,
                'dropbox_video_status' => $entertainment->dropbox_video_status,
                'dropbox_url' => $entertainment->dropbox_url
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dropbox status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update dropbox status for an entertainment
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDropboxStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:queued,uploading,processing,completed,failed',
                'dropbox_url' => 'nullable|url'
            ]);

            $this->entertainmentService->updateDropboxStatus(
                $id, 
                $request->status, 
                $request->dropbox_url
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Dropbox status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update dropbox status: ' . $e->getMessage()
            ], 500);
        }
    }

}



// <!-- <div class="d-flex align-items-center justify-content-between mt-5 pt-4 mb-3">
// <h5>{{ __('movie.lbl_actor_director') }}</h5>
// </div>
// <div class="card">
// <div class="card-body">
//     <div class="row gy-3">
//         <div class="col-md-6">
//             {{ html()->label(__('movie.lbl_actors') . '<span class="text-danger">*</span>', 'actors')->class('form-label') }}
//             {{ html()->select('actors[]', $actors->pluck('name', 'id'), $data->actors )->class('form-control select2')->id('actors')->multiple()->attribute('required','required') }}
//             @error('actors')
//                 <span class="text-danger">{{ $message }}</span>
//             @enderror
//              <div class="invalid-feedback" id="name-error">Actors field is required</div>
//         </div>

//         <div class="col-md-6">
//             {{ html()->label(__('movie.lbl_directors') . '<span class="text-danger">*</span>', 'directors')->class('form-label') }}
//             {{ html()->select('directors[]', $directors->pluck('name', 'id'), $data->directors )->class('form-control select2')->id('directors')->multiple()->attribute('required','required') }}
//             @error('directors')
//                 <span class="text-danger">{{ $message }}</span>
//             @enderror
//              <div class="invalid-feedback" id="name-error">Directors field is required</div>
//         </div>
//     </div>
// </div>
// </div> -->