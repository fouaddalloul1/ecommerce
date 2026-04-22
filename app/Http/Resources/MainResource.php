<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class MainResource extends JsonResource
{
    public static $wrap = false;

    public function __construct(public $resource, public ?string $message = null, private readonly int $statusCode = ResponseAlias::HTTP_OK) {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        $data = ['cache' => false];

        if (!is_null($this->message)) $data['message'] = $this->message;
        if (!is_null($this->resource)) $data['data'] = $this->resource;

        return $data;
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode($this->statusCode);
        parent::withResponse($request, $response);
    }
}
