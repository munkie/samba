<?php

namespace Samba;

class SambaClient
{
    const SOCKET_OPTIONS = "TCP_NODELAY IPTOS_LOWDELAY SO_KEEPALIVE SO_RCVBUF=8192 SO_SNDBUF=8192";
    const CLIENT = "smbclient";
    const VERSION = "0.8";

    /**
     * @var array
     */
    protected $infos = array();

    /**
     * @var array
     */
    protected $regexp = array(
        '^added interface ip=(.*) bcast=(.*) nmask=(.*)$' => 'skip',
        'Anonymous login successful' => 'skip',
        '^Domain=\[(.*)\] OS=\[(.*)\] Server=\[(.*)\]$' => 'skip',
        '^\tSharename[ ]+Type[ ]+Comment$' => 'shares',
        '^\t---------[ ]+----[ ]+-------$' => 'skip',
        '^\tServer   [ ]+Comment$' => 'servers',
        '^\t---------[ ]+-------$' => 'skip',
        '^\tWorkgroup[ ]+Master$' => 'workgroups',
        '^\t(.*)[ ]+(Disk|IPC)[ ]+IPC.*$' => 'skip',
        '^\tIPC\\\$(.*)[ ]+IPC' => 'skip',
        '^\t(.*)[ ]+(Disk)[ ]+(.*)$' => 'share',
        '^\t(.*)[ ]+(Printer)[ ]+(.*)$' => 'skip',
        '([0-9]+) blocks of size ([0-9]+)\. ([0-9]+) blocks available' => 'skip',
        'Got a positive name query response from ' => 'skip',
        '^(session setup failed): (.*)$' => 'error',
        '^(.*): ERRSRV - ERRbadpw' => 'error',
        '^Error returning browse list: (.*)$' => 'error',
        '^tree connect failed: (.*)$' => 'error',
        '^(Connection to .* failed)$' => 'error',
        '^NT_STATUS_(.*) ' => 'error',
        '^NT_STATUS_(.*)\$' => 'error',
        'ERRDOS - ERRbadpath \((.*).\)' => 'error',
        'cd (.*): (.*)$' => 'error',
        '^cd (.*): NT_STATUS_(.*)' => 'error',
        '^\t(.*)$' => 'srvorwg',
        '^([0-9]+)[ ]+([0-9]+)[ ]+(.*)$' => 'skip',
        '^Job ([0-9]+) cancelled' => 'skip',
        '^[ ]+(.*)[ ]+([0-9]+)[ ]+(Mon|Tue|Wed|Thu|Fri|Sat|Sun)[ ](Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[ ]+([0-9]+)[ ]+([0-9]{2}:[0-9]{2}:[0-9]{2})[ ]([0-9]{4})$' => 'files',
        '^message start: ERRSRV - (ERRmsgoff)' => 'error',
        '^Connection to (.+) failed \(Error (.+)\)$' => 'error',
    );

    /**
     * @param string $url
     * @return SambaUrl
     */
    public function parseUrl($url)
    {
        return new SambaUrl($url);
    }

    /**
     * @param SambaUrl $url
     * @return array
     */
    public function look(SambaUrl $url)
    {
        return $this->client('-L ' . escapeshellarg($url->getHost()), $url);
    }

    /**
     * @param string $command
     * @param SambaUrl $url
     * @return array
     */
    public function execute($command, SambaUrl $url)
    {
        return $this->client(
            '-d 0 '
            . escapeshellarg($url->getHostShare())
            . ' -c ' . escapeshellarg($command),
            $url
        );
    }

    /**
     * @param $line
     * @return array
     */
    protected function getTag($line)
    {
        $tag = 'skip';
        $regs = array();
        foreach ($this->regexp as $regexp => $t) {
            if (preg_match('/' . $regexp . '/', $line, $regs)) {
                $tag = $t;
                break;
            }
        }
        return array($tag, $regs);
    }

    /**
     * @param SambaUrl $url
     * @return string
     */
    protected function getAuth(SambaUrl $url)
    {
        $auth = '';


        return $auth;
    }

    /**
     * @param string $params
     * @param SambaUrl $url
     * @throws SambaException
     * @return array
     */
    public function client($params, SambaUrl $url)
    {
        $options = $this->createOptions($url);
        $output = $this->getProcessResource($params, $options);

        try {
            $info = $this->parseOutput($output);
            $this->closeProcessResource($output);
            return $info;
        } catch (SambaException $e) {
            $this->closeProcessResource($output);
            throw $e;
        }
    }

