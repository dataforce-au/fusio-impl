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

namespace Fusio\Impl\Service;

use Fusio\Engine\User\ProviderInterface;
use Fusio\Impl\Authorization\TokenGenerator;
use Fusio\Impl\Authorization\UserContext;
use Fusio\Impl\Backend\Schema as BackendSchema;
use Fusio\Impl\Event\User\ChangedPasswordEvent;
use Fusio\Impl\Event\User\ChangedStatusEvent;
use Fusio\Impl\Event\User\CreatedEvent;
use Fusio\Impl\Event\User\DeletedEvent;
use Fusio\Impl\Event\User\FailedAuthenticationEvent;
use Fusio\Impl\Event\User\UpdatedEvent;
use Fusio\Impl\Event\UserEvents;
use Fusio\Impl\Service;
use Fusio\Impl\Service\User\PasswordComplexity;
use Fusio\Impl\Service\User\ValidatorTrait;
use Fusio\Impl\Table;
use PSX\DateTime\DateTime;
use PSX\Http\Exception as StatusCode;
use PSX\Sql\Condition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * User
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class User
{
    use ValidatorTrait;

    /**
     * @var \Fusio\Impl\Table\Scope
     */
    protected $scopeTable;

    /**
     * @var \Fusio\Impl\Table\User
     */
    protected $userTable;

    /**
     * @var \Fusio\Impl\Table\App
     */
    protected $appTable;

    /**
     * @var \Fusio\Impl\Table\User\Scope
     */
    protected $userScopeTable;

    /**
     * @var \Fusio\Impl\Service\Config
     */
    protected $configService;

    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var array|null
     */
    protected $userAttributes;

    /**
     * @param \Fusio\Impl\Table\User $userTable
     * @param \Fusio\Impl\Table\Scope $scopeTable
     * @param \Fusio\Impl\Table\App $appTable
     * @param \Fusio\Impl\Table\User\Scope $userScopeTable
     * @param \Fusio\Impl\Service\Config $configService
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     * @param array|null $userAttributes
     */
    public function __construct(Table\User $userTable, Table\Scope $scopeTable, Table\App $appTable, Table\User\Scope $userScopeTable, Service\Config $configService, EventDispatcherInterface $eventDispatcher, array $userAttributes = null)
    {
        $this->userTable       = $userTable;
        $this->scopeTable      = $scopeTable;
        $this->appTable        = $appTable;
        $this->userScopeTable  = $userScopeTable;
        $this->configService   = $configService;
        $this->eventDispatcher = $eventDispatcher;
        $this->userAttributes  = $userAttributes;
    }

    /**
     * Authenticates a user based on the username and password. Returns the user
     * id if the authentication was successful else null
     *
     * @param string $username
     * @param string $password
     * @param array $status
     * @return integer|null
     */
    public function authenticateUser($username, $password, array $status)
    {
        if (empty($password)) {
            return null;
        }

        // allow login either through username or email
        if (preg_match('/' . BackendSchema\User::NAME_PATTERN . '/', $username)) {
            $column = 'name';
        } else {
            $column = 'email';
        }

        $condition = new Condition();
        $condition->equals($column, $username);
        $condition->in('status', $status);

        $user = $this->userTable->getOneBy($condition);

        if (!empty($user)) {
            // we can authenticate only local users
            if ($user['provider'] != ProviderInterface::PROVIDER_SYSTEM) {
                return null;
            }

            if ($user['status'] == Table\User::STATUS_DISABLED) {
                throw new StatusCode\BadRequestException('The assigned account is disabled');
            }

            if ($user['status'] == Table\User::STATUS_DELETED) {
                throw new StatusCode\BadRequestException('The assigned account is deleted');
            }

            // check password
            if (password_verify($password, $user['password'])) {
                return $user['id'];
            } else {
                $this->eventDispatcher->dispatch(new FailedAuthenticationEvent(UserContext::newContext($user['id'])), UserEvents::FAIL_AUTHENTICATION);
            }
        }

        return null;
    }

    public function assertPasswordComplexity($password)
    {
        PasswordComplexity::assert($password, $this->configService->getValue('user_pw_length'));
    }

    public function create($status, $name, $email, $password, array $scopes = null, UserContext $context)
    {
        // check whether user name exists
        if ($this->userTable->getCount(new Condition(['name', '=', $name])) > 0) {
            throw new StatusCode\BadRequestException('User name already exists');
        }

        // check whether user email exists
        if ($this->userTable->getCount(new Condition(['email', '=', $email])) > 0) {
            throw new StatusCode\BadRequestException('User email already exists');
        }

        // check values
        $this->assertName($name);
        $this->assertEmail($email);
        $this->assertPasswordComplexity($password);

        try {
            $this->userTable->beginTransaction();

            // create user
            $record = [
                'provider' => ProviderInterface::PROVIDER_SYSTEM,
                'status'   => $status,
                'name'     => $name,
                'email'    => $email,
                'password' => $password !== null ? \password_hash($password, PASSWORD_DEFAULT) : null,
                'points'   => $this->configService->getValue('points_default') ?: null,
                'date'     => new DateTime(),
            ];

            $this->userTable->create($record);

            $userId = $this->userTable->getLastInsertId();

            // add scopes
            $this->insertScopes($userId, $scopes);

            $this->userTable->commit();
        } catch (\Throwable $e) {
            $this->userTable->rollBack();

            throw $e;
        }

        $this->eventDispatcher->dispatch(new CreatedEvent($userId, $record, $scopes, $context), UserEvents::CREATE);

        return $userId;
    }

    public function createRemote($provider, $id, $name, $email, array $scopes = null, UserContext $context)
    {
        // check whether user exists
        $condition  = new Condition();
        $condition->equals('provider', $provider);
        $condition->equals('remote_id', $id);

        $user = $this->userTable->getOneBy($condition);

        if (!empty($user)) {
            return $user['id'];
        }

        // replace spaces with a dot
        $name = str_replace(' ', '.', $name);

        // check values
        $this->assertName($name);

        if (!empty($email)) {
            $this->assertEmail($email);
        } else {
            $email = null;
        }

        try {
            $this->userTable->beginTransaction();

            // create user
            $record = [
                'provider'  => $provider,
                'status'    => Table\User::STATUS_CONSUMER,
                'remote_id' => $id,
                'name'      => $name,
                'email'     => $email,
                'password'  => null,
                'points'    => $this->configService->getValue('points_default') ?: null,
                'date'      => new DateTime(),
            ];

            $this->userTable->create($record);

            $userId = $this->userTable->getLastInsertId();

            // add scopes
            $this->insertScopes($userId, $scopes);

            $this->userTable->commit();
        } catch (\Throwable $e) {
            $this->userTable->rollBack();

            throw $e;
        }

        $this->eventDispatcher->dispatch(new CreatedEvent($userId, $record, $scopes, $context), UserEvents::CREATE);

        return $userId;
    }

    public function update($userId, $status, $name, $email, array $scopes = null, $attributes = null, UserContext $context)
    {
        $user = $this->userTable->get($userId);

        if (empty($user)) {
            throw new StatusCode\NotFoundException('Could not find user');
        }

        if ($status === null) {
            $status = $user['status'];
        }

        if ($name === null) {
            $name = $user['name'];
        }

        // check values
        $this->assertName($name);
        $this->assertEmail($email);

        try {
            $this->userTable->beginTransaction();

            // update user
            $record = [
                'id'     => $user['id'],
                'status' => $status,
                'name'   => $name,
                'email'  => $email,
            ];

            $this->userTable->update($record);

            if ($scopes !== null) {
                // delete existing scopes
                $this->userScopeTable->deleteAllFromUser($user['id']);

                // add scopes
                $this->insertScopes($user['id'], $scopes);
            }

            // update attributes
            $this->updateAttributes($user['id'], $attributes);

            $this->userTable->commit();
        } catch (\Throwable $e) {
            $this->userTable->rollBack();

            throw $e;
        }

        $this->eventDispatcher->dispatch(new UpdatedEvent($userId, $record, $scopes, $user, $context), UserEvents::UPDATE);
    }

    public function delete($userId, UserContext $context)
    {
        $user = $this->userTable->get($userId);

        if (empty($user)) {
            throw new StatusCode\NotFoundException('Could not find user');
        }

        $record = [
            'id'     => $user['id'],
            'status' => Table\User::STATUS_DELETED,
        ];

        $this->userTable->update($record);

        $this->eventDispatcher->dispatch(new DeletedEvent($userId, $user, $context), UserEvents::DELETE);
    }

    public function changeStatus($userId, $status, UserContext $context)
    {
        $user = $this->userTable->get($userId);

        if (empty($user)) {
            throw new StatusCode\NotFoundException('Could not find user');
        }

        $record = [
            'id'     => $user['id'],
            'status' => $status,
        ];

        $this->userTable->update($record);

        $this->eventDispatcher->dispatch(new ChangedStatusEvent($userId, $user['status'], $status, $context), UserEvents::CHANGE_STATUS);
    }

    public function changePassword($oldPassword, $newPassword, $verifyPassword, UserContext $context)
    {
        $appId  = $context->getAppId();
        $userId = $context->getUserId();

        // we can only change the password through the backend app
        if (!in_array($appId, [1, 2])) {
            throw new StatusCode\BadRequestException('Changing the password is only possible through the backend or consumer app');
        }

        if (empty($newPassword)) {
            throw new StatusCode\BadRequestException('New password must not be empty');
        }

        if (empty($oldPassword)) {
            throw new StatusCode\BadRequestException('Old password must not be empty');
        }

        // check verify password
        if ($newPassword != $verifyPassword) {
            throw new StatusCode\BadRequestException('New password does not match the verify password');
        }

        // assert password complexity
        $this->assertPasswordComplexity($newPassword);

        // change password
        $result = $this->userTable->changePassword($userId, $oldPassword, $newPassword);

        if ($result) {
            $this->eventDispatcher->dispatch(new ChangedPasswordEvent($oldPassword, $newPassword, $context), UserEvents::CHANGE_PASSWORD);

            return true;
        } else {
            throw new StatusCode\BadRequestException('Changing password failed');
        }
    }

    public function getValidScopes($userId, array $scopes)
    {
        return Table\Scope::getNames($this->userScopeTable->getValidScopes($userId, $scopes));
    }

    public function getAvailableScopes($userId)
    {
        return Table\Scope::getNames($this->userScopeTable->getAvailableScopes($userId));
    }

    /**
     * Returns the default scopes which every new user gets automatically
     * assigned
     * 
     * @return array
     */
    public function getDefaultScopes()
    {
        $scopes = $this->configService->getValue('scopes_default');

        return array_filter(array_map('trim', Service\Scope::split($scopes)), function ($scope) {
            // we filter out the backend scope since this would be a major
            // security issue
            return !empty($scope) && $scope != 'backend';
        });
    }

    protected function insertScopes($userId, $scopes)
    {
        if (!empty($scopes) && is_array($scopes)) {
            $scopes = $this->scopeTable->getValidScopes($scopes);

            foreach ($scopes as $scope) {
                $this->userScopeTable->create(array(
                    'user_id'  => $userId,
                    'scope_id' => $scope['id'],
                ));
            }
        }
    }

    protected function updateAttributes($userId, $attributes)
    {
        if (empty($this->userAttributes)) {
            // in case we have no attributes defined
            return;
        }

        if (!empty($attributes)) {
            foreach ($attributes as $name => $value) {
                if (in_array($name, $this->userAttributes)) {
                    $this->userTable->setAttribute($userId, $name, $value);
                }
            }
        }
    }
}
