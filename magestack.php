<?php

const ENV_FILENAME = 'app/etc/env.php';

const STACKS = [
    'magento' => ['edition' => ['CE', 'EE']],
    'webserver' => ['name' => ['apache2', 'nginx']],
    'database' => ['name' => ['mysql', 'mariadb']],
    'composer',
    'php',
    'redis',
    'elasticsearch',
    'rabbitmq',
    'varnish',
    'memcache',
    'blackfire',
    'newrelic'
];

const STR = [
    'caching' => ['redis', 'varnish', 'memcache'],
    'search_engine' => ['elasticsearch', 'mysql', 'sphinx'],
    'message_queue' => ['rabbitmq', 'mysql'],
];

$conn = null;
$env_vars = require_once(ENV_FILENAME);
$db_info = $env_vars['db'];
$connection = $db_info['connection']['default'];
preg_match_all('/[^:]+/', $connection['host'], $result);
$db_table_prefix = isset($db_info['table_prefix'])? $db_info['table_prefix'] : '';
$db_host = isset($result[0][0])? $result[0][0] : $connection['host'];
$db_port = isset($result[0][1])? $result[0][1] : 3306;
$db_name = $connection['dbname'];
$db_username = $connection['username'];
$db_password = $connection['password'];

function get_db_connection()
{
    if (!$GLOBALS['conn']) {
        $connection_string = sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $GLOBALS['db_host'],
            $GLOBALS['db_port'],
            $GLOBALS['db_name']
        );
        $GLOBALS['conn'] = new PDO($connection_string, $GLOBALS['db_username'], $GLOBALS['db_password']);
    }
    return $GLOBALS['conn'];
}

function command_exists($cmd)
{
    exec("command -v $cmd >/dev/null", $output, $result);
    return $result == 0;
}

/************************************
 * GET INFO SECTION
 *
 */

/**
 * @return array
 */
function get_magento_info()
{
    $base_module_path="vendor/magento/magento2-base/composer.json";
    $base_module_data = json_decode(file_get_contents($base_module_path, true), true);
     
    // exec('php bin/magento --version', $output, $result);
    $name = 'magento';
    $using = true;
    $edition = '';
    $version = isset($base_module_data['version']) ? $base_module_data['version'] : '';
    $extra_info = [];
    $supported_verison = [
        'CE' => 'Community Edition',
        'EE' => 'Enterprise Edition'
    ];

    if (isset($base_module_data['description'])) { 
        foreach ($supported_verison as $k => $v) {
            if (strpos($base_module_data['description'], $v) !== false) {
                $edition = $k;
                break;
            }
        }
    }
    
    // if (0 == $result) {
    //     $extra_info['data']['version'] = $output;
    //     preg_match('/[\S]+$/', $output[0], $matches);
    //     if (count($matches) > 0) {
    //         $version = $matches[0];
    //     } else {
    //         $extra_info['message']['version'] = 'Can\'t parse magento version from CLI output';
    //     }
    // } else {
    //     $extra_info['message']['version'] = 'Can\'t get magento version via CLI';
    // }
    
    // exec('grep -P description composer.json', $e_output, $e_result);
    // if ($e_result == 0) {
    //     $isCE = strpos($e_output[0], 'Community Edition') !== false;
    //     $isEE = strpos($e_output[0], 'Enterprise Edition') !== false;
    //     if ($isCE || $isEE) {
    //         $edition = $isCE? 'CE' : 'EE';
    //     } else {
    //         $extra_info['message']['edition'] = 'Can\'t parse edition from CLI output';
    //     }
    //     $extra_info['data']['edition'] = $e_output;
    // } else {
    //     $extra_info['message']['edition'] = $e_output;
    // }
    
    $success = $version && $edition;
    return [
        'name' => $name,
        'version' => $version,
        'edition' => $edition,
        'using' => $using,
        'success' => $success,
        'extra_info' => $extra_info
    ];
}

