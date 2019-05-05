<?php
/*
 * Fusio
 * A web-application to create dynamically RESTful APIs
 *
 * Copyright (C) 2015-2018 Christoph Kappestein <christoph.kappestein@gmail.com>
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

namespace Fusio\Impl\Console\Plan;

use Fusio\Impl\Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * RenewCommand
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.gnu.org/licenses/agpl-3.0
 * @link    http://fusio-project.org
 */
class RenewCommand extends Command
{
    /**
     * @var \Fusio\Impl\Service\Event\Executor
     */
    protected $eventExecutorService;

    public function __construct(Service\Event\Executor $eventExecutorService)
    {
        parent::__construct();

        $this->eventExecutorService = $eventExecutorService;
    }

    protected function configure()
    {
        $this
            ->setName('plan:renew')
            ->setDescription('Executes all pending events');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->eventExecutorService->execute();

        $output->writeln('Execution successful');
    }
}