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

namespace Fusio\Impl\Consumer\Schema\Plan;

use Fusio\Impl\Consumer\Schema;
use PSX\Schema\SchemaAbstract;

/**
 * Contract
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class Contract extends SchemaAbstract
{
    public function getDefinition()
    {
        $sb = $this->getSchemaBuilder('Consumer Plan Contract');
        $sb->integer('id');
        $sb->integer('status');
        $sb->objectType('plan', $this->getSchema(Schema\Plan::class));
        $sb->number('amount');
        $sb->integer('points');
        $sb->integer('period');
        $sb->arrayType('invoices')
            ->setItems($this->getSchema(Schema\Plan\Invoice::class));
        $sb->dateTime('insertDate');

        return $sb->getProperty();
    }
}
