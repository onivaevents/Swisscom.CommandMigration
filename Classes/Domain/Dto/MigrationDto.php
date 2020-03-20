<?php
namespace Swisscom\CommandMigration\Domain\Dto;

use Swisscom\CommandMigration\AbstractMigration;
use Swisscom\CommandMigration\Domain\Model\MigrationStatus;

class MigrationDto
{

    /**
     * @var AbstractMigration
     */
    protected $migration;

    /**
     * @var MigrationStatus
     */
    protected $migrationStatus;

    /**
     * @param AbstractMigration $migration
     * @param null|MigrationStatus $migrationStatus
     */
    public function __construct(AbstractMigration $migration, ?MigrationStatus $migrationStatus)
    {
        $this->migration = $migration;
        $this->migrationStatus = $migrationStatus;
    }

    /**
     * @return AbstractMigration
     */
    public function getMigration(): AbstractMigration
    {
        return $this->migration;
    }

    /**
     * @return MigrationStatus
     */
    public function getMigrationStatus(): MigrationStatus
    {
        return $this->migrationStatus;
    }

    /**
     * @return bool
     */
    public function isMigrated(): bool
    {
        return ($this->migrationStatus instanceof MigrationStatus);
    }

}
