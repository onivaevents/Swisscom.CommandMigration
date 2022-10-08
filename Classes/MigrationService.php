<?php
namespace Swisscom\CommandMigration;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Migrations\Tools;
use Neos\Flow\Package\PackageInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Utility\Files;
use Swisscom\CommandMigration\Domain\Dto\MigrationDto;
use Swisscom\CommandMigration\Domain\Model\MigrationStatus;

/**
 * Migration service with the business logic of the command migrations
 *
 * @Flow\Scope("singleton")
 */
class MigrationService
{

    /**
     * @var array
     */
    protected $packagesData = null;

    /**
     * @var MigrationDto[]
     */
    protected $migrations = null;

    /**
     * @Flow\InjectConfiguration(package="Neos.Flow")
     * @var array
     */
    protected $flowSettings;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var \Swisscom\CommandMigration\Domain\Repository\MigrationStatusRepository
     */
    protected $migrationStatusRepository;

    /**
     * Execute all new migrations, up to $versionNumber if given.
     *
     * @param null|string $versionNumber
     * @param bool $dryRun
     * @param bool $quiet
     * @param \Closure $outputCallback
     */
    public function executeMigrations(string $versionNumber = null, bool $dryRun = false, bool $quiet = false, \Closure $outputCallback): void
    {
        $targetMigrationDto = is_string($versionNumber) ? $this->getMigrations($versionNumber)[$versionNumber] : null;

        /** @var MigrationDto[] $migrations */
        $migrations = array_filter($this->getMigrations(), function (MigrationDto $migration) {
            return (! $migration->isMigrated());
        });

        if (count($migrations) <= 0) {
            $outputCallback->call($this, 'No migration necessary');
        } else {
            $count = 0;
            foreach ($migrations as $versionNumber => $dto) {
                $this->migrate($dto, $dryRun, $quiet, $outputCallback);
                $count++;
                if ($dto === $targetMigrationDto) {
                    break;
                }
            }
            $outputCallback->call($this, (sprintf('%s migrations executed', $count)));
        }
    }

    /**
     * Execute a single migration
     *
     * @param string $versionNumber
     * @param bool $dryRun
     * @param bool $quiet
     * @param \Closure $outputCallback
     */
    public function executeMigration(string $versionNumber, bool $dryRun = false, bool $quiet = false, \Closure $outputCallback): void
    {
        $dto = $this->getMigrations($versionNumber)[$versionNumber];
        $this->migrate($dto, $dryRun, $quiet, $outputCallback);
        $outputCallback->call($this, ('Migration executed'));
    }

    /**
     * Returns a formatted string of current command migration status.
     * This function is copied from \Neos\Flow\Persistence\Doctrine\Service
     *
     * @param bool $showMigrations
     * @param bool $showDescriptions
     * @return string
     */
    public function getFormattedMigrationStatus(bool $showMigrations = false, bool $showDescriptions = false): string
    {
        $statusInformation = $this->getMigrationStatus();
        $output = PHP_EOL . '<info>==</info> Configuration' . PHP_EOL;

        foreach ($statusInformation as $name => $value) {
            if ($name == 'New Migrations') {
                $value = $value > 0 ? '<question>' . $value . '</question>' : 0;
            }
            if ($name == 'Executed Unavailable Migrations') {
                $value = $value > 0 ? '<error>' . $value . '</error>' : 0;
            }
            $output .= '   <comment>></comment> ' . $name . ': ' . str_repeat(' ', 35 - strlen($name)) . $value . PHP_EOL;
        }

        if ($showMigrations) {
            if ($migrations = $this->getMigrations()) {
                $output .= PHP_EOL . ' <info>==</info> Available Migration Versions' . PHP_EOL;

                foreach ($migrations as $versionNumber => $dto) {

                    $packageKey = $this->getPackageKeyFromMigrationVersion($dto->getMigration());
                    $croppedPackageKey = strlen($packageKey) < 30 ? $packageKey : substr($packageKey, 0, 29) . '~';
                    $packageKeyColumn = ' ' . str_pad($croppedPackageKey, 30, ' ');
                    $status = $dto->isMigrated() ? '<info>migrated</info>' : '<error>not migrated</error>';
                    $migrationDescription = '';
                    if ($showDescriptions) {
                        $migrationDescription = str_repeat(' ', 2) . MigrationUtility::getDescription($dto->getMigration());
                    }
                    $formattedVersion = MigrationUtility::getFormattedVersion($versionNumber);

                    $output .= '    <comment>></comment> ' . $formattedVersion . $packageKeyColumn .
                        str_repeat(' ', 2) . $status . $migrationDescription . PHP_EOL;
                }
            }
            $migratedVersions = $this->migrationStatusRepository->findAll()->toArray();
            $executedUnavailableMigrations = array_filter($migratedVersions, function(MigrationStatus $migrationStatus) use ($migrations) {
                return ! in_array($migrationStatus->getVersion(), array_keys($migrations));
            });
            if (count($executedUnavailableMigrations)) {
                $output .= PHP_EOL . ' <info>==</info> Previously Executed Unavailable Migration Versions' . PHP_EOL;
                /** @var MigrationStatus $executedUnavailableMigration */
                foreach ($executedUnavailableMigrations as $executedUnavailableMigration) {
                    $formattedVersion = MigrationUtility::getFormattedVersion($executedUnavailableMigration->getVersion());
                    $output .= '    <comment>></comment> ' . $formattedVersion . PHP_EOL;
                }
            }
        }

        return $output;
    }

