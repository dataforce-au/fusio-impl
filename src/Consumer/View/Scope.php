<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2020 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Fusio\Impl\Consumer\View;

use PSX\Sql\Condition;
use PSX\Sql\ViewAbstract;

/**
 * Scope
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class Scope extends ViewAbstract
{
    public function getCollection($userId, $startIndex = 0)
    {
        if (empty($startIndex) || $startIndex < 0) {
            $startIndex = 0;
        }

        $count = 16;

        $condition = new Condition();
        $condition->equals('user_scope.user_id', $userId);

        $countSql = $this->getBaseQuery(['COUNT(*) AS cnt'], $condition);
        $querySql = $this->getBaseQuery(['scope.id', 'scope.name', 'scope.description'], $condition, 'user_scope.id ASC');
        $querySql = $this->connection->getDatabasePlatform()->modifyLimitQuery($querySql, $count, $startIndex);

        $definition = [
            'totalResults' => $this->doValue($countSql, $condition->getValues(), $this->fieldInteger('cnt')),
            'startIndex' => $startIndex,
            'itemsPerPage' => $count,
            'entry' => $this->doCollection($querySql, $condition->getValues(), [
                'id' => $this->fieldInteger('id'),
                'name' => 'name',
                'description' => 'description',
            ]),
        ];

        return $this->build($definition);
    }

    /**
     * @param array $fields
     * @param \PSX\Sql\Condition $condition
     * @param string $orderBy
     * @return string
     */
    private function getBaseQuery(array $fields, Condition $condition, $orderBy = null)
    {
        $fields  = implode(',', $fields);
        $where   = $condition->getStatment($this->connection->getDatabasePlatform());
        $orderBy = $orderBy !== null ? 'ORDER BY ' . $orderBy : '';

        return <<<SQL
    SELECT {$fields}
      FROM fusio_user_scope user_scope
INNER JOIN fusio_scope scope
        ON user_scope.scope_id = scope.id
           {$where}
           {$orderBy}
SQL;
    }
}
