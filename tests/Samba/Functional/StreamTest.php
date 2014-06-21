<?php

namespace Samba\Functional;

class StreamTest extends FunctionalTestCase
{
    protected function setUp()
    {
        parent::setUp();

        copy(__DIR__ . '/../../../LICENSE', self::$sharePath . '/LICENSE');
    }

    public function testWrite()
    {
        $fh = fopen(self::$shareUrl . '/file.nfo', 'w');
        $this->assertInternalType('resource', $fh);

        $result = fwrite($fh, 'test');
        $this->assertEquals(4, $result);

        $result = fclose($fh);
        $this->assertTrue($result);

        clearstatcache();

        $localPath = self::$sharePath . '/file.nfo';
        $this->assertTrue(file_exists($localPath));
        $this->assertEquals('test', file_get_contents($localPath));
    }

    public function testGets()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');

        $this->assertEquals("The MIT License (MIT)\n", fgets($fh));
    }

    public function testSeek()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');

        $result = fseek($fh, 4);
        $this->assertSame(0, $result);

        $this->assertEquals("MIT License (MIT)\n", fgets($fh));
    }

    public function testFailedSeek()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');
        $result = fseek($fh, -1);
        $this->assertSame(-1, $result);
    }

    public function testTell()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');

        $this->assertSame(0, ftell($fh));

        fseek($fh, 10);

        $this->assertSame(10, ftell($fh));
    }

    public function testFlushOnDestruct()
    {
        $localPath = self::$sharePath . '/write.txt';
        file_put_contents($localPath, "Header\n");

        $fh = fopen(self::$shareUrl . '/write.txt', 'a+');
        fwrite($fh, "Footer\n");

        $this->assertEquals("Header\n", file_get_contents($localPath));

        unset($fh);

        $this->assertEquals("Header\nFooter\n", file_get_contents($localPath));
    }

    /**
     * @dataProvider failedOpenProvider
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage stream_open(): error in URL
     * @param string $url
     */
    public function testFailedOpen($url)
    {
        $url = $this->urlSub($url);
        fopen($url, 'r');
    }

    /**
     * @return array
     */
    public function failedOpenProvider()
    {
        return array(
            'host' => array('{hostUrl}'),
            'share' => array('{shareUrl}'),
            'invalid' => array('smb://'),
        );
    }

    public function testStat()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');

        $stat = fstat($fh);
        $this->assertStat($stat);
        $this->assertEquals(1066, $stat['size']);
    }
}
