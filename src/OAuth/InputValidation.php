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

namespace fkooman\RemoteStorage\OAuth;

class InputValidation
{
    const VSCHAR = '/^(?:[\x20-\x7E])*$/';
    const NQCHAR = '/^(?:\x21|[\x23-\x5B]|[\x5D-\x7E])*$/';

    public static function clientId($clientId)
    {
        //   The "client_id" element is defined in Section 2.3.1:
        //     client-id     = *VSCHAR

        // XXX: I do not understand why this is not 1*VSCHAR. So the client_id
        // parameter is allowed to be the empty string?
        return self::requireNonEmptyVsChar($clientId);
    }

    public static function responseType($responseType)
    {
        $supportedResponseTypes = [
            'code',
            'token',
        ];
        if (!in_array($responseType, $supportedResponseTypes)) {
            return false;
        }

        return $responseType;
    }

    public static function grantType($grantType)
    {
        // we only support 'authorization_code' for now
        if ('authorization_code' !== $grantType) {
            return false;
        }

        return $grantType;
    }

    public static function redirectUri($redirectUri)
    {
        //   The "redirect_uri" element is defined in Sections 4.1.1, 4.1.3,
        //   and 4.2.1:
        //     redirect-uri      = URI-reference

        //   The redirection endpoint URI MUST be an absolute URI as defined by
        //   [RFC3986] Section 4.3.  The endpoint URI MAY include an
        //   "application/x-www-form-urlencoded" formatted (per Appendix B) query
        //   component ([RFC3986] Section 3.4), which MUST be retained when adding
        //   additional query parameters.  The endpoint URI MUST NOT include a
        //   fragment component.

        // MUST be valid absolute URL
        if (false === filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            return false;
        }

        // MUST not have fragment
        if (null !== parse_url($redirectUri, PHP_URL_FRAGMENT)) {
            return false;
        }

        return $redirectUri;
    }

    public static function scope($scope)
    {
        //   The "scope" element is defined in Section 3.3:
        //     scope       = scope-token *( SP scope-token )
        //     scope-token = 1*NQCHAR
        if (1 > strlen($scope)) {
            return false;
        }
        $scopeTokens = explode(' ', $scope);
        foreach ($scopeTokens as $scopeToken) {
            if (1 > strlen($scopeToken)) {
                return false;
            }
            if (1 !== preg_match(self::NQCHAR, $scopeToken)) {
                return false;
            }
        }

        return $scope;
    }

    public static function state($state)
    {
        //   The "state" element is defined in Sections 4.1.1, 4.1.2, 4.1.2.1,
        //   4.2.1, 4.2.2, and 4.2.2.1:
        //     state      = 1*VSCHAR
        return self::requireNonEmptyVsChar($state);
    }

    public static function code($code)
    {
        //   The "code" element is defined in Section 4.1.3:
        //     code       = 1*VSCHAR
        return self::requireNonEmptyVsChar($code);
    }

    public static function token($token)
    {
        //   The "access_token" element is defined in Sections 4.2.2 and 5.1:
        //     access-token = 1*VSCHAR
        return self::requireNonEmptyVsChar($token);
    }

    public static function approval($approval)
    {
        if ('yes' !== $approval && 'no' !== $approval) {
            return false;
        }

        return $approval;
    }

    public static function requireNonEmptyVsChar($str)
    {
        if (1 > strlen($str)) {
            return false;
        }
        if (1 !== preg_match(self::VSCHAR, $str)) {
            return false;
        }

        return $str;
    }
}
