<?php

namespace Samba\Functional;

use Samba\SambaStreamWrapper;
use Samba\TestCase;

class StreamTest extends TestCase
{
    /**
     * @var string
     */
    protected $smbHost;

    /**
     * @var string
     */
    protected $localFolder = '/home/mshamin/';

    protected function setUp()
    {
        if (!SambaStreamWrapper::is_registered()) {
            SambaStreamWrapper::register();
        }
        $this->smbHost = 'smb://mshamin:samba@mshamin-ubuntu';
    }

    protected function tearDown()
    {
        SambaStreamWrapper::unregister();
    }

    public function testMkDir()
    {
        $result = mkdir($this->smbHost . '/samba-test/test-dir');
        $this->assertTrue($result);
    }
}
