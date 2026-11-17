<?php

namespace App\EventListener;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Transforme en réponses JSON propres deux familles d'erreurs de l'API qui finissaient en 500 :
 *  - données de requête du mauvais type (ex. un tableau à la place d'un texte) : 400 ;
 *  - suppression ou écriture bloquée par une contrainte de la base (élément encore utilisé, doublon) : 409.
 *
 * Aucun détail technique n'est renvoyé au client ; l'exception complète est écrite dans les logs.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
class ApiExceptionListener
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/admin')) {
            return;
        }

        $e = $event->getThrowable();

        if ($e instanceof ForeignKeyConstraintViolationException || $e instanceof UniqueConstraintViolationException) {
            $event->setResponse(new JsonResponse(['error' => 'Conflict: the resource is in use or already exists'], Response::HTTP_CONFLICT));

            return;
        }

        if ($e instanceof \TypeError || $e instanceof \ValueError || $e instanceof \JsonException) {
            // Presque toujours une donnée mal typée envoyée par le client. Journalisé en erreur pour
            // qu'un vrai bug de code ne passe pas inaperçu.
            $this->logger->error('Requête invalide ou erreur de typage', ['exception' => $e, 'path' => $event->getRequest()->getPathInfo()]);
            $event->setResponse(new JsonResponse(['error' => 'Invalid request data'], Response::HTTP_BAD_REQUEST));
        }
    }
}
