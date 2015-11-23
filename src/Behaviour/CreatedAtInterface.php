<?php

namespace ActiveCollab\DatabaseStructure\Behaviour;

/**
 * @package ActiveCollab\DatabaseStructure\Behaviour
 */
interface CreatedAtInterface
{
    /**
     * Return object ID
     *
     * @return integer
     */
    public function getId();

    /**
     * Return value of created_at field
     *
     * @return \ActiveCollab\DateValue\DateTimeValueInterface|null
     */
    public function getCreatedAt();

    /**
     * @param  \ActiveCollab\DateValue\DateTimeValueInterface|null $value
     * @return $this
     */
    public function &setCreatedAt($value);
}