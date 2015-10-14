<?php

namespace fkooman\RemoteStorage\Test;

use fkooman\Rest\Plugin\Authentication\Bearer\ValidatorInterface;
use fkooman\Rest\Plugin\Authentication\Bearer\TokenInfo;

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
                    array(
                        'active' => true,
                        'sub' => 'admin',
                        'scope' => 'foo:rw',
                    )
                );
            default:
                return new TokenInfo(
                    array(
                        'active' => false,
                    )
                );
        }
    }
}
