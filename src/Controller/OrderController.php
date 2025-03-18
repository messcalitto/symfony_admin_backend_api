<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\OrderRepository;
use App\Repository\OrderProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\Order;
use App\Entity\OrderProduct;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class OrderController extends AbstractController
{
    private $orderProductRepository;

    public function __construct(OrderProductRepository $orderProductRepository)
    {
        $this->orderProductRepository = $orderProductRepository;
    }
    

    #[Route("/api/orders", name: "api_orders", methods: ["GET"])]
    public function apiOrders(Request $request, PaginatorInterface $paginator): JsonResponse
    {
        $queryBuilder = $this->orderProductRepository->createQueryBuilder('op')
            ->leftJoin('op.order', 'o')
            ->leftJoin('op.product', 'p')
            ->leftJoin('op.user', 'u')
            ->select(
                'op.id as id', 
                'o.id as order_id', 
                'p.id as product_id', 
                'u.id as user_id', 
                'p.image as image',
                'p.title as title',
                'op.quantity as quantity', 
                'op.price as price', 
                'o.name as name',
                'o.email as email', 
                'o.phone as phone',
                'o.address as address',
                'o.city as city',
                'o.transactionId as transaction_id',
                'o.paidAmount as paid_amount',
                'op.status as status',
                'op.createdAt as created_at',
                'op.updatedAt as updated_at'
            );

        $pagination = $paginator->paginate(
            $queryBuilder, /* query NOT result */
            $request->query->getInt('page', 1), /* page number */
            $request->query->getInt('limit', 10) /* limit per page */
        );

        $data = $pagination->getItems();
        // convert image to array
        foreach ($data as &$item) {
            $item['image'] = json_decode($item['image'], true);
            
            if (is_array($item['image'])) {
                $item['image'] = $item['image'][0];
            }
        }
        return new JsonResponse([
            'data' => $data,
            'current_page' => $pagination->getCurrentPageNumber(),
            'total' => $pagination->getTotalItemCount()
        ]);
    }

    #[Route("/api/orders/{id}", name: "api_order_details", methods: ["GET"])]
    public function apiOrderDetails(int $id): JsonResponse
    {
        $queryBuilder = $this->orderProductRepository->createQueryBuilder('op')
            ->leftJoin('op.order', 'o')
            ->leftJoin('op.product', 'p')
            ->leftJoin('op.user', 'u')
            ->select(
                'op.id as id',
                'o.id as order_id',
                'p.id as product_id',
                'u.id as user_id',
                'p.image as image',
                'p.title as title',
                'op.quantity as quantity',
                'op.price as price',
                'o.name as name',
                'o.email as email',
                'o.phone as phone',
                'o.address as address',
                'o.city as city',
                'o.transactionId as transaction_id',
                'o.paidAmount as paid_amount',
                'op.status as status',
                'op.createdAt as created_at',
                'op.updatedAt as updated_at'
            )
            ->where('op.id = :id')
            ->setParameter('id', $id);

        $order = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$order) {
            return new JsonResponse([
                'error' => 'Order not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // convert image to array
        $order['image'] = json_decode($order['image'], true);

        return new JsonResponse($order);
    }

    #[Route("/api/orders/{id}", name: "api_order_update", methods: ["POST"])]
    public function apiOrderUpdate(Request $request, int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $order = $this->orderProductRepository->find($id);

        if (!$order) {
            return new JsonResponse([
                'error' => 'Order not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $order->setStatus($data['status'] ?? $order->getStatus());

        $entityManager->persist($order);
        $entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Order updated successfully'
        ]);
    }
}
