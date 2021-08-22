<?php

namespace Abivia\Hydration\Test\Objects;

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
     * @var string This property generates an error if an attempt is made to set it.
     */
    public string $blocked;

    /**
     * @var int This property has a validator ensuring the assigned number is even.
     */
    protected int $evenInt;

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

    private string $privateString = 'initial';

    /**
     * @var mixed Generic test property.
     */
    public $prop;

    /**
     * @var string This property is implicit, added by the Hydrator.
     */
    protected string $unspecified;

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

    public function setIgnorable($value): bool
    {
        $this->ignorable = $value;

        return true;
    }

}