    /**
     * Add a migration version to the migrations table or remove it.
     * This does not execute any migration code but simply records a version as migrated or not.
     *
     * @param string $versionNumber
     * @param bool $add
     */
    public function markAsMigrated(string $versionNumber, bool $add): void
    {
        $dto = $this->getMigrations($versionNumber)[$versionNumber];
        if ($add) {
            $this->markMigrationApplied($dto);
        } else {
            $this->unmarkMigrationApplied($dto);
        }
    }

    /**
     * Initialize the manager: read package information and register migrations.
     * This function is copied from \Neos\Flow\Core\Migrations\Manager
     */
    protected function initialize(): void
    {
        if ($this->packagesData !== null) {
            return;
        }
        $this->packagesData = Tools::getPackagesData(FLOW_PATH_PACKAGES);

        $this->migrations = [];
        foreach ($this->packagesData as $packageKey => $packageData) {
            $this->registerMigrationFiles(Files::concatenatePaths([FLOW_PATH_PACKAGES, $packageData['category'], $packageKey]));
        }
        ksort($this->migrations);
    }

    /**
     * Look for code migration files in the given package path and register them for further action.
     * This function is copied from \Neos\Flow\Core\Migrations\Manager
     *
     * @param string $packagePath
     */
    protected function registerMigrationFiles(string $packagePath): void
    {
        $packagePath = rtrim($packagePath, '/');
        $packageKey = substr($packagePath, strrpos($packagePath, '/') + 1);
        $migrationsDirectory = Files::concatenatePaths([$packagePath, 'Migrations/Command']);
        if (!is_dir($migrationsDirectory)) {
            return;
        }

        foreach (Files::getRecursiveDirectoryGenerator($migrationsDirectory, '.php') as $filenameAndPath) {
            /** @noinspection PhpIncludeInspection */
            require_once($filenameAndPath);
            $baseFilename = basename($filenameAndPath, '.php');
            $className = '\\Swisscom\\CommandMigration\\' . $baseFilename;
            /** @var AbstractMigration $migration */
            $migration = new $className($this, $packageKey);
            $versionNumber = MigrationUtility::getVersionNumber($migration);
            $migrationStatus =  $this->migrationStatusRepository->findOneByVersion($versionNumber);
            $dto = new MigrationDto($migration, $migrationStatus);
            $this->migrations[$versionNumber] = $dto;
        }
    }

    /**
     * Get the migration DTOs
     * This function is copied from \Neos\Flow\Core\Migrations\Manager
     *
     * @param null|string $versionNumber if specified only the migration with the specified version is returned
     * @return MigrationDto[]
     * @throws \InvalidArgumentException
     */
    protected function getMigrations(string $versionNumber = null): array
    {
        $this->initialize();

        if ($versionNumber === null) {
            return $this->migrations;
        }
        if (!isset($this->migrations[$versionNumber])) {
            throw new \InvalidArgumentException(sprintf('Migration "%s" was not found', $versionNumber), 1584740599);
        }
        return [$versionNumber => $this->migrations[$versionNumber]];
    }

