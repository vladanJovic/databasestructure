<?php

namespace ActiveCollab\DatabaseStructure;

use ActiveCollab\DatabaseConnection\ConnectionInterface;

/**
 * @package ActiveCollab\DatabaseStructure
 */
interface StructureInterface
{
    /**
     * Get all structure type
     *
     * @return Type[]
     */
    public function getTypes();

    /**
     * Return type by type name
     *
     * @param  string $type_name
     * @return Type
     */
    public function getType($type_name);

    /**
     * @return string
     */
    public function getNamespace();

    /**
     * @param  string|null $namespace
     * @return $this
     */
    public function &setNamespace($namespace);

    /**
     * Build model at the given path
     *
     * If $build_path is null, classes will be generated, evaled and loaded into the memory
     *
     * @param string|null         $build_path
     * @param ConnectionInterface $connection
     * @param array|null          $event_handlers
     */
    public function build($build_path = null, ConnectionInterface $connection = null, array $event_handlers = []);
}