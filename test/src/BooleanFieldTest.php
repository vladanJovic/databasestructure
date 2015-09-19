<?php

namespace ActiveCollab\DatabaseStructure\Test;

use ActiveCollab\DatabaseStructure\Field\Scalar\Boolean;

/**
 * @package ActiveCollab\DatabaseStructure\Test
 */
class BooleanFieldTest extends TestCase
{
    /**
     * @expectedException \LogicException
     */
    public function testExceptionWhenBooleanFieldIsUnique()
    {
        (new Boolean('should_not_be_required'))->unique();
    }
}