<?php

namespace App\Console\Commands\OneTimers;

use App\User;
use Exception;
use Faker\Factory as FakerFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class anonymizeDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anonymize:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Anonymize user names and emails except for admins and fake students';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            DB::beginTransaction();

            $faker = FakerFactory::create();

            $adminEmails = DB::table('admin_emails')->pluck('email')->toArray();

            $users = User::whereNotIn('email', $adminEmails)
                ->where('fake_student', 0)
                ->where('formative_student', 0)
                ->get();
            foreach ($users as $user) {
                $firstName = $faker->firstName();
                $lastName = $faker->lastName();

                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->email = $faker->unique()->safeEmail();
                $user->save();

                echo "Anonymized user {$user->id}\r\n";
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            echo $e->getMessage();
        }

        return 0;
    }
}
