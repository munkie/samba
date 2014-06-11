<?php

namespace Samba;

class SambaClient
{
    const SMB4PHP_AUTHMODE = "arg"; // set to 'env' to use USER environment variable
    const SMB4PHP_SMBOPTIONS = "TCP_NODELAY IPTOS_LOWDELAY SO_KEEPALIVE SO_RCVBUF=8192 SO_SNDBUF=8192";
    const SMB4PHP_SMBCLIENT = "smbclient";
    const SMB4PHP_VERSION = "0.8";

    /**
     * @var array
     */
    protected $cache = array();

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
        '^\tWorkgroup[ ]+Master$' => 'workg',
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
     * @param array $url
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

        if (self::SMB4PHP_AUTHMODE == 'env') {
            putenv(sprintf('USER=%s%%%s', $url->getUser(), $url->getPass()));
        } elseif ($url->getUser()) {
            $auth .= ' -U ' . escapeshellarg($url->getUser() . '%' . $url->getPass());
        }
        if ($url->getDomain()) {
            $auth .= ' -W ' . escapeshellarg($url->getDomain());
        }
        return $auth;
    }

    /**
     * @param string $params
     * @param SambaUrl $url
     * @return array
     */
    public function client($params, SambaUrl $url)
    {
        $auth = $this->getAuth($url);

        $port = !$url->isDefaultPort() ? ' -p ' . escapeshellarg($url->getPort()) : '';
        $options = '-O ' . escapeshellarg(self::SMB4PHP_SMBOPTIONS);

        $output = $this->getProcessResource($params, $auth, $options, $port);

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
                case 'workg':
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
                    throw new SambaWrapperException($regs[0]);
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
        $this->closeProcessResource($output);

        return $info;
    }

    # stats

    /**
     * @param SambaUrl $url
     * @return array
     */
    public function urlStat(SambaUrl $url)
    {
        if ($statFromCache = $this->getStatCache($url)) {
            return $statFromCache;
        }

        $stat = array();

        switch ($url->getType()) {
            case SambaUrl::TYPE_HOST:
                if ($lookInfo = $this->look($url)) {
                    $stat = $this->getDirStat();
                } else {
                    throw new SambaWrapperException("url_stat(): list failed for host '{$url->getHost()}'");
                }
                break;
            case SambaUrl::TYPE_SHARE:
                if ($lookInfo = $this->look($url)) {
                    $found = false;
                    $lowerShare = strtolower($url->getShare()); # fix by Eric Leung
                    foreach ($lookInfo['disk'] as $share) {
                        if ($lowerShare == strtolower($share)) {
                            $found = true;
                            $stat = $this->getDirStat();
                            break;
                        }
                    }
                    if (!$found) {
                        throw new SambaWrapperException(
                            "url_stat(): disk resource '{$lowerShare}' not found in '{$url->getHost()}'"
                        );
                    }
                }
                break;
            case SambaUrl::TYPE_PATH:
                if ($output = $this->dir($url)) {
                    $name = $url->getLastPath();
                    if (isset($output['info'][$name])) {
                        $stat = $this->setStatCache($url, $output['info'][$name]);
                    } else {
                        throw new SambaWrapperException("url_stat(): path '{$url->getPath()}' not found");
                    }
                } else {
                    throw new SambaWrapperException("url_stat(): dir failed for path '{$url->getPath()}'");
                }
                break;
            default:
                throw new SambaWrapperException('error in URL');
        }

        return $stat;
    }

    /**
     * @param $url
     * @param $info
     * @return array
     */
    public function setStatCache(SambaUrl $url, array $info)
    {
        $isFile = (strpos($info['attr'], 'D') === false);
        $stat = ($isFile) ? $this->getFileStat() : $this->getDirStat();
        $stat[7] = $stat['size'] = $info['size'];
        $stat[8] = $stat[9] = $stat[10] = $stat['atime'] = $stat['mtime'] = $stat['ctime'] = $info['time'];

        return $this->cache[$url->getUrl()] = $stat;
    }

    /**
     * @param SambaUrl $url
     * @return bool
     */
    public function getStatCache(SambaUrl $url)
    {
        return isset($this->cache[$url->getUrl()]) ? $this->cache[$url->getUrl()] : false;
    }

    /**
     * @param SambaUrl $url
     */
    public function clearStatCache(SambaUrl $url = null)
    {
        if (null === $url) {
            $this->cache = array();
        } elseif (isset($this->cache[$url->getUrl()])) {
            unset($this->cache[$url->getUrl()]);
        }
    }

    /**
     * @param string $params
     * @param string $auth
     * @param string $options
     * @param string $port
     * @return resource
     */
    public function getProcessResource($params, $auth, $options, $port)
    {
        return popen(
            self::SMB4PHP_SMBCLIENT . " -N {$auth} {$options} {$port} {$options} {$params} 2>/dev/null",
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
        $this->clearStatCache($url);
        $command = sprintf('put "%s" "%s"', $file, $url->getPath());
        return $this->execute($command, $url);
    }

    /**
     * @param SambaUrl $url
     * @return array
     */
    public function dir(SambaUrl $url)
    {
        $command = sprintf('dir "%s\*"', $url->getPath());
        $result = $this->execute($command, $url);

        if (isset($result['info'])) {
            foreach ($result['info'] as $name => $info) {
                $this->setStatCache($url->getChildUrl($name), $info);
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
        $this->clearStatCache($url);
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
        if (!$from->isFromSameUserShare($to)) {
            throw new SambaWrapperException('rename: FROM & TO must be in same server-share-user-pass-domain');
        }

        $this->checkUrlIsPath($from, 'rename');
        $this->checkUrlIsPath($to, 'rename');

        $this->clearStatCache($from);
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
        $this->clearStatCache($url);
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

        $this->clearStatCache($url);
        $command = sprintf('rmdir "%s"', $url->getPath());
        return $this->execute($command, $url);
    }

    /**
     * @param SambaUrl $url
     * @param string $command
     * @throws SambaWrapperException
     */
    protected function checkUrlIsPath(SambaUrl $url, $command)
    {
        if (!$url->isPath()) {
            throw new SambaWrapperException($command . ': error - URL should be path');
        }
    }

    /**
     * @return array
     */
    protected function getDirStat()
    {
        return stat('/tmp');
    }

    /**
     * @return array
     */
    protected function getFileStat()
    {
        return stat('/etc/passwd');
    }
}
