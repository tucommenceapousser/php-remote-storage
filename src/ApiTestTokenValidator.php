<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\RemoteStorage;

use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\Rest\Plugin\Authentication\Bearer\ValidatorInterface;

class ApiTestTokenValidator implements ValidatorInterface
{
    /**
     * @return TokenInfo
     */
    public function validate($bearerToken)
    {
        switch ($bearerToken) {
            case 'token':
                return new TokenInfo(
                    [
                        'active' => true,
                        'username' => 'demo',
                        'scope' => 'api-test:rw',
                    ]
                );
            case 'root_token':
                return new TokenInfo(
                    [
                        'active' => true,
                        'username' => 'demo',
                        'scope' => '*:rw',
                    ]
                );
            case 'read_only_token':
                return new TokenInfo(
                    [
                        'active' => true,
                        'username' => 'demo',
                        'scope' => 'api-test:r',
                    ]
                );
            case '12345':
                return new TokenInfo(
                    [
                        'active' => true,
                        'username' => 'foo',
                        'scope' => 'foo:rw bar:r',
                    ]
                );
            default:
                return new TokenInfo(
                    [
                        'active' => false,
                    ]
                );
        }
    }
}
