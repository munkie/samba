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
     * @var string
     */
    protected $url;

    /**
     * @var array
     */
    protected $parsed_url = array();

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
        $pu = $this->getClient()->parseUrl($path);
        switch ($pu['type']) {
            case 'host':
                if ($o = $this->getClient()->look($pu)) {
                    $this->dir = $o['disk'];
                    $this->dir_index = 0;
                } else {
                    throw new SambaWrapperException("dir_opendir(): list failed for host '{$pu['host']}'");
                }
                break;
            case 'share':
            case 'path':
                if ($o = $this->getClient()->execute('dir "' . $pu['path'] . '\*"', $pu)) {
                    $this->dir = array_keys($o['info']);
                    $this->dir_index = 0;
                    $this->add_dir_cache($path, $this->dir);
                    foreach ($o['info'] as $name => $info) {
                        $this->getClient()->addstatcache($path . '/' . urlencode($name), $info);
                    }
                } else {
                    $this->dir = array();
                    $this->dir_index = 0;
                }
                break;
            default:
                throw new SambaWrapperException('dir_opendir(): error in URL', E_USER_ERROR);
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
        $this->url = $url;
        $this->mode = $mode;
        $this->parsed_url = $pu = $this->getClient()->parseUrl($url);
        if ($pu['type'] != 'path') {
            throw new SambaWrapperException('stream_open(): error in URL');
        }
        switch ($mode) {
            case 'r':
            case 'r+':
            case 'rb':
            case 'a':
            case 'a+':
                $this->tmpfile = tempnam('/tmp', 'smb.down.');
                $this->getClient()->execute('get "' . $pu['path'] . '" "' . $this->tmpfile . '"', $pu);
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
            $this->getClient()->clearstatcache($this->url);
            $this->getClient()->execute('put "' . $this->tmpfile . '" "' . $this->parsed_url['path'] . '"', $this->parsed_url);
            $this->need_flush = false;
        }
        return true;
    }

    /**
     * @return array
     */
    public function stream_stat()
    {
        return $this->getClient()->url_stat($this->url);
    }

    /**
     * @param string $url
     * @return array
     */
    public function unlink($url)
    {
        $pu = $this->getClient()->parseUrl($url);
        if ($pu['type'] != 'path') {
            throw new SambaWrapperException('unlink(): error in URL');
        }
        $this->getClient()->clearstatcache($url);

        return $this->getClient()->execute('del "' . $pu['path'] . '"', $pu);
    }

    /**
     * @param string $url_from
     * @param string $url_to
     * @return array
     */
    public function rename($url_from, $url_to)
    {
        $from = $this->getClient()->parseUrl($url_from);
        $to = $this->getClient()->parseUrl($url_to);

        if ($from['host'] != $to['host'] ||
            $from['share'] != $to['share'] ||
            $from['user'] != $to['user'] ||
            $from['pass'] != $to['pass'] ||
            $from['domain'] != $to['domain']
        ) {
            throw new SambaWrapperException('rename(): FROM & TO must be in same server-share-user-pass-domain');
        }
        if ($from['type'] != 'path' || $to['type'] != 'path') {
            throw new SambaWrapperException('rename(): error in URL');
        }
        $this->getClient()->clearstatcache($url_from);

        return $this->getClient()->execute('rename "' . $from['path'] . '" "' . $to['path'] . '"', $to);
    }

    /**
     * @param string $url
     * @param int $mode
     * @param int $options
     * @return bool
     */
    public function mkdir($url, $mode, $options)
    {
        $pu = $this->getClient()->parseUrl($url);
        if ($pu['type'] != 'path') {
            throw new SambaWrapperException('mkdir(): error in URL');
        }

        return $this->getClient()->execute('mkdir "' . $pu['path'] . '"', $pu);
    }

    /**
     * @param $url
     * @return bool
     */
    public function rmdir($url)
    {
        $pu = $this->getClient()->parseUrl($url);
        if ($pu['type'] != 'path') {
            throw new SambaWrapperException('rmdir(): error in URL');
        }
        $this->getClient()->clearstatcache($url);

        return $this->getClient()->execute('rmdir "' . $pu['path'] . '"', $pu);
    }

    /**
     * @param string $url
     * @param int $flags
     * @return array
     */
    public function url_stat($url, $flags = STREAM_URL_STAT_LINK)
    {
        return $this->getClient()->url_stat($url);
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
