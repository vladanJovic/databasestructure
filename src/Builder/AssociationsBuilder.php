<?php

namespace ActiveCollab\DatabaseStructure\Builder;

use ActiveCollab\DatabaseStructure\Association\HasAndBelongsToManyAssociation;
use ActiveCollab\DatabaseStructure\Type;
use ActiveCollab\DatabaseStructure\Association\BelongsToAssociation;
use ActiveCollab\DatabaseStructure\TypeInterface;
use Doctrine\Common\Inflector\Inflector;

/**
 * @package ActiveCollab\DatabaseStructure\Builder
 */
class AssociationsBuilder extends DatabaseBuilder
{
    /**
     * Execute after types are built
     */
    public function postBuild()
    {
        if ($this->getConnection()) {
            foreach ($this->getStructure()->getTypes() as $type) {
                foreach ($type->getAssociations() as $association) {
                    if ($association instanceof BelongsToAssociation) {
                        if ($this->constraintExists($association->getConstraintName(), $association->getTargetTypeName())) {
                            $this->triggerEvent('on_association_exists', [$type->getName() . ' belongs to ' . $association->getTargetTypeName()]);
                        } else {
                            $this->getConnection()->execute($this->prepareBelongsToConstraintStatement($type, $association));
                            $this->triggerEvent('on_association_created', [$type->getName() . ' belongs to ' . $association->getTargetTypeName()]);
                        }
                    } elseif ($association instanceof HasAndBelongsToManyAssociation) {
                        $connection_table = $association->getConnectionTableName();

                        if ($this->constraintExists($association->getLeftConstraintName(), $association->getSourceTypeName())) {
                            $this->triggerEvent('on_association_skipped', [Inflector::singularize($association->getSourceTypeName()) . ' has many ' . $association->getTargetTypeName()]);
                        } else {
                            $left_field_name = $association->getLeftFieldName();
                            $this->getConnection()->execute($this->prepareHasAndBelongsToManyConstraintStatement($type->getName(), $connection_table, $association->getLeftConstraintName(), $left_field_name));
                            $this->triggerEvent('on_association_created', [Inflector::singularize($association->getSourceTypeName()) . ' has many ' . $association->getTargetTypeName()]);
                        }

                        if ($this->constraintExists($association->getRightConstraintName(), $association->getTargetTypeName())) {
                            $this->triggerEvent('on_association_skipped', [Inflector::singularize($association->getTargetTypeName()) . ' has many ' . $association->getSourceTypeName()]);
                        } else {
                            $right_field_name = $association->getRightFieldName();
                            $this->getConnection()->execute($this->prepareHasAndBelongsToManyConstraintStatement($association->getTargetTypeName(), $connection_table, $association->getRightConstraintName(), $right_field_name));
                            $this->triggerEvent('on_association_created', [Inflector::singularize($association->getTargetTypeName()) . ' has many ' . $association->getSourceTypeName()]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Prepare belongs to constraint statement
     *
     * @param  TypeInterface        $type
     * @param  BelongsToAssociation $association
     * @return string
     */
    public function prepareBelongsToConstraintStatement(TypeInterface $type, BelongsToAssociation $association)
    {
        $result = [];

        $result[] = 'ALTER TABLE ' . $this->getConnection()->escapeTableName($type->getName());
        $result[] = '    ADD CONSTRAINT ' . $this->getConnection()->escapeFieldName($association->getConstraintName());
        $result[] = '    FOREIGN KEY (' . $this->getConnection()->escapeFieldName($association->getFieldName()) . ') REFERENCES ' . $this->getConnection()->escapeTableName($association->getTargetTypeName()) . '(`id`)';

        if ($association->getOptional()) {
            $result[] = '    ON UPDATE SET NULL ON DELETE SET NULL';
        } else {
            $result[] = '    ON UPDATE CASCADE ON DELETE CASCADE';
        }

        return implode("\n", $result);
    }


    /**
     * Prepare has and belongs to many constraint statement
     *
     * @param  string $type_name
     * @param  string $connection_table
     * @param  string $constraint_name
     * @param  string $field_name
     * @return string
     */
    public function prepareHasAndBelongsToManyConstraintStatement($type_name, $connection_table, $constraint_name, $field_name)
    {
        $result = [];

        $result[] = 'ALTER TABLE ' . $this->getConnection()->escapeTableName($connection_table);
        $result[] = '    ADD CONSTRAINT ' . $this->getConnection()->escapeFieldName($constraint_name);
        $result[] = '    FOREIGN KEY (' . $field_name . ') REFERENCES ' . $this->getConnection()->escapeTableName($type_name) . '(`id`)';
        $result[] = '    ON UPDATE CASCADE ON DELETE CASCADE';

        return implode("\n", $result);
    }

    /**
     * Check if constraint exists at referenced table
     *
     * @param  string $constraint_name
     * @param  string $referencing_table
     * @return bool
     */
    private function constraintExists($constraint_name, $referencing_table)
    {
        return (boolean) $this->getConnection()->executeFirstCell('select
            COUNT(*) AS "row_count"
        from INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        where
            CONSTRAINT_NAME = ? AND REFERENCED_TABLE_NAME = ?;', $constraint_name, $referencing_table);
    }
}