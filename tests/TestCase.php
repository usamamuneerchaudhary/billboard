<?php

namespace Billboard\Tests;

use Billboard\ServiceProvider;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $this->storage = sys_get_temp_dir().'/billboard-tests-'.bin2hex(random_bytes(4));

        $app['config']->set('billboard.storage.ads', $this->storage.'/ads');
        $app['config']->set('billboard.storage.placements', $this->storage.'/placements');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:m7xjq0B5QnGkF4f8S2sQ6r1a9U3dC0vYh5tZpL2wE8o=');
    }

    protected function userWithPermissions(array $permissions)
    {
        Role::make('billboard_test')->permissions(['access cp', ...$permissions])->save();

        return tap(User::make()->email('editor@example.com')->assignRole('billboard_test'))->save();
    }

    protected function superUser()
    {
        return tap(User::make()->email('admin@example.com')->makeSuper())->save();
    }
}
