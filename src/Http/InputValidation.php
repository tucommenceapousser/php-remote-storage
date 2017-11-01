<?php
/**
 *  Copyright (C) 2016 SURFnet.
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage\Http;

use fkooman\RemoteStorage\Http\Exception\InputValidationException;

class InputValidation
{
    /**
     * @param mixed $displayName
     *
     * @return string
     */
    public static function displayName($displayName)
    {
        $displayName = filter_var($displayName, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);

        if (0 === mb_strlen($displayName)) {
            throw new InputValidationException('invalid "display_name"');
        }

        return $displayName;
    }

    /**
     * @param mixed $commonName
     *
     * @return string
     */
    public static function commonName($commonName)
    {
        if (1 !== preg_match('/^[a-fA-F0-9]{32}$/', $commonName)) {
            throw new InputValidationException('invalid "common_name"');
        }

        return $commonName;
    }

    /**
     * @param mixed $serverCommonName
     *
     * @return string
     */
    public static function serverCommonName($serverCommonName)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $serverCommonName)) {
            throw new InputValidationException('invalid "server_common_name"');
        }

        return $serverCommonName;
    }

    /**
     * @param mixed $profileId
     *
     * @return string
     */
    public static function profileId($profileId)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9]+$/', $profileId)) {
            throw new InputValidationException('invalid "profile_id"');
        }

        return $profileId;
    }

    /**
     * @param mixed $instanceId
     *
     * @return string
     */
    public static function instanceId($instanceId)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-.]+$/', $instanceId)) {
            throw new InputValidationException('invalid "instance_id"');
        }

        return $instanceId;
    }

    /**
     * @param mixed $languageCode
     *
     * @return string
     */
    public static function languageCode($languageCode)
    {
        $supportedLanguages = ['en_US', 'nl_NL', 'de_DE', 'fr_FR'];
        if (!in_array($languageCode, $supportedLanguages, true)) {
            throw new InputValidationException('invalid "language_code"');
        }

        return $languageCode;
    }

    /**
     * @param mixed $totpSecret
     *
     * @return string
     */
    public static function totpSecret($totpSecret)
    {
        if (1 !== preg_match('/^[A-Z0-9]{16}$/', $totpSecret)) {
            throw new InputValidationException('invalid "totp_secret"');
        }

        return $totpSecret;
    }

    /**
     * @param mixed $yubiKeyOtp
     *
     * @return string
     */
    public static function yubiKeyOtp($yubiKeyOtp)
    {
        if (1 !== preg_match('/^[a-z]{44}$/', $yubiKeyOtp)) {
            throw new InputValidationException('invalid "yubi_key_otp"');
        }

        return $yubiKeyOtp;
    }

    /**
     * @param mixed $totpKey
     *
     * @return string
     */
    public static function totpKey($totpKey)
    {
        if (1 !== preg_match('/^[0-9]{6}$/', $totpKey)) {
            throw new InputValidationException('invalid "totp_key"');
        }

        return $totpKey;
    }

    /**
     * @param mixed $clientId
     *
     * @return string
     */
    public static function clientId($clientId)
    {
        if (1 !== preg_match('/^(?:[\x20-\x7E])+$/', $clientId)) {
            throw new InputValidationException('invalid "client_id"');
        }

        return $clientId;
    }

    /**
     * @param mixed $userId
     *
     * @return string
     */
    public static function userId($userId)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-.@]+$/', $userId)) {
            throw new InputValidationException('invalid "user_id"');
        }

        return $userId;
    }

    /**
     * @param mixed $dateTime
     *
     * @return int
     */
    public static function dateTime($dateTime)
    {
        // try to parse first
        if (false === $unixTime = strtotime($dateTime)) {
            // if that fails, check if it is already unixTime
            if (is_numeric($dateTime)) {
                $unixTime = (int) $dateTime;
                if (0 <= $unixTime) {
                    return $unixTime;
                }
            }

            throw new InputValidationException('invalid "date_time"');
        }

        return $unixTime;
    }

    /**
     * @param mixed $ipAddress
     *
     * @return string
     */
    public static function ipAddress($ipAddress)
    {
        if (false === filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new InputValidationException('invalid "ip_address"');
        }

        // normalize the IP address (only makes a difference for IPv6)
        return inet_ntop(inet_pton($ipAddress));
    }

    /**
     * @param mixed $vootToken
     *
     * @return string
     */
    public static function vootToken($vootToken)
    {
        if (1 !== preg_match('/^[a-zA-Z0-9-]+$/', $vootToken)) {
            throw new InputValidationException('invalid "voot_token"');
        }

        return $vootToken;
    }

    /**
     * @param mixed $ip4
     *
     * @return string
     */
    public static function ip4($ip4)
    {
        if (false === filter_var($ip4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InputValidationException('invalid "ip4"');
        }

        return $ip4;
    }

    /**
     * @param mixed $ip6
     *
     * @return string
     */
    public static function ip6($ip6)
    {
        if (false === filter_var($ip6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new InputValidationException('invalid "ip6"');
        }

        // normalize the IPv6 address
        return inet_ntop(inet_pton($ip6));
    }

    /**
     * @param mixed $connectedAt
     *
     * @return int
     */
    public static function connectedAt($connectedAt)
    {
        if (!is_numeric($connectedAt) || 0 > (int) $connectedAt) {
            throw new InputValidationException('invalid "connected_at"');
        }

        return (int) $connectedAt;
    }

    /**
     * @param mixed $disconnectedAt
     *
     * @return int
     */
    public static function disconnectedAt($disconnectedAt)
    {
        if (!is_numeric($disconnectedAt) || 0 > (int) $disconnectedAt) {
            throw new InputValidationException('invalid "disconnected_at"');
        }

        return (int) $disconnectedAt;
    }

    /**
     * @param mixed $bytesTransferred
     *
     * @return int
     */
    public static function bytesTransferred($bytesTransferred)
    {
        if (!is_numeric($bytesTransferred) || 0 > (int) $bytesTransferred) {
            throw new InputValidationException('invalid "bytes_transferred"');
        }

        return (int) $bytesTransferred;
    }

    /**
     * @param mixed $twoFactorType
     *
     * @return string
     */
    public static function twoFactorType($twoFactorType)
    {
        if ('totp' !== $twoFactorType && 'yubi' !== $twoFactorType) {
            throw new InputValidationException('invalid "two_factor_type"');
        }

        return $twoFactorType;
    }

    /**
     * @param mixed $twoFactorValue
     *
     * @return string
     */
    public static function twoFactorValue($twoFactorValue)
    {
        if (!is_string($twoFactorValue) || 0 >= strlen($twoFactorValue)) {
            throw new InputValidationException('invalid "two_factor_value"');
        }

        return $twoFactorValue;
    }

    /**
     * @param mixed $messageId
     *
     * @return int
     */
    public static function messageId($messageId)
    {
        if (!is_numeric($messageId) || 0 >= $messageId) {
            throw new InputValidationException('invalid "message_id"');
        }

        return (int) $messageId;
    }

    /**
     * @param mixed $messageType
     *
     * @return string
     */
    public static function messageType($messageType)
    {
        if ('motd' !== $messageType && 'notification' !== $messageType && 'maintenance' !== $messageType) {
            throw new InputValidationException('invalid "message_type"');
        }

        return $messageType;
    }
}
