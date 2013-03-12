<?php
/**
 * Thumbnailer
 * PHP Version 5
 */

// {{{ Thumbnailer

/**
 * Image manipulation abstraction.
 */
abstract class Thumbnailer
{
    // {{{ properties

    protected $_bgcolor = array(0, 0, 0, 255);
    protected $_http = false;
    protected $_png = false;
    protected $_quality = 70;
    protected $_resampling = true;
    protected $_rotation = 0;
    protected $_tempDir = '/tmp';
    protected $_trimming = false;
    protected $_decorate_anigif = false;
    protected $_decorate_anigif_filepath = '';
    protected $_decorate_gifcaution = false;
    protected $_decorate_gifcaution_filepath = '';

    // }}}
    // {{{ getBgColor()

    /**
     * Gets background color.
     *
     * @return array
     */
    public function getBgColor()
    {
        return $this->_bgcolor;
    }

    // }}}
    // {{{ isHttp()

    /**
     * Gets whether to output HTTP headers.
     *
     * @return bool
     */
    public function isHttp()
    {
        return $this->_http;
    }

    // }}}
    // {{{ isPng()

    /**
     * Gets whether to save as png or jpeg.
     *
     * @return bool
     */
    public function isPng()
    {
        return $this->_png;
    }

    // }}}
    // {{{ getQuality()

    /**
     * Gets quality.
     *
     * @return int
     */
    public function getQuality()
    {
        return $this->_quality;
    }

    // }}}
    // {{{ doesResampling()

    /**
     * Gets whether to reample or not.
     *
     * @return bool
     */
    public function doesResampling()
    {
        return $this->_resampling;
    }

    // }}}
    // {{{ getRotation()

    /**
     * Gets rotation.
     *
     * @return int
     */
    public function getRotation()
    {
        return $this->_rotation;
    }

    // }}}
    // {{{ getTempDir()

    /**
     * Gets temporaty directory.
     *
     * @return string
     */
    public function getTempDir()
    {
        return $this->_tempDir;
    }

    // }}}
    // {{{ doesTrimming()

    /**
     * Gets whether to trim or not.
     *
     * @return bool
     */
    public function doesTrimming()
    {
        return $this->_trimming;
    }

    // }}}
    // {{{ setBgColor()

    /**
     * Sets background color.
     *
     * @param int $r
     * @param int $g
     * @param int $b
     * @param int $a (optional)
     * @return void
     */
    public function setBgColor($r, $g, $b, $a = 255)
    {
        $this->_bgcolor = array($r, $g, $b, $a);
    }

    // }}}
    // {{{ setHttp()

    /**
     * Sets whether to output HTTP headers.
     *
     * @param bool $http
     * @return void
     */
    public function setHttp($http)
    {
        $this->_http = $http;
    }

    // }}}
    // {{{ setPng()

    /**
     * Sets whether to save as png or jpeg.
     *
     * @param bool $png
     * @return void
     */
    public function setPng($png)
    {
        $this->_png = $png;
    }

    // }}}
    // {{{ setQuality()

    /**
     * Sets quality.
     *
     * @param int $quality
     * @return void
     */
    public function setQuality($quality)
    {
        $this->_quality = $quality;
    }

    // }}}
    // {{{ setResampling()

    /**
     * Sets whether to reample or not.
     *
     * @param bool $resample
     * @return void
     */
    public function setResampling($resample)
    {
        $this->_resampling = $resample;
    }

    // }}}
    // {{{ setRotation()

    /**
     * Sets rotation.
     *
     * @param int $degrees
     * @return void
     */
    public function setRotation($degrees)
    {
        $this->_rotation = (int)$degrees % 360;
    }

    // }}}
    // {{{ setTempDir()

    /**
     * Sets temporaty directory.
     *
     * @param string $dir
     * @return void
     */
    public function setTempDir($dir)
    {
        if (is_dir($dir)) {
            $this->_tempDir = realpath($dir);
        }
    }

    // }}}
    // {{{ setTrimming()

    /**
     * Sets whether to trim or not.
     *
     * @param bool $trim
     * @return void
     */
    public function setTrimming($trim)
    {
        $this->_trimming = $trim;
    }

    // }}}
    // {{{ isDecorateAnigif()

    /**
     * Gets whether to decorate when animated gif.
     *
     * @return bool
     */
    public function isDecorateAnigif()
    {
        return $this->_decorate_anigif;
    }

    // }}}
    // {{{ setDecorateAnigif()

    /**
     * Sets whether to decorate when animated gif.
     *
     * @param bool $decorate_anigif
     * @return void
     */
    public function setDecorateAnigif($decorate_anigif)
    {
        $this->_decorate_anigif = $decorate_anigif;
    }

    // }}}
    // {{{ getDecorateAnigifFilePath()

    /**
     * Gets overlay filepath when animated gif.
     *
     * @return string
     */
    public function getDecorateAnigifFilePath()
    {
        return $this->_decorate_anigif_filepath;
    }

