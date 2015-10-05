<?php

namespace fkooman\RemoteStorage;

use fkooman\RemoteStorage\Exception\PathException;

class Path
{
    /** @var string */
    private $p;

    /** @var array */
    private $pathParts;

    public function __construct($p)
    {
        if (!is_string($p)) {
            throw new PathException('invalid path: not a string');
        }

        // must contain at least one slash and start with it
        if (0 !== strpos($p, '/')) {
            throw new PathException('invalid path: does not start with /');
        }

        // must not contain ".."
        if (false !== strpos($p, '..')) {
            throw new PathException('invalid path: contains ..');
        }

        // must not contain "//"
        if (false !== strpos($p, '//')) {
            throw new PathException('invalid path: contains //');
        }

        // must at least contain a user
        $pathParts = explode('/', $p);
        if (count($pathParts) < 3) {
            throw new PathException('invalid path: no user specified');
        }

        $this->p = $p;
        $this->pathParts = $pathParts;
    }

    public function getPath()
    {
        return $this->p;
    }

    public function getIsPublic()
    {
        return count($this->pathParts) > 3 && 'public' === $this->pathParts[2];
    }

    public function getUserId()
    {
        return $this->pathParts[1];
    }

    public function getIsFolder()
    {
        return empty($this->pathParts[count($this->pathParts) - 1]);
    }

    public function getIsDocument()
    {
        return !$this->getIsFolder();
    }

    public function getModuleName()
    {
        $moduleNamePosition = $this->getIsPublic() ? 3 : 2;
        if (count($this->pathParts) > $moduleNamePosition + 1) {
            return $this->pathParts[$moduleNamePosition];
        }

        return false;
    }

    public function getFolderPath()
    {
        if ($this->getIsFolder()) {
            return $this->p;
        }

        return substr($this->p, 0, strrpos($this->p, '/') + 1);
    }

    public function getFolderTreeToUserRoot()
    {
        $p = $this->getFolderPath();
        do {
            $folderTree[] = $p;

            // remove from last "/" to previous "/", e.g.:
            // "/foo/bar/baz/" -> "/foo/bar/"

            // remove the last "/"
            $p = substr($p, 0, strlen($p) - 1);
            // remove everything after the now last "/"
            $p = substr($p, 0, strrpos($p, '/') + 1);
        } while (substr_count($p, '/') > 1);

        return $folderTree;
    }

    public function getFolderTreeFromUserRoot()
    {
        return array_reverse($this->getFolderTreeToUserRoot());
    }
}
