<?php

namespace fkooman\RemoteStorage;

use PDO;

class Metadata
{
    private $db;
    private $prefix;

    public function __construct(PDO $db, $prefix = "")
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->prefix = $prefix;
    }

    /**
     * Get the version of the path which can be either a folder or document
     *
     * @param $path The full path to the folder or document
     * @returns the version of the path, or null if path does not exist
     */
    private function getMetadata(Path $p)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "SELECT version, content_type FROM %s WHERE path = :path",
                $this->prefix . "md"
            )
        );
        $stmt->bindValue(":path", $p->getPath(), PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false !== $result) {
            return $result;
        }

        return null;
    }

    public function getVersion(Path $p)
    {
        $md = $this->getMetadata($p);

        return null !== $md ? $md['version'] : null;
    }

    public function getContentType(Path $p)
    {
        $md = $this->getMetadata($p);

        return null !== $md ? $md['content_type'] : null;
    }

    public function updateFolder(Path $p)
    {
        return $this->updateDocument($p, "http://remotestorage.io/spec/folder-description");
    }

    public function updateDocument(Path $p, $contentType)
    {
        $currentVersion = $this->getVersion($p);
        if (null === $currentVersion) {
            $newVersion = 1;
            $stmt = $this->db->prepare(
                sprintf(
                    "INSERT INTO %s (path, content_type, version) VALUES(:path, :content_type, :version)",
                    $this->prefix . "md"
                )
            );
        } else {
            $newVersion = $currentVersion + 1;
            $stmt = $this->db->prepare(
                sprintf(
                    "UPDATE %s SET version = :version, content_type = :content_type WHERE path = :path",
                    $this->prefix . "md"
                )
            );
        }

        $stmt->bindValue(":path", $p->getPath(), PDO::PARAM_STR);
        $stmt->bindValue(":content_type", $contentType, PDO::PARAM_STR);
        $stmt->bindValue(":version", $newVersion, PDO::PARAM_INT);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteEntry(Path $p)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "DELETE FROM %s WHERE path = :path",
                $this->prefix . "md"
            )
        );
        $stmt->bindValue(":path", $p->getPath(), PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public static function createTableQueries($prefix)
    {
        $query = array();
        $query[] = sprintf(
            "CREATE TABLE IF NOT EXISTS %s (
                path VARCHAR(255) NOT NULL,
                content_type VARCHAR(255) NOT NULL,
                version INTEGER NOT NULL,
                UNIQUE (path)
            )",
            $prefix . 'md'
        );

        return $query;
    }

    public function initDatabase()
    {
        $queries = self::createTableQueries($this->prefix);
        foreach ($queries as $q) {
            $this->db->query($q);
        }

        $tables = array('md');
        foreach ($tables as $t) {
            // make sure the tables are empty
            $this->db->query(
                sprintf(
                    "DELETE FROM %s",
                    $this->prefix . $t
                )
            );
        }
    }
}
