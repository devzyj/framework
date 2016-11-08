<?php
/**
 * Kerisy Framework
 *
 * PHP Version 7
 *
 * @author          kaihui.wang <hpuwang@gmail.com>
 * @copyright      (c) 2015 putao.com, Inc.
 * @package         kerisy/framework
 * @version         3.0.0
 */

namespace Kerisy\Foundation\Storage;

use Kerisy\Config\Config;
use Kerisy\Foundation\Exception\ConfigNotFoundException;
use Kerisy\Support\Log;
use Predis\Client;
use Kerisy\Support\Exception as SupportException;

class Redis
{
    protected static $client = null;
    protected static $lock = 0;

    protected static $conn = null;

    public function __construct()
    {
        $this->initializeDefault();
    }
    
    protected function initializeDefault()
    {
        if(self::$conn) return ;
        $config = Config::get("storage.redis");
        $servers = $config['servers'];
        if(!$servers) throw new ConfigNotFoundException("storage.redis.servers not config");
        $options = $config['options'];
        try {
            self::$conn = new Client($servers, $options);
        } catch (\Exception $e) {
            Log::error(SupportException::formatException($e));
        }catch (\Error $e) {
            Log::error(SupportException::formatException($e));
        }
    }

    public function __call($name, $arguments)
    {
        try {
            if ($arguments) {
                $result = self::$conn->$name(...$arguments);
            } else {
                $result = self::$conn->$name();
            }
            return $result;
        } catch (\Exception $e) {
            Log::error(SupportException::formatException($e));
        }catch (\Error $e) {
            Log::error(SupportException::formatException($e));
        }
    }

    public function __destruct()
    {
    }
}