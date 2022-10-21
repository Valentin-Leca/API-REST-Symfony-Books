<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class BookController extends AbstractController {

    #[Route('/api/books', name: 'all_books', methods: ['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer): JsonResponse {

        $bookList = $bookRepository->findAll();
        $jsonBookList = $serializer->serialize($bookList, 'json', ['groups' => 'getBooks']);

        return new JsonResponse(
            $jsonBookList,
            Response::HTTP_OK,
            [],
            true
        );
    }

    #[Route('/api/books/{id}', name: 'one_book', methods: ['GET'])]
    public function getOneBook(Book $book, SerializerInterface $serializer): JsonResponse {
        $jsonBook = $serializer->serialize($book, 'json', ['groups' => 'getBooks']);
        return new JsonResponse(
            $jsonBook,
            Response::HTTP_OK,
            [],
            true
        );
    }

    #[Route('/api/books/{id}', name: 'delete_book', methods: ['DELETE'])]
    public function deleteOneBook(Book $book, EntityManagerInterface $entityManager): JsonResponse {

        $entityManager->remove($book);
        $entityManager->flush();

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT,
        );
    }

    #[Route('/api/books', name: 'create_book', methods: ['POST'])]
    public function addOneBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager,
                               AuthorRepository $authorRepository, UrlGeneratorInterface $urlGenerator): JsonResponse {

        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $book->setAuthor($authorRepository->find($idAuthor));

        $entityManager->persist($book);
        $entityManager->flush();

        $jsonBook = $serializer->serialize($book, 'json', ['groups' => ['getBooks']]);
        $location = $urlGenerator->generate('one_book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(
            $jsonBook,
            Response::HTTP_CREATED,
            ['Location' => $location],
            true
        );
    }

    #[Route('/api/books/{id}', name: 'update_book', methods: ['PUT'])]
    public function updateOneBook(Request $request, SerializerInterface $serializer,
                                  Book $book, EntityManagerInterface $entityManager, AuthorRepository $authorRepository): JsonResponse {
        $updateBook = $serializer->deserialize($request->getContent(), Book::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $book]);

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;
        $updateBook->setAuthor($authorRepository->find($idAuthor));

        $entityManager->persist($updateBook);
        $entityManager->flush();

        return new JsonResponse(
            null,
            Response::HTTP_NO_CONTENT,
        );
    }
}
