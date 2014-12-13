<?php

abstract class QcPackAbstract
{
    protected $_boundary;

    protected function _toHeaderBoundaryLine()
    {
        return sprintf('-X-%s-X-', $this->_boundary);
    }

    protected function _toBoundaryLine()
    {
        return '--' . $this->_boundary;
    }
}

class QcPackEncode extends QcPackAbstract
{
    protected $_output;

    public function __construct($boundary = null)
    {
        $this->_boundary = md5(microtime());
    }

    public function addFile($fileAbsolutePath, $fileRelativePath, $offerId)
    {
        $this->_output .= $this->_toBoundaryLine() . PHP_EOL;
        $this->_output .= 'X-QC-HASH:' . md5_file($fileAbsolutePath) . PHP_EOL;
        $this->_output .= 'X-QC-FILEPATH:' . $fileRelativePath . PHP_EOL;
        $this->_output .= 'X-QC-OFFER_ID:' . intval($offerId) . PHP_EOL;

        /**
         * Отделяем заголовки от тела
         */
        $this->_output .= $this->_toHeaderBoundaryLine() . PHP_EOL;

        $content = file_get_contents($fileAbsolutePath);
        $this->_output .= base64_encode($content) . PHP_EOL;
    }

    public function getResult()
    {
        return 'X-QC-BOUNDARY: ' . $this->_boundary . PHP_EOL
        . PHP_EOL
        . $this->_output . PHP_EOL
        . $this->_toBoundaryLine() . PHP_EOL
        . PHP_EOL;
    }
}

class QcPackDecode extends QcPackAbstract
{
    protected $_path;

    public function __construct($resourceAbsolutePath)
    {
        $this->_path = realpath($resourceAbsolutePath);
    }

    function decode($text)
    {
        if (false === ($p = strpos($text, 'X-QC-BOUNDARY:'))) {
            return false;
        }

        $start = $p + 14;
        $end = strpos($text, "\n", $p);
        $this->_boundary = substr($text, $start, $end - $start);
        $this->_boundary = trim($this->_boundary);

        if (empty($this->_boundary) || 32 != strlen($this->_boundary)) {
            return false;
        }


        $start = $end;
        if (false === ($p = strpos($text, $this->_toBoundaryLine() . "\n", $start))) {
            return false;
        }

        $start = $p + strlen($this->_toBoundaryLine());
        while (false !== ($p = strpos($text, $this->_toBoundaryLine() . "\n", $start))) {
            $content = substr($text, $start, $p - $start);

            /**
             * @todo Нужна ли валидация?
             */
            $this->_toFile($content);
            $start = $p + strlen($this->_toBoundaryLine());
        }

        if (false === ($p = strpos($text, $this->_toBoundaryLine(), $start))) {

            /**
             * Невалидные файлы. Не найден последний разделитель
             */
            return false;
        }

        $content = substr($text, $start, $p - $start);

        /**
         * @todo Нужна ли валидация?
         */
        $this->_toFile($content);
        return true;
    }

    protected function _toFile($content)
    {
        $content = trim($content);

        $bn = $this->_toHeaderBoundaryLine();
        if (false === ($p = strpos($content, $bn))) {
            return false;
        }

        $params = array(
            'hash'     => false,
            'filepath' => false,
            'offer_id' => false
        );

        $hlist = trim(substr($content, 0, $p));
        foreach(explode("\n", $hlist) as $hline) {

            list($name, $value) = explode(':', $hline);
            $name = trim($name);
            $value = trim($value);

            if (0 !== strpos($name, 'X-QC-')) {
                continue;
            }

            $name = substr($name, 5);
            $name = strtolower($name);
            if (array_key_exists($name, $params)) {
                $params[$name] = $value;
            }
        }

        foreach($params as $value) {
            if (empty($value)) {
                return false;
            }
        }

        $content = substr($content, $p + strlen($bn));
        $content = trim($content);

        if (false === ($file = base64_decode($content))) {
            return false;
        }

        if (0 !== strcasecmp(md5($file), $params['hash'])) {
            return false;
        }

        $fileAbsolutePath = $this->_path . '/' . intval($params['offer_id']) . '/' . $params['filepath'];
        $dirAbsolutePath = dirname($fileAbsolutePath);
        if (!file_exists($dirAbsolutePath) || !is_dir($dirAbsolutePath)) {
            mkdir($dirAbsolutePath, 0775, true);
        }

        $oResult = (boolean) file_put_contents($fileAbsolutePath, $file);

        return $oResult;
    }
}