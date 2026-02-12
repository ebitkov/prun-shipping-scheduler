<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $fioUsername = trim((string) $request->request->get('fio_username'));
            $fioApiToken = trim((string) $request->request->get('fio_api_token'));

            if ($fioUsername === '' || $fioApiToken === '') {
                $error = 'Bitte alle Felder ausfüllen.';
            } else {
                $existing = $entityManager->getRepository(User::class)->findOneBy(['fioUsername' => $fioUsername]);
                if ($existing !== null) {
                    $error = 'Dieser Benutzername ist bereits registriert.';
                } else {
                    $user = new User($fioUsername, '', $fioApiToken);
                    $hashedPassword = $passwordHasher->hashPassword($user, $fioApiToken);
                    // Reconstruct with hashed password
                    $user = new User($fioUsername, $hashedPassword, $fioApiToken);

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->addFlash('success', 'Registrierung erfolgreich. Bitte anmelden.');

                    return $this->redirectToRoute('login');
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
        ]);
    }
}
