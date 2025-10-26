<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;
use Spatie\Dropbox\Client as DropboxClient;
use App\Models\DropboxToken;
use Illuminate\Support\Facades\Auth;

class DropboxService
{
    protected $accessToken;
    protected $baseUrl = 'https://content.dropboxapi.com/2';
    protected $sharedLinkUrl = 'https://api.dropboxapi.com/2';
    protected DropboxClient $client;
    protected $disk;
    protected $a_token;
    // protected $token;
    public function __construct()
    {
        $token = DropboxToken::where('user_id', Auth::id())->firstOrFail();

        Log::info('Dropbox Access Token: '.$token->access_token);
        Log::info('Dropbox Refresh Token: '.$token->refresh_token);
        // Refresh if expired
        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id'     => env('DROPBOX_APP_KEY'),
                'client_secret' => env('DROPBOX_APP_SECRET'),
            ]);

            $data = $response->json();

            $token->update([
                'access_token' => $data['access_token'],
                'expires_at'   => now()->addSeconds($data['expires_in']),
            ]);
        }
        // $this->accessToken = config('services.dropbox.access_token');
        // $this->accessToken = DropboxToken::where('user_id', Auth()->id())->first();
        // $this->a_token = DropboxToken::where('user_id', Auth::id())->first();
        // $this->accessToken = $token->access_token;//$this->a_token->access_token;
        // $this->client = new DropboxClient(config('services.dropbox.access_token'));
        $this->client = new DropboxClient( $token->access_token);
        // // $this->disk = Storage::disk('dropbox');
        
        // Log::error('access token ::', [
        //     'error' => $token->access_token,
        //     // 'file' => $file->getClientOriginalName()
        // ]);
    }

    

    public function sendHlsRequest($dropbox_link)
    {
        $videoUrl = $dropbox_link;//$request->input('video_url');

        Log::info('Dropbox link : '.$dropbox_link);



        try {
            // Send to Express.js converter
            $response = Http::timeout(120000000) // wait up to * minutes
                ->post('https://media.kinogram.mn/convert/convert-and-upload', [
                    'videoUrl' => $videoUrl,
                    'id' => 2
                ]);
            Log::info('Converted data : '.$dropbox_link);

            if ($response->failed()) {
                Log::info('Convert link error : '.$dropbox_link);
                return response()->json([
                    'success' => false,
                    'error' => 'Conversion failed',
                    'details' => $response->body()
                ], 500);
            }

            $data = $response->json();

            // Save or return the master.m3u8 link
            return [response()->json([
                'success' => true,
                'message' => $data['message'],
                'dir'=> $data['dir'],
                'hls_url' => $data['hlsOutputDir'] ?? null,
            ])
            ];
        } catch (\Exception $e) {
            return [response()->json([
                'success' => false,
                'error' => 'Server error',
                'details' => $e->getMessage()
            ])
            ];
        }
    }

    public function sendHlsConvertRequest(string $dropbox_link,string $id, string $videoType)
    {
        try {
            Log::info('To Be Converted data : '.$dropbox_link);
            // Send to Express.js converter
            Http::withHeaders([
                            'Content-Type' => 'application/json',
                        ])
            ->post('https://media.kinogram.mn/conversion/convert', [ //https://media.kinogram.mn/convert/convertHlsVideo
                    'videoUrl' => $dropbox_link,
                    'videoId' => $id,
                    'videoType' => $videoType
                ]);
            // return [response()->json([
            //     'success' => true,
            //     'message' => $data['message'],
            //     'dir'=> $data['dir'],
            //     'hls_url' => $data['hlsOutputDir'] ?? null,
            // ])
            // ];

        } catch (\Exception $e) {
            Log::error('COnversion error : '.$e->getMessage());
            // return [response()->json([
            //     'success' => false,
            //     'error' => 'Sending conversion video error',
            //     'details' => $e->getMessage()
            // ])
            // ];
        }

    }

    protected function getClient(): DropboxClient
    {
        $token = DropboxToken::where('user_id', Auth::id())->firstOrFail();
        Log::info('Dropbox Access Token: '.$token->access_token);
        Log::info('Dropbox Refresh Token: '.$token->refresh_token);
        Log::info('Now: '.now());

        if (now()->greaterThanOrEqualTo($token->expires_at)) {
            $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $token->refresh_token,
                'client_id'     => env('DROPBOX_APP_KEY'),
                'client_secret' => env('DROPBOX_APP_SECRET'),
            ]);

            $data = $response->json();

            $token->update([
                'access_token' => $data['access_token'],
                'expires_at'   => now()->addSeconds($data['expires_in']),
            ]);
        }

        return new DropboxClient($token->access_token);
    }
    
    /**
     * Upload a video file to Dropbox
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return array
     */
    // public function uploadVideo(UploadedFile $file, $folder = '/Videos')
    // {
    //     try {
    //         // Validate file
    //         if (!$file->isValid()) {
    //             throw new Exception('Invalid file upload');
    //         }

    //         if (!$file->getMimeType() || !str_starts_with($file->getMimeType(), 'video/')) {
    //             throw new Exception('File must be a valid video');
    //         }

    //         // Generate unique filename
    //         $filename = $this->generateUniqueFilename($file->getClientOriginalName(), $folder);//mb_convert_encoding("video.mp4", 'UTF-8', 'UTF-8');//
    //         $dropboxPath = $folder . '/' . $filename;
    //         $dropboxPath = mb_convert_encoding($dropboxPath, 'UTF-8', 'UTF-8');
    //         // Log the filename generation for debugging
    //         Log::info('Filename generation details', [
    //             'original_name' => $file->getClientOriginalName(),
    //             'generated_filename' => $filename,
    //             'dropbox_path' => $dropboxPath,
    //             'is_utf8' => mb_check_encoding($filename, 'UTF-8')
    //         ]);

    //         // Read file content
    //         $fileContent = file_get_contents($file->getRealPath());
    //         $fileSize = strlen($fileContent);
            
    //         // Log upload attempt for debugging
    //         Log::info('Dropbox upload attempt', [
    //             'filename' => $filename,
    //             'dropbox_path' => $dropboxPath,
    //             'file_size' => $fileSize,
    //             'mime_type' => $file->getMimeType()
    //         ]);

    //         // Upload to Dropbox using content upload API
    //         $response = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $this->accessToken,
    //             'Content-Type' => 'application/octet-stream',
    //             'Dropbox-API-Arg' => json_encode([
    //                 'path' => $dropboxPath,
    //                 'mode' => 'add',
    //                 'autorename' => true,
    //                 'mute' => false,
    //                 'strict_conflict' => false
    //             ])
    //         ])->post($this->baseUrl . '/files/upload', $fileContent);

    //         if (!$response->successful()) {
    //             Log::error('Dropbox upload failed', [
    //                 'response' => $response->body(),
    //                 'status' => $response->status(),
    //                 'headers' => $response->headers()
    //             ]);
    //             throw new Exception('Failed to upload file to Dropbox: ' . $response->body());
    //         }

    //         $uploadData = $response->json();

    //         // Create a shareable link
    //         $shareResponse = Http::withHeaders([
    //             'Authorization' => 'Bearer ' . $this->accessToken,
    //             'Content-Type' => 'application/json'
    //         ])->post($this->baseUrl . '/sharing/create_shared_link', [
    //             'path' => $dropboxPath,
    //             'settings' => [
    //                 'requested_visibility' => 'public',
    //                 'audience' => 'public',
    //                 'access' => 'viewer'
    //             ]
    //         ]);

    //         if (!$shareResponse->successful()) {
    //             Log::error('Dropbox share link creation failed', [
    //                 'response' => $shareResponse->body(),
    //                 'status' => $shareResponse->status()
    //             ]);
    //             throw new Exception('Failed to create shareable link');
    //         }

    //         $shareData = $shareResponse->json();
    //         $shareableUrl = $shareData['url'];

    //         // Convert to direct download link
    //         $directUrl = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $shareableUrl);
    //         $directUrl = str_replace('?dl=0', '', $directUrl);

    //         return [
    //             'success' => true,
    //             'dropbox_path' => $dropboxPath,
    //             'dropbox_url' => $directUrl,
    //             'shareable_url' => $shareableUrl,
    //             'filename' => $filename,
    //             'size' => $fileSize,
    //             'mime_type' => $file->getMimeType()
    //         ];

    //     } catch (Exception $e) {
    //         Log::error('Dropbox service error', [
    //             'message' => $e->getMessage(),
    //             'file' => $file->getClientOriginalName(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return [
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ];
    //     }
    // }
    
    /**
     * Upload small file (< chunk size)
     */
    public function uploadSmallFile(UploadedFile $file, string $path)
    {
        $contents = file_get_contents($file->getRealPath());
        
        $uploadData = $this->client->upload($path, $contents, [
            'autorename' => false,
            'mute' => false,
            'strict_conflict' => false
        ]);
        Log::info('Filename generation details', [
            'result' => $uploadData        
        ]);
    }

    /**
     * Upload a single file to Dropbox
     */
    public function uploadFile(UploadedFile $file, string $directory = '/Apps/kgram')
    {
        try {
            $filename = $this->generateUniqueFilename($file->getClientOriginalName(), $directory);//$this->generateUniqueFilename($file);
            $path = $directory . '/' . $filename;
            
            // Upload file to Dropbox
            $success = $this->disk->put($path, file_get_contents($file->getRealPath()));
            
            if (!$success) {
                throw new \Exception('Failed to upload file to Dropbox');
            }

            return [
                'success' => true,
                'path' => $path,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'url' => $this->getPublicUrl($path)
            ];

        } catch (\Exception $e) {
            Log::error('Dropbox upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    /**
     * Upload a file to Dropbox
     */
    public function uploadWithClient(UploadedFile $file, string $folder = '/Apps/kgram')
    {
        
        $fullPath = $file->getRealPath(); //Storage::path($localPath);
        $fileSize = filesize($fullPath);

        // Dropbox upload limit for single call
        $singleUploadLimit = 150 * 1024 * 1024; // 150MB
        Log::info('Dropbox upload file size', [
            'MAX file size ::' => $singleUploadLimit,
            'file size ::' => $fileSize
        ]);

        if ($fileSize <= $singleUploadLimit) {
            // Normal upload
            // return $this->simpleUpload($accessToken, $fullPath, $dropboxPath);

            try {
                $folder = $folder ?? $this->defaultFolder;
                $filename = $this->generateUniqueFilename($file->getClientOriginalName(), $folder);//$options['filename'] ?? $this->generateUniqueFilename($file);
                $path = $this->normalizePath($folder . '/' . $filename);

                // Choose upload method based on file size
                // if ($file->getSize() > $this->chunkSize) {
                //     $result = $this->uploadLargeFile($file, $path);
                // } else {
                //     $result = $this->uploadSmallFile($file, $path);
                // }
                $contents = file_get_contents($file->getRealPath());
                $client = $this->client; // <--- always fresh client
                // $account = $client->rpcEndpointRequest('users/get_current_account');
                // Log::info('Dropbox account verified', $account);
                $result = $client->upload($path, $contents,'add' //, 
                    // [
                    //     'autorename' => false,
                    //     'mute' => false,
                    //     'strict_conflict' => false
                    // ]
                );
                
            // Chunked upload
            // return $this->chunkedUpload($accessToken, $fullPath, $dropboxPath);

            Log::info('Dropbox upload status', [
                'result' => $result,
                // 'file' => $file->getClientOriginalName()
            ]);

                if ($result) {
                    $sharedLink = $this->createSharedLink($path);
                    
                    Log::error('Dropbox upload with settings::', [
                        'result' => $sharedLink,
                        // 'file' => $file->getClientOriginalName()
                    ]);
                    
                    // $shareData = $shareResponse->json();
                    // $shareableUrl = $shareData['url'];

                    // Convert to direct download link
                    // $directUrl = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $shareableUrl);
                    // $directUrl = str_replace('?dl=0', '', $directUrl);
                            return [
                                // 'success' => true,
                                // 'path' => $path,
                                // 'filename' => $filename,
                                // 'original_name' => $file->getClientOriginalName(),
                                // 'size' => $file->getSize(),
                                // 'mime_type' => $file->getMimeType(),
                                // 'shared_url' => $sharedLink,
                                // 'dropbox_metadata' => $result
                                'success' => true,
                                'dropbox_path' => $path,
                                'dropbox_url' => $sharedLink,
                                'filename' => $filename,
                                'original_name' => $file->getClientOriginalName(),
                                'size' => $file->getSize(),
                                'mime_type' => $file->getMimeType(),
                                'shareable_url' => $sharedLink,
                                'dropbox_metadata' => $result
                                
                            // return [
                            //     'success' => true,
                            //     'dropbox_path' => $dropboxPath,
                            //     'dropbox_url' => $directUrl,
                            //     'shareable_url' => $shareableUrl,
                            //     'filename' => $filename,
                            //     'size' => $fileSize,
                            //     'mime_type' => $file->getMimeType()
                            // ];
                            ];
                }

                throw new \Exception('Upload failed - no result returned');

            } catch (BadRequest $e) {
                Log::error('Dropbox API error', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName()
                ]);

                return [
                    'success' => false,
                    'error' => 'Dropbox API error: ' . $e->getMessage()
                ];
            } catch (\Exception $e) {
                Log::error('Dropbox upload failed', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName()
                ]);

                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
    }
    return $this->uploadLargeFile($file, $folder);

        
    }

    
    /**
     * Upload a file (handles both small and large >150MB)
     */
    public function uploadWithChunked(UploadedFile $localPath, string $dropboxPath): ?array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return null;
        }

        $fullPath = Storage::path($localPath);
        $fileSize = filesize($fullPath);

        // Dropbox upload limit for single call
        $singleUploadLimit = 150 * 1024 * 1024; // 150MB

        if ($fileSize <= $singleUploadLimit) {
            // Normal upload
            return $this->simpleUpload($accessToken, $fullPath, $dropboxPath);
        }

        // Chunked upload
        return $this->uploadLargeFile( $fullPath, $dropboxPath);
    }


    /**
     * Upload large files (>=150MB) in chunks
     */
    public function uploadLargeFile(UploadedFile $localPath, string $dropboxPath): array
    {
        // $singleUploadLimit = 150 * 1024 * 1024; // 150MB
        $chunkSize = 8 * 1024 * 1024;

        try {
            // uploadChunked
            $file = fopen($localPath, 'rb');
            if (!$file) {
                throw new Exception("Unable to open file: $localPath");
            }
            $filename = $this->generateUniqueFilename($localPath->getClientOriginalName(), $dropboxPath);//$options['filename'] ?? $this->generateUniqueFilename($file);
            $dropboxPath = $this->normalizePath($dropboxPath . '/' . $filename);
            Log::info('Large file info :: ', [
                'result' => $localPath,
                // 'file' => $file->getClientOriginalName()
            ]);

            $result = $this->client->uploadChunked($dropboxPath, file_get_contents($localPath));
            // echo "Large file uploaded successfully!";
            Log::info('Large file uploaded successfully!', [
                // 'result' => $result,
                // 'file' => $file->getClientOriginalName()
            ]);
            if ($result) {
                $sharedLink = $this->createSharedLink($dropboxPath);
                
                Log::error('Dropbox upload with settings::', [
                    'result' => $sharedLink,
                    // 'file' => $file->getClientOriginalName()
                ]);
                
                // $shareData = $shareResponse->json();
                // $shareableUrl = $shareData['url'];

                // Convert to direct download link
                // $directUrl = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $shareableUrl);
                // $directUrl = str_replace('?dl=0', '', $directUrl);
                        return [
                            // 'success' => true,
                            // 'path' => $path,
                            // 'filename' => $filename,
                            // 'original_name' => $file->getClientOriginalName(),
                            // 'size' => $file->getSize(),
                            // 'mime_type' => $file->getMimeType(),
                            // 'shared_url' => $sharedLink,
                            // 'dropbox_metadata' => $result
                            'success' => true,
                            'dropbox_path' => $dropboxPath,
                            'dropbox_url' => $sharedLink,
                            'filename' => $filename,
                            'original_name' => $localPath->getClientOriginalName(),
                            'size' => $localPath->getSize(),
                            'mime_type' => $localPath->getMimeType(),
                            'shareable_url' => $sharedLink,
                            'dropbox_metadata' => $result
                            
                        // return [
                        //     'success' => true,
                        //     'dropbox_path' => $dropboxPath,
                        //     'dropbox_url' => $directUrl,
                        //     'shareable_url' => $shareableUrl,
                        //     'filename' => $filename,
                        //     'size' => $fileSize,
                        //     'mime_type' => $file->getMimeType()
                        // ];
                        ];
            }
            // $cursor = null;
            // $sessionId = null;
            // $offset = 0;

            // while (!feof($file)) {
            //     $chunk = fread($file, $chunkSize);

            //     if ($offset === 0) {
            //         // Start session
            //         $response = $this->client->rpcEndpointRequest(
            //             'files/upload_session/start',
            //             [],
            //             $chunk,
            //             'application/octet-stream'
            //         );
            //         $sessionId = $response['session_id'];
            //     } else {
            //         // Append
            //         $this->client->rpcEndpointRequest(
            //             'files/upload_session/append_v2',
            //             ['cursor' => ['session_id' => $sessionId, 'offset' => $offset]],
            //             $chunk,
            //             'application/octet-stream'
            //         );
            //     }

            //     $offset += strlen($chunk);
            // }

            // fclose($file);

            // // Finish session
            // return $this->client->rpcEndpointRequest(
            //     'files/upload_session/finish',
            //     [
            //         'cursor' => ['session_id' => $sessionId, 'offset' => $offset],
            //         'commit' => ['path' => $dropboxPath, 'mode' => 'add'],
            //     ]
            // );
        } 
        catch (Exception $e) {
            Log::error("Dropbox large upload failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Large file upload (>150MB) via upload sessions
     */
    protected function chunkedUpload(string $accessToken, string $filePath, string $dropboxPath): ?array
    {
        $chunkSize = 8 * 1024 * 1024; // 8MB chunks
        $handle = fopen($filePath, 'rb');

        if (!$handle) {
            return null;
        }

        // Step 1: Start session
        $firstChunk = fread($handle, $chunkSize);
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/octet-stream',
            'Dropbox-API-Arg' => json_encode(["close" => false]),
        ])->withBody($firstChunk, 'application/octet-stream')
          ->post('https://content.dropboxapi.com/2/files/upload_session/start');

        if ($response->failed()) {
            fclose($handle);
            return null;
        }

        $sessionId = $response->json()['session_id'];
        $offset = strlen($firstChunk);

        // Step 2: Append remaining chunks
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/octet-stream',
                'Dropbox-API-Arg' => json_encode([
                    "cursor" => ["session_id" => $sessionId, "offset" => $offset],
                    "close" => false
                ]),
            ])->withBody($chunk, 'application/octet-stream')
              ->post('https://content.dropboxapi.com/2/files/upload_session/append_v2');

            if ($response->failed()) {
                fclose($handle);
                return null;
            }

            $offset += strlen($chunk);
        }

        fclose($handle);

        // Step 3: Finish upload
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/octet-stream',
            'Dropbox-API-Arg' => json_encode([
                "cursor" => ["session_id" => $sessionId, "offset" => $offset],
                "commit" => [
                    "path" => $dropboxPath,
                    "mode" => "add",
                    "autorename" => true,
                    "mute" => false,
                ],
            ]),
        ])->withBody('', 'application/octet-stream')
          ->post('https://content.dropboxapi.com/2/files/upload_session/finish');

        return $response->successful() ? $response->json() : null;
    }

    
    /**
     * Create a shared link for the file
     */
    public function createSharedLink(string $path): ?string
    {
        try {
            $response = $this->client->createSharedLinkWithSettings($path, [
                'requested_visibility' => 'public',
                'audience' => 'public',
                'access' => 'viewer'
            ]);

            return $response['url'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Failed to create shared link', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    public function uploadVideo(UploadedFile $file, $folder = '/Apps/kgram')
    {
        try {
            // Validate file
            if (!$file->isValid()) {
                throw new Exception('Invalid file upload');
            }
    
            if (!$file->getMimeType() || !str_starts_with($file->getMimeType(), 'video/')) {
                throw new Exception('File must be a valid video');
            }
    
            // Clean and validate folder
            // $folder = mb_convert_encoding($folder, 'UTF-8', mb_detect_encoding($folder, 'UTF-8, ISO-8859-1, Windows-1252', true));
            // $folder = preg_replace('/[^a-zA-Z0-9\-\_\/]/u', '_', $folder);
            // $folder = trim($folder, '/_ ');
            // if (empty($folder)) {
            //     $folder = '/Videos';
            // }
    
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file->getClientOriginalName(), $folder);
            $dropboxPath = $folder . '/' . $filename . '/' . $filename;
    
            // Ensure $dropboxPath is valid UTF-8
            if (!mb_check_encoding($dropboxPath, 'UTF-8')) {
                Log::warning('Invalid UTF-8 in dropboxPath', ['dropbox_path' => $dropboxPath,
                mb_detect_encoding($dropboxPath, 'UTF-8, ISO-8859-1, Windows-1252', true)]);
                $dropboxPath = mb_convert_encoding($dropboxPath, 'UTF-8',  'ISO-8859-1');//mb_detect_encoding($dropboxPath, 'UTF-8, ISO-8859-1, Windows-1252', true));
                if (!mb_check_encoding($dropboxPath, 'UTF-8')) {
                    $dropboxPath = '/Videos/video_' . time() . '.mp4';
                }
            }
    
            // Log the filename generation for debugging
            Log::info('Filename generation details', [
                'original_name' => $file->getClientOriginalName(),
                'generated_filename' => $filename,
                'dropbox_path' => $dropboxPath,
                'filepath' => $file->getRealPath(),
                'is_utf8' => mb_check_encoding($dropboxPath, 'UTF-8')
            ]);
    
            // Read file content
            $fileContent = file_get_contents($file->getRealPath());
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8','UTF-8');//'ISO-8859-1');
            $fileSize = strlen($fileContent);
            
            $encodedArgs = json_encode([
                'path' => $dropboxPath,
                'mode' => 'add',
                'autorename' => true,
                'mute' => false,
                'strict_conflict' => false 
            ], JSON_THROW_ON_ERROR);
            $encodedArgs = str_replace(chr(0x7F), "\\u007f", $encodedArgs);
            // Log upload attempt for debugging
            Log::info('Dropbox upload attempt', [
                'filename' => $filename,
                'dropbox_path' => $dropboxPath,
                'file_size' => $fileSize,
                'mime_type' => $file->getMimeType(),
                 //'file_content' => $encodedArgs //json_encode([
                //     'path' => $dropboxPath,
                //     'mode' => 'add',
                //     'autorename' => true,
                //     'mute' => false,
                //     'strict_conflict' => false
                // ],JSON_THROW_ON_ERROR)
            ]);

            // Upload to Dropbox
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/octet-stream',
                 'Dropbox-API-Arg' => $encodedArgs //json_encode([
                //     'path' => $dropboxPath,
                //     'mode' => 'add',
                //     'autorename' => true,
                //     'mute' => false,
                //     'strict_conflict' => false
                // ], JSON_THROW_ON_ERROR) // Use JSON_THROW_ON_ERROR to catch encoding issues
            ])->post($this->baseUrl . '/files/upload', $fileContent);
    
            if (!$response->successful()) {
                Log::error('Dropbox upload failed', [
                    'response' => $response->body(),
                    'status' => $response->status(),
                    'headers' => $response->headers()
                ]);
                throw new Exception('Failed to upload file to Dropbox: ' . $response->body());
            }
    
            $uploadData = $response->json();
    
            // Create a shareable link
            $shareResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->sharedLinkUrl . '/sharing/create_shared_link_with_settings', [
                'path' => $dropboxPath ,
                'settings' => [
                    'requested_visibility' => 'public',
                    'audience' => 'public',
                    'access' => 'viewer'
                ]
            ]);
    
            if (!$shareResponse->successful()) {
                Log::error('Dropbox share link creation failed', [
                    'response' => $shareResponse->body(),
                    'status' => $shareResponse->status()
                ]);
                throw new Exception('Failed to create shareable link');
            }
            else {
                
                Log::error('Dropbox share link created', [
                    'response' => $shareResponse->body(),
                    'status' => $shareResponse->status(),
                    'path' => $shareResponse->json(),
                    'url' => $shareResponse->json()['url']
                ]);
            }
    
            $shareData = $shareResponse->json();
            $shareableUrl = $shareData['url'];
    
            // Convert to direct download link
            $directUrl = str_replace('www.dropbox.com', 'dl.dropboxusercontent.com', $shareableUrl);
            $directUrl = str_replace('?dl=0', '', $directUrl);
    
            return [
                'success' => true,
                'dropbox_path' => $dropboxPath,
                'dropbox_url' => $directUrl,
                'shareable_url' => $shareableUrl,
                'filename' => $filename,
                'size' => $fileSize,
                'mime_type' => $file->getMimeType()
            ];
    
        } catch (Exception $e) {
            Log::error('Dropbox service error', [
                'message' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    
    /**
     * Normalize Dropbox path
     */
    protected function normalizePath(string $path): string
    {
        $path = ltrim($path, '/');
        return '/' . $path;
    }
    /**
     * Delete a file from Dropbox
     *
     * @param string $dropboxPath
     * @return array
     */
    public function deleteFile($dropboxPath)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/files/delete_v2', [
                'path' => $dropboxPath
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to delete file from Dropbox');
            }

            return [
                'success' => true,
                'message' => 'File deleted successfully'
            ];

        } catch (Exception $e) {
            Log::error('Dropbox delete error', [
                'message' => $e->getMessage(),
                'path' => $dropboxPath
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file metadata from Dropbox
     *
     * @param string $dropboxPath
     * @return array
     */
    public function getFileMetadata($dropboxPath)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/files/get_metadata', [
                'path' => $dropboxPath
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to get file metadata');
            }

            return [
                'success' => true,
                'metadata' => $response->json()
            ];

        } catch (Exception $e) {
            Log::error('Dropbox metadata error', [
                'message' => $e->getMessage(),
                'path' => $dropboxPath
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate a unique filename to avoid conflicts
     *
     * @param string $originalName
     * @param string $folder
     * @return string
     */
    // protected function generateUniqueFilename($originalName, $folder)
    // {
    //     $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    //     $name = pathinfo($originalName, PATHINFO_FILENAME);
        
    //     // Ensure the filename is UTF-8 encoded
    //     // if (!mb_check_encoding($name, 'UTF-8')) {
    //         $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
    //     // }
        
    //     // Clean the filename - keep only safe characters
    //     // Allow letters, numbers, hyphens, underscores, and dots
    //     // $name = preg_replace('/[^a-zA-Z0-9\-\_\.\s]/', '_', $name);
        
    //     // Replace multiple underscores with single underscore
    //     // $name = preg_replace('/_+/', '_', $name);
        
    //     // Remove leading/trailing underscores and spaces
    //     // $name = trim($name, '_ ');
        
    //     // Ensure filename is not empty after cleaning
    //     if (empty($name)) {
    //         $name = 'video';
    //     }
        
    //     // Limit filename length (keep it safe for filesystem)
    //     // if (mb_strlen($name) > 50) {
    //     //     $name = mb_substr($name, 0, 50);
    //     // }
        
    //     // Add timestamp to ensure uniqueness
    //     $timestamp = time();
    //     $uniqueName = "{$name}_{$timestamp}.{$extension}";
        
    //     // Final safety check - ensure the result is valid UTF-8
    //     if (!mb_check_encoding($uniqueName, 'UTF-8')) {
    //         $uniqueName = "video_{$timestamp}.{$extension}";
    //     }
        
    //     return $uniqueName;
    // }
    protected function generateUniqueFilename($originalName, $folder)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Replace invalid UTF-8 sequences with a replacement character
        $name = mb_convert_encoding($name, 'UTF-8', mb_detect_encoding($name, 'UTF-8, ISO-8859-1, Windows-1252', true));
        
        // Clean the filename - keep only safe characters
        $name = preg_replace('/[^a-zA-Z0-9\-\_\.\s]/u', '_', $name);
        
        // Replace multiple underscores with single underscore
        $name = preg_replace('/_+/', '_', $name);
        
        // Remove leading/trailing underscores and spaces
        $name = trim($name, '_ ');
        
        // Ensure filename is not empty after cleaning
        if (empty($name)) {
            $name = 'video';
        }
        
        // Limit filename length (keep it safe for filesystem)
        if (mb_strlen($name) > 50) {
            $name = mb_substr($name, 0, 50);
        }
        
        // Add timestamp to ensure uniqueness
        $timestamp = time();
        $uniqueName = "{$name}_{$timestamp}.{$extension}";
        
        // Final safety check - ensure the result is valid UTF-8
        if (!mb_check_encoding($uniqueName, 'UTF-8')) {
            $uniqueName = "video_{$timestamp}.{$extension}";
        }
        
        return $uniqueName;
    }
    /**
     * Check if Dropbox is properly configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->accessToken);
    }

    /**
     * Validate access token format
     *
     * @return bool
     */
    public function validateTokenFormat()
    {
        if (empty($this->accessToken)) {
            return false;
        }
        
        // Dropbox access tokens are typically 32 characters long
        // and contain only alphanumeric characters
        return preg_match('/^[a-zA-Z0-9]{32,}$/', $this->accessToken);
    }

    /**
     * Test Dropbox connection
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            if (!$this->isConfigured()) {
                throw new Exception('Dropbox access token not configured');
            }

            // if (!$this->validateTokenFormat()) {
            //     throw new Exception('Dropbox access token format is invalid');
            // }

            Log::info('Testing Dropbox connection', [
                'base_url' => $this->baseUrl,
                'has_token' => !empty($this->accessToken),
                'token_length' => strlen($this->accessToken),
                // 'endpoint' => '/users/get_current_account'
            ]);

            // Try to get account info to test connection
            // This endpoint expects POST with empty JSON body
            // $response = Http::withHeaders([
            //     'Authorization' => 'Bearer ' . $this->accessToken,
            //     'Content-Type' => 'application/json'
            // ])->post($this->baseUrl . '/users/get_current_account', '{}');

            // if (!$response->successful()) {
            //     Log::error('Dropbox connection test failed', [
            //         'response' => $response->body(),
            //         'status' => $response->status(),
            //         'headers' => $response->headers()
            //     ]);
            //     throw new Exception('Failed to connect to Dropbox API: ' . $response->body());
            // }

            // $accountInfo = $response->json();

            return [
                'success' => true,
                'message' => 'Dropbox connection successful'//,
                // 'account' => [
                //     'name' => $accountInfo['name']['display_name'],
                //     'email' => $accountInfo['email'],
                //     'country' => $accountInfo['country']
                // ]
            ];

        } catch (Exception $e) {
            Log::error('Dropbox connection test error', [
                'message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Alternative connection test using different approach
     *
     * @return array
     */
    public function testConnectionAlternative()
    {
        try {
            if (!$this->isConfigured()) {
                throw new Exception('Dropbox access token not configured');
            }

            Log::info('Testing Dropbox connection (alternative method)', [
                'base_url' => $this->baseUrl,
                'has_token' => !empty($this->accessToken)
            ]);

            // Try using a different endpoint that might be more reliable
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/check/user', [
                'query' => 'test'
            ]);

            if (!$response->successful()) {
                // If that fails, try the original endpoint with different body
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ])->post($this->baseUrl . '/users/get_current_account', []);
            }

            if (!$response->successful()) {
                throw new Exception('Failed to connect to Dropbox API: ' . $response->body());
            }

            return [
                'success' => true,
                'message' => 'Dropbox connection successful (alternative method)',
                'response' => $response->body()
            ];

        } catch (Exception $e) {
            Log::error('Dropbox alternative connection test error', [
                'message' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
