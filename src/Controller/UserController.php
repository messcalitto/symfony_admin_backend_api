<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends AbstractController
{
    private $userRepository;
    private $entityManager;
    private $passwordHasher;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route("/users", name: "user_index", methods: ["GET"])]
    public function index(): Response
    {
        $users = $this->userRepository->findAll();

        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
            'users' => $users,
        ]);
    }

    #[Route("/user/new", name: "user_new", methods: ["GET", "POST"])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $user = new User();
            $user->setName($request->request->get('name'));
            $user->setEmail($request->request->get('email'));
            $user->setPassword($this->passwordHasher->hashPassword($user, $request->request->get('password')));
            $user->setRoles($request->get('roles', []));
            $user->setCreatedAt(new \DateTime());
            $user->setUpdatedAt(new \DateTime());

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/new.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    #[Route("/user/{id}", name: "user_show", methods: ["GET"])]
    public function show($id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('The user does not exist');
        }

        return $this->render('user/show.html.twig', [
            'controller_name' => 'UserController',
            'user' => $user,
        ]);
    }

    #[Route("/api/users", name: "api_users", methods: ["GET"])]
    public function api_users(Request $request, PaginatorInterface $paginator): JsonResponse
    {
        $queryBuilder = $this->userRepository->createQueryBuilder('u')
            ->select('u.id, u.name, u.email');

        $pagination = $paginator->paginate(
            $queryBuilder, /* query NOT result */
            $request->query->getInt('page', 1), /* page number */
            $request->query->getInt('limit', 10) /* limit per page */
        );

        $users = $pagination->getItems();

        return new JsonResponse([
            'data' => $users,
            'current_page' => $pagination->getCurrentPageNumber(),
            'total' => $pagination->getTotalItemCount()
        ]);
    }

    #[Route("/api/users/{id}", name: "api_user_details", methods: ["GET"])]
    public function api_users_details(Request $request, int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
        ]);
    }

    #[Route("/api/users/{id}", name: "api_user_update", methods: ["POST"])]
    public function api_user_update(Request $request, int $id, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'User not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $user->setName($data['name'] ?? $user->getName());
        $user->setEmail($data['email'] ?? $user->getEmail());

        if (isset($data['password']) && !empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $emailExists = $this->userRepository->createQueryBuilder('u')
            ->select('count(u.id)')
            ->where('u.email = :email')
            ->andWhere('u.id != :id')
            ->setParameter('email', $data['email'])
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();

        if ($emailExists) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'This Email already exists. Please enter another one.'
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => $errorMessages
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail()
            ]
        ]);
    }

    #[Route("/api/admin/{id}", name: "api_admin_details", methods: ["GET"])]
    public function api_admin_details(Request $request, int $id): JsonResponse
    {
        $id  = 1;
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'name' => $user->getName(),
            'username' => $user->getEmail(),
        ]);
    }
        
    #[Route("/api/admin/{id}", name: "api_admin_update", methods: ["POST"])]
    public function api_admin_update(Request $request, int $id, ValidatorInterface $validator): JsonResponse
    {
        $id  = 1;
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'User not found'
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        $user->setName($data['name'] ?? $user->getName());
        $user->setEmail($data['username'] ?? $user->getEmail());

        if (isset($data['password']) && !empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        $emailExists = $this->userRepository->createQueryBuilder('u')
            ->select('count(u.id)')
            ->where('u.email = :email')
            ->andWhere('u.id != :id')
            ->setParameter('email', $data['username'])
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();

        if ($emailExists) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'This Email already exists. Please enter another one.'
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => $errorMessages
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'username' => $user->getEmail()
            ]
        ]);
    }
}