    /**
     * @param SambaUrl $url
     * @return array
     */
    protected function createOptions(SambaUrl $url)
    {
        $options = array();
        $options['-O'] = self::SOCKET_OPTIONS;
        if ($url->getUser()) {
            $options['-U'] = "{$url->getUser()}%{$url->getPass()}";
        }
        if ($url->getDomain()) {
            $options['-W'] = $url->getDomain();
        }
        if (!$url->isDefaultPort()) {
            $options['-p'] = $url->getPort();
        }
        return $options;
    }

    # stats

    /**
     * @param SambaUrl $url
     * @return array
     */
    public function info(SambaUrl $url)
    {
        if ($info = $this->getInfoCache($url)) {
            return $info;
        }

        switch ($url->getType()) {
            case SambaUrl::TYPE_HOST:
                $info = $this->hostInfo($url);
                break;
            case SambaUrl::TYPE_SHARE:
                $info = $this->shareInfo($url);
                break;
            case SambaUrl::TYPE_PATH:
                $info = $this->pathInfo($url);
                break;
            default:
                throw new SambaException('error in URL');
        }

        return $info;
    }

    /**
     * @param SambaUrl $url
     * @return array
     * @throws SambaException
     */
    protected function hostInfo(SambaUrl $url)
    {
        if ($lookInfo = $this->look($url)) {
            return array(
                'attr' => 'D',
                'size' => 0,
                'time' => time(),
            );
        }

        throw new SambaException("url_stat(): list failed for host '{$url->getHost()}'");
    }

    /**
     * @param SambaUrl $url
     * @return array
     * @throws SambaException
     */
    protected function pathInfo(SambaUrl $url)
    {
        if ($output = $this->dir($url)) {
            $name = $url->getLastPath();
            if (isset($output['info'][$name])) {
                $info = $output['info'][$name];
                $this->setInfoCache($url, $info);
                return $info;
            }
        }

        throw new SambaException("url_stat(): dir failed for path '{$url->getPath()}'");
    }

    /**
     * @param SambaUrl $url
     * @return array
     * @throws SambaException
     */
    protected function shareInfo(SambaUrl $url)
    {
        $lowerShare = strtolower($url->getShare()); # fix by Eric Leung

        if ($lookInfo = $this->look($url)) {
            foreach ($lookInfo['disk'] as $share) {
                if ($lowerShare == strtolower($share)) {
                    return array(
                        'attr' => 'D',
                        'size' => 0,
                        'time' => time(),
                    );
                }
            }
        }

        throw new SambaException(
            "url_stat(): disk resource '{$lowerShare}' not found in '{$url->getHost()}'"
        );
    }

    /**
     * @param $url
     * @param $info
     * @return array
     */
    public function setInfoCache(SambaUrl $url, array $info)
    {
        return $this->infos[$url->getUrl()] = $info;
    }

    /**
     * @param SambaUrl $url
     * @return bool
     */
    public function getInfoCache(SambaUrl $url)
    {
        return isset($this->infos[$url->getUrl()]) ? $this->infos[$url->getUrl()] : false;
    }

    /**
     * @param SambaUrl $url
     */
    public function clearInfoCache(SambaUrl $url = null)
    {
        if (null === $url) {
            $this->infos = array();
        } elseif (isset($this->infos[$url->getUrl()])) {
            unset($this->infos[$url->getUrl()]);
        }
    }

    /**
     * @param string $params
     * @param array $options
     * @return resource
     */
    public function getProcessResource($params, array $options)
    {
        $args = '';
        foreach ($options as $key => $value) {
            $args.= ' ' . $key . ' ' . escapeshellarg($value);
        }
        return popen(
            self::CLIENT . " -N {$args} {$params} 2>/dev/null",
            'r'
        );
    }

    /**
     * @param $output
     */
    public function closeProcessResource($output)
    {
        pclose($output);
    }

    /**
     * Commands
     */

    /**
     * @param SambaUrl $url
     * @param string $file
     * @return array
     */
    public function get(SambaUrl $url, $file)
    {
        $command = sprintf('get "%s" "%s"', $url->getPath(), $file);
        return $this->execute($command, $url);
    }

    /**
     * @param SambaUrl $url
     * @param string $file
     * @return array
     */
    public function put(SambaUrl $url, $file)
    {
        $this->clearInfoCache($url);
        $command = sprintf('put "%s" "%s"', $file, $url->getPath());
        return $this->execute($command, $url);
    }

