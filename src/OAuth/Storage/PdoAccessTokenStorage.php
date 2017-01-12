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

use fkooman\IO\IO;
use fkooman\RemoteStorage\OAuth\AccessToken;
use fkooman\RemoteStorage\OAuth\AccessTokenStorageInterface;
use PDO;

class PdoAccessTokenStorage extends PdoBaseStorage implements AccessTokenStorageInterface
{
    /** @var \fkooman\IO\IO */
    private $io;

    public function __construct(PDO $db, $dbPrefix = '', IO $io = null)
    {
        parent::__construct($db, $dbPrefix);
        if (null === $io) {
            $io = new IO();
        }
        $this->io = $io;
    }

    public function storeAccessToken(AccessToken $accessToken)
    {
        $generatedToken = $this->io->getRandom();

        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (token, client_id, user_id, issued_at, scope) VALUES(:token, :client_id, :user_id, :issued_at, :scope)',
                $this->dbPrefix.'access_token'
            )
        );
        $stmt->bindValue(':token', $generatedToken, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $accessToken->getClientId(), PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $accessToken->getUserId(), PDO::PARAM_STR);
        $stmt->bindValue(':issued_at', $accessToken->getIssuedAt(), PDO::PARAM_INT);
        $stmt->bindValue(':scope', $accessToken->getScope(), PDO::PARAM_STR);
        $stmt->execute();

        return $generatedToken;
    }

    public function retrieveAccessToken($accessToken)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT client_id, user_id, issued_at, scope FROM %s WHERE token = :token',
                $this->dbPrefix.'access_token'
            )
        );
        $stmt->bindValue(':token', $accessToken, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $result) {
            return false;
        }

        return new AccessToken(
            $result['client_id'],
            $result['user_id'],
            $result['issued_at'],
            $result['scope']
        );
    }

    public function createTableQueries($dbPrefix)
    {
        return [
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    token VARCHAR(255) NOT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    user_id VARCHAR(255) NOT NULL,
                    issued_at INT NOT NULL,
                    scope VARCHAR(255) NOT NULL,
                    PRIMARY KEY (token)
                )',
                $dbPrefix.'access_token'
            ),
        ];
    }
}
