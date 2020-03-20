# Swisscom.CommandMigration

Neos Flow package for framework based CLI command migrations.

Command migrations allow to create migration scripts similar to Doctrine migrations to execute Flow CLI commands when executing the migration by the command `./flow commandmigration:migrate`.

This solves the problem when executing one-off commands on distributed environments as well as staging environments. The migration command might become part of your deployment strategy as well as the `./flow doctrine:migrate` command probably is already.


## Usage

### Create migrations

To create a migration inherit from the `AbstractMigration` with the naming convention `VersionYmdHis` and implent the `up()` method. The class has to be within the namespace `Swisscom\CommandMigration` and be stored in your package under `Migrations/Command/`.

Example migration `Packages/Your.Package/Migrations/Command/Version20200220114245.php`:

    <?php
    namespace Swisscom\CommandMigration;
    
    /**
     * A test migration
     */
    class Version20200220114245 extends AbstractMigration
    {
    
        /**
         * @return void
         */
        public function up(): void
        {
            $this->addCommand('your:command', ['test' => true]);
        }
    }


### Executing commands

The provided commands follow the naming of the familiar Doctrine commands and work in likewise manner:

| Command identifier                | Description                           |
|-----------------------------------|---------------------------------------|
| commandmigration:migrate          | Execute the pending migrations        |
| commandmigration:migrationexecute | Execute a single migration            |
| commandmigration:migrationstatus  | Show the current migration status     |
| commandmigration:migrationversion | Mark/unmark migrations as migrated    |


## Notes

The package is highly inspired by the Flow Doctrine migrations and the Flow code migrations. Some logic, semantics as well as code is borrowed from those core modules. 
