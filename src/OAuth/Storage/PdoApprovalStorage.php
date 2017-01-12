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

use fkooman\RemoteStorage\OAuth\Approval;
use fkooman\RemoteStorage\OAuth\ApprovalStorageInterface;
use PDO;

class PdoApprovalStorage extends PdoBaseStorage implements ApprovalStorageInterface
{
    public function __construct(PDO $db, $dbPrefix = '')
    {
        parent::__construct($db, $dbPrefix);
    }

    public function storeApproval(Approval $approval)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (user_id, client_id, response_type, scope) VALUES(:user_id, :client_id, :response_type, :scope)',
                $this->dbPrefix.'approval'
            )
        );
        $stmt->bindValue(':user_id', $approval->getUserId(), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $approval->getClientId(), PDO::PARAM_STR);
        $stmt->bindValue(':response_type', $approval->getResponseType(), PDO::PARAM_STR);
        $stmt->bindValue(':scope', $approval->getScope(), PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function isApproved(Approval $approval)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_id, client_id, response_type, scope FROM %s WHERE user_id = :user_id AND client_id = :client_id AND response_type = :response_type AND scope = :scope',
                $this->dbPrefix.'approval'
            )
        );
        $stmt->bindValue(':user_id', $approval->getUserId(), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $approval->getClientId(), PDO::PARAM_STR);
        $stmt->bindValue(':response_type', $approval->getResponseType(), PDO::PARAM_STR);
        $stmt->bindValue(':scope', $approval->getScope(), PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $result) {
            return false;
        }

        return true;
    }

    public function createTableQueries($dbPrefix)
    {
        return [
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    user_id VARCHAR(255) NOT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    response_type VARCHAR(255) NOT NULL,
                    scope VARCHAR(255) NOT NULL,
                    UNIQUE (user_id, client_id, response_type, scope)
                )',
                $dbPrefix.'approval'
            ),
        ];
    }
}
