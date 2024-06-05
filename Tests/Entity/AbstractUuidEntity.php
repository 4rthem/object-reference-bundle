<?php

declare(strict_types=1);

namespace Arthem\ObjectReferenceBundle\Tests\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\MappedSuperclass]
abstract class AbstractUuidEntity
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36, unique: true)]
    private string $id;

    public function __construct(?string $id = null)
    {
        $this->id = $id ?? Uuid::uuid4()->toString();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
