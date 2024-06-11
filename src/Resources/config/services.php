<?php

declare(strict_types=1);

use Drjele\Doctrine\Audit\Service\AnnotationReadService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator->services();

    $services->set(AnnotationReadService::class)
        ->autowire()
        ->autoconfigure();
};
