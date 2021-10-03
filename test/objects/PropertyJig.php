<?php
/** @noinspection ALL */

namespace Abivia\Hydration\Test\Objects;

use Abivia\Hydration\Property;

/**
 * Contains properties for most of the tests
 */
class PropertyJig
{
    /**
     * @var array Contents vary on the test that uses this property.
     */
    public array $arrayOfTestData;

    /**
     * @var array Contents vary on the test that uses this property.
     */
    protected array $arrayProtected;

    /**
     * @var string This property generates an error if an attempt is made to set it.
     */
    public string $blocked;

    /**
     * @var int This property has a validator ensuring the assigned number is even.
     */
    protected int $evenInt = 2;

    public bool $getPropertyIsNull;

    /**
     * @var string Property used to test the "ignore" state
     */
    public string $ignorable = 'unchanged';

    protected object $objectClass;

    public array $objectClassArray;

    /**
     * @var string This property has a name different to that in the source data.
     */
    protected string $internalName;

    private ?string $privateString = 'initial';

    public bool $setPropertyIsNull;

    /**
     * @var RequiredConfig|null for testing Hydrator::reflectionType();
     */
    protected ?RequiredConfig $subClass;

    /**
     * @var mixed Generic test property.
     */
    public $prop;

    /**
     * @var string This property is implicit, added by the Hydrator.
     */
    protected string $unspecified;

    public function getEvenInt(?Property $property = null): int
    {
        $this->getPropertyIsNull = $property === null;
        return $this->evenInt;
    }

    public function getObjectClass(): object
    {
        return $this->objectClass;
    }

    /**
     * @return string
     */
    public function getPrivateString(): string
    {
        return $this->privateString;
    }

    public function setArray($value): bool
    {
        if (in_array('bad', $value)) {
            return false;
        }
        $this->arrayOfTestData = $value;
        return true;
    }

    public function setIgnorable($value, ?Property $property = null): bool
    {
        $this->setPropertyIsNull = $property === null;
        if (substr($value, 0, 3) === 'bad') {
            return false;
        }
        $this->ignorable = $value;

        return true;
    }

}