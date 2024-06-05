<?php

namespace Arthem\ObjectReferenceBundle\Tests;

use Arthem\ObjectReferenceBundle\Doctrine\ObjectReferenceListener;
use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Arthem\ObjectReferenceBundle\Tests\Entity\Actor;
use Arthem\ObjectReferenceBundle\Tests\Entity\Civilian;
use Arthem\ObjectReferenceBundle\Tests\Entity\Story;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;

class MappingTest extends TestCase
{
    public function testMapping(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__.'/Entity'],
            isDevMode: true,
        );
        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => __DIR__.'/db.sqlite',
        ], $config);

        $objectMapper = new ObjectMapper([
            'actor' => Actor::class,
            'civil' => Civilian::class,
        ]);

        $eventManager = new EventManager();
        $eventManager->addEventSubscriber(new ObjectReferenceListener($objectMapper));
        $entityManager = new EntityManager($connection, $config, $eventManager);

        $metadata = $entityManager->getClassMetadata(Story::class);

        $this->assertEquals(Story::class, $metadata->getName());
        $this->assertEquals([
            'id',
            'personType',
            'personId',
            'ownerType',
            'ownerId',
        ], $metadata->getFieldNames());
        $this->assertEquals([
            'id',
            'person_type',
            'person_id',
            'owner_type',
            'owner_id',
        ], $metadata->getColumnNames());

        $mapping = $metadata->getFieldMapping('personType');
        $this->assertEquals(false, $mapping['nullable']);
        $mapping = $metadata->getFieldMapping('ownerType');
        $this->assertEquals(true, $mapping['nullable']);
        $this->assertEquals('owner_type', $mapping['columnName']);
    }
}
