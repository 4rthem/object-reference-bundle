<?php

namespace Arthem\ObjectReferenceBundle\Tests\Doctrine;

use Arthem\ObjectReferenceBundle\Doctrine\ObjectReferenceListener;
use Arthem\ObjectReferenceBundle\Mapper\ObjectMapper;
use Arthem\ObjectReferenceBundle\Tests\Entity\Actor;
use Arthem\ObjectReferenceBundle\Tests\Entity\Civilian;
use Arthem\ObjectReferenceBundle\Tests\Entity\Story;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

class ObjectReferenceListenerTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__.'/../Entity'],
            isDevMode: true,
        );
        $config->setNamingStrategy(new UnderscoreNamingStrategy());
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        $objectMapper = new ObjectMapper([
            'actor' => Actor::class,
            'civil' => Civilian::class,
        ]);

        $eventManager = new EventManager();
        $eventManager->addEventListener([
            Events::loadClassMetadata,
            Events::prePersist,
            Events::preFlush,
            Events::postLoad,
        ], new ObjectReferenceListener($objectMapper));

        $this->em = new EntityManager($connection, $config, $eventManager);
    }

    public function testReferenceFieldIsReplacedByTypeAndIdFields(): void
    {
        $metadata = $this->em->getClassMetadata(Story::class);

        $this->assertSame(Story::class, $metadata->getName());
        $this->assertSame([
            'id',
            'personType',
            'personId',
            'ownerType',
            'ownerId',
        ], array_values($metadata->getFieldNames()));
        $this->assertSame([
            'id',
            'person_type',
            'person_id',
            'owner_type',
            'owner_id',
        ], $metadata->getColumnNames());
        $this->assertFalse($metadata->hasField('person'));
        $this->assertFalse($metadata->hasField('owner'));
    }

    public function testTypeFieldMapping(): void
    {
        $metadata = $this->em->getClassMetadata(Story::class);

        $mapping = $metadata->getFieldMapping('personType');
        $this->assertSame('string', $mapping['type']);
        $this->assertSame(15, $mapping['length']);
        $this->assertFalse($mapping['nullable']);
        $this->assertSame('person_type', $mapping['columnName']);

        $mapping = $metadata->getFieldMapping('ownerType');
        $this->assertSame('string', $mapping['type']);
        $this->assertSame(15, $mapping['length']);
        $this->assertTrue($mapping['nullable']);
        $this->assertSame('owner_type', $mapping['columnName']);
    }

    public function testIdFieldCopiesTheReferenceColumnDefinition(): void
    {
        $metadata = $this->em->getClassMetadata(Story::class);

        $mapping = $metadata->getFieldMapping('personId');
        $this->assertSame('string', $mapping['type']);
        $this->assertSame(36, $mapping['length']);
        $this->assertFalse($mapping['nullable']);
        $this->assertSame('person_id', $mapping['columnName']);

        $mapping = $metadata->getFieldMapping('ownerId');
        $this->assertSame('string', $mapping['type']);
        $this->assertSame(36, $mapping['length']);
        $this->assertTrue($mapping['nullable']);
        $this->assertSame('owner_id', $mapping['columnName']);
    }

    public function testNonReferencingEntityIsLeftUntouched(): void
    {
        $metadata = $this->em->getClassMetadata(Actor::class);

        $this->assertSame(['id'], array_values($metadata->getFieldNames()));
    }

    public function testPersistStoresTypeAndIdOfReferencedObjects(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $civilian = new Civilian();
        $story = new Story();
        $story->setPerson($actor);
        $story->setOwner($civilian);

        $this->em->persist($actor);
        $this->em->persist($civilian);
        $this->em->persist($story);
        $this->em->flush();

        $this->assertSame([
            'person_type' => 'actor',
            'person_id' => $actor->getId(),
            'owner_type' => 'civil',
            'owner_id' => $civilian->getId(),
        ], $this->fetchStoryRow($story->getId()));
    }

    public function testPersistWithoutReferenceStoresNull(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $story = new Story();
        $story->setPerson($actor);

        $this->em->persist($actor);
        $this->em->persist($story);
        $this->em->flush();

        $this->assertSame([
            'person_type' => 'actor',
            'person_id' => $actor->getId(),
            'owner_type' => null,
            'owner_id' => null,
        ], $this->fetchStoryRow($story->getId()));
    }

    public function testLoadResolvesReferencedObjectsLazily(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $civilian = new Civilian();
        $story = new Story();
        $story->setPerson($civilian);
        $story->setOwner($actor);

        $this->em->persist($actor);
        $this->em->persist($civilian);
        $this->em->persist($story);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(Story::class, $story->getId());
        $this->assertNotSame($story, $loaded);

        $person = $loaded->getPerson();
        $this->assertInstanceOf(Civilian::class, $person);
        $this->assertSame($civilian->getId(), $person->getId());
        $this->assertSame($person, $loaded->getPerson());

        $owner = $loaded->getOwner();
        $this->assertInstanceOf(Actor::class, $owner);
        $this->assertSame($actor->getId(), $owner->getId());
    }

    public function testLoadWithoutReferenceReturnsNull(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $story = new Story();
        $story->setPerson($actor);

        $this->em->persist($actor);
        $this->em->persist($story);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(Story::class, $story->getId());

        $this->assertInstanceOf(Actor::class, $loaded->getPerson());
        $this->assertNull($loaded->getOwner());
    }

    public function testLoadWithReferenceToRemovedObjectReturnsNull(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $story = new Story();
        $story->setPerson($actor);

        $this->em->persist($actor);
        $this->em->persist($story);
        $this->em->flush();
        $this->em->remove($actor);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(Story::class, $story->getId());

        $this->assertNull($loaded->getPerson());
    }

    public function testUpdatingReferenceOfManagedEntityIsPersisted(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $civilian = new Civilian();
        $story = new Story();
        $story->setPerson($actor);
        $story->setOwner($civilian);

        $this->em->persist($actor);
        $this->em->persist($civilian);
        $this->em->persist($story);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(Story::class, $story->getId());
        $loaded->setPerson($this->em->find(Civilian::class, $civilian->getId()));
        $loaded->setOwner(null);
        $this->em->flush();

        $this->assertSame([
            'person_type' => 'civil',
            'person_id' => $civilian->getId(),
            'owner_type' => null,
            'owner_id' => null,
        ], $this->fetchStoryRow($story->getId()));
    }

    public function testUpdatingReferenceBeforeFlushOfNewEntityIsPersisted(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $civilian = new Civilian();
        $story = new Story();
        $story->setPerson($actor);
        $story->setOwner($actor);

        $this->em->persist($actor);
        $this->em->persist($civilian);
        $this->em->persist($story);
        $story->setPerson($civilian);
        $story->setOwner(null);
        $this->em->flush();

        $this->assertSame([
            'person_type' => 'civil',
            'person_id' => $civilian->getId(),
            'owner_type' => null,
            'owner_id' => null,
        ], $this->fetchStoryRow($story->getId()));
    }

    public function testUnresolvedReferenceIsKeptOnFlush(): void
    {
        $this->createSchema();

        $actor = new Actor();
        $story = new Story();
        $story->setPerson($actor);
        $story->setOwner($actor);

        $this->em->persist($actor);
        $this->em->persist($story);
        $this->em->flush();
        $this->em->clear();

        $this->em->find(Story::class, $story->getId());
        $this->em->flush();

        $this->assertSame([
            'person_type' => 'actor',
            'person_id' => $actor->getId(),
            'owner_type' => 'actor',
            'owner_id' => $actor->getId(),
        ], $this->fetchStoryRow($story->getId()));
    }

    private function createSchema(): void
    {
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(Actor::class),
            $this->em->getClassMetadata(Civilian::class),
            $this->em->getClassMetadata(Story::class),
        ]);
    }

    private function fetchStoryRow(string $id): array
    {
        return $this->em->getConnection()->fetchAssociative(
            'SELECT person_type, person_id, owner_type, owner_id FROM story WHERE id = ?',
            [$id],
        );
    }
}
