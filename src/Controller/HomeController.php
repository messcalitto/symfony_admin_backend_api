<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class HomeController extends AbstractController
{
    private $productRepository;
    private $entityManager;
    private $slugger;

    public function __construct(ProductRepository $productRepository, EntityManagerInterface $entityManager, SluggerInterface $slugger)
    {
        $this->productRepository = $productRepository;
        $this->entityManager = $entityManager;
        $this->slugger = $slugger;
    }
    
    #[Route("/", name: "home")]
    public function index(): Response
    {
        $products = $this->productRepository->findAll();

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'products' => $products,
        ]);
    }

    #[Route("/products", name: "products")]
    public function products(): Response
    {
        $products = $this->productRepository->findAll();

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'products' => $products,
        ]);
    }

    #[Route("/product/new", name: "product_new", methods: ["GET"])]
    public function new(): Response
    {
        $product = new Product();

        return $this->render('home/product_edit.html.twig', [
            'controller_name' => 'HomeController',
            'product' => $product,
        ]);
    }

    #[Route("/product/{id}", name: "product_edit", methods: ["GET"])]
    public function product($id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            throw $this->createNotFoundException('The product does not exist');
        }

        return $this->render('home/product_edit.html.twig', [
            'controller_name' => 'HomeController',
            'product' => $product,
        ]);
    }

    #[Route("/product/save", name: "product_save", methods: ["POST"])]
    public function save(Request $request, ValidatorInterface $validator): Response
    {
        $id = $request->request->get('id');
        $product = $this->productRepository->find($id);

        if (!$product) {
            $product = new Product();
        }

        $product->setTitle($request->request->get('title'));
        $product->setDescription($request->request->get('description'));
        $product->setShortNotes($request->request->get('short_notes'));
        $product->setPrice((float) $request->request->get('price'));
        $product->setDiscountPrice((float) $request->request->get('discount_price'));
        $product->setQuantity((int) $request->request->get('quantity'));
        $product->setUserId(1);

        $errors = $validator->validate($product);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->render('home/product_edit.html.twig', [
                'controller_name' => 'HomeController',
                'product' => $product,
                'errors' => $errorMessages,
            ]);
        }

        $imageFile = $request->files->get('image');
        if ($imageFile) {
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('uploads_directory'),
                    $newFilename
                );
            } catch (FileException $e) {
                // handle exception if something happens during file upload
            }

            $product->setImage([$newFilename]);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->redirectToRoute('home');
    }

    #[Route("/product/delete/{id}", name: "product_delete", methods: ["POST"])]
    public function delete($id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $this->addFlash('error', 'Product not found.');
            return $this->redirectToRoute('home');
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $this->addFlash('success', 'Product deleted successfully.');

        return $this->redirectToRoute('home');
    }

   
}
