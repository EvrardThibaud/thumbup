<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password/test', name: 'app_forgot_password_test', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $to = trim((string) $request->request->get('email', ''));
            if ($to !== '') {
                $email = (new Email())
                    ->from('hello@example.com')
                    ->to($to)
                    ->subject('ThumbUp password reset test')
                    ->text('This is a test email from ThumbUp.')
                    ->html('<p>This is a <strong>test</strong> email from ThumbUp.</p>');

                $mailer->send($email);

                $this->addFlash('success', 'Test email sent (if configuration is correct).');
            } else {
                $this->addFlash('danger', 'Please enter an email address.');
            }
        }

        return $this->render('security/forgot_password_test.html.twig');
    }
}
