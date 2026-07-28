<?php

namespace Swisscom\CommandMigration;

use Neos\Flow\Annotations as Flow;

/**
 * Utility class for migrations
 */
class MigrationUtility
{
    /**
     * Returns the version of a migration object, e.g. '20120126163610'.
     * This function is copied from \Neos\Flow\Core\Migrations\AbstractMigration
     *
     * @param object $object The migration class object
     * @return string
     */
    public static function getVersionNumber(object $object): string
    {
        $className = get_class($object);
        $lastStrPos = strrchr($className, 'Version');
        if ($lastStrPos === false) {
            throw new \Exception(sprintf('Migration classname %s did not match pattern "Version[__Timestamp__]"', $className));
        }
        return substr($lastStrPos, 7);
    }

    /**
     * Returns the first line of the class doc comment
     * This function is copied from \Neos\Flow\Core\Migrations\AbstractMigration
     *
     * @param object $object The migration class object
     * @return string
     */
    public static function getDescription(object $object): string
    {
        $reflectionClass = new \ReflectionClass($object);
        $lines = explode(chr(10), $reflectionClass->getDocComment() ?: '');
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === '/**' || $line === '*' || $line === '*/' || strpos($line, '* @') !== false) {
                continue;
            }
            return preg_replace('/\s*\\/?[\\\\*]*\s?(.*)$/', '$1', $line) ?? '';
        }
        return '';
    }

    /**
     * Returns a formatted version string
     *
     * @param string $version
     * @return string
     */
    public static function getFormattedVersion(string $version): string
    {
        if ($version === '') {
            return '<comment>0</comment>';
        }

        return self::getDateTime($version) . ' (<comment>' . $version . '</comment>)';
    }

    /**
     * Returns the datetime of a version
     *
     * @param string $version
     * @return string
     */
    public static function getDateTime(string $version): string
    {
        if ($datetime = \DateTime::createFromFormat('YmdHis', $version)) {
            return $datetime->format('Y-m-d H:i:s');
        }

        return '';
    }
}
