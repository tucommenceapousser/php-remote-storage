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

use fkooman\RemoteStorage\Exception\DocumentException;
use fkooman\RemoteStorage\Exception\DocumentMissingException;

class Document
{
    private $baseDir;

    public function __construct($baseDir)
    {
        $this->baseDir = $baseDir;
    }

    public function getBaseDir()
    {
        return $this->baseDir;
    }

    public function getDocument(Path $p)
    {
        if ($p->getIsFolder()) {
            throw new DocumentException("unable to get folder");
        }

        $documentPath = $this->baseDir . $p->getPath();
        if (false === file_exists($documentPath)) {
            throw new DocumentMissingException();
        }

        $documentContent = @file_get_contents($documentPath);
        if (false === $documentContent) {
            throw new DocumentException("unable to read document");
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
            throw new DocumentException("unable to put folder");
        }

        $folderTree = $p->getFolderTreeFromRoot();
        foreach ($folderTree as $pathItem) {
            if (!file_exists($this->baseDir . $pathItem)) {
                // create it
                if (false === @mkdir($this->baseDir . $pathItem, 0770)) {
                    throw new DocumentException("unable to create directory");
                }
            }
        }

        $documentPath = $this->baseDir . $p->getPath();
        if (false === @file_put_contents($documentPath, $documentContent)) {
            throw new DocumentException("unable to write document");
        }

        return $p->getFolderTreeFromModuleRoot();
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
            throw new DocumentException("unable to delete folder");
        }

        $documentPath = $this->baseDir . $p->getPath();

        if (false === file_exists($documentPath)) {
            throw new DocumentMissingException();
        }

        if (false === @unlink($documentPath)) {
            throw new DocumentException("unable to delete file");
        }

        $deletedObjects = array();
        $deletedObjects[] = $p->getPath();

        // delete all empty folders in the tree up to the module root if
        // they are empty
        $p = $p->getParentFolderPath();
        while (!$p->getIsModuleRoot()) {
            // not the module root
            if ($this->isEmptyFolder($p)) {
                // and it is empty, delete it
                $this->deleteFolder($p);
                $deletedObjects[] = $p->getPath();
            }
            $p = $p->getParentFolderPath();
        }

        return $deletedObjects;
    }

    public function getFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new DocumentException("not a folder");
        }

        $folderPath = $this->baseDir . $p->getPath();

        $entries = glob($folderPath . "*", GLOB_ERR|GLOB_MARK);
        if (false === $entries) {
            // directory does not exist, return empty list
            return array();
        }
        $folderEntries = array();
        foreach ($entries as $e) {
            if (is_dir($e)) {
                $folderEntries[basename($e) . "/"] = array();
            } else {
                $folderEntries[basename($e)] = array(
                    "Content-Length" => filesize($e)
                );
            }
        }

        return $folderEntries;
    }

    private function isEmptyFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new DocumentException("not a folder");
        }

        $folderPath = $this->baseDir . $p->getPath();

        $entries = glob($folderPath . "*", GLOB_ERR);
        if (false === $entries) {
            throw new DocumentException("unable to read folder");
        }

        return 0 === count($entries);
    }

    private function deleteFolder(Path $p)
    {
        if (!$p->getIsFolder()) {
            throw new DocumentException("not a folder");
        }
        $folderPath = $this->baseDir . $p->getPath();
        if (false === @rmdir($folderPath)) {
            throw new DocumentException("unable to delete folder");
        }
    }
}
