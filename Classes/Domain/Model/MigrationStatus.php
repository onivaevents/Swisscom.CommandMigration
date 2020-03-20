<?php
namespace Swisscom\CommandMigration\Domain\Model;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Entity
 */
class MigrationStatus
{
    /**
     * @var string
     */
    protected $version = '';

    /**
     * @param string $version
     */
    public function __construct(string $version)
    {
        $this->version = $version;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

}
