<?php

namespace fkooman\RemoteStorage\Test;

use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;
use fkooman\Rest\Plugin\Authentication\Bearer\ValidatorInterface;

class TestTokenValidator implements ValidatorInterface
{
    /**
     * @return TokenInfo
     */
    public function validate($bearerToken)
    {
        switch ($bearerToken) {
            case 'test_token':
                return new TokenInfo(
                    [
                        'active' => true,
                        'sub' => 'admin',
                        'scope' => 'foo:rw',
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
