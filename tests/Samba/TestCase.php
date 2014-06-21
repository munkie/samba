<?php

namespace Samba;

class TestCase extends \PHPUnit_Framework_TestCase
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
    protected function convertStringToResource($string)
    {
        $stream = fopen('php://memory', 'r+');
        if (false !== $string) {
            fwrite($stream, $string);
            rewind($stream);
        }
        return $stream;
    }

    /**
     * @return array
     */
    protected function getExpectedDirInfo()
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

    /**
     * @param string $file
     * @param int $size
     * @param int $time
     * @return array
     */
    protected function createStatInfo($file, $size, $time)
    {
        $stat = stat($file);
        $stat[7] = $stat['size'] = $size;
        $stat[8]
            = $stat[9]
            = $stat[10]
            = $stat['atime']
            = $stat['mtime']
            = $stat['ctime']
            = $time;
        return $stat;
    }

    /**
     * @param array $stat
     */
    protected function assertStat(array $stat)
    {
        $expectedKeys = array_merge(
            range(0, 12),
            array(
                'dev',
                'ino',
                'mode',
                'nlink',
                'uid',
                'gid',
                'rdev',
                'size',
                'atime',
                'mtime',
                'ctime',
                'blksize',
                'blocks',
            )
        );
        $this->assertEquals($expectedKeys, array_keys($stat));
    }

    /**
     * @param array $expected
     * @param array $actual
     * @param string $message
     */
    protected function assertArrayEquals($expected, $actual, $message = '')
    {
        $this->assertEquals($expected, $actual, $message, 0, 10, true);
    }
}
