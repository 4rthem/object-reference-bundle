<?php

namespace Arthem\ObjectReferenceBundle;

use Arthem\ObjectReferenceBundle\Doctrine\ObjectReferenceListener;
use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class ArthemObjectReferenceBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('mapping')
                    ->info('Object keys mapped to their class name.')
                    ->example(['user' => 'App\Entity\User'])
                    ->useAttributeAsKey('key')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services
            ->defaults()
                ->autowire()
                ->autoconfigure();

        $services->set(ObjectMapper::class)
            ->arg('$mapping', $config['mapping']);
        $services->set(ObjectReferenceListener::class);
    }
}
