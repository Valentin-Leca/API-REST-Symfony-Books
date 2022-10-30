<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class BookController extends AbstractController {


    /**
     *
     * Cette Méthode permet de récupérer l'ensemble des livres.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des livres",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *     )
     * )
     *
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="La page que l'on veut récupérer (par défaut, la première)",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Le nombre d'éléments que l'on veut récupérer (par défaut, 10)",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Books")
     *
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/books', name: 'all_books', methods: ['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer, Request $request,
                                TagAwareCacheInterface $cache): JsonResponse {

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer) {
            $item->tag('getAllBooksCache');
            $bookList = $bookRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(["getBooks"]);
            return $serializer->serialize($bookList, 'json', $context);
        });

        return new JsonResponse(
            $jsonBookList,
            Response::HTTP_OK,
            [],
            true
        );
    }

    #[Route('/api/books/{id}', name: 'one_book', methods: ['GET'])]
    public function getOneBook(Book $book, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse {

        $context = SerializationContext::create()->setGroups(['getBooks']);
        $version = $versioningService->getVersion();
        $context->setVersion($version);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse(
            $jsonBook,
            Response::HTTP_OK,
            [],
            true
        );
    }

    #[Route('/api/books/{id}', name: 'delete_book', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre.')]
    public function deleteOneBook(Book $book, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse {

        $entityManager->remove($book);
        $entityManager->flush();

        //On vide le cache.
        $cache->invalidateTags(['getAllBooksCache']);

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT,
        );
    }

    #[Route('/api/books', name: 'create_book', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre.')]
    public function addOneBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager,
                               UrlGeneratorInterface $urlGenerator,AuthorRepository $authorRepository , ValidatorInterface
                               $validator, TagAwareCacheInterface $cache): JsonResponse {

        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        //On vérifie les erreurs
        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        $entityManager->persist($book);
        $entityManager->flush();

        //On vide le cache.
        $cache->invalidateTags(['getAllBooksCache']);

        $context = SerializationContext::create()->setGroups(["getBooks"]);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        $location = $urlGenerator->generate('one_book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(
            $jsonBook,
            Response::HTTP_CREATED,
            ['Location' => $location],
            true
        );
    }

    #[Route('/api/books/{id}', name: 'update_book', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour éditer un livre.')]
    public function updateOneBook(Request $request, SerializerInterface $serializer, Book $currentBook,
                                  EntityManagerInterface $entityManager, AuthorRepository $authorRepository,
                                  ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse {

        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');

        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        // On vérifie les erreurs
        $errors = $validator->validate($currentBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $currentBook->setAuthor($authorRepository->find($idAuthor));

        $entityManager->persist($currentBook);
        $entityManager->flush();

        // On vide le cache.
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT,
        );
    }
}
