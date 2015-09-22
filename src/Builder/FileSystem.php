<?php

namespace ActiveCollab\DatabaseStructure\Builder;

/**
 * @package ActiveCollab\DatabaseStructure\Builder
 */
abstract class FileSystem extends Builder
{
    /**
     * Build path. If empty, class will be built to memory
     *
     * @var string
     */
    private $build_path;

    /**
     * Return build path
     *
     * @return string
     */
    public function getBuildPath()
    {
        return $this->build_path;
    }

    /**
     * Set build path. If empty, class will be built in memory
     *
     * @param  string $value
     * @return $this
     */
    public function &setBuildPath($value)
    {
        $this->build_path = $value;

        return $this;
    }
}