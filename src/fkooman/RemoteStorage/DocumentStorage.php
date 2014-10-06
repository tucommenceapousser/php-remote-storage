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

use fkooman\Http\Exception\NotFoundException;
use fkooman\Http\Exception\ConflictException;
use fkooman\Http\Exception\InternalServerErrorException;

class DocumentStorage
{
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function getDocument(Path $p)
    {
        if ($p->getIsFolder()) {
            throw new BadRequestException("unable to get folder");
        }

        $documentPath = $this->baseDir.$p->getPath();
        if (false === file_exists($documentPath)) {
            throw new NotFoundException("document not found");
        }

        $documentContent = @file_get_contents($documentPath);
        if (false === $documentContent) {
            throw new InternalServerErrorException("unable to read document");
        }

        return $documentContent;
    }

    /**
     * Store a new document.
     *
     * @returns an array of all created objects
     */
    public function putDocument(Path $p, $documentContent)
    {
        if ($p->getIsFolder()) {
            throw new BadRequestException("unable to put folder");
        }

        $folderTree = $p->getFolderTreeFromUserRoot();
        foreach ($folderTree as $pathItem) {
            $folderPath = $this->baseDir.$pathItem;
            $folderPathAsFile = substr($folderPath, 0, strlen($folderPath)-1);
            if (file_exists($folderPathAsFile) && is_file($folderPathAsFile)) {
                throw new ConflictException("file already exists in path preventing folder creation");
            }
            if (!file_exists($folderPath)) {
                // create it
                if (false === @mkdir($this->baseDir.$pathItem, 0770)) {
                    throw new InternalServerErrorException("unable to create directory");
                }
            }
        }

        $documentPath = $this->baseDir.$p->getPath();
        if (file_exists($documentPath) && is_dir($documentPath)) {
            throw new ConflictException("document path is already a folder");
        }
        if (false === @file_put_contents($documentPath, $documentContent)) {
            throw new InternalServerErrorException("unable to write document");
        }
        // PHP caches files and doesn't flush on getting file size, so we
        // really have to flush the cache manually, otherwise directory listings
        // potentially give you the wrong information. This only affects the
        // unit tests, as getting a directory listing and putting a file are
        // always separated executions
        clearstatcache(true, $documentPath);

        return $folderTree;
    }

    /**
     * Delete a document and all empty parent directories if there are any.
     *
     * @param $p the path of a document to delete
     * @returns an array of all deleted objects
     */
    public function deleteDocument(Path $p)
    {
        if ($p->getIsFolder()) {
            throw new BadRequestException("unable to delete folder");
        }

        $documentPath = $this->baseDir.$p->getPath();

        if (false === file_exists($documentPath)) {
            throw new NotFoundException("document not found");
        }

        if (false === @unlink($documentPath)) {
            throw new InternalServerErrorException("unable to delete file");
        }

        $deletedObjects = array();
        $deletedObjects[] = $p->getPath();

        // delete all empty folders in the tree up to the user root if
        // they are empty
        foreach ($p->getFolderTreeToUserRoot() as $pathItem) {
            if ($this->isEmptyFolder(new Path($pathItem))) {
                $this->deleteFolder(new Path($pathItem));
                $deletedObjects[] = $pathItem;
            }
        }

        return $deletedObjects;
    }

    public function getFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new BadRequestException("not a folder");
        }

        $folderPath = $this->baseDir.$p->getPath();

        $entries = glob($folderPath."*", GLOB_ERR|GLOB_MARK);
        if (false === $entries) {
            // directory does not exist, return empty list
            return array();
        }
        $folderEntries = array();
        foreach ($entries as $e) {
            if (is_dir($e)) {
                $folderEntries[basename($e)."/"] = array();
            } else {
                $folderEntries[basename($e)] = array(
                    "Content-Length" => filesize($e),
                );
            }
        }

        return $folderEntries;
    }

    private function isEmptyFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new BadRequestException("not a folder");
        }

        $folderPath = $this->baseDir.$p->getPath();

        $entries = glob($folderPath."*", GLOB_ERR);
        if (false === $entries) {
            throw new InternalServerErrorException("unable to read folder");
        }

        return 0 === count($entries);
    }

    private function deleteFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new BadRequestException("not a folder");
        }
        $folderPath = $this->baseDir.$p->getPath();
        if (false === @rmdir($folderPath)) {
            throw new InternalServerErrorException("unable to delete folder");
        }
    }
}
