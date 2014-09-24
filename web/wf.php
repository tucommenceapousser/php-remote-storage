<?php

/**
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

require_once dirname(__DIR__) . "/vendor/autoload.php";

use fkooman\Http\Request;
use fkooman\Http\IncomingRequest;
use fkooman\Http\Response;
use fkooman\Json\Json;

try {
    $request = Request::fromIncomingRequest(new IncomingRequest());

    $response = new Response(200);
    $response->setHeader("Access-Control-Allow-Origin", "*");
    $response->setContentType("application/jrd+json");

    $resource = $request->getQueryParameter('resource');

    if (null === $resource) {
        throw new Exception("resource missing");
    }

    $eResource = explode(":", $resource);
    if (2 != count($eResource) || "acct" !== $eResource[0]) {
        throw new Exception("invalid resource");
    }
    $userAddress = $eResource[1];

    // verify email address
    // FIXME: this does not work for @localhost domains!
    // FIXME: fix this better
#    if (false === filter_var($userAddress, FILTER_VALIDATE_EMAIL)) {
#        throw new Exception("not a valid user address");
#    }

    $storageUri = 'https://localhost/php-remote-storage/api.php';
    $authorizeUri = 'https://localhost/php-oauth-as/authorize.php';

    // get the user
    list($user, $domain) = explode("@", $userAddress);

    $wf = array (
      'links' =>
      array (
        array (
          'href' => sprintf('%s/%s', $storageUri, $user),
          'properties' =>
          array (
            'http://remotestorage.io/spec/version' => 'draft-dejong-remotestorage-03',
            'http://tools.ietf.org/html/rfc2616#section-14.16' => false,
            'http://tools.ietf.org/html/rfc6749#section-2.3' => true,
            'http://tools.ietf.org/html/rfc6749#section-4.2' => sprintf('%s?x_resource_owner_hint=%s', $authorizeUri, $user),
          ),
          'rel' => 'remotestorage',
        ),
      ),
    );
    $json = new Json();
    $response->setContent($json->encode($wf));
    $response->sendResponse();
} catch (Exception $e) {
    $json = new Json();
    $errorResponse = new Response(400);
    $errorResponse->setHeader("Access-Control-Allow-Origin", "*");
    $errorResponse->setContentType("application/json");
    $errorResponse->setContent($json->encode(array("error" => $e->getMessage())));
    $errorResponse->sendResponse();
}
