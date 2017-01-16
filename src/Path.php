<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

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
        //        error_log($p);

        if (!is_string($p)) {
            throw new PathException('invalid path: not a string');
        }

        // MUST contain at least one slash and start with it
        if (0 !== strpos($p, '/')) {
            throw new PathException('invalid path: does not start with /');
        }

        // MUST NOT contain encoded "/"
        if (false !== stripos($p, '%2f')) {
            throw new PathException('invalid path: contains encoded "/"');
        }

        // MUST NOT contain encoded "\0"
        if (false !== strpos($p, '%00')) {
            throw new PathException('invalid path: contains encoded "\0"');
        }

        // MUST NOT contain ".."
        if (false !== strpos($p, '..')) {
            throw new PathException('invalid path: contains ..');
        }

        // MUST NOT contain "%2e%2e"
        if (false !== stripos($p, '%2e%2e')) {
            throw new PathException('invalid path: contains encoded ".."');
        }

        // MUST NOT contain "//"
        if (false !== strpos($p, '//')) {
            throw new PathException('invalid path: contains //');
        }

        // MUST contain a user
        $pathParts = explode('/', $p);
        if (count($pathParts) < 3) {
            throw new PathException('invalid path: no user specified');
        }

        foreach ($pathParts as $pathPart) {
            $this->pathParts[] = rawurldecode($pathPart);
        }
        $this->p = implode('/', $this->pathParts);
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
