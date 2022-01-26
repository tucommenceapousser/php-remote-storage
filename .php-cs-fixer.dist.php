<?php

declare(strict_types=1);

$config = new PhpCsFixer\Config();

return $config->setRules(
    [
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP74Migration' => true,
        '@PHP74Migration:risky' => true,
        '@PHPUnit84Migration:risky' => true,
        'no_alternative_syntax' => false,
        'echo_tag_syntax' => ['format' => 'short'],
        'header_comment' => [
            'header' => <<< 'EOD'
                php-remote-storage - PHP remoteStorage implementation

                Copyright: 2016 SURFnet
                Copyright: 2022 François Kooman <fkooman@tuxed.net>
                
                SPDX-License-Identifier: AGPL-3.0+
                EOD,
        ],
    ]
)
    ->setRiskyAllowed(true)
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__))
;
