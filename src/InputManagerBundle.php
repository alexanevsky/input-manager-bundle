<?php

namespace Alexanevsky\InputManagerBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class InputManagerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
