<?php

namespace ActiveCollab\DatabaseStructure\Test;

use mysqli;
use ActiveCollab\DatabaseConnection\Connection;
use RuntimeException;

/**
 * @package ActiveCollab\JobsQueue\Test
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @var mysqli
     */
    protected $link;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * Set up test environment
     */
    public function setUp()
    {
        parent::setUp();

        $this->link = new \MySQLi('localhost', 'root', '');

        if ($this->link->connect_error) {
            throw new RuntimeException('Failed to connect to database. MySQL said: ' . $this->link->connect_error);
        }

        if (!$this->link->select_db('activecollab_database_structure_test')) {
            throw new RuntimeException('Failed to select database');
        }

        $this->connection = new Connection($this->link);
    }

    /**
     * Tear down test environment
     */
    public function tearDown()
    {
        $this->connection = null;
        $this->link->close();

        parent::tearDown();
    }
}