<?php

/**
 *  Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\RemoteStorage\OAuth\Storage;

use fkooman\RemoteStorage\OAuth\ResourceServer;
use fkooman\RemoteStorage\OAuth\ResourceServerStorageInterface;
use PDO;

class PdoResourceServerStorage extends PdoBaseStorage implements ResourceServerStorageInterface
{
    public function __construct(PDO $db, $dbPrefix = '')
    {
        parent::__construct($db, $dbPrefix);
    }

    public function getResourceServer($resourceServerId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT id, scope, secret FROM %s WHERE id = :id',
                $this->dbPrefix.'resource_server'
            )
        );

        $stmt->bindValue(':id', $resourceServerId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $result) {
            return false;
        }

        return new ResourceServer(
            $result['id'],
            $result['scope'],
            $result['secret']
        );
    }

    public function createTableQueries($dbPrefix)
    {
        return [
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    id VARCHAR(255) NOT NULL,
                    scope VARCHAR(255) NOT NULL,
                    secret VARCHAR(255) NOT NULL,
                    PRIMARY KEY (id)
                )',
                $dbPrefix.'resource_server'
            ),
        ];
    }
}
