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

use fkooman\RemoteStorage\Http\HtmlResponse;
use fkooman\RemoteStorage\Http\RedirectResponse;
use fkooman\RemoteStorage\Http\Request;
use fkooman\RemoteStorage\OAuth\TokenStorage;

class UiModule
{
    private $remoteStorage;

    /** @var TplInterface */
    private $tpl;

    /** @var \fkooman\RemoteStorage\OAuth\TokenStorage */
    private $tokenStorage;

    public function __construct(RemoteStorage $remoteStorage, TplInterface $tpl, TokenStorage $tokenStorage)
    {
        $this->remoteStorage = $remoteStorage;
        $this->tpl = $tpl;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @param string $userId
     */
    public function getHome(Request $request, $userId)
    {
        $approvalList = $this->tokenStorage->getAuthorizedClients($userId);
        $userAddress = $userId.'@'.$request->getServerName();
        if ('https' === $request->getScheme() && 443 === $request->getPort()) {
            return $userAddress;
        }
        if ('http' === $request->getScheme() && 80 === $request->getPort()) {
            return $userAddress;
        }

        $userAddress .= ':'.$request->getPort();

        return new HtmlResponse(
            $this->tpl->render(
                'home',
                [
                    'approval_list' => $approvalList,
                    'user_address' => $userAddress,
                    'disk_usage' => $this->remoteStorage->getFolderSize(new Path(sprintf('/%s/', $userId))),
                    'request_url' => $request->getUri(),
                    'show_account_icon' => true,
                ]
            )
        );
    }

    /**
     * @param string $userId
     */
    public function postHome(Request $request, $userId)
    {
        // XXX InputValidation
        $clientId = $request->getPostParameter('client_id');
        $this->tokenStorage->removeClientTokens($userId, $clientId);

        return new RedirectResponse($request->getUri(), 302);
    }
}
