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

use fkooman\RemoteStorage\Exception\MetadataStorageException;
use PDO;

class MetadataStorage
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
    }

    public function getVersion(Path $p): ?string
    {
        $md = $this->getMetadata($p);

        return null !== $md ? $md['version'] : null;
    }

    public function getContentType(Path $p): ?string
    {
        $md = $this->getMetadata($p);

        return null !== $md ? $md['content_type'] : null;
    }

    public function updateFolder(Path $p): void
    {
        if (!$p->getIsFolder()) {
            throw new MetadataStorageException('not a folder');
        }

        $this->updateDocument($p, null);
    }

    /**
     * We have a very weird version update method by including a sequence number
     * that makes it easy for tests to see if there is correct behavior, a sequence
     * number is not enough though as deleting a file would reset the sequence number and
     * thus make it possible to have files with different content to have the same
     * sequence number in the same location, but in order to check if all versions
     * are updated up to the root we have to do this this way...
     *
     * @param mixed $contentType
     */
    public function updateDocument(Path $p, $contentType): void
    {
        if (null === $currentVersion = $this->getVersion($p)) {
            $newVersion = '1:'.sodium_bin2hex($this->randomBytes());
            $stmt = $this->db->prepare(
                'INSERT INTO md (path, content_type, version) VALUES(:path, :content_type, :version)'
            );
        } else {
            [$versionNumber,] = explode(':', $currentVersion, 2);
            $explodedData = explode(':', $currentVersion);
            $newVersion = sprintf('%d:%s', ((int) $versionNumber) + 1, sodium_bin2hex($this->randomBytes()));
            $stmt = $this->db->prepare(
                'UPDATE md SET version = :version, content_type = :content_type WHERE path = :path'
            );
        }

        $stmt->bindValue(':path', $p->getPath(), PDO::PARAM_STR);
        $stmt->bindValue(':content_type', $contentType, PDO::PARAM_STR);
        $stmt->bindValue(':version', $newVersion, PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new MetadataStorageException('unable to update node');
        }
    }

    public function deleteNode(Path $p): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM md WHERE path = :path'
        );
        $stmt->bindValue(':path', $p->getPath(), PDO::PARAM_STR);
        $stmt->execute();

        if (1 !== $stmt->rowCount()) {
            throw new MetadataStorageException('unable to delete node');
        }
    }

    public static function createTableQueries()
    {
        return [
            'CREATE TABLE IF NOT EXISTS md (
                path VARCHAR(255) NOT NULL,
                content_type VARCHAR(255) DEFAULT NULL,
                version VARCHAR(255) NOT NULL,
                UNIQUE (path)
            )',
        ];
    }

    public function init(): void
    {
        $queries = self::createTableQueries();
        foreach ($queries as $q) {
            $this->db->query($q);
        }
    }

    protected function randomBytes(): string
    {
        return random_bytes(8);
    }

    /**
     * Get the version of the path which can be either a folder or document.
     *
     * @return array{version:string,content_type:string}
     */
    private function getMetadata(Path $p): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT version, content_type FROM md WHERE path = :path'
        );
        $stmt->bindValue(':path', $p->getPath(), PDO::PARAM_STR);
        $stmt->execute();
        if (false === $resultRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return null;
        }

        return [
            'version' => (string) $resultRow['version'],
            'content_type' => (string) $resultRow['content_type'],
        ];
    }
}
