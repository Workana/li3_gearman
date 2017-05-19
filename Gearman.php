<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Mariano Iglesias (http://marianoiglesias.com.ar)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_gearman;

use lithium\aop\Filters;
use lithium\core\Adaptable;
use lithium\core\ConfigException;

class Gearman extends Adaptable
{
    /**
     * Stores configurations for various authentication adapters.
     *
     * @var object `Collection` of authentication configurations.
     */
    protected static $_configurations = [];

    /**
     * Libraries::locate() compatible path to adapters for this class.
     *
     * @see lithium\core\Libraries::locate()
     * @var string Dot-delimited path.
     */
    protected static $_adapters = 'adapter.queue';

    /**
     * Run/schedule a job on a configuration
     *
     * @param string $configName Configuration to use
     * @param string $action Action name (job) to execute
     * @param array $args Arguments to pass to the action
     * @param array $options Extra options (used by adapter)
     * @return mixed Returned value by adapter's run() method
     */
    public static function run($configName, $action, array $args = [], array $options = [])
    {
        $config = static::getConfig($configName);
        $filters = (array) $config['filters'];

        foreach ($filters as $currentFilter) {
            Filters::apply(get_called_class(), __FUNCTION__, $currentFilter);
        }

        $params = compact('action', 'args', 'configName');

        return Filters::run(get_called_class(), __FUNCTION__, $params, function ($params) use ($options) {
            return self::adapter($params['configName'])->run(
                $params['action'],
                $params['args'],
                ['configName' => $params['configName']] + $options
            );
        });
    }

    /**
     * Executes job on a configuration
     *
     * @param string $configName Configuration to use
     * @param string $action Action name (job) to execute
     * @param array $args Arguments to pass to the action
     * @param array $env Settings to merge on $_SERVER
     * @param array $workload Full workload
     * @return mixed Returned value by adapter's execute() method
     */
    public static function execute($configName, $action, array $args, array $env = [], array $workload = [])
    {
        $config = static::getConfig($configName);
        $filters = $config['filters'];
        $params = compact('action', 'args', 'env', 'workload');
        return static::_filter(
            __FUNCTION__,
            $params,
            function ($self, $params) use ($configName) {
                return $self::adapter($configName)->execute(
                    $params['action'],
                    $params['args'],
                    $params['env'],
                    $params['workload']
                );
            },
            $filters
        );
    }

    /**
     * Processes a scheduled job
     *
     * @param string $configName Configuration to use
     * @return mixed Returned value by adapter's scheduled() method
     */
    public static function scheduled($configName)
    {
        $config = static::getConfig($configName);
        $filters = $config['filters'];
        return static::_filter(
            __FUNCTION__,
            [],
            function ($self, $params) use ($configName) {
                return $self::adapter($configName)->scheduled();
            },
            $filters
        );
    }

    /**
     * Gets the given config, checking for validity
     *
     * @param string $name Configuration name
     * @return array Configuration
     */
    public static function getConfig($name)
    {
        if (($config = static::_config($name)) === null) {
            throw new ConfigException("Configuration {$config} has not been defined.");
        } elseif (!is_array($config)) {
            throw new ConfigException('Invalid configuration: not an array');
        } elseif (empty($config['servers'])) {
            throw new ConfigException('No servers defined. Add them to the "servers" setting');
        }
        return $config;
    }

    /**
     * Initializes configuration with default settings
     *
     * @param string $name The name of the configuration which is being accessed. This is the key
     *               name containing the specific set of configuration passed into `config()`.
     * @param array $config Contains the configuration assigned to `$name`. If this configuration is
     *              segregated by environment, then this will contain the configuration for the
     *              current environment.
     * @return array Returns the final array of settings for the given named configuration.
     */
    protected static function _initConfig($name, $config)
    {
        $defaults = [
            'filters' => [],
            'servers' => []
        ];
        $config = parent::_initConfig($name, $config) + $defaults;
        foreach (['filters', 'servers'] as $arrayVar) {
            if (!empty($config[$arrayVar]) && !is_array($config[$arrayVar])) {
                $config[$arrayVar] = (array) $config[$arrayVar];
            } elseif (empty($config[$arrayVar])) {
                $config[$arrayVar] = [];
            }
        }
        if (empty($config['adapter'])) {
            $config['adapter'] = 'Job';
        }
        return $config;
    }

    /**
     * Executed through the Gearmand shell for testing that a worker is alive
     */
    public static function ping($scheduled = false)
    {
        if ($scheduled) {
            echo "[Scheduled PING] OK\n";
            return;
        }
        return 'OK';
    }
}