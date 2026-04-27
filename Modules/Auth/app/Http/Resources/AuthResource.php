<?php

namespace Modules\Auth\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\User\Http\Controllers\Resources\UserResource;

class AuthResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user'  => new UserResource($this->resource['user']),
            'token' => $this->resource['token'],
        ];
    }
}
