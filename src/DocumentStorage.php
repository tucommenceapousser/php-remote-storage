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

use fkooman\RemoteStorage\Exception\DocumentStorageException;
use fkooman\RemoteStorage\Http\Exception\HttpException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class DocumentStorage
{
    private $baseDir;

    public function __construct($baseDir)
    {
        // check if baseDir exists, if not, try to create it
        if (!is_dir($baseDir)) {
            if (false === @mkdir($baseDir, 0770, true)) {
                throw new RuntimeException('unable to create baseDir');
            }
        }
        $this->baseDir = $baseDir;
    }

    public function isDocument(Path $p)
    {
        $documentPath = $this->baseDir.$p->getPath();
        if (false === file_exists($documentPath) || !is_file($documentPath)) {
            return false;
        }

        return true;
    }

    /**
     * Get the full absolute location of the document on the filesystem.
     */
    public function getDocumentPath(Path $p)
    {
        $documentPath = $this->baseDir.$p->getPath();
        if (!is_readable($documentPath)) {
            throw new DocumentStorageException('unable to read document');
        }

        return $documentPath;
    }

    public function getDocument(Path $p)
    {
        $documentPath = $this->baseDir.$p->getPath();
        if (!is_readable($documentPath)) {
            throw new DocumentStorageException('unable to read document');
        }
        $documentContent = @file_get_contents($documentPath);
        if (false === $documentContent) {
            throw new DocumentStorageException('error reading document');
        }

        return $documentContent;
    }

    /**
     * Store a new document.
     *
     * @returns an array of all created objects
     *
     * @param mixed $documentContent
     */
    public function putDocument(Path $p, $documentContent)
    {
        $folderTree = $p->getFolderTreeFromUserRoot();
        foreach ($folderTree as $pathItem) {
            $folderPath = $this->baseDir.$pathItem;
            $folderPathAsFile = substr($folderPath, 0, \strlen($folderPath) - 1);
            if (file_exists($folderPathAsFile) && is_file($folderPathAsFile)) {
                throw new HttpException('file already exists in path preventing folder creation', 409);
            }
            if (!file_exists($folderPath)) {
                // create it
                if (false === @mkdir($this->baseDir.$pathItem, 0770)) {
                    throw new DocumentStorageException('unable to create directory');
                }
            }
        }

        $documentPath = $this->baseDir.$p->getPath();
        if (file_exists($documentPath) && is_dir($documentPath)) {
            throw new HttpException('document path is already a folder', 409);
        }
        if (false === @file_put_contents($documentPath, $documentContent, \LOCK_EX)) {
            throw new DocumentStorageException('unable to write document');
        }
        // PHP caches files and doesn't flush on getting file size, so we
        // really have to flush the cache manually, otherwise directory listings
        // potentially give you the wrong information. This only affects the
        // unit tests, as getting a directory listing and putting a file are
        // always separate script executions
        clearstatcache(true, $documentPath);

        return $folderTree;
    }

    /**
     * Delete a document and all empty parent directories if there are any.
     */
    public function deleteDocument(Path $p): array
    {
        $documentPath = $this->baseDir.$p->getPath();
        if (false === @unlink($documentPath)) {
            throw new DocumentStorageException('unable to delete file');
        }

        $deletedObjects = [];
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

    public function isFolder(Path $p)
    {
        $folderPath = $this->baseDir.$p->getPath();
        if (false === file_exists($folderPath) || !is_dir($folderPath)) {
            return false;
        }

        return true;
    }

    public function getFolder(Path $p)
    {
        $folderPath = $this->baseDir.$p->getPath();
        $entries = glob($folderPath.'*', \GLOB_ERR | \GLOB_MARK);
        if (false === $entries) {
            // directory does not exist, return empty list
            return [];
        }
        $folderEntries = [];
        foreach ($entries as $e) {
            if (is_dir($e)) {
                $folderEntries[basename($e).'/'] = [];
            } else {
                $folderEntries[basename($e)] = [
                    'Content-Length' => filesize($e),
                ];
            }
        }

        return $folderEntries;
    }

    public function getFolderSize(Path $p)
    {
        if (!$this->isFolder($p)) {
            return 0;
        }
        $folderPath = $this->baseDir.$p->getPath();
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folderPath)) as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function isEmptyFolder(Path $p)
    {
        $folderPath = $this->baseDir.$p->getPath();

        $entries = glob($folderPath.'*', \GLOB_ERR);
        if (false === $entries) {
            throw new DocumentStorageException('unable to read folder');
        }

        return 0 === \count($entries);
    }

    private function deleteFolder(Path $p)
    {
        $folderPath = $this->baseDir.$p->getPath();
        if (false === @rmdir($folderPath)) {
            throw new DocumentStorageException('unable to delete folder');
        }
    }
}
