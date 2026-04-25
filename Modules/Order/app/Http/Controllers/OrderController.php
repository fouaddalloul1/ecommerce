<?php

namespace Modules\Order\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Http\Resources\MainResource;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Order\Http\Requests\UpdateOrderStatusRequest;
use Modules\Order\Data\CreateOrderData;
use Modules\Order\Repositories\OrderRepository;
use Modules\Order\Http\Resources\OrderResource;
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
     *             @OA\Property(property="items", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2)
     *                 )
     *             ),
     *
     *             @OA\Property(property="shipping", type="number", example=10),
     *             @OA\Property(property="currency", type="string", example="USD")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully"
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
     */
    public function create(StoreOrderRequest $request): MainResource
    {
        $data = CreateOrderData::from(array_merge($request->validated(), ['user_id' => auth()->id()]));
        $this->repository->create($data);

        // return MainResource::make(new OrderResource($order), 'Order created', ResponseAlias::HTTP_CREATED);
        return MainResource::make();
    }

    public function show(int $id): MainResource
    {
        $order = $this->repository->find($id);
        return MainResource::make(new OrderResource($order));
    }

    public function myOrders(): MainResource
    {
        $orders = $this->repository->listForUser(auth()->id(), request('per_page', 15));
        return MainResource::make(OrderResource::collection($orders));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $id): MainResource
    {
        $order = $this->repository->find($id);
        $order = $this->repository->updateStatus($order, $request->input('status'), $request->input('payment_status'));
        return MainResource::make(new OrderResource($order), 'Order status updated');
    }

    public function cancel(int $id): MainResource
    {
        $order = $this->repository->find($id);
        $order = $this->repository->cancel($order);
        return MainResource::make(new OrderResource($order), 'Order cancelled');
    }
}
