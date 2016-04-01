<?php

use History\Entities\Models\User;
use Phinx\Seed\AbstractSeed;

class Users extends AbstractSeed
{
    /**
     * Run Method.
     * Write your database seeder using this method.
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        User::seed(50);
    }
}
