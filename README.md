# Swisscom.CommandMigration

Neos Flow package for framework based CLI command migrations.

The package allows to create migration scripts similar to Doctrine migrations. Contrary to executing SQL statements, it allows you to define versions with sets of Flow CLI commands. Those are executed when running the migration by the command `./flow commandmigration:migrate`.

This mainly solves the problem of executing one-off commands on distributed environments as well as different staging environments. Without this package, those one-off commands are possibly executed manually when needed, or are part of some other scripts in your deployment process. With this package, this becomes part of the code base. For integration, the migration command might be added to your deployment strategy the same way as the `./flow doctrine:migrate` command probably already is.


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
