<?php

declare(strict_types=1);

/*
 * php-remote-storage - PHP remoteStorage implementation
 *
 * Copyright: 2016 SURFnet
 * Copyright: 2022 François Kooman <fkooman@tuxed.net>
 *
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace fkooman\RemoteStorage;

use fkooman\RemoteStorage\Exception\DocumentStorageException;
use fkooman\RemoteStorage\Http\Exception\HttpException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class DocumentStorage
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        // check if baseDir exists, if not, try to create it
        if (!is_dir($baseDir)) {
            if (false === @mkdir($baseDir, 0770, true)) {
                throw new RuntimeException('unable to create baseDir');
            }
        }
        $this->baseDir = $baseDir;
    }

    public function isDocument(Path $p): bool
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
    public function getDocumentPath(Path $p): string
    {
        $documentPath = $this->baseDir.$p->getPath();
        if (!is_readable($documentPath)) {
            throw new DocumentStorageException('unable to read document');
        }

        return $documentPath;
    }

    public function getDocument(Path $p): string
    {
        $documentPath = $this->baseDir.$p->getPath();
        if (false === $documentContent = file_get_contents($documentPath)) {
            throw new DocumentStorageException('unable to read document');
        }

        return $documentContent;
    }

    /**
     * Store a new document.
     */
    public function putDocument(Path $p, string $documentContent): array
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
        if (false === @file_put_contents($documentPath, $documentContent, LOCK_EX)) {
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
     *
     * @return array<string>
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

    public function isFolder(Path $p): bool
    {
        $folderPath = $this->baseDir.$p->getPath();
        if (false === file_exists($folderPath) || !is_dir($folderPath)) {
            return false;
        }

        return true;
    }

    public function getFolder(Path $p): array
    {
        /**
         * XXX one cannot put "numeric" files as a key in an array as PHP will
         * convert it to int, no matter what you do... true for folder and
         * file names!
         *
         * @see https://lobste.rs/s/t8cdqz/php_frankenstein_arrays#c_lceuqy
         */
        $folderPath = $this->baseDir.$p->getPath();
        $entries = glob($folderPath.'*', GLOB_ERR | GLOB_MARK);
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

    public function getFolderSize(Path $p): int
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

    private function isEmptyFolder(Path $p): bool
    {
        $folderPath = $this->baseDir.$p->getPath();

        $entries = glob($folderPath.'*', GLOB_ERR);
        if (false === $entries) {
            throw new DocumentStorageException('unable to read folder');
        }

        return 0 === \count($entries);
    }

    private function deleteFolder(Path $p): void
    {
        $folderPath = $this->baseDir.$p->getPath();
        if (false === @rmdir($folderPath)) {
            throw new DocumentStorageException('unable to delete folder');
        }
    }
}
