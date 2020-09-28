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

namespace Fusio\Impl\Consumer\Api\User;

use Fusio\Impl\Consumer\Model;
use PSX\Api\Resource;
use PSX\Api\SpecificationInterface;
use PSX\Framework\Controller\SchemaApiAbstract;
use PSX\Http\Environment\HttpContextInterface;
use PSX\Http\Exception as StatusCode;
use PSX\Oauth2\AccessToken;

/**
 * Login
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class Login extends SchemaApiAbstract
{
    /**
     * @Inject
     * @var \Fusio\Impl\Service\User\Login
     */
    protected $userLoginService;

    /**
     * @inheritdoc
     */
    public function getDocumentation(?string $version = null): ?SpecificationInterface
    {
        $builder = $this->apiManager->getBuilder(Resource::STATUS_ACTIVE, $this->context->getPath());

        $post = $builder->addMethod('POST');
        $post->setRequest(Model\User_Login::class);
        $post->addResponse(200, Model\User_JWT::class);

        $put = $builder->addMethod('PUT');
        $put->setRequest(Model\User_Refresh::class);
        $put->addResponse(200, Model\User_JWT::class);

        return $builder->getSpecification();
    }

    /**
     * @inheritdoc
     */
    protected function doPost($record, HttpContextInterface $context)
    {
        $token = $this->userLoginService->login(
            $record
        );

        return $this->renderToken($token);
    }

    /**
     * @inheritdoc
     */
    protected function doPut($record, HttpContextInterface $context)
    {
        $token = $this->userLoginService->refresh(
            $record
        );

        return $this->renderToken($token);
    }

    private function renderToken(?AccessToken $token)
    {
        if ($token instanceof AccessToken) {
            return [
                'token' => $token->getAccessToken(),
                'expires_in' => $token->getExpiresIn(),
                'refresh_token' => $token->getRefreshToken(),
            ];
        } else {
            throw new StatusCode\BadRequestException('Invalid name or password');
        }
    }
}
