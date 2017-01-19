<?php

/*
 * This file is part of the Active Collab DatabaseStructure project.
 *
 * (c) A51 doo <info@activecollab.com>. All rights reserved.
 */

namespace ActiveCollab\DatabaseStructure\Builder;

use ActiveCollab\DatabaseStructure\MultiRecordInterface;
use ActiveCollab\DatabaseStructure\RecordInterface;

/**
 * @package ActiveCollab\DatabaseStructure\Builder
 */
class RecordsBuilder extends DatabaseBuilder implements FileSystemBuilderInterface
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
        /** @var RecordInterface $record */
        foreach ($this->getStructure()->getRecords() as $record) {
            $insert_statement = $this->prepareInsertStatement($record);

            $this->appendToStructureSql($insert_statement, $record->getComment());

            $this->getConnection()->execute($insert_statement);
            $this->triggerEvent('on_record_inserted', [$this->getInsertMessage($record)]);
        }
    }

    private function prepareInsertStatement(RecordInterface $record)
    {
        $statement = "INSERT INTO {$this->getConnection()->escapeTableName($record->getTableName())} ({$this->prepareFieldNames($record->getFields())}) VALUES\n";

        if ($record instanceof MultiRecordInterface) {
            foreach ($record->getValues() as $v) {
                $statement .= "    ({$this->prepareValues($v)}),\n";
            }
        } else {
            $statement .= "    ({$this->prepareValues($record->getValues())}),\n";
        }

        return rtrim(rtrim($statement, "\n"), ',') . ";\n";
    }

    /**
     * @param  array  $field_names
     * @return string
     */
    private function prepareFieldNames(array $field_names)
    {
        return implode(',', array_map(function($field_name) {
            return $this->getConnection()->escapeFieldName($field_name);
        }, $field_names));
    }

    /**
     * @param  array  $values
     * @return string
     */
    private function prepareValues(array $values)
    {
        return implode(',', array_map(function($value) {
            return $this->getConnection()->escapeValue($value);
        }, $values));
    }

    private function getInsertMessage(RecordInterface $record)
    {
        $records_to_insert = 1;

        if ($record instanceof MultiRecordInterface) {
            $records_to_insert = count($record->getValues());
        }

        if ($records_to_insert > 1) {
            $message = "Inserting {$records_to_insert} records into {$record->getTableName()} table.";
        } else {
            $message = "Inserting a record into {$record->getTableName()} table.";
        }

        return $message;
    }
}