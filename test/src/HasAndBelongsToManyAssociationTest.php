<?php

namespace ActiveCollab\DatabaseStructure\Test;

use ActiveCollab\DatabaseStructure\Association\HasAndBelongsToMany;
use ActiveCollab\DatabaseStructure\Type;

/**
 * @package ActiveCollab\DatabaseStructure\Test
 */
class HasAndBelongsToManyAssociationTest extends TestCase
{
    /**
     * Return field names
     */
    public function testFieldNames()
    {
        $writers = new Type('writers');
        $books = new Type('books');
        $book_writers = new HasAndBelongsToMany('writers');
        $books->addAssociation($book_writers);

        $this->assertEquals('book_id', $book_writers->getLeftFieldName());
        $this->assertEquals('writer_id', $book_writers->getRightFieldName());
    }

    /**
     * Test constraint name for belongs to association
     */
    public function testConstraintNames()
    {
        $writers = new Type('writers');
        $books = new Type('books');
        $book_writers = new HasAndBelongsToMany('writers');
        $books->addAssociation($book_writers);

        $this->assertEquals('book_id_constraint', $book_writers->getLeftConstraintName());
        $this->assertEquals('writer_id_constraint', $book_writers->getRightConstraintName());
    }
}