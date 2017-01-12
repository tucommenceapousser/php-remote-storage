<?php

/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\RemoteStorage\Base64;

use InvalidArgumentException;

class Base64Url extends Base64
{
    /**
     * Encode to base64url.
     *
     * @param string $data the data to encode
     *
     * @return string the encoded data
     */
    public static function encode($data)
    {
        if (!is_string($data)) {
            throw new InvalidArgumentException('data must be string');
        }

        $encodedData = parent::encode($data);

        // URL safe replacement and remove padding
        return rtrim(strtr($encodedData, '+/', '-_'), '=');
    }

    /**
     * Decode base64url.
     *
     * @param string $data the data to decode
     *
     * @return string the decoded data
     */
    public static function decode($data)
    {
        if (!is_string($data)) {
            throw new InvalidArgumentException('data must be string');
        }

        // undo the URL safe replacement
        $convertedData = strtr($data, '-_', '+/');

        // restore the padding
        switch (strlen($convertedData) % 4) {
            case 0:
                break;
            case 2:
                $convertedData .= '==';
                break;
            case 3:
                $convertedData .= '=';
                break;
            default:
                throw new InvalidArgumentException('invalid base64url string length');
        }

        return parent::decode($convertedData);
    }
}
