<?php

namespace App\EventListener;

use App\Doctrine\Type\VectorType;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\HttpKernel\Event\KernelRequestEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
class DoctrineBootstrapListener
{
    public function __invoke(KernelRequestEvent $event): void
    {
        try {
            if (!Type::hasType(VectorType::NAME)) {
                Type::addType(VectorType::NAME, VectorType::class);
            }
        } catch (\Exception $e) {
            // Silently fail if type is already registered
        }
    }
}
