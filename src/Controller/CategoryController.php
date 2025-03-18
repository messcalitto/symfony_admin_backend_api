<?php
namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;


class CategoryController extends AbstractController
{
    private $categoryRepository;
    private $entityManager;

    public function __construct(CategoryRepository $categoryRepository, EntityManagerInterface $entityManager)
    {
        $this->categoryRepository = $categoryRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findAll();

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/category/new', name: 'category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ValidatorInterface $validator): Response
    {
        $category = new Category();

        if ($request->isMethod('POST')) {
            $category->setName($request->request->get('name'));
            $category->setDescription($request->request->get('description'));

            $errors = $validator->validate($category);
            if (count($errors) > 0) {
                return $this->render('category/new.html.twig', [
                    'category' => $category,
                    'errors' => $errors,
                ]);
            }

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            return $this->redirectToRoute('categories');
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/category/edit/{id}', name: 'category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, $id, ValidatorInterface $validator): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('The category does not exist');
        }

        if ($request->isMethod('POST')) {
            $category->setName($request->request->get('name'));
            $category->setDescription($request->request->get('description'));

            $errors = $validator->validate($category);
            if (count($errors) > 0) {
                return $this->render('category/edit.html.twig', [
                    'category' => $category,
                    'errors' => $errors,
                ]);
            }

            $this->entityManager->flush();

            return $this->redirectToRoute('categories');
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/category/delete/{id}', name: 'category_delete', methods: ['POST'])]
    public function delete($id): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            $this->addFlash('error', 'Category not found.');
            return $this->redirectToRoute('categories');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        $this->addFlash('success', 'Category deleted successfully.');

        return $this->redirectToRoute('categories');
    }

    #[Route("/api/categories", name: "api_categories", methods: ["GET"])]
    public function apiProducts(Request $request, PaginatorInterface $paginator): JsonResponse
    {
        $queryBuilder = $this->categoryRepository->createQueryBuilder('c')
            ->select('c.id', 'c.name');

        $pagination = $paginator->paginate(
            $queryBuilder, /* query NOT result */
            $request->query->getInt('page', 1), /* page number */
            $request->query->getInt('limit', 10) /* limit per page */
        );

        $data = $pagination->getItems();

        return new JsonResponse([
            'data' => $data,
            'current_page' => $pagination->getCurrentPageNumber(),
            'total' => $pagination->getTotalItemCount()
        ]);
    }

    #[Route("/api/categories/{id}", name: "api_category_details", methods: ["GET"])]
    public function api_category_details(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        return new JsonResponse([
            'id' => $category->getId(),
            'name' => $category->getName(),
        ]);
    }

    #[Route("/api/categories/{id}", name: "api_category_update", methods: ["POST"])]
    public function api_category_update(int $id, Request $request): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $category->setName($data['name'] ?? $category->getName());

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Category updated successfully'
        ]);
    }

    #[Route("/api/categories", name: "api_category_update", methods: ["POST"])]
    public function api_category_create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate category name
        if (empty($data['name'])) {
            return new JsonResponse(['error' => 'Category name is required'], 400);
        }

        $category = new Category();
        $category->setName($data['name']);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return new JsonResponse([
            'category' => ['name' => $category->getName(), 'id' => $category->getId()],
            'message' => 'Category created successfully'
        ]);
    }

    #[Route("/api/categories/{id}/delete", name: "api_category_update", methods: ["GET"])]
    public function api_category_delete(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Category deleted successfully'
        ]);
    }
}
