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

use fkooman\RemoteStorage\Http\Exception\HttpException;
use fkooman\RemoteStorage\TplInterface;

class FormAuthentication
{
    /** @var SessionInterface */
    private $session;

    /** @var \fkooman\RemoteStorage\TplInterface */
    private $tpl;

    /** @var array */
    private $userPass;

    public function __construct(SessionInterface $session, TplInterface $tpl, array $userPass)
    {
        $this->session = $session;
        $this->tpl = $tpl;
        $this->userPass = $userPass;
    }

    public function optionalAuth(Request $request)
    {
        if ($this->session->has('_form_auth_user')) {
            return $this->session->get('_form_auth_user');
        }

        return false;
    }

    public function requireAuth(Request $request)
    {
        if ($this->session->has('_form_auth_user')) {
            return $this->session->get('_form_auth_user');
        }

        // any other URL, enforce authentication
        $response = new Response(200, 'text/html');
        $response->setBody(
            $this->tpl->render(
                'authenticate',
                [
                    '_form_auth_invalid_credentials' => false,
                    '_form_auth_redirect_to' => $request->getUri(),
                    '_form_auth_login_page' => true,
                ]
            )
        );

        return $response;
    }

    public function verifyAuth(Request $request)
    {
        $this->session->delete('_form_auth_user');

        $authUser = $request->getPostParameter('userName');
        $authPass = $request->getPostParameter('userPass');
        $redirectTo = $request->getPostParameter('_form_auth_redirect_to');

        // validate the URL
        if (false === filter_var($redirectTo, \FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED | \FILTER_FLAG_PATH_REQUIRED)) {
            throw new HttpException('invalid redirect_to URL', 400);
        }
        // extract the "host" part of the URL
        if (false === $redirectToHost = parse_url($redirectTo, \PHP_URL_HOST)) {
            throw new HttpException('invalid redirect_to URL, unable to extract host', 400);
        }
        if ($request->getServerName() !== $redirectToHost) {
            throw new HttpException('redirect_to does not match expected host', 400);
        }

        if (\array_key_exists($authUser, $this->userPass)) {
            if (false !== password_verify($authPass, $this->userPass[$authUser])) {
                $this->session->set('_form_auth_user', $authUser);

                return new RedirectResponse($redirectTo, 302);
            }
        }

        // invalid authentication
        $response = new Response(200, 'text/html');
        $response->setBody(
            $this->tpl->render(
                'authenticate',
                [
                    '_form_auth_invalid_credentials' => true,
                    '_form_auth_invalid_credentials_user' => $authUser,
                    '_form_auth_redirect_to' => $redirectTo,
                    '_form_auth_login_page' => true,
                ]
            )
        );

        return $response;
    }

    public function logout(Request $request)
    {
        $this->session->delete('_form_auth_user');

        return new RedirectResponse($request->getRootUri(), 302);
    }
}