    /**
     * @param MigrationDto $dto
     * @param bool $dryRun
     * @param bool $quiet
     * @param \Closure $outputCallback
     */
    protected function migrate(MigrationDto $dto, bool $dryRun, bool $quiet, \Closure $outputCallback): void
    {
        $migration = $dto->getMigration();
        $versionNumber = MigrationUtility::getVersionNumber($migration);
        // Output starts with EOL in case of command output of previous migration which does not ends with a new line
        $outputCallback->call($this, PHP_EOL . '++ migrating ' . $versionNumber);
        $migration->up();
        if ($dryRun) {
            $outputCallback->call($this, $migration->dryRun());
        } else {
            $migration->execute($this->flowSettings, !$quiet);
            $this->markMigrationApplied($dto);
        }
    }

    /**
     * Returns the current migration status as an array.
     *
     * @return string[]
     */
    protected function getMigrationStatus(): array
    {
        $migrations = $this->getMigrations();
        $numExecutedMigrations = $this->migrationStatusRepository->countAll();
        $numNewMigrations = $numExecutedAvailableMigrations = 0;

        foreach ($migrations as $versionNumber => $dto) {
            if ($dto->isMigrated()) {
                $previous = $current ?? null;
                $current = $versionNumber;
                $numExecutedAvailableMigrations++;
            } else {
                $next = $next ?? $versionNumber;
                $numNewMigrations++;
            }
            $latest = $versionNumber;
        }
        $previous = $previous ?? 'Already at first version';
        $current = $current ?? 'No migration executed yet';
        $next = $next ?? 'Already at latest version';
        $latest = $latest ?? 'No migrations available yet';
        $numExecutedUnavailableMigrations = $numExecutedMigrations - $numExecutedAvailableMigrations;

        return [
            'Name' => 'Command Migrations',
            'Previous Version' => MigrationUtility::getFormattedVersion($previous),
            'Current Version' => MigrationUtility::getFormattedVersion($current),
            'Next Version' => MigrationUtility::getFormattedVersion($next),
            'Latest Version' => MigrationUtility::getFormattedVersion($latest),
            'Executed Migrations' => $numExecutedMigrations,
            'Executed Unavailable Migrations' => $numExecutedUnavailableMigrations,
            'Available Migrations' => count($migrations),
            'New Migrations' => $numNewMigrations,
        ];
    }

    /**
     * @param MigrationDto $dto
     */
    protected function markMigrationApplied(MigrationDto $dto): void
    {
        if (! $dto->isMigrated()) {
            $versionNumber = MigrationUtility::getVersionNumber($dto->getMigration());
            $migrationStatus = new MigrationStatus($versionNumber);
            $this->migrationStatusRepository->add($migrationStatus);

            // Flow backwards compatibility check
            if (method_exists($this->persistenceManager, 'allowObject')) {
                $this->persistenceManager->allowObject($migrationStatus);
            } else {
                $this->persistenceManager->whitelistObject($migrationStatus);
            }

            $this->persistenceManager->persistAll(true);
        }
    }


    /**
     * @param MigrationDto $dto
     */
    protected function unmarkMigrationApplied(MigrationDto $dto): void
    {
        if ($dto->isMigrated()) {
            $migrationStatus = $dto->getMigrationStatus();
            $this->migrationStatusRepository->remove($migrationStatus);

            // Flow backwards compatibility check
            if (method_exists($this->persistenceManager, 'allowObject')) {
                $this->persistenceManager->allowObject($migrationStatus);
            } else {
                $this->persistenceManager->whitelistObject($migrationStatus);
            }

            $this->persistenceManager->persistAll(true);
        }
    }

    /**
     * Tries to find out a package key which the Version belongs to.
     * If no package could be found, an empty string is returned.
     * This function is copied from \Neos\Flow\Persistence\Doctrine\Service
     *
     * @param AbstractMigration $migration
     * @return string
     * @throws \ReflectionException
     */
    protected function getPackageKeyFromMigrationVersion(AbstractMigration $migration): string
    {
        $sortedAvailablePackages = $this->packageManager->getAvailablePackages();
        usort($sortedAvailablePackages, function (PackageInterface $packageOne, PackageInterface $packageTwo) {
            return strlen($packageTwo->getPackagePath()) - strlen($packageOne->getPackagePath());
        });

        $reflectedClass = new \ReflectionClass($migration);
        $classPathAndFilename = Files::getUnixStylePath($reflectedClass->getFileName());

        /** @var $package PackageInterface */
        foreach ($sortedAvailablePackages as $package) {
            $packagePath = Files::getUnixStylePath($package->getPackagePath());
            if (strpos($classPathAndFilename, $packagePath) === 0) {
                return $package->getPackageKey();
            }
        }

        return '';
    }

}
