<?php

namespace History\Console\Commands\Sync;

use History\Console\Commands\AbstractCommand;
use History\Entities\Models\Company;
use History\Entities\Models\Request;
use History\Entities\Models\Threads\Group;
use History\Entities\Models\Threads\Thread;
use History\Entities\Models\User;

class SlugsCommand extends AbstractCommand
{
    /**
     * Run the command.
     */
    public function run()
    {
        $models = [User::class, Request::class, Company::class, Thread::class, Group::class];

        foreach ($models as $model) {
            $entries = $model::all();
            $this->output->comment('Refreshing '.class_basename($model).' slugs');
            $iterator = $this->output->progressIterator($entries);
            foreach ($iterator as $entity) {
                $entity->slug = $entity->getSlug();
                $entity->save();
            }
        }
    }
}
