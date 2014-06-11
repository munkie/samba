<?php

namespace Samba;

class SambaStreamWrapperTest extends \PHPUnit_Framework_TestCase
{
    public function onConsecutiveCallsArray(array $array)
    {
        return new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls($array);
    }

    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|SambaStreamWrapper
     */
    protected function getSambaWrapperMock(array $methods)
    {
        $client = $this->getSambaClientMock($methods);
        $wrapper = $this->getMock('\\Samba\\SambaStreamWrapper', $methods);
        $wrapper->setClient($client);
        return $wrapper;
    }

    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|SambaClient
     */
    protected function getSambaClientMock(array $methods)
    {
        return $this->getMock('\\Samba\\SambaClient', $methods);
    }

    /**
     * @param $url
     * @param $expectedParsedUrl
     *
     * @dataProvider parserUrlProvider
     */
    public function testParseUrlMethod($url, $expectedParsedUrl)
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
        );

        $samba = new SambaClient();

        $parsedUrl = $samba->parseUrl($url);

        $this->assertEquals($expectedParsedUrl, $parsedUrl);
    }

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

    public function testLookMethod()
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

    public function testExecuteMethod()
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

    public function testUnlinkMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));
        $sambaClient = $sambaMock->getClient();
        $parsedUrl = $sambaClient->parseUrl($url);

        $expectedExecuteCommand = 'del "to\dir\file.doc"';

        $sambaClient
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrl));

        $sambaMock->unlink($url);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUnLinkExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $sambaMock->unlink($url);
    }

    public function testRenameMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";
        $urlNew = "smb://user:password@host/base_path/to/dir/file_new.doc";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $parsedUrlNew = $sambaMock->getClient()->parseUrl($urlNew);

        $expectedExecuteCommand = 'rename "to\dir\file.doc" "to\dir\file_new.doc"';

        $sambaMock->getClient()
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrlNew));

        $sambaMock->rename($url, $urlNew);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testRenameExceptionOnNotOneServer()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";
        $urlNew = "smb://user:password@new_host/base_path/to/dir/file_new.doc";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $sambaMock->rename($url, $urlNew);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testRenameExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";
        $urlNew = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $sambaMock->rename($url, $urlNew);
    }

    public function testMkDirMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));
        $parsedUrl = $sambaMock->getClient()->parseUrl($url);

        $expectedExecuteCommand = 'mkdir "to\dir"';

        $sambaMock->getClient()
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrl));

        $sambaMock->mkdir($url, '', '');
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testMkDirExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $sambaMock->mkdir($url, '', '');
    }

    public function testRmDirMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));
        $parsedUrl = $sambaMock->getClient()->parseUrl($url);

        $expectedExecuteCommand = 'rmdir "to\dir"';

        $sambaMock->getClient()
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrl));

        $sambaMock->rmdir($url);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testRmDirExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $sambaMock->rmdir($url);
    }

    public function testStatCacheMethods()
    {
        $urlFile = "smb://user:password@host/base_path/to/dir/file.doc";
        $urlDir = "smb://user:password@host/base_path/to/dir";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $this->assertFalse($sambaMock->getstatcache($urlFile));
        $this->assertFalse($sambaMock->getstatcache($urlDir));

        $infoFile = array(
            'attr' => 'F',
            'size' => 4,
            'time' => 777,
        );
        $statFile = stat("/etc/passwd");
        $statFile[7] = $statFile['size'] = $infoFile['size'];
        $statFile[8]
            = $statFile[9]
            = $statFile[10]
            = $statFile['atime']
            = $statFile['mtime']
            = $statFile['ctime']
            = $infoFile['time'];

        $infoDir = $infoFile;
        $infoDir['attr'] = 'D';
        $statDir = stat("/tmp");
        $statDir[7] = $statDir['size'] = $infoDir['size'];
        $statDir[8]
            = $statDir[9]
            = $statDir[10]
            = $statDir['atime']
            = $statDir['mtime']
            = $statDir['ctime']
            = $infoDir['time'];

        $this->assertEquals($statFile, $sambaMock->addstatcache($urlFile, $infoFile));
        $this->assertEquals($statDir, $sambaMock->addstatcache($urlDir, $infoDir));

        $this->assertEquals($statFile, $sambaMock->getstatcache($urlFile));
        $this->assertEquals($statDir, $sambaMock->getstatcache($urlDir));

        $sambaMock->clearstatcache($urlFile);

        $this->assertFalse($sambaMock->getstatcache($urlFile));
        $this->assertEquals($statDir, $sambaMock->getstatcache($urlDir));

        $this->assertEquals($statFile, $sambaMock->addstatcache($urlFile, $infoFile));
        $sambaMock->clearstatcache();
        $this->assertFalse($sambaMock->getstatcache($urlFile));
        $this->assertFalse($sambaMock->getstatcache($urlDir));
    }



    /**
     * @param array $strings
     * @return \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls
     */
    protected function onConsecutiveCallsStringToResources(array $strings)
    {
        return new \PHPUnit_Framework_MockObject_Stub_ConsecutiveCalls(
            array_map(
                array($this, 'convertStringToResource'),
                $strings
            )
        );
    }

    public function testUrlStatMethod()
    {
        $urlFile = "smb://user:password@host/base_path/catalog-goods_1378998029.xml";
        $urlDir = "smb://user:password@host/base_path/success";
        $urlHost = "smb://user:password@host";
        $urlShare = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaWrapperMock(array('look', 'execute'));
        
        $lookInfo = array(
            "disk" => array("centrum"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $sambaMock->getClient()
            ->expects($this->any())
            ->method('look')
            ->will($this->returnValue($lookInfo));

        $sambaMock->getClient()
            ->expects($this->any())
            ->method('execute')
            ->will($this->returnValue($this->getDirInfoArray()));
        
        $expectedStatInfoHost = stat("/tmp");
        
        $actualStatInfoHost = $sambaMock->url_stat($urlHost);
        $this->assertEquals($expectedStatInfoHost, $actualStatInfoHost);
        
        
        $expectedStatInfoDir = stat("/tmp");
        $expectedStatInfoDir[7] = $expectedStatInfoDir['size'] = 0;
        $expectedStatInfoDir[8]
            = $expectedStatInfoDir[9]
            = $expectedStatInfoDir[10]
            = $expectedStatInfoDir['atime']
            = $expectedStatInfoDir['mtime']
            = $expectedStatInfoDir['ctime']
            = 1380789766;

        $actualStatInfoDir = $sambaMock->url_stat($urlDir);
        $this->assertEquals($expectedStatInfoDir, $actualStatInfoDir);


        $expectedStatInfoFile = stat('/etc/passwd');
        $expectedStatInfoFile[7] = $expectedStatInfoFile['size'] = 70;
        $expectedStatInfoFile[8]
            = $expectedStatInfoFile[9]
            = $expectedStatInfoFile[10]
            = $expectedStatInfoFile['atime']
            = $expectedStatInfoFile['mtime']
            = $expectedStatInfoFile['ctime']
            = 1378998030;

        $actualStatInfoFile = $sambaMock->url_stat($urlFile);
        $this->assertEquals($expectedStatInfoFile, $actualStatInfoFile);


        $shareLookInfo = array(
            "disk" => array("base_path"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $sambaMock = $this->getSambaWrapperMock(array('look'));

        $sambaMock->getClient()
            ->expects($this->any())
            ->method('look')
            ->will($this->returnValue($shareLookInfo));

        $expectedStatInfoShare = stat("/tmp");

        $actualStatInfoShare = $sambaMock->url_stat($urlShare);
        $this->assertEquals($expectedStatInfoShare, $actualStatInfoShare);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatHostException()
    {
        $sambaMock = $this->getSambaWrapperMock(array('look'));

        $urlHost = "smb://user:password@host";

        $sambaMock->url_stat($urlHost);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatShareException()
    {
        $shareLookInfo = array(
            "disk" => array("centrum"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $sambaMock = $this->getSambaWrapperMock(array('look'));

        $sambaMock->getClient()
            ->expects($this->any())
            ->method('look')
            ->will($this->returnValue($shareLookInfo));

        $urlShare = "smb://user:password@host/base_path";

        $sambaMock->url_stat($urlShare);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatPathException()
    {
        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $urlDir = "smb://user:password@host/base_path/success";

        $sambaMock->url_stat($urlDir);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatNotTypeUrlException()
    {
        $sambaMock = $this->getSambaWrapperMock(array('execute'));

        $url = "smb://";

        $sambaMock->url_stat($url);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatNotFoundPath()
    {
        $executeOutput =  <<<EOF
Anonymous login successful
Domain=[MYGROUP] OS=[Unix] Server=[Samba 3.0.33-3.39.el5_8]
NT_STATUS_NO_SUCH_FILE listing \reportsw
EOF;

        $executeOutputStream = SambaClientTest::convertStringToResource($executeOutput);

        $sambaMock = $this->getSambaWrapperMock(array('getProcessResource'));

        $sambaMock->getClient()
            ->expects($this->any())
            ->method('getProcessResource')
            ->will($this->returnValue($executeOutputStream));

        $urlDir = "smb://user:password@host/base_path/success";

        $sambaMock->url_stat($urlDir);
    }

    public function testDirOpenDirMethod()
    {
        $urlDir = "smb://user:password@host/base_path/to/dir";
        $urlHost = "smb://user:password@host";

        $sambaMock = $this->getSambaWrapperMock(array('look', 'execute'));

        $lookInfo = array(
            "disk" => array("centrum"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $sambaMock->getClient()
            ->expects($this->once())
            ->method('look')
            ->will($this->returnValue($lookInfo));

        $sambaMock->dir_opendir($urlHost, array());

        $this->assertEquals(array("centrum"), $sambaMock->dir);


        $sambaMock->getClient()
            ->expects($this->any())
            ->method('execute')
            ->will($this->returnValue($this->getDirInfoArray()));

        $sambaMock->dir_opendir($urlDir, array());

        $expectedDir = array(
            'success',
            'test',
            'error',
            'tmp',
            'source',
            'catalog-goods_1234-13-09-2013_11-30-14.xml',
            'catalog-goods_1378998029.xml',
            'catalog-goods_1379058741.xml',
        );

        $this->assertEquals($expectedDir, $sambaMock->dir);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testDirOpenDirExceptionHostNotLook()
    {
        $urlHost = "smb://user:password@host";

        $sambaMock = $this->getSambaWrapperMock(array('look'));

        $sambaMock->dir_opendir($urlHost, '');
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testDirOpenDirExceptionErrorType()
    {
        $urlHost = "smb://";

        $sambaMock = $this->getSambaWrapperMock(array('look'));

        $sambaMock->dir_opendir($urlHost, '');
    }

    /**
     * @return array
     */
    public function getDirInfoArray()
    {
        $expectedDirInfo = array(
            'info' => array(
                'success' => array(
                    'success',
                    'folder',
                    'attr' => 'D',
                    'size' => 0,
                    'time' => 1380789766,
                ),
                'test' => array(
                    'test',
                    'file',
                    'attr' => 'A',
                    'size' => 2,
                    'time' => 1372439631,
                ),
                'error' => array(
                    'error',
                    'folder',
                    'attr' => 'D',
                    'size' => 0,
                    'time' => 1378911191,
                ),
                'tmp' => array(
                    'tmp',
                    'folder',
                    'attr' => 'D',
                    'size' => 0,
                    'time' => 1380789766,
                ),
                'source' => array(
                    'source',
                    'folder',
                    'attr' => 'D',
                    'size' => 0,
                    'time' => 1380789766,
                ),
                'catalog-goods_1234-13-09-2013_11-30-14.xml' => array(
                    'catalog-goods_1234-13-09-2013_11-30-14.xml',
                    'file',
                    'attr' => 'A',
                    'size' => 1120,
                    'time' => 1379057353,
                ),
                'catalog-goods_1378998029.xml' => array(
                    'catalog-goods_1378998029.xml',
                    'file',
                    'attr' => 'A',
                    'size' => 70,
                    'time' => 1378998030,
                ),
                'catalog-goods_1379058741.xml' => array(
                    'catalog-goods_1379058741.xml',
                    'file',
                    'attr' => 'A',
                    'size' => 3917,
                    'time' => 1379058742,
                ),
            ),
            'folder' => array(
                'success',
                'error',
                'tmp',
                'source'
            ),
            'file' => array(
                'test',
                'catalog-goods_1234-13-09-2013_11-30-14.xml',
                'catalog-goods_1378998029.xml',
                'catalog-goods_1379058741.xml',
            ),
        );
        return $expectedDirInfo;
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testStreamOpenExceptionHost()
    {
        $urlHost = "smb://user:password@host";

        $sambaMock = $this->getSambaWrapperMock(array('look'));

        $path = null;
        $sambaMock->stream_open($urlHost, '', '', $path);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testStreamOpenExceptionShare()
    {
        $urlHost = "smb://user:password@host/share";

        $sambaMock = $this->getSambaWrapperMock(array('look'));

        $path = null;
        $sambaMock->stream_open($urlHost, '', '', $path);
    }
}
