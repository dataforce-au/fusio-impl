<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2016 Christoph Kappestein <k42b3.x@gmail.com>
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

namespace Fusio\Impl\Tests\Action;

use Fusio\Engine\ResponseInterface;
use Fusio\Impl\Action\Transform;
use Fusio\Impl\App;
use Fusio\Impl\Form\Builder;
use Fusio\Impl\Form\Container;
use Fusio\Impl\Tests\ActionTestCaseTrait;
use Fusio\Impl\Tests\DbTestCase;
use PSX\Framework\Test\Environment;
use PSX\Record\Record;

/**
 * TransformTest
 *
 * @author  Christoph Kappestein <k42b3.x@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class TransformTest extends DbTestCase
{
    use ActionTestCaseTrait;

    public function testHandle()
    {
        $action = new Transform();
        $action->setTemplateFactory(Environment::getService('template_factory'));
        $action->setProcessor(Environment::getService('processor'));

        $patch = <<<JSON
[
    { "op": "test", "path": "/title", "value": "foo" },
    { "op": "remove", "path": "/id" },
    { "op": "add", "path": "/foo", "value": "bar" },
    { "op": "replace", "path": "/author/name", "value": "foo" }
]
JSON;

        $parameters = $this->getParameters([
            'action' => 3,
            'patch'  => $patch,
        ]);

        $body = Record::fromArray([
            'id'     => 1,
            'title'  => 'foo',
            'author' => Record::fromArray([
                'name' => 'bar'
            ]),
        ]);

        $request  = $this->getRequest(null, [], [], [], $body);
        $response = $action->handle($request, $parameters, $this->getContext());

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([], $response->getHeaders());
        $this->assertEquals(['id' => 1, 'title' => 'foo', 'content' => 'bar', 'date' => '2015-02-27 19:59:15'], $response->getBody());
    }

    public function testGetForm()
    {
        $action  = new Transform();
        $builder = new Builder();
        $factory = Environment::getService('form_element_factory');

        $action->configure($builder, $factory);

        $this->assertInstanceOf(Container::class, $builder->getForm());
    }
}
