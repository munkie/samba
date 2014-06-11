<?php

namespace Samba;

class SambaUrl
{
    const DEFAULT_PORT = 139;

    const TYPE_PATH = 'path';
    const TYPE_SHARE = 'share';
    const TYPE_HOST = 'host';
    const TYPE_ERROR = '**error**';

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var string
     */
    protected $user;

    /**
     * @var string
     */
    protected $pass;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $scheme;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $share;

    /**
     * @var string
     */
    protected $type;

    /**
     * @param $url
     */
    public function __construct($url)
    {
        $this->parseUrl($url);
    }

    /**
     * @param string $url
     * @return array purl
     */
    protected function parseUrl($url)
    {
        $this->url = trim($url);

        $parsedUrl = parse_url($this->url);
        foreach (array('domain', 'user', 'pass', 'host', 'port', 'path', 'scheme') as $part) {
            if (isset($parsedUrl[$part])) {
                $this->$part = $parsedUrl[$part];
            }
        }

        $userDomain = explode(';', urldecode($this->user));

        if (count($userDomain) > 1) {
            $this->domain = $userDomain[0];
            $this->user = $userDomain[1];
        }

        $this->path = trim(urldecode($this->path), '/');

        $matches = null;
        if (preg_match('/^([^\/]+)\/(.*)/', $this->path, $matches)) {
            $this->share = $matches[1];
            $this->path = str_replace('/', '\\', $matches[2]);
        } else {
            $this->share = $this->path;
            $this->path = '';
        }

        if ($this->path) {
            $this->type = self::TYPE_PATH;
        } elseif ($this->share) {
            $this->type = self::TYPE_SHARE;
        } elseif ($this->host) {
            $this->type = self::TYPE_HOST;
        } else {
            $this->type = self::TYPE_ERROR;
        }

        $this->port = intval($this->port);
        if (!$this->port) {
            $this->port = self::DEFAULT_PORT;
        }
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getPass()
    {
        return $this->pass;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getShare()
    {
        return $this->share;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isType($type)
    {
        return $this->type == $type;
    }

    /**
     * @return bool
     */
    public function isPath()
    {
        return $this->isType(self::TYPE_PATH);
    }

    /**
     * @return bool
     */
    public function isDefaultPort()
    {
        return self::DEFAULT_PORT == $this->getPort();
    }

    /**
     * @param SambaUrl $url
     * @return bool
     */
    public function isFromSameUserShare(SambaUrl $url)
    {
        if ($this->getDomain() == $url->getDomain()
            && $this->getHost() == $url->getHost()
            && $this->getShare() == $url->getShare()
            && $this->getUser() == $url->getUser()
            && $this->getPass() == $url->getPass()
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return string
     */
    public function getHostShare()
    {
        return '//' . $this->getHost() . '/' . $this->getShare();
    }

    /**
     * @return string
     */
    public function getLastPath()
    {
        $parts = explode('\\', $this->getPath());
        return end($parts);
    }

    /**
     * @param string $name
     * @return SambaUrl
     */
    public function getChildUrl($name)
    {
        return new self($this->getUrl() . '/' . urlencode($name));
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'scheme' => $this->getScheme(),
            'domain' => $this->getDomain(),
            'user' => $this->getUser(),
            'pass' => $this->getPass(),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'path' => $this->getPath(),
            'share' => $this->getShare(),
            'type' => $this->getType(),
            'url' => $this->getUrl(),
        );
    }
}
