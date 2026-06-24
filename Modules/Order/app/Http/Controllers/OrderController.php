<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainResource;
use Illuminate\Support\Facades\Log;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Order\Http\Requests\UpdateOrderStatusRequest;
use Modules\Order\Data\CreateOrderData;
use Modules\Order\Repositories\OrderRepository;
use Modules\Order\Http\Resources\OrderResource;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;


class OrderController extends Controller
{
    public function __construct(private OrderRepository $repository) {}

    /**
     * @OA\Post(
     *     path="/api/v1/orders",
     *     tags={"Orders"},
     *     summary="Create a new order",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items"},
     *
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2)
     *                 )
     *             ),

     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Order created"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function create(StoreOrderRequest $request): MainResource
    {
        $data = CreateOrderData::from(array_merge($request->validated(), ['user_id' => auth()->id()]));

        $order = $this->repository->create($data);

        return MainResource::make(
            new OrderResource($order),
            'Order created; invoice and notification were queued.',
            ResponseAlias::HTTP_CREATED
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/{id}",
     *     summary="Get order details",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Order retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="total", type="number", example=110),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     )
     * )
     */
    public function show($id): MainResource
    {
        Log::info("Fetching order details for order ID: {$id}");
        $order = $this->repository->find($id);
        return MainResource::make(new OrderResource($order));
    }


    /**
     * @OA\Get(
     *     path="/api/v1/orders/my",
     *     summary="Get authenticated user orders",
     *     description="Returns paginated list of orders for the logged-in user",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of user orders",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="total", type="number", example=120.50),
     *                     @OA\Property(property="status", type="string", example="pending")
     *                 )
     *             ),
     *
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 description="Pagination links"
     *             ),
     *
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 description="Pagination metadata"
     *             )
     *         )
     *     ),
     *
     * )
     */
    public function myOrders(): MainResource
    {
        $orders = $this->repository->listForUser();

        return MainResource::make(
            OrderResource::collection($orders)
        );
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): MainResource
    {
        $order = $this->repository->find($id);
        $order = $this->repository->updateStatus($order, $request->input('status'), $request->input('payment_status'));
        return MainResource::make(new OrderResource($order), 'Order status updated');
    }


    /**
     * @OA\Post(
     *     path="/api/v1/orders/{id}/cancel",
     *     summary="Cancel an order",
     *     description="Cancels an existing order and restores stock",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="message", type="string", example="Order cancelled")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     ),
     *
     * )
     */
    public function cancel(int $id): MainResource
    {
        $order = $this->repository->find($id);
        $order = $this->repository->cancel($order);
        return MainResource::make(null, 'Order cancelled');
    }
}
