<?php

namespace App\EventListener;

use App\Doctrine\Type\VectorType;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'console.command')]
class ConsoleBootstrapListener
{
    public function __invoke(ConsoleCommandEvent $event): void
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
