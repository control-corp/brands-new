<?php

namespace Micro\Application;

class Config
{
    protected static $config = [];

    public function __construct($data = \null)
    {
        if ($data !== \null) {
            $this->load($data);
        }
    }

    public function load(array $data)
    {
        static::$config = \array_replace_recursive(static::$config, $data);

        return $this;
    }

    public function get($prop = \null, $default = \null)
    {
        $config = static::$config;

        if ($prop !== \null && \is_string($prop)) {
            foreach (explode('.', $prop) as $key) {
                if (!isset($config[$key])) {
                    return $default;
                }
                $config = &$config[$key];
            }
        }

        return $config;
    }
}