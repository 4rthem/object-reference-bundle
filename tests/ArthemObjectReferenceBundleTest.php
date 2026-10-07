<?php

namespace Arthem\ObjectReferenceBundle\Tests;

use Arthem\ObjectReferenceBundle\ArthemObjectReferenceBundle;
use Arthem\ObjectReferenceBundle\Doctrine\ObjectReferenceListener;
use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Arthem\ObjectReferenceBundle\Tests\Entity\Actor;
use Arthem\ObjectReferenceBundle\Tests\Entity\Civilian;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ArthemObjectReferenceBundleTest extends TestCase
{
    public function testExtensionAlias(): void
    {
        $this->assertSame('arthem_object_reference', (new ArthemObjectReferenceBundle())->getContainerExtension()->getAlias());
    }

    public function testServicesAreRegistered(): void
    {
        $container = $this->load([]);

        foreach ([
            ObjectMapper::class,
            ObjectReferenceListener::class,
        ] as $id) {
            $this->assertTrue($container->hasDefinition($id), sprintf('Service "%s" is not registered', $id));
            $this->assertTrue($container->getDefinition($id)->isAutowired(), sprintf('Service "%s" is not autowired', $id));
            $this->assertTrue($container->getDefinition($id)->isAutoconfigured(), sprintf('Service "%s" is not autoconfigured', $id));
        }
    }

    public function testMappingIsEmptyByDefault(): void
    {
        $container = $this->load([]);

        $this->assertSame([], $container->getDefinition(ObjectMapper::class)->getArgument('$mapping'));
    }

    public function testObjectMapperReceivesTheConfiguredMapping(): void
    {
        $container = $this->load([
            'mapping' => [
                'actor' => Actor::class,
                'civil' => Civilian::class,
            ],
        ]);

        $this->assertSame(
            ['actor' => Actor::class, 'civil' => Civilian::class],
            $container->getDefinition(ObjectMapper::class)->getArgument('$mapping')
        );
    }

    public function testMappingsAreMergedAcrossConfigs(): void
    {
        $container = $this->createContainer();
        (new ArthemObjectReferenceBundle())->getContainerExtension()->load([
            ['mapping' => ['actor' => Actor::class]],
            ['mapping' => ['civil' => Civilian::class]],
        ], $container);

        $this->assertSame(
            ['actor' => Actor::class, 'civil' => Civilian::class],
            $container->getDefinition(ObjectMapper::class)->getArgument('$mapping')
        );
    }

    public function testCompiledContainerProvidesAWorkingMapper(): void
    {
        $container = $this->load([
            'mapping' => ['actor' => Actor::class],
        ]);
        $container->getDefinition(ObjectMapper::class)->setPublic(true);
        $container->compile();

        $mapper = $container->get(ObjectMapper::class);
        $this->assertInstanceOf(ObjectMapper::class, $mapper);
        $this->assertSame(Actor::class, $mapper->getClassName('actor'));
    }

    public function testUnknownOptionsAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['unknown' => true]);
    }

    public function testNonScalarMappingIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['mapping' => ['actor' => ['foo']]]);
    }

    private function load(array $config): ContainerBuilder
    {
        $container = $this->createContainer();
        (new ArthemObjectReferenceBundle())->getContainerExtension()->load([$config], $container);

        return $container;
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        return $container;
    }
}