    // }}}
    // {{{ setDecorateAnigifFilePath()

    /**
     * Sets overlay filepath when animated gif.
     *
     * @param bool $decorate_anigif_filepath
     * @return void
     */
    public function setDecorateAnigifFilePath($decorate_anigif_filepath)
    {
        $this->_decorate_anigif_filepath = $decorate_anigif_filepath;
    }

    // }}}
    // {{{ isDecorateGifCaution()

    /**
     * Gets whether to decorate when gif caution.
     *
     * @return bool
     */
    public function isDecorateGifCaution()
    {
        return $this->_decorate_gifcaution;
    }

    // }}}
    // {{{ setDecorateGifCaution()

    /**
     * Sets whether to decorate when gif caution.
     *
     * @param bool $decorate_gifcaution
     * @return void
     */
    public function setDecorateGifCaution($decorate_gifcaution)
    {
        $this->_decorate_gifcaution = $decorate_gifcaution;
    }

    // }}}
    // {{{ getDecorateGifCautionFilePath()

    /**
     * Gets overlay filepath when gif caution.
     *
     * @return string
     */
    public function getDecorateGifCautionFilePath()
    {
        return $this->_decorate_gifcaution_filepath;
    }

    // }}}
    // {{{ setDecorateGifCautionFilePath()

    /**
     * Sets overlay filepath when gif caution.
     *
     * @param bool $decorate_gifcaution_filepath
     * @return void
     */
    public function setDecorateGifCautionFilePath($decorate_gifcaution_filepath)
    {
        $this->_decorate_gifcaution_filepath = $decorate_gifcaution_filepath;
    }

    // }}}
    // {{{ save()

    /**
     * Convert and save.
     *
     * @param string $source
     * @param string $thumbnail
     * @param array $size
     * @return boolean
     * @throws PEAR_Error
     */
    abstract public function save($source, $thumbnail, $size);

    /**
     * Convert and capture.
     *
     * @param string $source
     * @param array $size
     * @return boolean
     * @throws PEAR_Error
     */
    abstract public function capture($source, $size);

    /**
     * Convert and output.
     *
     * @param string $source
     * @param string $name
     * @param array $size
     * @return boolean
     * @throws PEAR_Error
     */
    abstract public function output($source, $name, $size);

    // }}}
    // {{{ _convert()

    /**
     * Image conversion abstraction.
     *
     * @param string $source
     * @param array $size
     * @return mixed
     */
    abstract protected function _convert($source, $size);

    // }}}
    // {{{ _tempnam()

    /**
     * Creates temporary file name which will be removed on shutdown.
     *
     * @return string
     */
    protected function _tempnam()
    {
        $tmp = tempnam($this->_tempDir, 'thumb_temp_');
        register_shutdown_function('Thumbnailer::removeTemporaryFile', $tmp);
        return $tmp;
    }

    // }}}
    // {{{ _httpHeader()

    /**
     * Outputs HTTP header.
     *
     * @param string $name
     * @param int $length
     * @return void
     */
    protected function _httpHeader($name = null, $length = null)
    {
        if ($this->isHttp()) {
            $mimetype = 'image/' . (($this->_png) ? 'png' : 'jpeg');
            if ($name) {
                $name = 'filename="' . basename($name) . '"';
                header('Content-Type: ' . $mimetype . '; ' . $name);
                header('Content-Disposition: inline; ' . $name);
            } else {
                header('Content-Type: ' . $mimetype);
                header('Content-Disposition: inline');
            }
            if ($length) {
                header(sprintf('Content-Length: %d', $length));
            }
        }
    }

    // }}}
    // {{{ removeTemporaryFile()

    /**
     * Removes the temporary file.
     *
     * @param string $filename
     * @return void
     */
    static public function removeTemporaryFile($filename)
    {
        if (file_exists($filename)) {
            @unlink($filename);
        }
    }

    // }}
    // {{{ decorate()

    /**
     * process of decorates thumbnail.
     *
     * @param string $source
     * @param mixed $thumb
     * @return mixed
     */
    protected function decorate($source, $thumb)
    {
        // decorate for animation GIF
        if ($this->isDecorateAnigif()) {
            $thumb = $this->_decorateAnimationGif($thumb);
        }
        // decorate for gif caution
        if ($this->isDecorateGifCaution()) {
            $thumb = $this->_decorateGifCaution($thumb);
        }
        return $thumb;
    }

    // }}}
    // {{{ _decorateAnimationGif()

    /**
     * stamp animation gif mark.
     *
     * @param mixed $thumb
     * @return mixed
     */
    abstract protected function _decorateAnimationGif($thumb);

    // }}}
    // {{{ _decorateGifCaution()

    /**
     * stamp gif caution mark.
     *
     * @param mixed $thumb
     * @return mixed
     */
    abstract protected function _decorateGifCaution($thumb);

    // }}}
}

// }}}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
