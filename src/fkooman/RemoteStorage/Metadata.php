<?php

namespace fkooman\RemoteStorage;

use PDO;

class Metadata
{
    private $db;

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
    public function getMetadata(Path $p)
    {
        $stmt = $this->db->prepare(
            sprintf(
                "SELECT version, type FROM %s WHERE path = :path",
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

    public function getType(Path $p)
    {
        $md = $this->getMetadata($p);

        return null !== $md ? $md['type'] : null;
    }

    public function updateMetadata(Path $p, $type)
    {
        $currentVersion = $this->getVersion($p);
        if (null === $currentVersion) {
            $newVersion = 1;
            $stmt = $this->db->prepare(
                sprintf(
                    "INSERT INTO %s (path, type, version) VALUES(:path, :type, :version)",
                    $this->prefix . "md"
                )
            );
        } else {
            $newVersion = $currentVersion + 1;
            $stmt = $this->db->prepare(
                sprintf(
                    "UPDATE %s SET version = :version, type = :type WHERE path = :path",
                    $this->prefix . "md"
                )
            );
        }

        $stmt->bindValue(":path", $p->getPath(), PDO::PARAM_STR);
        $stmt->bindValue(":type", $type, PDO::PARAM_STR);
        $stmt->bindValue(":version", $newVersion, PDO::PARAM_INT);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function deleteMetadata(Path $p)
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
                type VARCHAR(255) NOT NULL,
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
