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

use fkooman\RemoteStorage\Exception\RemoteStorageException;
use fkooman\RemoteStorage\Http\Exception\HttpException;

class RemoteStorage
{
    private MetadataStorage $md;
    private DocumentStorage $d;

    public function __construct(MetadataStorage $md, DocumentStorage $d)
    {
        $this->md = $md;
        $this->d = $d;
    }

    public function putDocument(Path $p, string $contentType, string $documentData, ?array $ifMatch = null, ?array $ifNoneMatch = null): void
    {
        if (null !== $ifMatch && !\in_array($this->md->getVersion($p), $ifMatch, true)) {
            throw new HttpException('version mismatch', 412);
        }

        if (null !== $ifNoneMatch && \in_array('*', $ifNoneMatch, true) && null !== $this->md->getVersion($p)) {
            throw new HttpException('document already exists', 412);
        }

        $updatedEntities = $this->d->putDocument($p, $documentData);
        $this->md->updateDocument($p, $contentType);
        foreach ($updatedEntities as $u) {
            $this->md->updateFolder(new Path($u));
        }
    }

    public function deleteDocument(Path $p, ?array $ifMatch = null): void
    {
        if (null !== $ifMatch && !\in_array($this->md->getVersion($p), $ifMatch, true)) {
            throw new HttpException('version mismatch', 412);
        }
        $deletedEntities = $this->d->deleteDocument($p);
        foreach ($deletedEntities as $d) {
            $this->md->deleteNode(new Path($d));
        }

        // increment the version from the folder containing the last deleted
        // folder and up to the user root, we cannot conveniently do this from
        // the MetadataStorage class :(
        foreach ($p->getFolderTreeToUserRoot() as $i) {
            if (null !== $this->md->getVersion(new Path($i))) {
                $this->md->updateFolder(new Path($i));
            }
        }
    }

    public function getVersion(Path $p): ?string
    {
        return $this->md->getVersion($p);
    }

    public function getContentType(Path $p): ?string
    {
        return $this->md->getContentType($p);
    }

    public function getDocument(Path $p, ?array $ifNoneMatch = null): string
    {
        if (null !== $ifNoneMatch && \in_array($this->md->getVersion($p), $ifNoneMatch, true)) {
            throw new RemoteStorageException('document not modified');
        }

        return $this->d->getDocumentPath($p);
    }

    public function getFolder(Path $p, ?array $ifNoneMatch = null): string
    {
        if (null !== $ifNoneMatch && \in_array($this->md->getVersion($p), $ifNoneMatch, true)) {
            throw new RemoteStorageException('folder not modified');
        }

        $f = [
            '@context' => 'http://remotestorage.io/spec/folder-description',
            'items' => $this->d->getFolder($p),
        ];
        foreach ($f['items'] as $name => $meta) {
            $f['items'][$name]['ETag'] = $this->md->getVersion(new Path($p->getFolderPath().$name));

            // if item is a folder we don't want Content-Type
            if (strrpos($name, '/') !== \strlen($name) - 1) {
                $f['items'][$name]['Content-Type'] = $this->md->getContentType(new Path($p->getFolderPath().$name));
            }
        }

        return json_encode($f, JSON_FORCE_OBJECT);
    }

    public function getFolderSize(Path $p): string
    {
        return self::sizeToHuman($this->d->getFolderSize($p));
    }

    public static function sizeToHuman(int $byteSize): string
    {
        $kB = 1024;
        $MB = $kB * 1024;
        $GB = $MB * 1024;

        if ($byteSize > $GB) {
            return sprintf('%0.2fGB', $byteSize / $GB);
        }
        if ($byteSize > $MB) {
            return sprintf('%0.2fMB', $byteSize / $MB);
        }

        return sprintf('%0.0fkB', $byteSize / $kB);
    }
}
