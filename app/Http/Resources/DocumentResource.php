<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'construction_site' => ConstructionSiteResource::make($this->whenLoaded('constructionSite')),
            'site_manager' => WorkerResource::make($this->whenLoaded('siteManager')),
            'uploader' => WorkerResource::make($this->whenLoaded('uploader')),
            'created_at' => $this->created_at,
            'download_url' => route('documents.download', $this->resource,),
            'extension' => $this->extension,
        ];
    }
}
