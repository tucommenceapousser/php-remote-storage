<?php

namespace fkooman\RemoteStorage;

use fkooman\RemoteStorage\Exception\PathException;

class Path
{
    /** @var string */
    private $userId;

    /** @var boolean */
    private $isPublic;

    /** @var string */
    private $moduleName;

    /** @var boolean */
    private $isFolder;

    /** @var boolean */
    private $isModuleRoot;

    /** @var string */
    private $path;

    public function __construct($path)
    {
        if (!is_string($path)) {
            throw new PathException("path must be a string");
        }
        if (0 !== strpos($path, "/")) {
            throw new PathException("path must start with a '/'");
        }

        $entityParts = explode("/", $path);
        $partCount = count($entityParts);
        if (4 > $partCount) {
            throw new PathException("path must include user and module folder");
        }
        $this->isModuleRoot = (4 === $partCount && 0 === strlen($entityParts[3]));

        if ("public" === $entityParts[2]) {
            // if public, the entityParts need to contain an extra as "public" does not count then
            if (5 > $partCount) {
                throw new PathException("public path must include user and module folder");
            }
            $this->isModuleRoot = (5 === $partCount && 0 === strlen($entityParts[4]));
        }

        // path parts cannot be empty, except the last one
        for ($i = 1; $i < $partCount-1; $i++) {
            if (0 >= strlen($entityParts[$i])) {
                throw new PathException("path part cannot be empty");
            }
        }

        $this->userId = $entityParts[1];
        $this->isPublic = "public" === $entityParts[2];
        $this->moduleName = ($this->isPublic) ? $entityParts[3] : $entityParts[2];
        $this->isFolder = empty($entityParts[count($entityParts)-1]);
        $this->path = $path;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getIsPublic()
    {
        return $this->isPublic;
    }

    public function getModuleName()
    {
        return $this->moduleName;
    }

    public function getIsFolder()
    {
        return $this->isFolder;
    }

    public function getIsDocument()
    {
        return !$this->isFolder;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getIsModuleRoot()
    {
        return $this->isModuleRoot;
    }

    public function getParentFolderPath()
    {
        if ($this->getIsModuleRoot()) {
            return false;
        }

        return new Path(dirname($this->getPath()) . "/");
    }

    public function getFolderPath()
    {
        if ($this->getIsFolder()) {
            return $this;
        }

        return $this->getParentFolderPath();
    }

    public function getFolderTreeFromRoot()
    {
        $p = $this->getFolderPath()->getPath();
        $folderTree = array($p);

        do {
            $rpos = strrpos($p, "/", -2);
            $p = substr($p, 0, $rpos + 1);
            $folderTree[] = $p;
        } while (0 !== strrpos($p, "/", -2));
        $folderTree[] = "/";

        return array_reverse($folderTree);
    }

    public function getFolderTreeFromModuleRoot()
    {
        $folderTree = array();

        $folderPath = $this->getFolderPath();

        do {
            $folderTree[] = $folderPath->getPath();
            $folderPath = $folderPath->getParentFolderPath();
        } while (false !== $folderPath);

        return array_reverse($folderTree);
    }
}
