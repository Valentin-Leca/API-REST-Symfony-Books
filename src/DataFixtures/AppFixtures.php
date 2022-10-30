<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher) {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void {

        $user = new User();
        $user->setEmail('test1.test1@sfr.fr');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        $userAdmin = new User();
        $userAdmin->setEmail('test2.test2@sfr.fr');
        $userAdmin->setRoles(['ROLE_ADMIN']);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $manager->persist($userAdmin);

        $listAuthor = [];
        for ($i = 0; $i < 10; $i++) {
            $author = new Author();
            $author->setFirstName("Prénom-".$i);
            $author->setLastName("Nom-".$i);
            $manager->persist($author);
            $listAuthor[] = $author;
        }

        for ($i = 0; $i < 20; $i++) {
            $book = new Book();
            $book->setTitle("Titre-".$i);
            $book->setCoverText("Quatrième de couverture n° : ".$i);
            $book->setAuthor($listAuthor[array_rand($listAuthor)]);
            $book->setComment("Commentaire du bibliothécaire " . $i);
            $manager->persist($book);
        }

        $manager->flush();
    }
}
