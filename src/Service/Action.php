<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2016 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Impl\Service;

use Fusio\Engine\Repository;
use Fusio\Impl\Table;
use PSX\DateTime;
use PSX\Http\Exception as StatusCode;
use PSX\Model\Common\ResultSet;
use PSX\Sql\Condition;
use PSX\Sql\Fields;
use PSX\Sql\Sql;

/**
 * Action
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class Action
{
    /**
     * @var \Fusio\Impl\Table\Action
     */
    protected $actionTable;

    /**
     * @var \Fusio\Impl\Table\Routes\Action
     */
    protected $routesActionTable;

    /**
     * @var \Fusio\Impl\Table\Routes\Method
     */
    protected $routesMethodTable;

    public function __construct(Table\Action $actionTable, Table\Routes\Action $routesActionTable, Table\Routes\Method $routesMethodTable)
    {
        $this->actionTable       = $actionTable;
        $this->routesActionTable = $routesActionTable;
        $this->routesMethodTable = $routesMethodTable;
    }

    public function getAll($startIndex = 0, $search = null, $routeId = null)
    {
        $condition = new Condition();

        if (!empty($search)) {
            $condition->like('name', '%' . $search . '%');
        }

        if (!empty($routeId)) {
            $sql = 'SELECT actionId
                      FROM fusio_routes_action
                     WHERE routeId = ?';

            $condition->raw('id IN (' . $sql . ')', [$routeId]);
        }

        return new ResultSet(
            $this->actionTable->getCount($condition),
            $startIndex,
            16,
            $this->actionTable->getAll(
                $startIndex,
                16,
                'id',
                Sql::SORT_DESC,
                $condition,
                Fields::blacklist(['class', 'config'])
            )
        );
    }

    public function get($actionId)
    {
        $action = $this->actionTable->get($actionId);

        if (!empty($action)) {
            return $action;
        } else {
            throw new StatusCode\NotFoundException('Could not find action');
        }
    }

    public function create($name, $class, $config)
    {
        // check whether action exists
        $condition  = new Condition();
        $condition->equals('name', $name);

        $action = $this->actionTable->getOneBy($condition);

        if (!empty($action)) {
            throw new StatusCode\BadRequestException('Action already exists');
        }

        // create action
        $this->actionTable->create(array(
            'status' => Table\Action::STATUS_ACTIVE,
            'name'   => $name,
            'class'  => $class,
            'config' => $config,
            'date'   => new \DateTime(),
        ));
    }

    public function update($actionId, $name, $class, $config)
    {
        $action = $this->actionTable->get($actionId);

        if (!empty($action)) {
            $this->actionTable->update(array(
                'id'     => $action->id,
                'name'   => $name,
                'class'  => $class,
                'config' => $config,
                'date'   => new \DateTime(),
            ));
        } else {
            throw new StatusCode\NotFoundException('Could not find action');
        }
    }

    public function delete($actionId)
    {
        $action = $this->actionTable->get($actionId);

        if (!empty($action)) {
            // check depending
            if ($this->routesMethodTable->hasAction($actionId)) {
                throw new StatusCode\BadRequestException('Cannot delete action because a route depends on it');
            }

            // delete route dependencies
            $this->routesActionTable->deleteByAction($action['id']);

            $this->actionTable->delete(array(
                'id' => $action['id']
            ));
        } else {
            throw new StatusCode\NotFoundException('Could not find action');
        }
    }
}
