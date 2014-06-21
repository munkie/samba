<?php

namespace Samba;

class SambaClientTest extends TestCase
{
    /**
     * @param string $url
     * @param array $expectedParsedUrl
     *
     * @dataProvider parserUrlProvider
     */
    public function testSambaUrl($url, array $expectedParsedUrl)
    {
        $expectedParsedUrl = $expectedParsedUrl + array(
            'type' => 'path',
            'path' => 'to\dir',
            'host' => 'host',
            'user' => 'user',
            'pass' => 'password',
            'domain' => '',
            'share' => 'base_path',
            'port' => 139,
            'scheme' => 'smb',
            'url' => $url
        );

        $url = new SambaUrl($url);

        $this->assertEquals($expectedParsedUrl, $url->toArray());
    }

    /**
     * @return array
     */
    public function parserUrlProvider()
    {
        return array(
            'full base url' => array(
                "smb://user:password@host/base_path/to/dir",
                array(),
            ),
            'full base url with file' => array(
                "smb://user:password@host/base_path/to/dir/file.doc",
                array(
                    'path' => 'to\dir\file.doc',
                ),
            ),
            'base url without password' => array(
                "smb://user@host/base_path/to/dir",
                array(
                    'pass' => '',
                ),
            ),
            'base url without user and password' => array(
                "smb://host/base_path/to/dir",
                array(
                    'user' => '',
                    'pass' => '',
                ),
            ),
            'base url with port' => array(
                "smb://user:password@host:222/base_path/to/dir",
                array(
                    'port' => '222',
                ),
            ),
            'base url with port and domain' => array(
                "smb://domain.local;user:password@host:222/base_path/to/dir",
                array(
                    'port' => '222',
                    'domain' => 'domain.local',
                ),
            ),
            'base url without path' => array(
                "smb://user:password@host/base_path",
                array(
                    'path' => '',
                    'type' => 'share',
                ),
            ),
            'url without share' => array(
                "smb://user:password@host",
                array(
                    'path' => '',
                    'share' => '',
                    'type' => 'host',
                ),
            ),
            'base url empty' => array(
                "",
                array(
                    'user' => '',
                    'pass' => '',
                    'domain' => '',
                    'host' => '',
                    'share' => '',
                    'path' => '',
                    'type' => '**error**',
                    'scheme' => '',
                ),
            ),
        );
    }

    public function testLook()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";

        $sambaMock = $this->getSambaClientMock(array('client'));

        $parsedUrl = $sambaMock->parseUrl($url);

        $sambaMock
            ->expects($this->once())
            ->method('client')
            ->with($this->equalTo("-L 'host'"), $this->equalTo($parsedUrl));

