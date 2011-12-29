<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

/**
 * Storage for profiler using files.
 *
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class FileProfilerStorage implements ProfilerStorageInterface
{
    /**
     * Folder where profiler data are stored.
     *
     * @var string
     */
    private $folder;

    /**
     * Constructs the file storage using a "dsn-like" path.
     *
     * Example : "file:/path/to/the/storage/folder"
     *
     * @param string $dsn The DSN
     */
    public function __construct($dsn)
    {
        if (0 !== strpos($dsn, 'file:')) {
            throw new \InvalidArgumentException("FileStorage DSN must start with file:");
        }
        $this->folder = substr($dsn, 5);

        if (!is_dir($this->folder)) {
            mkdir($this->folder);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function find($ip, $url, $limit, $method)
    {
        $result = array();

        try {
            $file = new \SplFileObject($this->getIndexFilename());
        } catch (\RuntimeException $e) {
            return $result;
        }

        $file->fseek(0, SEEK_END);
        while (0 < $limit) {
            $line = $this->readLineFromFile($file);

            if (false === $line) {
                break;
            }

            if ('' === $line) {
                continue;
            }

            list($csvToken, $csvIp, $csvMethod, $csvUrl, $csvTime, $csvParent) = str_getcsv($line);

            if ($ip && false === strpos($csvIp, $ip) || $url && false === strpos($csvUrl, $url) || $method && false === strpos($csvMethod, $method)) {
                continue;
            }

            $result[] = array(
                'token'  => $csvToken,
                'ip'     => $csvIp,
                'method' => $csvMethod,
                'url'    => $csvUrl,
                'time'   => $csvTime,
                'parent' => $csvParent,
            );

            --$limit;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function purge()
    {
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveDirectoryIterator($this->folder, $flags);
        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $file) {
            if (is_file($file)) {
                unlink($file);
            } else {
                rmdir($file);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($token)
    {
        if (!$token || !file_exists($file = $this->getFilename($token))) {
            return null;
        }

        return $this->createProfileFromData($token, unserialize(file_get_contents($file)));
    }

    /**
     * {@inheritdoc}
     */
    public function write(Profile $profile)
    {
        $file = $this->getFilename($profile->getToken());

        // Create directory
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $data = array(
            'token'    => $profile->getToken(),
            'parent'   => $profile->getParentToken(),
            'children' => array_map(function ($p) { return $p->getToken(); }, $profile->getChildren()),
            'data'     => $profile->getCollectors(),
            'ip'       => $profile->getIp(),
            'method'   => $profile->getMethod(),
            'url'      => $profile->getUrl(),
            'time'     => $profile->getTime(),
        );

        // Store profile
        $file = new \SplFileObject($file, 'w');
        if (null === $file->fwrite(serialize($data))) {
            return false;
        }

        // Add to index
        try {
            $file = new \SplFileObject($this->getIndexFilename(), 'a');
            if (false === $file->flock(LOCK_EX)) {
                return false;
            }

            $file->fputcsv(array(
                $profile->getToken(),
                $profile->getIp(),
                $profile->getMethod(),
                $profile->getUrl(),
                $profile->getTime(),
                $profile->getParentToken(),
            ));

            $file->flock(LOCK_UN);
        } catch (\RuntimeException $e) {
            return false;
        }


        return true;
    }

    /**
     * Gets filename to store data, associated to the token.
     *
     * @return string The profile filename
     */
    protected function getFilename($token)
    {
        // Uses 4 last characters, because first are mostly the same.
        $folderA = substr($token, -2, 2);
        $folderB = substr($token, -4, 2);

        return $this->folder.'/'.$folderA.'/'.$folderB.'/'.$token;
    }

    /**
     * Gets the index filename.
     *
     * @return string The index filename
     */
    protected function getIndexFilename()
    {
        return $this->folder.'/index.csv';
    }

    /**
     * Reads a line in the file, ending with the current position.
     *
     * This function automatically skips the empty lines and do not include the line return in result value.
     *
     * @param \SplFileObject $file The file object, with the pointer placed at the end of the line to read
     *
     * @return mixed A string representating the line or FALSE if beginning of file is reached
     */
    protected function readLineFromFile(\SplFileObject $file)
    {
        if (0 === $file->ftell()) {
            return false;
        }

        $file->fseek(-1, SEEK_CUR);
        $str = '';

        while (true) {
            $char = $file->fgetc();
            if ("\n" === $char) {
                // Leave the file with cursor before the line return
                $file->fseek(-1, SEEK_CUR);
                break;
            }

            $str = $char.$str;
            if (1 === $file->ftell($file)) {
                // All file is read, so we move cursor to the position 0
                $file->fseek(-1, SEEK_CUR);
                break;
            }

            $file->fseek(-2, SEEK_CUR);
        }

        return '' === $str ? $this->readLineFromFile($file) : $str;
    }

    protected function createProfileFromData($token, $data, $parent = null)
    {
        $profile = new Profile($token);
        $profile->setIp($data['ip']);
        $profile->setMethod($data['method']);
        $profile->setUrl($data['url']);
        $profile->setTime($data['time']);
        $profile->setCollectors($data['data']);

        if (!$parent && $data['parent']) {
            $parent = $this->read($data['parent']);
        }

        if ($parent) {
            $profile->setParent($parent);
        }

        foreach ($data['children'] as $token) {
            if (!$token || !file_exists($file = $this->getFilename($token))) {
                continue;
            }

            $profile->addChild($this->createProfileFromData($token, unserialize(file_get_contents($file)), $profile));
        }

        return $profile;
    }
}
