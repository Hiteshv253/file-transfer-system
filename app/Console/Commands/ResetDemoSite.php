<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Demo\HydrateUserWithSampleDriveContents;
use Common\Auth\Permissions\Permission;
use Common\Core\Install\UpdateActions;
use Common\Files\Uploads\Uploads;
use Common\Search\ImportRecordsIntoScout;
use Common\Settings\Settings;
use Faker\Generator;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class ResetDemoSite extends Command
{
    protected $signature = 'demo:reset';

    public function handle(): int
    {
        Artisan::call('optimize:clear');
        Artisan::call('down');

        $originalScoutDriver = config('scout.driver');
        config()->set('scout.driver', 'null');

        $originalCacheDriver = config('cache.default');
        config()->set('cache.default', 'null');

        $this->recreateDatabase();
        $this->deleteAllUploadedFiles();

        app(Settings::class)->loadSettings();

        $this->createDemoAccounts();

        config()->set('cache.default', $originalCacheDriver);
        config()->set('scout.driver', $originalScoutDriver);

        (new ImportRecordsIntoScout())->execute('*');

        Artisan::call('up');
        if (config('app.env') === 'production') {
            Artisan::call('optimize');
        }

        return Command::SUCCESS;
    }

    protected function recreateDatabase()
    {
        Schema::dropAllTables();

        (new UpdateActions())->execute();

        Artisan::call('migrate', ['--force' => true]);
    }

    protected function deleteAllUploadedFiles()
    {
        $types = Uploads::getAllTypes();

        foreach ($types as $type) {
            $backends = Uploads::getAllBackends($type);

            foreach ($backends as $backend) {
                $disk = Uploads::disk($type, $backend);

                foreach ($disk->allDirectories() as $directory) {
                    $disk->deleteDirectory($directory);
                }

                foreach ($disk->allFiles() as $file) {
                    $disk->delete($file);
                }
            }
        }
    }

    protected function createDemoAccounts(): array
    {
        $faker = Container::getInstance()->make(Generator::class);
        $adminPermission = Permission::where('name', 'admin')->first();

        $gender = $faker->randomElement(['male', 'female']);
        $avatarNumber = random_int(1, 4);
        $demoUser = User::create([
            'name' => $faker->name($gender),
            'email' => $faker->unique()->safeEmail($gender),
            'email_verified_at' => now(),
            'language' => $faker->languageCode,
            'country' => $faker->countryCode,
            'timezone' => $faker->timezone,
            'image' => url("images/avatars/gender-{$avatarNumber}.jpg"),
        ]);

        $demoAdmin = User::create([
            'name' => 'Demo Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('admin'),
            'email_verified_at' => now(),
        ]);
        $demoAdmin->permissions()->attach($adminPermission);

        (new HydrateUserWithSampleDriveContents(
            $demoAdmin,
            $demoUser,
        ))->execute();

        $superAdmin = User::create([
            'email' => config('app.demo_email'),
            'type' => 'admin',
            'password' => config('app.demo_password'),
        ]);
        $superAdmin->permissions()->attach($adminPermission->id);

        return [
            'demoUser' => $demoUser,
            'demoAdmin' => $demoAdmin,
            'superAdmin' => $superAdmin,
        ];
    }
}
