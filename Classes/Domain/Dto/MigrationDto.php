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
     * @var MigrationStatus|null
     */
    protected $migrationStatus;

    /**
     * @param AbstractMigration $migration
     * @param MigrationStatus|null $migrationStatus
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
        if ($this->migrationStatus === null) {
            throw new \Exception('No status before migrating');
        }
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