function get_webserver_info()
{
    $name = '';
    $version = '';
    $using = true;
    $extra_info = [];
    
    exec('ps -aux | grep -m 1 -Po "(?:[\S]+)?(?:apache2|httpd)(?= \-(?:DFOREGROUND|k))"', $apache_output, $is_apache);
    exec('ps -aux | grep -m 1 -Po "(?<=nginx: master process )[\w\/]+"', $nginx_output, $is_nginx);
    if ($is_apache == 0) {
        $name = 'apache2';
        $run_path = $apache_output[0];
        exec("$run_path -v", $apache_info, $a_code);
        if ($a_code == 0) {
            foreach ($apache_info as $line) {
                if (strpos($line, 'Server version') !== false) {
                    preg_match('/[\d.]+/', $line, $matches);
                    $version = $matches[0];
                    break;
                }
            }
        } else {
            $extra_info['message'] = 'Can\'t get nginx info via CLI';
        }
    } elseif ($is_nginx == 0) {
        $name = 'nginx';
        $run_path = $nginx_output[0];
        exec("$run_path -v 2>&1", $nginx_info, $n_code);
        if ($n_code == 0) {
            foreach ($nginx_info as $line) {
                if (strpos($line, 'nginx version:') !== false) {
                    $version = str_replace('nginx version: nginx/', '', $line);
                    break;
                }
            }
        } else {
            $extra_info['message'] = 'Can\'t get nginx info via CLI';
        }
    } else {
        $extra_info['message'] = 'Can\'t get webserver info via CLI';
    }
    
    
    $success = $name && $version;
    return [
        'name' => $name,
        'version' => $version,
        'using' => $using,
        'success' => $success,
        'extra_info' => $extra_info
    ];
}

function get_database_info()
{
    $name = '';
    $version = '';
    $using = true;
    $success = false;
    $extra_info = [];
    if (command_exists("mysql")) {
        exec("mysql --version", $output, $result);
        if ($result == 0) {
            $isMariaDB = strpos(strtolower($output[0]), 'mariadb') !== false;
            if ($isMariaDB) {
                $name = 'mariadb';
                preg_match('/[\S]+(?=-MariaDB)/', $output[0], $matches);
            } else {
                $name = 'mysql';
                // Example: mysql  Ver 14.14 Distrib 5.7.27, for Linux (x86_64) using  EditLine wrapper
                preg_match_all('/(?<=Distrib|Ver) [\d\.]+/', $output[0], $matches);
                // preg_match('/(?<=Ver )[\S]+/', $output[0], $matches);
            }
            //$version = $matches[0];
            $version = is_array($matches[0]) ? end($matches[0]) : $matches[0];
            $extra_info['data'] = $output;
            $success = true;
        } else {
            $extra_info['message'] = 'Can\'t get database info from CLI';
        }
    } else {
        $conn = get_db_connection();
        $result = $conn->query("SHOW VARIABLES LIKE '%version%'");
        $result = $result->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        foreach ($result as $row) {
            if (isset($row['Variable_name']) && $row['Variable_name'] == 'version_comment') {
                $name = (strpos($row['Value'], 'MySQL') !== false? 'mysql' : 'mariadb');
            }
            if (isset($row['Variable_name']) && $row['Variable_name'] == 'version') {
                $version = $row['Value'];
            }
            $data[] = implode(' ', $row);
        }
        $success = ($name && $version);
        if (!$success) {
            $extra_info['message'] = 'Can\'t get database info from mysql query';
        }
        $extra_info['data'] = implode(PHP_EOL, $data);
    }
    
    $success = $name && $version;
    return [
        'name' => $name,
        'version' => $version,
        'using' => $using,
        'success' => $success,
        'extra_info' => $extra_info
    ];
}

function get_composer_info()
{
    $name = 'composer';
    $version = '';
    $using = true;
    $extra_info = [];
    
    if (command_exists('composer')) {
        exec('composer --version', $output, $result);
        if ($result == 0) {
            preg_match('/(?<=version )[\S]+/', $output[0], $matches);
            $version = $matches[0];
        } else {
            $extra_info['message'] = 'Can\'t parse composer version from CLI output';
        }
        $extra_info['data'] = implode(PHP_EOL, $output);
    } else {
        $using = false;
        $extra_info['message'] = 'Can\'t get composer info from CLI';
    }
    
    $success = !empty($version);
    return [
        'name' => $name,
        'version' => $version,
        'using' => $using,
        'success' => $success,
        'extra_info' => $extra_info
    ];
}

