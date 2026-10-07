# Object Reference bundle

[![CI](https://github.com/4rthem/object-reference-bundle/actions/workflows/ci.yaml/badge.svg)](https://github.com/4rthem/object-reference-bundle/actions/workflows/ci.yaml)

Stores a reference to any mapped Doctrine entity in two columns (`<field>Type` and `<field>Id`).

## Installation

```bash
composer require arthem/object-reference-bundle
```

```php
// config/bundles.php
return [
    // ...
    Arthem\ObjectReferenceBundle\ArthemObjectReferenceBundle::class => ['all' => true],
];
```

## Configuration

Map each referenceable class to a key, which is stored in the `<field>Type` column:

```yaml
# config/packages/arthem_object_reference.yaml
arthem_object_reference:
    mapping:
        actor: App\Entity\Actor
```

## Usage

```php
namespace App\Entity;

use Arthem\ObjectReferenceBundle\Mapping\Attribute\ObjectReference;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Story
{
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
    #[ObjectReference(keyLength: 15)]
    private \Closure|Actor|null $actor = null;
    private $actorId; // must be declared, even if not used
    private $actorType; // must be declared, even if not used

    public function getActor(): ?Actor
    {
        if ($this->actor instanceof \Closure) {
            $this->actor = $this->actor->call($this);
        }

        return $this->actor;
    }

    public function setActor(?Actor $actor): void
    {
        $this->actor = $actor;
    }
}
```

## Development

```bash
composer test
composer cs
```
