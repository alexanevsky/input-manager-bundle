<?php

namespace Alexanevsky\InputManagerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class InputManagerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder
            ->getDefinition('alexanevsky.input_manager.input_validator')
            ->setPublic(true);
    }
}
