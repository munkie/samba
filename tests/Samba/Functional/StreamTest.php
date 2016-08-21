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
        static::assertResource($fh);

        $result = fwrite($fh, 'test');
        static::assertSame(4, $result);

        $result = fclose($fh);
        static::assertTrue($result);

        clearstatcache();

        $localPath = self::$sharePath . '/file.nfo';
        static::assertFileExists($localPath);
        static::assertFileEquals('test', $localPath);
    }

    public function testGets()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');

        static::assertSame("The MIT License (MIT)\n", fgets($fh));
    }

    public function testSeek()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');

        $result = fseek($fh, 4);
        static::assertSame(0, $result);

        static::assertSame("MIT License (MIT)\n", fgets($fh));
    }

    public function testFailedSeek()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');
        $result = fseek($fh, -1);
        static::assertSame(-1, $result);
    }

    public function testTell()
    {
        $fh = fopen(self::$shareUrl . '/LICENSE', 'r');

        static::assertSame(0, ftell($fh));

        fseek($fh, 10);

        static::assertSame(10, ftell($fh));
    }

    public function testFlushOnDestruct()
    {
        $localPath = self::$sharePath . '/write.txt';
        file_put_contents($localPath, "Header\n");

        $fh = fopen(self::$shareUrl . '/write.txt', 'a+');
        fwrite($fh, "Footer\n");

        static::assertFileEquals("Header\n", $localPath);

        unset($fh);

        static::assertFileEquals("Header\nFooter\n", $localPath);
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
        static::assertStat($stat);
        static::assertSame(1066, $stat['size']);
    }
}
