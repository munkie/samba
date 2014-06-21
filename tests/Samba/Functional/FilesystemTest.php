<?php

namespace Samba\Functional;

class FilesystemTest extends FunctionalTestCase
{
    public function testMkDir()
    {
        $url = self::$shareUrl . '/test-dir';
        $result = mkdir($url);
        $this->assertTrue($result);

        $localPath = self::$localPath . '/test-dir';
        $this->assertTrue(file_exists($localPath));
        $this->assertTrue(is_dir($localPath));
    }

    public function testRmDir()
    {
        $localPath = self::$localPath . '/test-dir';
        mkdir($localPath);

        $this->assertTrue(file_exists($localPath));
        $this->assertTrue(is_dir($localPath));

        $url = self::$shareUrl . '/test-dir';
        $result = rmdir($url);
        $this->assertTrue($result);
        $this->assertFalse(file_exists($localPath));
        $this->assertFalse(file_exists($url));
    }

    /**
     * @dataProvider statNotExistsProvider
     * @expectedException \Samba\SambaException
     *
     * @param string $url
     */
    public function testStatNotExists($url)
    {
        $url = $this->urlSub($url);

        $this->assertFalse(file_exists($url));
        stat($url);
    }

    /**
     * @return array
     */
    public function statNotExistsProvider()
    {
        return array(
            'host' => array('smb://not-found'),
            'share' => array('smb://{hostname}/share-test-not-found'),
            'path' => array('smb://{hostname}/{share}/file-not-found'),
        );
    }

    /**
     * @dataProvider pathStatProvider
     * @param string $path
     */
    public function testPathStat($path)
    {
        file_put_contents(self::$localPath . '/first.nfo', 'first');
        mkdir(self::$localPath . '/first-stat');
        file_put_contents(self::$localPath . '/first-stat/second.nfo', 'second');
        mkdir(self::$localPath . '/first-stat/second-stat');
        file_put_contents(self::$localPath . '/first-stat/second-stat/third.nfo', 'third');

        touch(self::$localPath . $path, 1403344333);
        $url = self::$shareUrl . '/' . $path;

        $smbStat = stat($url);
        $this->assertInternalType('array', $smbStat);
        $this->assertArrayHasKey('mtime', $smbStat);
        $this->assertEquals(1403344333, $smbStat['mtime']);
    }

    /**
     * @return array
     */
    public function pathStatProvider()
    {
        return array(
            'file first level' => array('/first.nfo', ),
            'folder first level' => array('/first-stat'),
            'file second level' => array('/first-stat/second.nfo'),
            'folder second level' => array('/first-stat/second-stat'),
            'file third level' => array('/first-stat/second-stat/third.nfo'),
        );
    }

    public function testHostStat()
    {
        $stat = stat(self::$hostUrl);
        $this->assertStat($stat);
    }

    public function testShareStat()
    {
        $stat = stat(self::$shareUrl);
        $this->assertStat($stat);
    }

    public function testDir()
    {
        $dirPath = self::$localPath . '/dir-test';

        mkdir($dirPath);
        file_put_contents($dirPath . '/one', 'content');
        file_put_contents($dirPath . '/second.txt', 'more content');
        mkdir($dirPath . '/sub-dir');

        $dh = opendir(self::$shareUrl . '/dir-test');

        $this->assertInternalType('resource', $dh);

        $this->assertEquals('second.txt', readdir($dh));
        $this->assertEquals('sub-dir', readdir($dh));
        $this->assertEquals('one', readdir($dh));
        $this->assertFalse(readdir($dh));

        rewinddir($dh);

        $this->assertEquals('second.txt', readdir($dh));

        closedir($dh);
    }

    public function testDirShare()
    {
        file_put_contents(self::$localPath . '/one', 'content');
        file_put_contents(self::$localPath . '/second.txt', 'more content');
        mkdir(self::$localPath . '/sub-dir');

        $dh = opendir(self::$shareUrl);

        $this->assertInternalType('resource', $dh);

        $this->assertEquals('second.txt', readdir($dh));
        $this->assertEquals('sub-dir', readdir($dh));
        $this->assertEquals('one', readdir($dh));
        $this->assertFalse(readdir($dh));
    }

    public function testDirEmpty()
    {
        $dh = opendir(self::$shareUrl);

        $this->assertInternalType('resource', $dh);

        $this->assertFalse(readdir($dh));
    }

    public function testDirHost()
    {
        $dh = opendir(self::$hostUrl);
        $files = array();
        while (false !== ($file = readdir($dh))) {
            $files[] = $file;
        }

        $this->assertContains(self::$share, $files);
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage NT_STATUS_OBJECT_PATH_NOT_FOUND listing
     */
    public function testDirNotExists()
    {
        opendir(self::$shareUrl . '/not-found-dir');
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage dir_opendir(): error in URL
     */
    public function testDirInvalidUrl()
    {
        opendir('smb://');
    }
}
