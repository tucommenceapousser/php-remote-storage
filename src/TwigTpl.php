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

namespace fkooman\RemoteStorage;

use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigTpl implements TplInterface
{
    private Environment $twig;

    /** @var array */
    private $defaultVariables;

    /**
     * Create TwigTemplateManager.
     *
     * @param array  $templateDirs template directories to look in where later
     *                             paths override the earlier paths
     * @param string $cacheDir     the writable directory to store the cache
     */
    public function __construct(array $templateDirs, $cacheDir = null)
    {
        $existingTemplateDirs = [];
        foreach ($templateDirs as $templateDir) {
            if (false !== is_dir($templateDir)) {
                $existingTemplateDirs[] = $templateDir;
            }
        }
        $existingTemplateDirs = array_reverse($existingTemplateDirs);

        $environmentOptions = [
            'strict_variables' => true,
        ];

        if (null !== $cacheDir) {
            if (false === is_dir($cacheDir)) {
                if (false === @mkdir($cacheDir, 0700, true)) {
                    throw new RuntimeException('unable to create template cache directory');
                }
            }
            $environmentOptions['cache'] = $cacheDir;
        }

        $this->twig = new Environment(
            new FilesystemLoader(
                $existingTemplateDirs
            ),
            $environmentOptions
        );

        $this->defaultVariables = [];
    }

    public function setDefault(array $templateVariables): void
    {
        $this->defaultVariables = $templateVariables;
    }

    public function addDefault(array $templateVariables): void
    {
        $this->defaultVariables = array_merge(
            $this->defaultVariables,
            $templateVariables
        );
    }

    /**
     * Render the template.
     */
    public function render(string $templateName, array $templateVariables): string
    {
        $templateVariables = array_merge($this->defaultVariables, $templateVariables);

        return $this->twig->render(
            sprintf(
                '%s.twig',
                $templateName
            ),
            $templateVariables
        );
    }
}
