<?php

namespace Samba\Functional;

use Samba\SambaStreamWrapper;
use Samba\TestCase;
use FilesystemIterator;
use Symfony\Component\Filesystem\Filesystem;

class StreamTest extends TestCase
{
    /**
     * @var string
     */
    protected static $home;

    /**
     * @var string
     */
    protected static $user;
    /**
     * @var string
     */
    protected static $hostname;

    /**
     * @var string
     */
    protected static $password = 'password';

    /**
     * @var string
     */
    protected static $host;

    /**
     * @var string
     */
    protected static $share = 'samba-test';

    /**
     * @var string
     */
    protected static $url;

    /**
     * @var
     */
    protected static $localPath;

    public static function setUpBeforeClass()
    {
        self::$home = getenv('HOME');
        self::$user = getenv('USER');
        self::$hostname = gethostname();
        self::$host = 'smb://' . self::$user . ':' . self::$password . '@' . self::$hostname;
        self::$url = self::$host . '/' . self::$share;
        self::$localPath = self::$home . '/' . self::$share;
    }

    protected function setUp()
    {
        if (!SambaStreamWrapper::is_registered()) {
            SambaStreamWrapper::register();
        }

        $this->clearTestDir();
    }

    protected function clearTestDir()
    {
        $testDir = new FilesystemIterator(
            self::$localPath,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME
        );

        $filesystem = new Filesystem();

        foreach ($testDir as $filePath) {
            $filesystem->remove($filePath);
        }
    }

    protected function tearDown()
    {
        SambaStreamWrapper::unregister();
    }

    public function testMkDir()
    {
        $url = self::$url . '/test-dir';
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

        $url = self::$url . '/test-dir';
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
        $url = $this->urlSub('{url}' . $path);

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
        $stat = stat(self::$host);
        $this->assertStat($stat);
    }

    public function testShareStat()
    {
        $stat = stat(self::$url);
        $this->assertStat($stat);
    }

    /**
     * @param string $url
     * @return string
     */
    protected function urlSub($url)
    {
        return strtr(
            $url,
            array(
                '{hostname}' => self::$hostname,
                '{share}' => self::$share,
                '{url}' => self::$url,
            )
        );
    }
}
