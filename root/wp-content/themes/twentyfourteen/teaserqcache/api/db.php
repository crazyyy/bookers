<?php

final class QcDb
{
    const MODE_DEFAULT = 1;
    const MODE_SPLIT = 2;

    private static $__cacheRootPath;

    private $_mode = self::MODE_DEFAULT;

    const INSTANCE_DEFAULT = 'DEFAULT';

    protected $_filecache;

    protected $_data = array();

    private static $__instances = array();

    protected $_name;

    /**
     * @param string $name
     * @param bool $createOnMissingCache
     * @throws Exception
     * @return QcDb
     */
    public static function getInstance($name, $createOnMissingCache)
    {
        if (!array_key_exists($name, self::$__instances)) {
            self::$__instances[$name] = new self($name, self::MODE_DEFAULT, $createOnMissingCache);
        }

        return self::$__instances[$name];
    }

    /**
     * @param $name
     * @param $id
     * @param bool $createOnMissingCache
     *
     * @return QcDb
     */
    public static function getInstanceSplit($name, $id, $createOnMissingCache)
    {
        $name = sprintf('%s-%s', $name, $id);

        if (!array_key_exists($name, self::$__instances)) {
            self::$__instances[$name] = new self($name, self::MODE_SPLIT, $createOnMissingCache);
        }

        return self::$__instances[$name];
    }

    private function __construct($name, $mode, $createOnMissingCache)
    {
        $this->_name = $name;

        $this->_filecache = self::$__cacheRootPath . DIRECTORY_SEPARATOR . strtolower(basename($name)) . '.cache';
        $this->_mode = $mode;

        if (!$isFileExists = file_exists($this->_filecache)) {
            if (false == $createOnMissingCache) {
                return;
            }

            $isFileExists = touch($this->_filecache);
        }

        if (!$isFileExists || !is_writeable($this->_filecache)) {
            throw new Exception('Нет прав для записи базы: невозможно отредактировать базу');
        }

        $content = file_get_contents($this->_filecache);
        if (!empty($content)) {
            $this->_data = @ unserialize($content);

            if (!is_array($this->_data)) {
                $this->_data = array();
            }
        }

        self::$__instances[$name] = $this;
    }

    protected function _toEngine()
    {
        if ($this->_mode == self::MODE_DEFAULT) {
            return $this;
        }

        return $this;
    }

    public static function setCacheRootPath($path)
    {
        $path = realpath($path);
        if (
            false === $path
            || !is_dir($path)
            || !is_writeable($path)
        ) {
            return false;
        }

        self::$__cacheRootPath = $path;
        return true;
    }

    public function & toValue()
    {
        return $this->_data;
    }

    public function toValueCondition($name)
    {
        $oResult = null;
        return array_key_exists($name, $this->_data) ? $this->_data[$name] : $oResult;
    }

    public function & findWithCondition($resource, $id)
    {
        $oResult = null;
        if (!array_key_exists($resource, $this->_data)) {
            return $oResult;
        }

        if (!is_string($id) && !is_integer($id)) {
            return $oResult;
        }

        return array_key_exists($id, $this->_data[$resource]) ? $this->_data[$resource][$id] : $oResult;
    }

    /**
     * @param $resource
     *
     * @return array|bool
     */
    public function & fetchAllWithCondition($resource)
    {
        if (!array_key_exists($resource, $this->_data)) {
            $oResult = array();
            return $oResult;
        }

        return $this->_data[$resource];
    }

    public function refresh($data)
    {
        if (!is_array($data)) {
            return false;
        }

        /**
         * @todo Add validation
         */
        $oResult = file_put_contents($this->_filecache, serialize($data));
        if (false !== $oResult) {
            $this->_data = $data;
        }

        return (boolean) $oResult;
    }

    public function setWithCondition($name, $value)
    {
        $this->_data[$name] = $value;
    }

    public function save()
    {
        return $this->refresh($this->_data);
    }

    public function merge($resource, array $data, $mergeModePk = true)
    {
        $rowset = $this->fetchAllWithCondition($resource);
        if (empty($rowset) || !is_array($rowset)) {
            $this->setWithCondition($resource, $data);
        } elseif (false === $mergeModePk) {
            $this->setWithCondition($resource, array_unique(array_merge($rowset, $data)));
        } else {
            foreach($data as $id => $v) {
                if (null === $v) {
                    unset($rowset[$id]);
                    continue;
                }

                $rowset[$id] = $v;
            }
            $this->setWithCondition($resource, $rowset);
        }

        return $this;
    }

    public function getModifiedTimestamp()
    {
        return filemtime($this->_filecache);
    }

    public function isExpiredDb($seconds = 300)
    {
        if (empty($seconds)) {
            return false;
        }

        return $this->getModifiedTimestamp() + $seconds < time();
    }

    public function isBlank()
    {
        return empty($this->_data);
    }

    public function dropDb()
    {
        unlink($this->_filecache);
    }

    public function disconnect()
    {
        unset(self::$__instances[$this->_name]);
    }
}