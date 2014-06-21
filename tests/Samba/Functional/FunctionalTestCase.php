<?php

namespace Samba\Functional;

use Samba\SambaStreamWrapper;
use Samba\TestCase;
use FilesystemIterator;
use Symfony\Component\Filesystem\Filesystem;

class FunctionalTestCase extends TestCase
{
    /**
     * @var string
     */
    protected static $homePath;

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
    protected static $hostUrl;

    /**
     * @var string
     */
    protected static $share = 'samba-test';

    /**
     * @var string
     */
    protected static $shareUrl;

    /**
     * @var string
     */
    protected static $sharePath;

    public static function setUpBeforeClass()
    {
        self::$homePath = getenv('HOME');
        self::$user = getenv('USER');
        self::$hostname = gethostname();
        self::$hostUrl = 'smb://' . self::$user . ':' . self::$password . '@' . self::$hostname;
        self::$shareUrl = self::$hostUrl . '/' . self::$share;
        self::$sharePath = self::$homePath . '/' . self::$share;
    }

    protected function setUp()
    {
        if (!SambaStreamWrapper::is_registered()) {
            SambaStreamWrapper::register();
        }

        $this->clearTestDir();
    }

    protected function tearDown()
    {
        SambaStreamWrapper::unregister();
    }

    protected function clearTestDir()
    {
        $testDir = new FilesystemIterator(
            self::$sharePath,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_PATHNAME
        );

        $filesystem = new Filesystem();

        foreach ($testDir as $filePath) {
            $filesystem->remove($filePath);
        }
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
                '{hostUrl}' => self::$hostUrl,
                '{share}' => self::$share,
                '{shareUrl}' => self::$shareUrl,
            )
        );
    }
}