    /**
     * @param SambaUrl $url
     * @param string $mask
     * @return array
     */
    public function dir(SambaUrl $url, $mask = '')
    {
        $command = sprintf('dir "%s%s"', $url->getPath(), $mask);
        $result = $this->execute($command, $url);

        if (isset($result['info'])) {
            foreach ($result['info'] as $name => $info) {
                $this->setInfoCache($url->getChildUrl($name), $info);
            }
        }

        return $result;
    }

    /**
     * @param SambaUrl $url
     * @return array
     */
    public function del(SambaUrl $url)
    {
        $this->checkUrlIsPath($url, 'del');
        $this->clearInfoCache($url);
        $command = sprintf('del "%s"', $url->getPath());
        return $this->execute($command, $url);
    }

    /**
     * @param SambaUrl $from purl
     * @param SambaUrl $to purl
     * @return array
     */
    public function rename(SambaUrl $from, SambaUrl $to)
    {
        $this->checkUrlIsPath($from, 'rename');
        $this->checkUrlIsPath($to, 'rename');

        if (!$from->isFromSameUserShare($to)) {
            throw new SambaException('rename: FROM & TO must be in same server-share-user-pass-domain');
        }

        $this->clearInfoCache($from);
        $command = sprintf('rename "%s" "%s"', $from->getPath(), $to->getPath());
        return $this->execute($command, $to);
    }

    /**
     * @param SambaUrl $url
     * @return array
     */
    public function mkdir(SambaUrl $url)
    {
        $this->checkUrlIsPath($url, 'mkdir');
        $this->clearInfoCache($url);
        $command = sprintf('mkdir "%s"', $url->getPath());
        return $this->execute($command, $url);
    }

    /**
     * @param SambaUrl $url
     * @return array
     */
    public function rmdir(SambaUrl $url)
    {
        $this->checkUrlIsPath($url, 'rmdir');

        $this->clearInfoCache($url);
        $command = sprintf('rmdir "%s"', $url->getPath());
        return $this->execute($command, $url);
    }

    /**
     * @param SambaUrl $url
     * @param string $command
     * @throws SambaException
     */
    protected function checkUrlIsPath(SambaUrl $url, $command)
    {
        if (!$url->isPath()) {
            throw new SambaException($command . ': error - URL should be path');
        }
    }

    /**
     * @param resource $output
     * @return array
     */
    protected function parseOutput($output)
    {
        $info = array();

        while (($line = fgets($output)) !== false) {
            $i = array();

            list($tag, $regs) = $this->getTag($line);

            switch ($tag) {
                case 'skip':
                    continue;
                case 'shares':
                    $mode = 'shares';
                    break;
                case 'servers':
                    $mode = 'servers';
                    break;
                case 'workgroups':
                    $mode = 'workgroups';
                    break;
                case 'share':
                    list($name, $type) = array(
                        trim(substr($line, 1, 15)),
                        trim(strtolower(substr($line, 17, 10)))
                    );
                    $i = ($type != 'disk' && preg_match('/^(.*) Disk/', $line, $regs))
                        ? array(trim($regs[1]), 'disk')
                        : array($name, 'disk');
                    break;
                case 'srvorwg':
                    list ($name, $master) = array(
                        strtolower(trim(substr($line, 1, 21))),
                        strtolower(trim(substr($line, 22)))
                    );
                    $i = (isset($mode) && $mode == 'servers')
                        ? array($name, "server")
                        : array($name, "workgroup", $master);
                    break;
                case 'files':
                    list ($attr, $name) = preg_match("/^(.*)[ ]+([D|A|H|S|R]+)$/", trim($regs[1]), $regs2)
                        ? array(trim($regs2[2]), trim($regs2[1]))
                        : array('', trim($regs[1]));
                    list ($his, $im) = array(
                        explode(':', $regs[6]),
                        1 + strpos("JanFebMarAprMayJunJulAugSepOctNovDec", $regs[4]) / 3
                    );
                    $i = ($name != '.' && $name != '..')
                        ? array(
                            $name,
                            (strpos($attr, 'D') === false) ? 'file' : 'folder',
                            'attr' => $attr,
                            'size' => intval($regs[2]),
                            'time' => mktime($his[0], $his[1], $his[2], $im, $regs[5], $regs[7])
                        )
                        : array();
                    break;
                case 'error':
                    throw new SambaException($regs[0]);
            }
            if ($i) {
                switch ($i[1]) {
                    case 'file':
                    case 'folder':
                        $info['info'][$i[0]] = $i;
                        $info[$i[1]][] = $i[0];
                        break;
                    case 'disk':
                    case 'server':
                    case 'workgroup':
                        $info[$i[1]][] = $i[0];
                        break;
                }
            }
        }

        return $info;
    }
}
