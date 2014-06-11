<?php

namespace Samba;

class SambaClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|SambaClient
     */
    protected function getSambaClientMock(array $methods)
    {
        return $this->getMock('\\Samba\\SambaClient', $methods);
    }

    /**
     * @param string $string
     * @return resource
     */
    public static function convertStringToResource($string)
    {
        $stream = fopen('php://memory', 'r+');
        if (false !== $string) {
            fwrite($stream, $string);
            rewind($stream);
        }
        return $stream;
    }

    public function testAuthRequest()
    {
        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $payload = $this->convertStringToResource(
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
            ->will($this->returnValue($payload));

        $expectedLookInfo = array(
            "disk" => array("centrum"),
            "server" => array("vm6"),
            "workgroup" => array("cmag", "mygroup"),
        );

        $urlFile = "smb://user:password@host/base_path/to/dir/file.doc";

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

        $dirInfo = $sambaMock->execute('dir "' . $parsedUrlDir['path'] . '\*""', $parsedUrlDir);

        $expectedDirInfo = $this->getExpectedDirInfo();

        $this->assertEquals($expectedDirInfo, $dirInfo);
    }

    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testRequestError()
    {
        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $errorResponseStream = $this->convertStringToResource('tree connect failed: test');

        $sambaMock
            ->expects($this->once())
            ->method('getProcessResource')
            ->will($this->returnValue($errorResponseStream));

        $urlDir = "smb://user:password@host/base_path/to/dir";

        $parsedUrlDir = $sambaMock->parseUrl($urlDir);

        $sambaMock->client('-L ' . escapeshellarg($urlDir), $parsedUrlDir);
    }


    /**
     * @expectedException \Samba\SambaWrapperException
     */
    public function testBadNetworkNameError()
    {
        $urlDir = "smb://user:password@host/base_path/to/dir";

        $sambaMock = $this->getSambaClientMock(array('getProcessResource'));

        $expectedResponseStream = $this->convertStringToResource(
            "Connection to faro.lighthouse.pro failed (Error NT_STATUS_BAD_NETWORK_NAME)\n"
        );
        $sambaMock
            ->expects($this->any())
            ->method('getProcessResource')
            ->will($this->returnValue($expectedResponseStream));

        $parsedUrlDir = $sambaMock->parseUrl($urlDir);

        $sambaMock->execute('dir "' . $parsedUrlDir['path'] . '\*"', $parsedUrlDir);
    }

    /**
     * @return array
     */
    public function getExpectedDirInfo()
    {
        return array(
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
    }
}
