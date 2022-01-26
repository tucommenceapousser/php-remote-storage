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

namespace fkooman\RemoteStorage\OAuth;

use DateTime;
use PDO;

class TokenStorage
{
    /** @var PDO */
    private $db;

    public function __construct(PDO $db)
    {
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db = $db;
    }

    public function store($userId, $accessTokenKey, $accessToken, $clientId, $scope, DateTime $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tokens (
                user_id,
                access_token_key,
                access_token,
                client_id,
                scope,
                expires_at
             )
             VALUES(
                :user_id,
                :access_token_key,
                :access_token,
                :client_id,
                :scope,
                :expires_at
             )'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':access_token_key', $accessTokenKey, PDO::PARAM_STR);
        $stmt->bindValue(':access_token', $accessToken, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', $expiresAt->format('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->execute();
    }

    public function getExistingToken($userId, $clientId, $scope)
    {
        $stmt = $this->db->prepare(
            'SELECT
                access_token_key,
                access_token,
                expires_at
             FROM tokens
             WHERE
                user_id = :user_id AND
                client_id = :client_id AND
                scope = :scope'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function get($accessTokenKey)
    {
        $stmt = $this->db->prepare(
            'SELECT
                user_id,
                access_token,
                client_id,
                scope,
                expires_at
             FROM tokens
             WHERE
                access_token_key = :access_token_key'
        );

        $stmt->bindValue(':access_token_key', $accessTokenKey, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAuthorizedClients($userId)
    {
        $stmt = $this->db->prepare(
            'SELECT
                client_id,
                scope
             FROM tokens
             WHERE
                user_id = :user_id'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeClientTokens($userId, $clientId): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM tokens
             WHERE user_id = :user_id AND client_id = :client_id'
        );

        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function init(): void
    {
        $queryList = [
            'CREATE TABLE IF NOT EXISTS tokens (
                user_id VARCHAR(255) NOT NULL,
                access_token_key VARCHAR(255) NOT NULL,
                access_token VARCHAR(255) NOT NULL,
                client_id VARCHAR(255) NOT NULL,
                scope VARCHAR(255) NOT NULL,
                expires_at VARCHAR(255) NOT NULL,
                UNIQUE(access_token_key)
            )',
        ];

        foreach ($queryList as $query) {
            $this->db->query($query);
        }
    }
}
