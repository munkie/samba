<?php

namespace Samba;

class SambaStreamWrapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|SambaClient
     */
    protected function getSambaClientMock(array $methods)
    {
        return $this->getMock('\\Samba\\SambaClient', $methods);
    }

    public function testUnlinkMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";

        $sambaMock = $this->getSambaClientMock(array('execute'));
        $parsedUrl = $sambaMock->parseUrl($url);

        $expectedExecuteCommand = 'del "to\dir\file.doc"';

        $sambaMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrl));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->unlink($url);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUnLinkExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";


        $sambaMock = $this->getSambaClientMock(array('execute'));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->unlink($url);
    }

    public function testRenameMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";
        $urlNew = "smb://user:password@host/base_path/to/dir/file_new.doc";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $parsedUrlNew = $sambaMock->parseUrl($urlNew);

        $expectedExecuteCommand = 'rename "to\dir\file.doc" "to\dir\file_new.doc"';

        $sambaMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrlNew));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->rename($url, $urlNew);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testRenameExceptionOnNotOneServer()
    {
        $url = "smb://user:password@host/base_path/to/dir/file.doc";
        $urlNew = "smb://user:password@new_host/base_path/to/dir/file_new.doc";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->rename($url, $urlNew);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testRenameExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";
        $urlNew = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->rename($url, $urlNew);
    }

    public function testMkDirMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir";

        $sambaMock = $this->getSambaClientMock(array('execute'));
        $parsedUrl = $sambaMock->parseUrl($url);

        $expectedExecuteCommand = 'mkdir "to\dir"';

        $sambaMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrl));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->mkdir($url, '', '');
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testMkDirExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->mkdir($url, '', '');
    }

    public function testRmDirMethod()
    {
        $url = "smb://user:password@host/base_path/to/dir";

        $sambaMock = $this->getSambaClientMock(array('execute'));
        $parsedUrl = $sambaMock->parseUrl($url);

        $expectedExecuteCommand = 'rmdir "to\dir"';

        $sambaMock
            ->expects($this->once())
            ->method('execute')
            ->with($this->equalTo($expectedExecuteCommand), $this->equalTo($parsedUrl));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->rmdir($url);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testRmDirExceptionNotAPath()
    {
        $url = "smb://user:password@host/base_path";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->rmdir($url);
    }

    public function testUrlStatHost()
    {
        $urlHost = "smb://user:password@host";

        $sambaMock = $this->getSambaClientMock(array('look'));

        $lookInfo = array(
            "disk" => array("centrum"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $sambaMock
            ->expects($this->any())
            ->method('look')
            ->will($this->returnValue($lookInfo));

        $expectedStatInfoHost = stat("/tmp");

        $wrapper = new SambaStreamWrapper($sambaMock);
        $actualStatInfoHost = $wrapper->url_stat($urlHost);

        $this->assertEquals($expectedStatInfoHost, $actualStatInfoHost);
    }

    public function testUrlStatDir()
    {
        $urlDir = "smb://user:password@host/base_path/success";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $sambaMock
            ->expects($this->any())
            ->method('execute')
            ->will($this->returnValue(SambaClientTest::getExpectedDirInfo()));

        $expectedStatInfoDir = stat("/tmp");
        $expectedStatInfoDir[7] = $expectedStatInfoDir['size'] = 0;
        $expectedStatInfoDir[8]
            = $expectedStatInfoDir[9]
            = $expectedStatInfoDir[10]
            = $expectedStatInfoDir['atime']
            = $expectedStatInfoDir['mtime']
            = $expectedStatInfoDir['ctime']
            = 1380789766;

        $wrapper = new SambaStreamWrapper($sambaMock);
        $actualStatInfoDir = $wrapper->url_stat($urlDir);

        $this->assertEquals($expectedStatInfoDir, $actualStatInfoDir);
    }

    public function testUrlStatFile()
    {
        $urlFile = "smb://user:password@host/base_path/catalog-goods_1378998029.xml";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $sambaMock
            ->expects($this->any())
            ->method('execute')
            ->will($this->returnValue(SambaClientTest::getExpectedDirInfo()));

        $expectedStatInfoFile = stat('/etc/passwd');
        $expectedStatInfoFile[7] = $expectedStatInfoFile['size'] = 70;
        $expectedStatInfoFile[8]
            = $expectedStatInfoFile[9]
            = $expectedStatInfoFile[10]
            = $expectedStatInfoFile['atime']
            = $expectedStatInfoFile['mtime']
            = $expectedStatInfoFile['ctime']
            = 1378998030;

        $wrapper = new SambaStreamWrapper($sambaMock);
        $actualStatInfoFile = $wrapper->url_stat($urlFile);

        $this->assertEquals($expectedStatInfoFile, $actualStatInfoFile);
    }

    public function testUrlStatShare()
    {
        $urlShare = "smb://user:password@host/base_path";

        $shareLookInfo = array(
            "disk" => array("base_path"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $sambaMock = $this->getSambaClientMock(array('look'));

        $sambaMock
            ->expects($this->any())
            ->method('look')
            ->will($this->returnValue($shareLookInfo));

        $expectedStatInfoShare = stat("/tmp");

        $wrapper = new SambaStreamWrapper($sambaMock);
        $actualStatInfoShare = $wrapper->url_stat($urlShare);

        $this->assertEquals($expectedStatInfoShare, $actualStatInfoShare);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatHostException()
    {
        $sambaMock = $this->getSambaClientMock(array('look'));

        $urlHost = "smb://user:password@host";

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->url_stat($urlHost);
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

        $sambaMock = $this->getSambaClientMock(array('look'));

        $sambaMock
            ->expects($this->any())
            ->method('look')
            ->will($this->returnValue($shareLookInfo));

        $urlShare = "smb://user:password@host/base_path";

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->url_stat($urlShare);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatPathException()
    {
        $sambaMock = $this->getSambaClientMock(array('execute'));

        $urlDir = "smb://user:password@host/base_path/success";

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->url_stat($urlDir);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testUrlStatNotTypeUrlException()
    {
        $sambaMock = $this->getSambaClientMock(array('execute'));

        $url = "smb://";

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->url_stat($url);
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

        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $sambaMock
            ->expects($this->any())
            ->method('getProcessResource')
            ->will($this->returnValue($executeOutputStream));

        $urlDir = "smb://user:password@host/base_path/success";

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->url_stat($urlDir);
    }

    public function testDirOpenHost()
    {
        $urlHost = "smb://user:password@host";

        $sambaMock = $this->getSambaClientMock(array('look'));

        $lookInfo = array(
            "disk" => array("centrum"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $sambaMock
            ->expects($this->once())
            ->method('look')
            ->will($this->returnValue($lookInfo));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->dir_opendir($urlHost, 0);

        $this->assertEquals(array("centrum"), $wrapper->dir);
    }

    public function testDirOpenDir()
    {
        $urlDir = "smb://user:password@host/base_path/to/dir";

        $sambaMock = $this->getSambaClientMock(array('execute'));

        $sambaMock
            ->expects($this->any())
            ->method('execute')
            ->will($this->returnValue(SambaClientTest::getExpectedDirInfo()));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->dir_opendir($urlDir, 0);

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

        $this->assertEquals($expectedDir, $wrapper->dir);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testDirOpenDirExceptionHostNotLook()
    {
        $urlHost = "smb://user:password@host";

        $sambaMock = $this->getSambaClientMock(array('look'));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->dir_opendir($urlHost, 0);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testDirOpenDirExceptionErrorType()
    {
        $urlHost = "smb://";

        $sambaMock = $this->getSambaClientMock(array('look'));

        $wrapper = new SambaStreamWrapper($sambaMock);
        $wrapper->dir_opendir($urlHost, 0);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testStreamOpenExceptionHost()
    {
        $urlHost = "smb://user:password@host";

        $sambaMock = $this->getSambaClientMock(array('look'));

        $wrapper = new SambaStreamWrapper($sambaMock);

        $path = null;
        $wrapper->stream_open($urlHost, '', '', $path);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testStreamOpenExceptionShare()
    {
        $urlHost = "smb://user:password@host/share";

        $sambaMock = $this->getSambaClientMock(array('look'));

        $wrapper = new SambaStreamWrapper($sambaMock);

        $path = null;
        $wrapper->stream_open($urlHost, '', '', $path);
    }
}
