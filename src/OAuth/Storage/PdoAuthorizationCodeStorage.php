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
use fkooman\RemoteStorage\OAuth\AuthorizationCode;
use fkooman\RemoteStorage\OAuth\AuthorizationCodeStorageInterface;
use PDO;

class PdoAuthorizationCodeStorage extends PdoBaseStorage implements AuthorizationCodeStorageInterface
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

    public function storeAuthorizationCode(AuthorizationCode $authorizationCode)
    {
        $generatedCode = $this->io->getRandom();

        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (code, client_id, user_id, issued_at, redirect_uri, scope) VALUES(:code, :client_id, :user_id, :issued_at, :redirect_uri, :scope)',
                $this->dbPrefix.'authorization_code'
            )
        );
        $stmt->bindValue(':code', $generatedCode, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $authorizationCode->getClientId(), PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $authorizationCode->getUserId(), PDO::PARAM_STR);
        $stmt->bindValue(':issued_at', $authorizationCode->getIssuedAt(), PDO::PARAM_INT);
        $stmt->bindValue(':redirect_uri', $authorizationCode->getRedirectUri(), PDO::PARAM_STR);
        $stmt->bindValue(':scope', $authorizationCode->getScope(), PDO::PARAM_STR);
        $stmt->execute();

        return $generatedCode;
    }

    public function retrieveAuthorizationCode($authorizationCode)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT client_id, user_id, issued_at, redirect_uri, scope FROM %s WHERE code = :code',
                $this->dbPrefix.'authorization_code'
            )
        );
        $stmt->bindValue(':code', $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (false === $result) {
            return false;
        }

        return new AuthorizationCode(
            $result['client_id'],
            $result['user_id'],
            $result['issued_at'],
            $result['redirect_uri'],
            $result['scope']
        );
    }

    public function isFreshAuthorizationCode($authorizationCode)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT code FROM %s WHERE code = :code',
                $this->dbPrefix.'authorization_code_log'
            )
        );
        $stmt->bindValue(':code', $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (false !== $result) {
            return false;
        }

        $stmt = $this->db->prepare(
            sprintf(
                'INSERT INTO %s (code) VALUES(:code)',
                $this->dbPrefix.'authorization_code_log'
            )
        );
        $stmt->bindValue(':code', $authorizationCode, PDO::PARAM_STR);
        $stmt->execute();

        return true;
    }

    public function createTableQueries($dbPrefix)
    {
        return [
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    code VARCHAR(255) NOT NULL,
                    client_id VARCHAR(255) NOT NULL,
                    user_id VARCHAR(255) NOT NULL,
                    issued_at INT NOT NULL,
                    redirect_uri VARCHAR(255) NOT NULL,
                    scope VARCHAR(255) NOT NULL,
                    PRIMARY KEY (code)
                )',
                $dbPrefix.'authorization_code'
            ),
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (
                    code VARCHAR(255) NOT NULL,
                    UNIQUE (code)
                )',
                $dbPrefix.'authorization_code_log'
            ),
        ];
    }
}
