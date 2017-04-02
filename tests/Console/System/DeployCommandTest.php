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

namespace Fusio\Impl\Tests\Console\System;

use Fusio\Impl\Tests\Fixture;
use PSX\Api\Resource;
use PSX\Framework\Test\ControllerDbTestCase;
use PSX\Framework\Test\Environment;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * DeployCommandTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class DeployCommandTest extends ControllerDbTestCase
{
    public function getDataSet()
    {
        return Fixture::getDataSet();
    }

    public function testCommand()
    {
        $command = Environment::getService('console')->find('system:deploy');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'file'    => __DIR__ . '/resource/deploy.yaml',
        ]);

        $display = $commandTester->getDisplay();

        $this->assertRegExp('/Deploy successful!/', $display, $display);
        $this->assertRegExp('/- \[CREATED\] connection New-Connection/', $display, $display);
        $this->assertRegExp('/- \[CREATED\] schema Request-Schema/', $display, $display);
        $this->assertRegExp('/- \[CREATED\] schema Response-Schema/', $display, $display);
        $this->assertRegExp('/- \[CREATED\] action Test-Action/', $display, $display);
        $this->assertRegExp('/- \[CREATED\] routes \/bar/', $display, $display);
        $this->assertRegExp('/- \[EXECUTED\] Native v1_schema.sql/', $display, $display);

        // check connection
        $connection = $this->connection->fetchAssoc('SELECT id, class, config FROM fusio_connection WHERE name = :name', [
            'name' => 'New-Connection',
        ]);

        $this->assertEquals(2, $connection['id']);
        $this->assertEquals('Fusio\Adapter\Sql\Connection\Sql', $connection['class']);
        $this->assertEquals(197, strlen($connection['config']));

        // check schema
        $schema = $this->connection->fetchAssoc('SELECT id, source, cache FROM fusio_schema WHERE name = :name', [
            'name' => 'Request-Schema',
        ]);

        $source = <<<JSON
{
    "id": "http://phpsx.org#",
    "title": "test",
    "type": "object",
    "properties": {
        "title": {
            "type": "string"
        },
        "content": {
            "type": "string"
        },
        "date": {
            "type": "string",
            "format": "date-time"
        }
    }
}
JSON;

        $this->assertEquals(3, $schema['id']);
        $this->assertJsonStringEqualsJsonString($source, $schema['source']);
        $this->assertInstanceOf('PSX\Schema\Schema', unserialize($schema['cache']));

        $schema = $this->connection->fetchAssoc('SELECT id, source, cache FROM fusio_schema WHERE name = :name', [
            'name' => 'Response-Schema',
        ]);

        $source = <<<JSON
{
    "id": "http://phpsx.org#",
    "title": "test",
    "type": "object",
    "properties": {
        "title": {
            "type": "string"
        },
        "content": {
            "type": "string"
        },
        "date": {
            "type": "string",
            "format": "date-time"
        }
    }
}
JSON;

        $this->assertEquals(4, $schema['id']);
        $this->assertJsonStringEqualsJsonString($source, $schema['source']);
        $this->assertInstanceOf('PSX\Schema\Schema', unserialize($schema['cache']));

        // check action
        $action = $this->connection->fetchAssoc('SELECT id, class, config FROM fusio_action WHERE name = :name', [
            'name' => 'Test-Action',
        ]);

        $this->assertEquals(4, $action['id']);
        $this->assertEquals('Fusio\Adapter\Util\Action\UtilStaticResponse', $action['class']);
        $this->assertEquals(['response' => '{"foo": "bar"}'], unserialize($action['config']));

        // check routes
        $route = $this->connection->fetchAssoc('SELECT id, status, methods, controller FROM fusio_routes WHERE path = :path', [
            'path' => '/bar',
        ]);

        $this->assertEquals(Fixture::getLastRouteId() + 2, $route['id']);
        $this->assertEquals(1, $route['status']);
        $this->assertEquals('GET|POST|PUT|DELETE', $route['methods']);
        $this->assertEquals('Fusio\Impl\Controller\SchemaApiController', $route['controller']);

        // check methods
        $methods = $this->connection->fetchAll('SELECT routeId, method, version, status, active, public, request, response, action FROM fusio_routes_method WHERE routeId = :routeId', [
            'routeId' => $route['id'],
        ]);

        $this->assertEquals(1, count($methods));
        $this->assertEquals(['routeId' => Fixture::getLastRouteId() + 2, 'method' => 'GET', 'version' => 1, 'status' => Resource::STATUS_DEVELOPMENT, 'active' => 1, 'public' => 1, 'request' => 3, 'response' => 4, 'action' => 4], $methods[0]);

        // check migration entries
        $migrations = $this->connection->fetchAll('SELECT id, connection, file, fileHash, executeDate FROM fusio_deploy_migration');

        $this->assertEquals(1, count($migrations));
        $this->assertEquals(1, $migrations[0]['id']);
        $this->assertEquals('Native', $migrations[0]['connection']);
        $this->assertEquals('v1_schema.sql', $migrations[0]['file']);
        $this->assertEquals('6692667d14a7d93e4d22694b4853a2b4b78fcdc4', $migrations[0]['fileHash']);
        $this->assertNotEmpty($migrations[0]['executeDate']);

        // check whether the native connection has the migrated tables
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = Environment::getService('connection');
        $tables     = $connection->getSchemaManager()->listTableNames();

        $this->assertContains('acme_foo', $tables);
        $this->assertContains('acme_bar', $tables);
    }
}