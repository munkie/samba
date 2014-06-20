<?php

namespace Samba\Functional;

use Samba\SambaStreamWrapper;
use Samba\TestCase;
use FilesystemIterator;

class StreamTest extends TestCase
{
    /**
     * @var string
     */
    protected static $host;

    /**
     * @var string
     */
    protected static $user;

    /**
     * @var string
     */
    protected static $home;

    /**
     * @var string
     */
    protected static $password = 'password';

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
    protected static $dir = 'samba-test';

    /**
     * @var string
     */
    protected $localFolder = '/home/mshamin/';

    public static function setUpBeforeClass()
    {
        self::$home = getenv('HOME');
        self::$user = getenv('USER');
        self::$host = gethostname();
        self::$url = 'smb://' . self::$user . ':' . self::$password . '@' . self::$host . '/' . self::$dir;
        self::$localPath = self::$home . '/' . self::$dir;
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
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
        );
        /* @var \SplFileInfo $fileInfo */
        foreach ($testDir as $fileInfo) {
            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());
            } else {
                unlink($fileInfo->getPathname());
            }
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
}
