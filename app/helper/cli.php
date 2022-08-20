<?php

namespace Helper;

class Cli extends \Prefab
{
    /**
     * Parse CLI options with defaults
     *
     * Also shows argument help if "-h" or "--help" is passed, and exits
     * with a relevant message if required arguments are not specified.
     *
     * @param array $options Associative array, argument keys => default values
     * @param array $argv
     * @return array|null
     */
    public function parseOptions(array $options, ?array $argv = null): ?array
    {
        $keys = array_keys($options);
        if ($argv === null) {
            $argv = $_SERVER['argv'];
        }

        // Show argument help
        if (getopt('h', ['help']) || (is_countable($argv) ? count($argv) : 0) == 1) {
            return $this->showHelp($keys, $options);
        }

        // Parse options
        $data = getopt('', $keys);

        // Check that required options are set
        foreach ($keys as $key) {
            if (substr_count($key, ':') != 1) {
                continue;
            }
            $o = rtrim($key, ':');
            if (!array_key_exists($o, $data)) {
                echo "Required argument --$o not specified.", PHP_EOL;
                exit(1);
            }
        }

        // Fill result with defaults
        $result = [];
        foreach ($keys as $o) {
            $key = rtrim($o, ':');
            $result[$key] = $data[$key] ?? $options[$o];
        }
        return $result;
    }

    /**
     * Output help message showing parseOptions() options and defaults
     */
    protected function showHelp(array $options, array $defaultMap): void
    {
        $required = [];
        $optional = [];
        $flags = [];
        foreach ($options as $o) {
            $colons = substr_count($o, ':');
            if ($colons == 2) {
                $optional[] = $o;
            } elseif ($colons == 1) {
                $required[] = $o;
            } else {
                $flags[] = $o;
            }
        }

        echo 'Required values:', PHP_EOL;
        foreach ($required as $key) {
            $o = rtrim($key, ':');
            echo "--$o={$defaultMap[$key]}", PHP_EOL;
        }
        echo PHP_EOL;

        echo 'Optional values:', PHP_EOL;
        foreach ($optional as $key) {
            $o = rtrim($key, ':');
            echo "--$o={$defaultMap[$key]}", PHP_EOL;
        }
        echo PHP_EOL;

        echo 'Optional flags:', PHP_EOL;
        foreach ($flags as $key) {
            $o = rtrim($key, ':');
            echo "--$o", PHP_EOL;
        }
        echo PHP_EOL;
    }
}
