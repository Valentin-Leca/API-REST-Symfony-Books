<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class AuthorController extends AbstractController {

    #[Route('/api/authors', name: 'app_author')]
    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer): JsonResponse {

        $authorList = $authorRepository->findAll();
        $jsonAuthorList = $serializer->serialize($authorList, 'json', ['groups' => 'getAuthors']);

        return new JsonResponse(
            $jsonAuthorList,
            Response::HTTP_OK,
            [],
            true
        );

    }

    #[Route('/api/authors/{id}', name: 'one_author', methods: ['GET'])]
    public function getOneBook(Author $author, SerializerInterface $serializer): JsonResponse {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getBooks']);
        return new JsonResponse(
            $jsonAuthor,
            Response::HTTP_OK,
            [],
            true
        );
    }
}
