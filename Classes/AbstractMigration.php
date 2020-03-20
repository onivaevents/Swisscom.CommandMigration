<?php
namespace Swisscom\CommandMigration;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Core\Booting\Scripts;

/**
 * The base class for command migrations.
 */
abstract class AbstractMigration
{

    /**
     * @var array
     */
    protected $commands = [];

    /**
     * Anything that needs to be done in the migration needs to go into this method.
     *
     * @return void
     * @api
     */
    abstract public function up(): void;

    /**
     * Execute the commands for this migration
     *
     * @param array $settings The Neos.Flow settings
     * @param bool $outputResults If false the output of this command is only echoed if the execution was not successful
     */
    public function execute($settings, bool $outputResults = true): void
    {
        foreach ($this->commands as $command) {
            Scripts::executeCommand($command['identifier'], $settings, $outputResults, $command['arguments']);
        }
    }

    /**
     * Return the commands that are executed for this migration as output string
     *
     * @return string
     */
    public function dryRun(): string
    {
        $result = '';
        foreach ($this->commands as $command) {
            $argumentString = implode(', ', array_map(
                function ($v, $k) {
                    $format = is_string($v) ? "%s='%s'" : '%s=%s';
                    return sprintf($format, $k, $v);
                },
                $command['arguments'],
                array_keys($command['arguments'])
            ));

            $result = './flow ' . $command['identifier'] . ' ' . $argumentString . PHP_EOL;
        }

        return $result;
    }

    /**
     * Add a command to be executed during the migration
     *
     * @param string $commandIdentifier Command identifier, i.e. neos.flow:cache:flush
     * @param array $commandArguments The arguments for the given command
     */
    protected function addCommand(string $commandIdentifier, array $commandArguments = []): void
    {
        $this->commands[] = [
            'identifier' => $commandIdentifier,
            'arguments' => $commandArguments,
        ];
    }

}
