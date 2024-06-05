<?php

namespace Arthem\ObjectReferenceBundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Civilian extends AbstractUuidEntity implements PersonInterface
{
    public function getType(): string
    {
        return 'civilian';
    }
}
