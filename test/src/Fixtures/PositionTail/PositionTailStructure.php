<?php

/*
 * This file is part of the Active Collab DatabaseStructure project.
 *
 * (c) A51 doo <info@activecollab.com>. All rights reserved.
 */

namespace ActiveCollab\DatabaseStructure\Test\Fixtures\PositionTail;

use ActiveCollab\DatabaseStructure\Field\Composite\PositionField;
use ActiveCollab\DatabaseStructure\Structure;

/**
 * @package ActiveCollab\DatabaseStructure\Test\Fixtures\PositionTail
 */
class PositionTailStructure extends Structure
{
    /**
     * Configure the structure.
     */
    public function configure()
    {
        $this->addType('position_tail_entries')->addFields([
            (new PositionField())->tail(),
        ]);
    }
}
