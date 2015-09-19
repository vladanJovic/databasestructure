<?php

namespace ActiveCollab\DatabaseStructure\Test;

use ActiveCollab\DatabaseStructure\Test\Fixtures\Writers\WritersStructure;

/**
 * @package ActiveCollab\DatabaseStructure\Test
 */
class StructureDefinitionTest extends TestCase
{
    /**
     * Use structure's namespace by default
     */
    public function testStructureUsesItsOwnNamespaceByDefault()
    {
        $structure = new WritersStructure($this->connection);
        $this->assertEquals((new \ReflectionClass(WritersStructure::class))->getNamespaceName(), $structure->getNamespace());
    }

    /**
     * Namespace can be set to a custom value
     */
    public function testStructureNamespaceCanBeChanged()
    {
        $structure = new WritersStructure($this->connection);
        $structure->setNamespace('\\Vendor\\Project\\Model');
        $this->assertEquals('Vendor\\Project\\Model', $structure->getNamespace());
    }

    /**
     * Global namespace defaults to empty string
     */
    public function testGlobalNamespaceDefaultsToEmptyString()
    {
        $structure = new WritersStructure($this->connection);
        $structure->setNamespace('\\');
        $this->assertEquals('', $structure->getNamespace());
    }

    public function testBaseClassDump()
    {
        $structure = new WritersStructure($this->connection);
        $structure->build();
    }
}