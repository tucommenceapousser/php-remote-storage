<?php

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

    public function putDocument(Path $p, $documentContent)
    {
        $parentFolder = $this->baseDir . $p->getParentFolder();

        // check if parent folder exists
        if (!file_exists($parentFolder)) {
            if (false === @mkdir($parentFolder, 0770, true)) {
                throw new DocumentException("unable to create directory");
            }
        }

        $documentPath = $this->baseDir . $p->getPath();
        if (false === @file_put_contents($documentPath, $documentContent)) {
            throw new DocumentException("unable to write document");
        }
    }

    public function deleteDocument(Path $p)
    {
        $documentPath = $this->baseDir . $p->getPath();
        if (false === file_exists($documentPath)) {
            throw new DocumentMissingException();
        }
        if (false === @unlink($documentPath)) {
            throw new DocumentException("unable to delete file");
        }
    }
}
