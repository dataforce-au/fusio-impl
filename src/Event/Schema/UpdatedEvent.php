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

namespace Fusio\Impl\Event\Schema;

use Fusio\Impl\Authorization\UserContext;
use Fusio\Impl\Event\EventAbstract;

/**
 * UpdatedEvent
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class UpdatedEvent extends EventAbstract
{
    /**
     * @var integer
     */
    protected $schemaId;

    /**
     * @var array
     */
    protected $record;

    /**
     * @var array
     */
    protected $schema;

    /**
     * @param integer $schemaId
     * @param array $record
     * @param array $schema
     * @param \Fusio\Impl\Authorization\UserContext $context
     */
    public function __construct($schemaId, array $record, $schema, UserContext $context)
    {
        parent::__construct($context);

        $this->schemaId = $schemaId;
        $this->record   = $record;
        $this->schema   = $schema;
    }

    public function getSchemaId()
    {
        return $this->schemaId;
    }

    public function getRecord()
    {
        return $this->record;
    }

    public function getSchema()
    {
        return $this->schema;
    }
}
