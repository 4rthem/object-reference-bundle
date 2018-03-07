<?php

namespace Arthem\Bundle\ObjectReferenceBundle\DependencyInjection;

use Arthem\Bundle\ObjectReferenceBundle\Mapper\ObjectMapper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class ArthemObjectReferenceExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $mapperDef = $container->findDefinition(ObjectMapper::class);
        $mapperDef->setArgument('$mapping', $config['mapping']);
    }
}
