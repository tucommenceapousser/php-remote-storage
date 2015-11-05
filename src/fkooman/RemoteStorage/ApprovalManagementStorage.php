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

use PDO;
use fkooman\OAuth\Storage\PdoBaseStorage;
use fkooman\OAuth\Approval;

class ApprovalManagementStorage extends PdoBaseStorage
{
    public function __construct(PDO $db, $dbPrefix = '')
    {
        parent::__construct($db, $dbPrefix);
    }

    public function deleteApproval(Approval $approval)
    {
        // delete approvals
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s WHERE user_id = :user_id AND client_id = :client_id AND response_type = :response_type AND scope = :scope',
                $this->dbPrefix.'approval'
            )
        );
        $stmt->bindValue(':user_id', $approval->getUserId(), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $approval->getClientId(), PDO::PARAM_STR);
        $stmt->bindValue(':response_type', $approval->getResponseType(), PDO::PARAM_STR);
        $stmt->bindValue(':scope', $approval->getScope(), PDO::PARAM_STR);
        $stmt->execute();

        // delete access tokens as well
        $stmt = $this->db->prepare(
            sprintf(
                'DELETE FROM %s WHERE user_id = :user_id AND client_id = :client_id AND scope = :scope',
                $this->dbPrefix.'access_token'
            )
        );
        $stmt->bindValue(':user_id', $approval->getUserId(), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $approval->getClientId(), PDO::PARAM_STR);
        $stmt->bindValue(':scope', $approval->getScope(), PDO::PARAM_STR);
        $stmt->execute();

        return 1 === $stmt->rowCount();
    }

    public function getApprovalList($userId)
    {
        $stmt = $this->db->prepare(
            sprintf(
                'SELECT user_id, client_id, response_type, scope FROM %s WHERE user_id = :user_id',
                $this->dbPrefix.'approval'
            )
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $approvalList = array();
        foreach ($result as $r) {
            $approvalList[] = new Approval($r['user_id'], $r['client_id'], $r['response_type'], $r['scope']);
        }

        return $approvalList;
    }

    public function createTableQueries($dbPrefix)
    {
        return array();
    }
}
