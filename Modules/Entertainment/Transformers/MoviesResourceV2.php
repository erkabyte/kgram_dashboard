<?php

namespace Modules\Entertainment\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Genres\Transformers\GenresResource;

class MoviesResourceV2 extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->e_id,
            'name' => $this->name,
            'description' => strip_tags($this->description),
            'trailer_url_type' => $this->trailer_url_type,
            'type' => $this->type,
            'trailer_url' => $this->trailer_url_type=='Local' ? setBaseUrlWithFileName($this->trailer_url) : $this->trailer_url,
            'movie_access' => $this->movie_access,
            'plan_id' => $this->plan_id,
            'plan_level' => $this->plan_level ?? 0,
            'language' => $this->language,
            'imdb_rating' => $this->IMDb_rating,
            'content_rating' => $this->content_rating,
            'duration' => $this->duration,
            'release_date' => $this->release_date,
            'is_restricted' => $this->is_restricted,
            'video_upload_type' => $this->video_upload_type,
            'video_url_input' => $this->video_upload_type=='Local' ? setBaseUrlWithFileName($this->video_url_input) : $this->video_url_input,
            'download_status' => $this->download_status,
            'enable_quality' => $this->enable_quality,
            'download_url' => $this->download_url,
            'poster_image' => setBaseUrlWithFileName($this->poster_image ?? null),
            'thumbnail_image' =>setBaseUrlWithFileName($this->thumbnail_image ?? null),
            'is_watch_list' => ($this->is_watch_list == 1) ? true : false,
            'genres' => GenresResource::collection($this->genres),
            'status' => $this->status,

        ];
    }
}
