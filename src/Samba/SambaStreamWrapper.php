<?php

namespace Samba;

class SambaStreamWrapper
{
    const PROTOCOL = 'smb';

    /**
     * @var SambaClient
     */
    protected $client;

    /**
     * @var array
     */
    protected $dir_cache = array();

    /**
     * @var resource
     */
    protected $stream;

    /**
     * @var SambaUrl
     */
    protected $url;

    /**
     * @var string
     */
    protected $mode;

    /**
     * @var string
     */
    protected $tmpfile;

    /**
     * @var bool
     */
    protected $need_flush = false;

    /**
     * @var array
     */
    public $dir = array();

    /**
     * @var int
     */
    protected $dir_index = -1;

    public function __construct(SambaClient $client = null)
    {
        if ($client) {
            $this->setClient($client);
        }
    }

    /**
     * @param SambaClient $client
     */
    public function setClient(SambaClient $client)
    {
        $this->client = $client;
    }

    /**
     * @return SambaClient
     */
    public function getClient()
    {
        if (null === $this->client) {
            $this->client = new SambaClient();
        }
        return $this->client;
    }

    /**
     * @param string $path
     * @param int $options
     * @return bool
     */
    public function dir_opendir($path, $options)
    {
        if ($d = $this->get_dir_cache($path)) {
            $this->dir = $d;
            $this->dir_index = 0;

            return true;
        }
        $purl = $this->getClient()->parseUrl($path);
        switch ($purl->getType()) {
            case SambaUrl::TYPE_HOST:
                if ($o = $this->getClient()->look($purl)) {
                    $this->dir = $o['disk'];
                    $this->dir_index = 0;
                } else {
                    throw new SambaException("dir_opendir(): list failed for host '{$purl->getHost()}'");
                }
                break;
            case SambaUrl::TYPE_SHARE:
            case SambaUrl::TYPE_PATH:
                if ($o = $this->getClient()->dir($purl)) {
                    $this->dir = array_keys($o['info']);
                    $this->dir_index = 0;
                    $this->add_dir_cache($path, $this->dir);
                } else {
                    $this->dir = array();
                    $this->dir_index = 0;
                }
                break;
            default:
                throw new SambaException('dir_opendir(): error in URL');
        }

        return true;
    }

    /**
     * @return string
     */
    public function dir_readdir()
    {
        return ($this->dir_index < count($this->dir)) ? $this->dir[$this->dir_index++] : false;
    }

    /**
     * @return bool
     */
    public function dir_rewinddir()
    {
        $this->dir_index = 0;
    }

    /**
     * @return bool
     */
    public function dir_closedir()
    {
        $this->dir = array();
        $this->dir_index = -1;

        return true;
    }

    /**
     * @param string $path
     * @param string $content
     * @return string
     */
    protected function add_dir_cache($path, $content)
    {
        return $this->dir_cache[$path] = $content;
    }

    /**
     * @param string $path
     * @return bool
     */
    protected function get_dir_cache($path)
    {
        return isset($this->dir_cache[$path]) ? $this->dir_cache[$path] : false;
    }

    protected function clear_dir_cache()
    {
        $this->dir_cache = array();
    }

    /**
     * @param string $url
     * @param string $mode
     * @param int $options
     * @param string $opened_path
     * @return bool
     */
    public function stream_open($url, $mode, $options, &$opened_path)
    {
        $this->mode = $mode;
        $this->url = $purl = $this->getClient()->parseUrl($url);
        if (!$purl->isPath()) {
            throw new SambaException('stream_open(): error in URL');
        }
        switch ($mode) {
            case 'r':
            case 'r+':
            case 'rb':
            case 'a':
            case 'a+':
                $this->tmpfile = tempnam('/tmp', 'smb.down.');
                $this->getClient()->get($purl, $this->tmpfile);
                break;
            case 'w':
            case 'w+':
            case 'wb':
            case 'x':
            case 'x+':
                $this->clear_dir_cache();
                $this->tmpfile = tempnam('/tmp', 'smb.up.');
        }
        $this->stream = fopen($this->tmpfile, $mode);

        return true;
    }

    public function stream_close()
    {
        fclose($this->stream);
    }

    /**
     * @param int $count
     * @return string
     */
    public function stream_read($count)
    {
        return fread($this->stream, $count);
    }

    /**
     * @param string $data
     * @return int
     */
    public function stream_write($data)
    {
        $this->need_flush = true;

        return fwrite($this->stream, $data);
    }

    /**
     * @return bool
     */
    public function stream_eof()
    {
        return feof($this->stream);
    }

    /**
     * @return int
     */
    public function stream_tell()
    {
        return ftell($this->stream);
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return int
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->stream, $offset, $whence);
    }

    /**
     * @return bool
     */
    public function stream_flush()
    {
        if ($this->mode != 'r' && $this->need_flush) {
            $this->getClient()->put($this->url, $this->tmpfile);
            $this->need_flush = false;
        }
        return true;
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        return $this->url_stat($this->url->getUrl());
    }

    /**
     * @param string $path
     * @return array
     */
    public function unlink($path)
    {
        $url = $this->getClient()->parseUrl($path);
        return $this->getClient()->del($url);
    }

    /**
     * @param string $path_from
     * @param string $path_to
     * @return array
     */
    public function rename($path_from, $path_to)
    {
        $url_from = $this->getClient()->parseUrl($path_from);
        $url_to = $this->getClient()->parseUrl($path_to);

        return $this->getClient()->rename($url_from, $url_to);
    }

    /**
     * @param string $path
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($path, $mode, $options)
    {
        $url = $this->getClient()->parseUrl($path);
        return $this->getClient()->mkdir($url);
    }

    /**
     * @param $path
     * @return bool
     */
    public function rmdir($path)
    {
        $url = $this->getClient()->parseUrl($path);
        return $this->getClient()->rmdir($url);
    }

    /**
     * @param string $path
     * @param int $flags
     * @return array
     */
    public function url_stat($path, $flags = STREAM_URL_STAT_LINK)
    {
        $url = $this->getClient()->parseUrl($path);
        return $this->getClient()->urlStat($url);
    }

    public function __destruct()
    {
        if ($this->tmpfile != '') {
            if ($this->need_flush) {
                $this->stream_flush();
            }
            unlink($this->tmpfile);
        }
    }

    /**
     * @return bool
     */
    public static function register()
    {
        return stream_wrapper_register(static::PROTOCOL, get_called_class());
    }

    /**
     * @return bool
     */
    public static function unregister()
    {
        return stream_wrapper_unregister(static::PROTOCOL);
    }
}