        $sambaMock->look($parsedUrl);
    }

    public function testExecute()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";

        $sambaMock = $this->getSambaClientMock(array('client'));

        $parsedUrl = $sambaMock->parseUrl($url);

        $expectedClientParams = "-d 0 '//host/base_path' -c 'test_command'";

        $sambaMock
            ->expects($this->once())
            ->method('client')
            ->with($this->equalTo($expectedClientParams), $this->equalTo($parsedUrl));

        $sambaMock->execute('test_command', $parsedUrl);
    }

    public function testLookInfo()
    {
        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $commandOutputStream = $this->convertStringToResource(
            "Anonymous login successful\n" .
            "Domain=[MYGROUP] OS=[Unix] Server=[Samba 3.0.33-3.39.el5_8]\n" .
            "\n" .
            "\tSharename       Type      Comment\n" .
            "\t---------       ----      -------\n" .
            "\tIPC$            IPC       IPC Service (Centrum Server Lighthouse)\n" .
            "\tcentrum         Disk      Centrum ERP integration\n" .
            "Anonymous login successful\n" .
            "Domain=[MYGROUP] OS=[Unix] Server=[Samba 3.0.33-3.39.el5_8]\n" .
            "\n" .
            "\tServer               Comment\n" .
            "\t---------            -------\n" .
            "\tVM6                  Centrum Server Lighthouse\n" .
            "\n" .
            "\tWorkgroup            Master\n" .
            "\t---------            -------\n" .
            "\tCMAG                 SHOP1\n" .
            "\tMYGROUP              VM6\n"
        );

        $sambaMock
            ->expects($this->once())
            ->method('getProcessResource')
            ->will($this->returnValue($commandOutputStream));

        $expectedLookInfo = array(
            'disk' => array('centrum'),
            'server' => array('vm6'),
            'workgroup' => array('cmag', 'mygroup'),
        );

        $urlFile = 'smb://user:password@host/base_path/to/dir/file.doc';

        $parsedUrlFile = $sambaMock->parseUrl($urlFile);

        $lookInfo = $sambaMock->client('-L test.host', $parsedUrlFile);
        $this->assertEquals($expectedLookInfo, $lookInfo);
    }

    public function testDirRequest()
    {
        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $urlDir = "smb://user:password@host/base_path/to/dir";

        $parsedUrlDir = $sambaMock->parseUrl($urlDir);

        $openDirInfo = <<<EOF
Anonymous login successful
Domain=[MYGROUP] OS=[Unix] Server=[Samba 3.0.33-3.39.el5_8]
  .                                   D        0  Fri Sep 13 11:13:28 2013
  ..                                  D        0  Thu Sep  5 16:54:33 2013
  success                             D        0  Thu Oct  3 12:42:46 2013
  test                                A        2  Fri Jun 28 21:13:51 2013
  error                               D        0  Wed Sep 11 18:53:11 2013
  tmp                                 D        0  Thu Oct  3 12:42:46 2013
  source                              D        0  Thu Oct  3 12:42:46 2013
  catalog-goods_1234-13-09-2013_11-30-14.xml      A     1120  Fri Sep 13 11:29:13 2013
  catalog-goods_1378998029.xml        A       70  Thu Sep 12 19:00:30 2013
  catalog-goods_1379058741.xml        A     3917  Fri Sep 13 11:52:22 2013

                37382 blocks of size 524288. 29328 blocks available
EOF;
        $openDirInfoStream = $this->convertStringToResource($openDirInfo);

        $sambaMock
            ->expects($this->once())
            ->method('getProcessResource')
            ->will($this->returnValue($openDirInfoStream));

        $dirInfo = $sambaMock->dir($parsedUrlDir, '\*');

        $expectedDirInfo = $this->getExpectedDirInfo();

        $this->assertEquals($expectedDirInfo, $dirInfo);
    }

    /**
     * @expectedException \Samba\SambaException
     */
    public function testRequestError()
    {
        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $errorResponseStream = $this->convertStringToResource('tree connect failed: test');

        $sambaMock
            ->expects($this->once())
            ->method('getProcessResource')
            ->will($this->returnValue($errorResponseStream));

        $urlDir = 'smb://user:password@host/base_path/to/dir';

        $parsedUrlDir = $sambaMock->parseUrl($urlDir);

        $sambaMock->client('-L ' . escapeshellarg($urlDir), $parsedUrlDir);
    }


    /**
     * @expectedException \Samba\SambaException
     */
    public function testBadNetworkNameError()
    {
        $urlDir = 'smb://user:password@host/base_path/to/dir';

        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $expectedResponseStream = $this->convertStringToResource(
            "Connection to faro.lighthouse.pro failed (Error NT_STATUS_BAD_NETWORK_NAME)\n"
        );
        $sambaMock
            ->expects($this->any())
            ->method('getProcessResource')
            ->will($this->returnValue($expectedResponseStream));

        $parsedUrlDir = $sambaMock->parseUrl($urlDir);

        $sambaMock->dir($parsedUrlDir);
    }

    public function testStatCacheClear()
    {
        $urlFile = 'smb://user:password@host/base_path/to/dir/file.doc';
        $urlDir = 'smb://user:password@host/base_path/to/dir';

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $parsedUrlFile = $sambaMock->parseUrl($urlFile);
        $parsedUrlDir = $sambaMock->parseUrl($urlDir);

        $infoFile = array(
            'attr' => 'F',
            'size' => 4,
            'time' => 777,
        );

        $infoDir = array(
            'attr' => 'D',
            'size' => 4,
            'time' => 777,
        );

        $this->assertEquals($infoFile, $sambaMock->setInfoCache($parsedUrlFile, $infoFile));
        $this->assertEquals($infoDir, $sambaMock->setInfoCache($parsedUrlDir, $infoDir));

        $this->assertEquals($infoFile, $sambaMock->getInfoCache($parsedUrlFile));
        $this->assertEquals($infoDir, $sambaMock->getInfoCache($parsedUrlDir));

        $sambaMock->clearInfoCache($parsedUrlFile);

        $this->assertFalse($sambaMock->getInfoCache($parsedUrlFile));
        $this->assertEquals($infoDir, $sambaMock->getInfoCache($parsedUrlDir));

        $this->assertEquals($infoFile, $sambaMock->setInfoCache($parsedUrlFile, $infoFile));

        $sambaMock->clearInfoCache();

        $this->assertFalse($sambaMock->getInfoCache($parsedUrlFile));
        $this->assertFalse($sambaMock->getInfoCache($parsedUrlDir));
    }

    /**
     * @dataProvider statCacheProvider
     * @param string $url
     * @param string $mode
     */
    public function testStatCache($url, $mode)
    {
        $sambaMock = $this->getSambaClientMock(array('execute'));

        $parsedUrl = $sambaMock->parseUrl($url);

        $this->assertFalse($sambaMock->getInfoCache($parsedUrl));

        $info = array(
            'attr' => $mode,
            'size' => 4,
            'time' => 777,
        );

        $this->assertEquals($info, $sambaMock->setInfoCache($parsedUrl, $info));

        $this->assertEquals($info, $sambaMock->getInfoCache($parsedUrl));
    }

    /**
     * @return array
     */
    public function statCacheProvider()
    {
        return array(
            'dir' => array(
                'smb://user:password@host/base_path/to/dir',
                'D',
            ),
            'file' => array(
                'smb://user:password@host/base_path/to/dir/file.doc',
                'F',
            ),
        );
    }
}
