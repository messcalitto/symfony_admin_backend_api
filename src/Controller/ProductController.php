<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\ProductRepository;
use App\Entity\Product;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ProductController extends AbstractController
{
    private $productRepository;
    private $entityManager;

    public function __construct(ProductRepository $productRepository, EntityManagerInterface $entityManager)
    {
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
    }

    #[Route("/api/products", name: "api_products", methods: ["GET"])]
    public function apiProducts(Request $request, PaginatorInterface $paginator): JsonResponse
    {
        $queryBuilder = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->select('p.id', 'p.title', 'p.description', 
            'p.short_notes', 'p.price', 
            'p.discount_price as discount_price', 
            'c.id as category_id', 'c.name as category', 
            'p.image', 'p.quantity');

        $pagination = $paginator->paginate(
            $queryBuilder, /* query NOT result */
            $request->query->getInt('page', 1), /* page number */
            $request->query->getInt('limit', 10) /* limit per page */
        );

        $products = $pagination->getItems();

        // Decode the image property if necessary
        foreach ($products as &$product) {
            if (isset($product['image'])) {
                $product['image'] = json_decode($product['image'], true);
                // add images/ to each image path
                // foreach ($product['image'] as &$image) {
                //     $image = 'images/' . $image;
                // }
            }
        }

        return new JsonResponse([
            'data' => $products,
            'current_page' => $pagination->getCurrentPageNumber(),
            'total' => $pagination->getTotalItemCount()
        ]);
    }

    #[Route("/api/products/{id}", name: "api_product_details", methods: ["GET"])]
    public function apiProductDetails(Request $request, int $id): JsonResponse
    {
        $queryBuilder = $this->productRepository->createQueryBuilder('p')
            ->select('p.id, p.title, p.description',
            'p.short_notes', 'p.price',
            'p.discount_price', 'p.quantity',
            'c.id as category_id', 'c.name as category',
            'p.image')
            ->leftJoin('p.category', 'c')
            ->where('p.id = :id')
            ->setParameter('id', $id);

        $product = $queryBuilder->getQuery()->getOneOrNullResult();

        if (!$product) {
            return new JsonResponse([
                'error' => 'Product not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $product['id'],
            'title' => $product['title'],
            'description' => $product['description'],
            'short_notes' => $product['short_notes'],
            'price' => $product['price'],
            'discount_price' => $product['discount_price'],
            'quantity' => $product['quantity'],
            'category_id' => $product['category_id'],
            'category' => $product['category'],
            'image' => json_decode($product['image'], true),
        ]);
    }

    #[Route("/api/products/{id}", name: "api_product_update", methods: ["POST"])]
    public function api_update_product(Request $request, int $id, ValidatorInterface $validator): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Product not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        
        // remove id, category from data
        unset($data['id']);
        unset($data['category']);


        $constraints = new Assert\Collection([
            'title' => [new Assert\NotBlank()],
            'description' => [new Assert\NotBlank()],
            'short_notes' => [new Assert\NotBlank()],
            'price' => [new Assert\NotBlank()],
            'discount_price' => [new Assert\NotBlank()],
            'category_id' => [new Assert\NotBlank()],
            'quantity' => [new Assert\NotBlank()],
            'image' => []
        ]);

        $errors = $validator->validate($data, $constraints);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => $errorMessages
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['image']) && is_array($data['image'])) {
            $images = [];

            foreach ($data['image'] as $index => $image) {
                
                

                if (substr($image, 0, 10) != 'data:image') {
                    // get file name from url
                    $image = explode('/', $image);
                    $image = end($image);
                    $image = str_replace('"', '', $image);
                    $images[] = $image;
                } else {
                    $extension = substr($image, 11, 4);
                    $extension = str_replace(';', '', $extension);
                    $filename = 'p' . $id . "_" . $index . '.' . $extension;

                    $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));

                    // save image to file
                    file_put_contents($this->getParameter('uploads_directory') . '/' . $filename, $imageData);

                    $images[] = $filename;
                }
            }

            $product->setImage($images);
        }

        $category = $this->entityManager->getRepository(Category::class)->find($data['category_id']);

        $product->setTitle($data['title']);
        $product->setDescription($data['description']);
        $product->setShortNotes($data['short_notes']);
        $product->setCategory($category);
        $product->setPrice((float)$data['price']);
        $product->setDiscountPrice((float)$data['discount_price']);
        $product->setQuantity((int)$data['quantity']);

        $this->entityManager->flush();

        $updatedProduct = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->select('p.id', 'p.title', 'p.description', 'p.short_notes', 'p.price', 'p.discount_price', 'p.quantity', 'c.id as category_id', 'c.name as category', 'p.image')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($updatedProduct['image']) {
            $updatedProduct['image'] = json_decode($updatedProduct['image'], true);
        }

        return new JsonResponse([
            'message' => 'Product updated successfully',
            'product' => $updatedProduct
        ]);
    }

    #[Route("/api/products", name: "api_product_create", methods: ["POST"])]
    public function api_create_product(Request $request, ValidatorInterface $validator): JsonResponse
    {
        $product = new Product();

        $data = json_decode($request->getContent(), true);
        
        // remove id, category from data
        unset($data['id']);
        unset($data['category']);


        $constraints = new Assert\Collection([
            'title' => [new Assert\NotBlank()],
            'description' => [new Assert\NotBlank()],
            'short_notes' => [new Assert\NotBlank()],
            'price' => [new Assert\NotBlank()],
            'discount_price' => [new Assert\NotBlank()],
            'category_id' => [new Assert\NotBlank()],
            'quantity' => [new Assert\NotBlank()],
            'image' => []
        ]);

        

        $errors = $validator->validate($data, $constraints);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => $errorMessages
            ], JsonResponse::HTTP_BAD_REQUEST);
        }


        
        if (isset($data['image']) && is_array($data['image'])) {
            $images = [];

            foreach ($data['image'] as $index => $image) {
                
                $extension = substr($image, 11, 4);
                $extension = str_replace(';', '', $extension);
                $filename = 'p' . uniqid() . "_" . $index . '.' . $extension;

                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));

                // save image to file
                file_put_contents($this->getParameter('uploads_directory') . '/' . $filename, $imageData);

                $images[] = $filename;
            }

            $product->setImage($images);
        }

        $category = $this->entityManager->getRepository(Category::class)->find($data['category_id']);


        $product->setTitle($data['title']);
        $product->setDescription($data['description']);
        $product->setShortNotes($data['short_notes']);
        $product->setCategory($category);
        $product->setPrice((float)$data['price']);
        $product->setDiscountPrice((float)$data['discount_price']);
        $product->setQuantity((int)$data['quantity']);
        $product->setUserId(1);

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $updatedProduct = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->select('p.id', 'p.title', 'p.description', 'p.short_notes', 'p.price', 'p.discount_price', 'p.quantity', 'c.id as category_id', 'c.name as category', 'p.image')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($updatedProduct['image']) {
            $updatedProduct['image'] = json_decode($updatedProduct['image'], true);
        }

        return new JsonResponse([
            'message' => 'Product updated successfully',
            'product' => $updatedProduct
        ]);
    }


    #[Route("/api/products/{id}/delete", name: "api_product_delete", methods: ["GET"])]
    public function api_delete_product($id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Product not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ]);
    }
    
    #[Route("/product/new", name: "product_new", methods: ["GET", "POST"])]
    public function new(Request $request): Response
    {
        $product = new Product();

        if ($request->isMethod('POST')) {
            $product->setTitle($request->request->get('title'));
            $product->setDescription($request->request->get('description'));
            $product->setShortNotes($request->request->get('short_notes'));
            $product->setPrice((float) $request->request->get('price'));
            $product->setDiscountPrice((float) $request->request->get('discount_price'));
            $product->setQuantity((int) $request->request->get('quantity'));
            $product->setUserId(1);
            $product->setCreatedAt(new \DateTime());
            $product->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($product);
            $this->entityManager->flush();

            return $this->redirectToRoute('api_products');
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route("/product/edit/{id}", name: "product_edit", methods: ["GET", "POST"])]
    public function edit(Request $request, int $id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('The product does not exist');
        }

        if ($request->isMethod('POST')) {
            $product->setTitle($request->request->get('title'));
            $product->setDescription($request->request->get('description'));
            $product->setShortNotes($request->request->get('short_notes'));
            $product->setPrice((float) $request->request->get('price'));
            $product->setDiscountPrice((float) $request->request->get('discount_price'));
            $product->setQuantity((int) $request->request->get('quantity'));
            $product->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            return $this->redirectToRoute('api_products');
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
        ]);
    }
}
