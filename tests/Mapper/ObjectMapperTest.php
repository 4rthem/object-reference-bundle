<?php

namespace Arthem\ObjectReferenceBundle\Tests\Mapper;

use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Arthem\ObjectReferenceBundle\Tests\Entity\AbstractUuidEntity;
use Arthem\ObjectReferenceBundle\Tests\Entity\Actor;
use Arthem\ObjectReferenceBundle\Tests\Entity\Civilian;
use Arthem\ObjectReferenceBundle\Tests\Entity\Story;
use PHPUnit\Framework\TestCase;

class ObjectMapperTest extends TestCase
{
    private ObjectMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ObjectMapper([
            'actor' => Actor::class,
            'civil' => Civilian::class,
        ]);
    }

    public function testGetClassName(): void
    {
        $this->assertSame(Actor::class, $this->mapper->getClassName('actor'));
        $this->assertSame(Civilian::class, $this->mapper->getClassName('civil'));
    }

    public function testGetClassNameOfUndefinedKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Undefined object "unknown" in the object mapping');

        $this->mapper->getClassName('unknown');
    }

    public function testGetObjectKeyFromObject(): void
    {
        $this->assertSame('actor', $this->mapper->getObjectKey(new Actor()));
        $this->assertSame('civil', $this->mapper->getObjectKey(new Civilian()));
    }

    public function testGetObjectKeyFromClassName(): void
    {
        $this->assertSame('actor', $this->mapper->getObjectKey(Actor::class));
    }

    public function testGetObjectKeyFromDoctrineProxyClassName(): void
    {
        $this->assertSame('actor', $this->mapper->getObjectKey('Proxies\\__CG__\\'.Actor::class));
    }

    public function testGetObjectKeyFallsBackOnParentClass(): void
    {
        $mapper = new ObjectMapper(['entity' => AbstractUuidEntity::class]);

        $this->assertSame('entity', $mapper->getObjectKey(new Actor()));
        $this->assertSame('entity', $mapper->getObjectKey(Story::class));
    }

    public function testGetObjectKeyOfUnmappedClassThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Class "%s" is not defined in the object mapping', Story::class));

        $this->mapper->getObjectKey(new Story());
    }

    public function testIsObjectMapped(): void
    {
        $this->assertTrue($this->mapper->isObjectMapped(new Actor()));
        $this->assertTrue($this->mapper->isObjectMapped(Civilian::class));
        $this->assertTrue($this->mapper->isObjectMapped('Proxies\\__CG__\\'.Civilian::class));
        $this->assertFalse($this->mapper->isObjectMapped(new Story()));
        $this->assertFalse($this->mapper->isObjectMapped(\stdClass::class));
    }

    public function testIsObjectMappedFallsBackOnParentClass(): void
    {
        $mapper = new ObjectMapper(['entity' => AbstractUuidEntity::class]);

        $this->assertTrue($mapper->isObjectMapped(new Actor()));
        $this->assertFalse($mapper->isObjectMapped(\stdClass::class));
    }
}
