<?php
namespace Swisscom\CommandMigration\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;

/**
 * @Flow\Scope("singleton")
 */
class CommandMigrationCommandController extends CommandController
{

    /**
     * @var \Swisscom\CommandMigration\MigrationService
     * @Flow\Inject
     */
    protected $migrationService;

    /**
     * Execute the pending migrations
     *
     * Run the pending migration commands provided by currently active packages.
     *
     * @param string $version The version to migrate to
     * @param boolean $dryRun Whether to do a dry run or not
     * @param boolean $quiet if set, only output of failed commands will be echoed
     */
    public function migrateCommand(?string $version = null, bool $dryRun = false, bool $quiet = false)
    {
        $consoleOutput = $this->output;
        $this->migrationService->executeMigrations($version, $dryRun, $quiet, function ($output) use ($quiet, $consoleOutput) {
            if (!$quiet) {
                $consoleOutput->outputLine($output);
            }
        });
    }

    /**
     * Execute a single migration
     *
     * Manually run a single migration independent from the migration status.
     *
     * @param string $version The migration to execute
     * @param boolean $dryRun Whether to do a dry run or not
     */
    public function migrationExecuteCommand(string $version, bool $dryRun = false)
    {
        $consoleOutput = $this->output;
        $this->migrationService->executeMigration($version, $dryRun, false, function ($output) use ($consoleOutput) {
            $consoleOutput->outputLine($output);
        });
    }

    /**
     * Show the current migration status
     *
     * Displays the migration status as well as the number of available, executed and pending migrations.
     *
     * @param boolean $showMigrations Output a list of all migrations and their status
     * @param boolean $showDescriptions Show descriptions for the migrations (enables versions display)
     */
    public function migrationStatusCommand(bool $showMigrations = false, bool $showDescriptions = false)
    {
        if ($showDescriptions) {
            $showMigrations = true;
        }
        $this->outputLine($this->migrationService->getFormattedMigrationStatus($showMigrations, $showDescriptions));
    }

    /**
     * Mark/unmark migrations as migrated
     *
     * @param string $version The migration to execute
     * @param boolean $add The migration to mark as migrated
     * @param boolean $delete The migration to mark as not migrated
     */
    public function migrationVersionCommand(string $version, bool $add = false, bool $delete = false)
    {

        if ($add === false && $delete === false) {
            throw new \InvalidArgumentException('You must specify whether you want to --add or --delete the specified version.');
        }
        $this->migrationService->markAsMigrated($version, $add ?: false);
        $this->outputLine('Migration %s marked as %smigrated', [$version, $add ? '': 'not ']);
    }
}