function get_php_info()
{
    $name = 'php';
    $version = PHP_VERSION;
    $extensions = get_loaded_extensions();
    $using = true;
    $success = false;
    $extra_info = ['extensions' => $extensions];
    // exec('php --version', $output, $result);
    // if ($result == 0) {
    //     $extra_info['data'] = implode(PHP_EOL, $output);
    //     $success = true;
    // } else {
    //     $extra_info['message'] = 'Can\'t get php info from CLI';
    // }
    $success = !empty($version);
    return [
        'name' => $name,
        'version' => $version,
        'using' => $using,
        'success' => $success,
        'extra_info' => $extra_info
    ];
}

function get_redis_info()
{
    $name = 'redis';
    $version = '';
    $extra_info = [];
    $using = $GLOBALS['env_vars']['session']['save'] == 'redis' || (bool)array_search('Redis', $GLOBALS['env_vars']['cache']);
    if (command_exists("redis-cli")) {
        exec('redis-cli --version', $output, $result);
        $version = str_replace("redis-cli ", "", $output[0]);
        $extra_info['data'] = implode(PHP_EOL, $output);
    } elseif (command_exists("redis-server")) {
        exec('redis-server --version', $output, $result);
        preg_match('/(?<=v=)[\S]+/', $output[0], $matches);
        $version = $matches[0];
        $extra_info['data'] = implode(PHP_EOL, $output);
    } else {
        $extra_info['message'] = 'Can\'t get redis version from cli';
    }
    
    $success = !empty($version);
    return [
        'name' => $name,
        'version' => $version,
        'using' => $using,
        'success' => $success,
        'extra_info' => $extra_info
    ];
}

function get_elastic_search_info()
{
    $name = 'elasticsearch';
    $version = '';
    $using = (bool) preg_match('/2\.4\./', exec('php bin/magento --version'));;
    $success = false;
    $extra_info = [];
    
    $conn = get_db_connection();
    
    exec('curl -s localhost:9200', $output, $cmd_result);
    if ($cmd_result == 0) {
        $extra_info['data'] = (is_array($output) ? json_decode(implode('', $output), true) : (string) $output);
        $version = $extra_info['data']['version']['number'];
        $success = true;
    }
    
    $query = sprintf(
        'SELECT * FROM %s%s WHERE path="%s"',
        $GLOBALS['db_table_prefix'],
        'core_config_data',
        'catalog/search/engine'
    );
    $result = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    if (count((array)$result) > 0 && $result[0]['value'] != 'mysql') {
        $searchEngine = $result[0]['value'];
        $isElasticSearch = strpos($searchEngine, $name) !== false;
        if ($isElasticSearch) {
            $using = true;
            $query = sprintf(
                'SELECT * FROM %s%s WHERE path LIKE "%s"',
                $GLOBALS['db_table_prefix'],
                'core_config_data',
                'catalog/search/engine'
            );
            $result = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            $extra_info['message'] = "Using '$searchEngine' as Search Engine";
        }
    } else {
        $extra_info['message'] = 'Using mysql as Search Engine';
    }
    $success = !empty($version);
    return [
        'name' => $name,
        'version' => $version,
        'using' => $using,
        'success' => $success,
        'extra_info' => $extra_info
    ];
}

function get_rabbitmq_info()
{
    $name = 'rabbitmq';
    $using = isset($GLOBALS['env_vars']['queue']) && isset($GLOBALS['env_vars']['queue']['amqp']);
    $success = false;
    $version = '';
    $extra_info = '';
    
    if ($using) {
        $amqp = $GLOBALS['env_vars']['queue']['amqp'];
        $host = isset($amqp['host'])? $amqp['host'] : '';
        $port = 15672;
        $user = isset($amqp['user'])? $amqp['user'] : '';
        $password = isset($amqp['password'])? $amqp['password'] : '';
        exec("curl -s -u $user:$password -H 'content-type:application/json' $host:$port/api/overview/", $output, $result);
        $response = json_decode($output[0], true);
        if (isset($response['management_version'])) {
            $version = $response['management_version'];
        } elseif (isset($response['product_version'])) {
            $version = $response['product_version'];
        } elseif (isset($response['rabbitmq_version'])) {
            $version = $response['rabbitmq_version'];
        }
        $extra_info = json_encode($response);
    } elseif (command_exists('rabbitmqctl')) {
        exec('rabbitmqctl version', $output, $result);
        if ($result == 0) {
            //Todo: get rabbitmq version here
            $version = $output[0];
        } else {
            $extra_info = $output;
        }
    }
    $success = !empty($version);
    return [
        'name' => $name,
        'using' => $using,
        'success' => $success,
        'version' => $version,
        'extra_info' => $extra_info
    ];
}

