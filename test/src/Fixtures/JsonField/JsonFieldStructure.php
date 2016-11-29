<?php

/*
 * This file is part of the Active Collab DatabaseStructure project.
 *
 * (c) A51 doo <info@activecollab.com>. All rights reserved.
 */

namespace ActiveCollab\DatabaseStructure\Test\Fixtures\JsonField;

use ActiveCollab\DatabaseConnection\Record\ValueCasterInterface;
use ActiveCollab\DatabaseStructure\Field\Scalar\DateField;
use ActiveCollab\DatabaseStructure\Field\Scalar\JsonField;
use ActiveCollab\DatabaseStructure\Index;
use ActiveCollab\DatabaseStructure\Structure;

/**
 * @package ActiveCollab\DatabaseStructure\Test\Fixtures\JsonSerialization
 */
class JsonFieldStructure extends Structure
{
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        $this->addType('stats_snapshots')->addFields([
            new DateField('day'),
            (new JsonField('stats'))
                ->extractValue('plan_name', '$.plan_name', ValueCasterInterface::CAST_STRING, true, true)
                ->extractValue('number_of_active_users', '$.users.num_active', ValueCasterInterface::CAST_INT, true)
                ->extractValue('is_used_on_day', '$.is_used_on_day', ValueCasterInterface::CAST_BOOL, false),
        ])->addIndex(new Index('day'));
    }
}
