<?php

/*
 * This file is part of the Active Collab DatabaseStructure project.
 *
 * (c) A51 doo <info@activecollab.com>. All rights reserved.
 */

namespace ActiveCollab\DatabaseStructure\Builder;

use ActiveCollab\DatabaseStructure\Association\BelongsToAssociation;
use ActiveCollab\DatabaseStructure\Association\HasAndBelongsToManyAssociation;
use ActiveCollab\DatabaseStructure\Association\HasOneAssociation;
use ActiveCollab\DatabaseStructure\TypeInterface;
use Doctrine\Common\Inflector\Inflector;

/**
 * @package ActiveCollab\DatabaseStructure\Builder
 */
class AssociationsBuilder extends DatabaseBuilder implements FileSystemBuilderInterface
{
    use StructureSql;

    /**
     * Build path. If empty, class will be built to memory.
     *
     * @var string
     */
    private $build_path;

    /**
     * Return build path.
     *
     * @return string
     */
    public function getBuildPath()
    {
        return $this->build_path;
    }

    /**
     * Set build path. If empty, class will be built in memory.
     *
     * @param  string $value
     * @return $this
     */
    public function &setBuildPath($value)
    {
        $this->build_path = $value;

        return $this;
    }

    /**
     * Execute after types are built.
     */
    public function postBuild()
    {
        if ($this->getConnection()) {
            foreach ($this->getStructure()->getTypes() as $type) {
                foreach ($type->getAssociations() as $association) {
                    if ($association instanceof BelongsToAssociation) {
                        $create_constraint_statement = $this->prepareBelongsToConstraintStatement($type, $association);
                        $this->appendToStructureSql($create_constraint_statement, 'Create ' . $this->getConnection()->escapeTableName($association->getConstraintName()) . ' constraint');

                        if ($this->constraintExists($association->getConstraintName(), $association->getTargetTypeName())) {
                            $this->triggerEvent('on_association_exists', [$type->getName() . ' belongs to ' . Inflector::singularize($association->getTargetTypeName())]);
                        } else {
                            $this->getConnection()->execute($create_constraint_statement);
                            $this->triggerEvent('on_association_created', [$type->getName() . ' belongs to ' . Inflector::singularize($association->getTargetTypeName())]);
                        }
                    } elseif ($association instanceof HasOneAssociation) {
                        $create_constraint_statement = $this->prepareHasOneConstraintStatement($type, $association);
                        $this->appendToStructureSql($create_constraint_statement, 'Create ' . $this->getConnection()->escapeTableName($association->getConstraintName()) . ' constraint');

                        if ($this->constraintExists($association->getConstraintName(), $association->getTargetTypeName())) {
                            $this->triggerEvent('on_association_exists', [$type->getName() . ' has one ' . Inflector::singularize($association->getTargetTypeName())]);
                        } else {
                            $this->getConnection()->execute($create_constraint_statement);
                            $this->triggerEvent('on_association_created', [$type->getName() . ' has one ' . Inflector::singularize($association->getTargetTypeName())]);
                        }
                    } elseif ($association instanceof HasAndBelongsToManyAssociation) {
                        $connection_table = $association->getConnectionTableName();

                        $left_field_name = $association->getLeftFieldName();
                        $create_left_field_constraint_statement = $this->prepareHasAndBelongsToManyConstraintStatement($type->getName(), $connection_table, $association->getLeftConstraintName(), $left_field_name);

                        $this->appendToStructureSql($create_left_field_constraint_statement, 'Create ' . $this->getConnection()->escapeTableName($association->getLeftConstraintName()) . ' constraint');

                        $right_field_name = $association->getRightFieldName();
                        $create_right_field_constraint_statement = $this->prepareHasAndBelongsToManyConstraintStatement($association->getTargetTypeName(), $connection_table, $association->getRightConstraintName(), $right_field_name);

                        $this->appendToStructureSql($create_right_field_constraint_statement, 'Create ' . $this->getConnection()->escapeTableName($association->getRightConstraintName()) . ' constraint');

                        if ($this->constraintExists($association->getLeftConstraintName(), $association->getSourceTypeName())) {
                            $this->triggerEvent('on_association_skipped', [Inflector::singularize($association->getSourceTypeName()) . ' has many ' . $association->getTargetTypeName()]);
                        } else {
                            $this->getConnection()->execute($create_left_field_constraint_statement);
                            $this->triggerEvent('on_association_created', [Inflector::singularize($association->getSourceTypeName()) . ' has many ' . $association->getTargetTypeName()]);
                        }

                        if ($this->constraintExists($association->getRightConstraintName(), $association->getTargetTypeName())) {
                            $this->triggerEvent('on_association_skipped', [Inflector::singularize($association->getTargetTypeName()) . ' has many ' . $association->getSourceTypeName()]);
                        } else {
                            $this->getConnection()->execute($create_right_field_constraint_statement);
                            $this->triggerEvent('on_association_created', [Inflector::singularize($association->getTargetTypeName()) . ' has many ' . $association->getSourceTypeName()]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Prepare belongs to constraint statement.
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

        if ($association->isRequired()) {
            $result[] = '    ON UPDATE CASCADE ON DELETE CASCADE;';
        } else {
            $result[] = '    ON UPDATE SET NULL ON DELETE SET NULL;';
        }

        return implode("\n", $result);
    }

    /**
     * Prepare has one constraint statement.
     *
     * @param  TypeInterface     $type
     * @param  HasOneAssociation $association
     * @return string
     */
    public function prepareHasOneConstraintStatement(TypeInterface $type, HasOneAssociation $association)
    {
        $result = [];

        $result[] = 'ALTER TABLE ' . $this->getConnection()->escapeTableName($type->getName());
        $result[] = '    ADD CONSTRAINT ' . $this->getConnection()->escapeFieldName($association->getConstraintName());
        $result[] = '    FOREIGN KEY (' . $this->getConnection()->escapeFieldName($association->getFieldName()) . ') REFERENCES ' . $this->getConnection()->escapeTableName($association->getTargetTypeName()) . '(`id`)';

        if ($association->isRequired()) {
            $result[] = '    ON UPDATE CASCADE ON DELETE CASCADE;';
        } else {
            $result[] = '    ON UPDATE SET NULL ON DELETE SET NULL;';
        }

        return implode("\n", $result);
    }

    /**
     * Prepare has and belongs to many constraint statement.
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
        $result[] = '    ON UPDATE CASCADE ON DELETE CASCADE;';

        return implode("\n", $result);
    }

    /**
     * Check if constraint exists at referenced table.
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