function get_varnish_cache_info()
{
    $name = 'varnish';
    $version = '';
    $using = false;
    $success = false;
    $extra_info = '';
    $conn = get_db_connection();
    $query = sprintf(
        'SELECT * FROM %s%s WHERE path="%s" AND value="%s"',
        $GLOBALS['db_table_prefix'],
        'core_config_data',
        'system/full_page_cache/caching_application',
        2
    );
    $result = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($result) > 0) {
        $using = true;
        if (command_exists("varnishd")) {
            exec('varnishd -V 2>&1', $output, $result);
            preg_match('/(?<=varnish\-)[\S]+/', $output[0], $matches);
            $version = $matches[0];
            $extra_info = $output;
        } elseif (exec("ps -aux | grep -m 1 -Po '(?:[\S]+)?varnishd(?= \-j)'", $output, $v_code)) {
            $varnish = $output[0];
            exec("$varnish -V 2>&1", $output, $result);
            preg_match('/(?<=varnish\-)[\S]+/', $output[0], $matches);
            $version = $matches[0];
            $extra_info = $output;
        } else {
            $extra_info = 'Can\'t get varnish version from cli';
        }
    }
    
    $success = !empty($version);
    return [
        'name' => $name,
        'using' => $using,
        'success' => $success,
        'version' => $version,
        'extra_info' => $extra_info
    ];
}

function get_memcache_info()
{
    $name = 'memcache';
    $using = false;
    $success = false;
    $version = '';
    $extra_info = '';
    exec('php -i | grep memcache', $output, $result);
    
    if ($result == 0) {
        $using = true;
        foreach ($output as $line) {
            if (strpos($line, 'version') !== false) {
                preg_match('/\d[\S]+/', $line, $matches);
                $version = $matches[0];
            }
            if (strpos($line, 'memcache support') !== false) {
                $using = (strpos($line, 'enabled') !== false);
            }
        }
        $extra_info = $output;
    }
    
    $success = !empty($version);
    return [
        'name' => $name,
        'using' => $using,
        'success' => $success,
        'version' => $version,
        'extra_info' => $extra_info
    ];
    
}

function format_output($data) {
    $extra = [];
    foreach ($data as $stack => &$info) {
        if (isset($info['extra_info'])) {
            $extra[$stack] = $info['extra_info'];
            unset($info['extra_info']);
        }
    }
    return [
        'stacks' => $data,
        'extra_info' => $extra
    ];
}

function print_output($stacks) {
    $mask = "|%20.20s |%10.10s |%10.10s |%10.10s |\n";
    echo sprintf($mask, 'name', 'version', 'using', 'success');
    foreach ($stacks as $stack) {
        echo sprintf($mask, $stack['name'], $stack['version'], $stack['using'], $stack['success']);
    }
}

function get_stacks()
{
    $stacks = [
        'magento' => get_magento_info(),
        'webserver' => get_webserver_info(),
        'database' => get_database_info(),
        'composer' => get_composer_info(),
        'php' => get_php_info(),
        'elasticsearch' => get_elastic_search_info(),
        'redis' => get_redis_info(),
        'rabbitmq' => get_rabbitmq_info(),
        'varnish' => get_varnish_cache_info(),
        'memcache' => get_memcache_info()
    ];
    print_output($stacks);
    
    $data = format_output($stacks);
    $data['created_at'] = date('c');
    echo json_encode($data);
}

get_stacks();
