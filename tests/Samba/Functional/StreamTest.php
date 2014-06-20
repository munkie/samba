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

    /**
     * @var string
     */
    protected $localFolder = '/home/mshamin/';

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
     * @expectedException \PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessage stat failed for
     */
    public function testStatNotExists($url)
    {
        $url = strtr(
            $url,
            array(
                '{hostname}' => self::$hostname,
                '{share}' => self::$share
            )
        );

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

    public function testStat()
    {
        mkdir(self::$localPath . '/test-stat');
        file_put_contents(self::$localPath . '/test-stat/file.nfo', 'text');

        $stat = stat(self::$url . '/test-stat');
        $this->assertSame(array(), $stat);
    }
}
