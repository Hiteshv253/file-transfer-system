<?php

namespace App\Providers;

use App\Listeners\BackupUploadedFileToSynology;
use App\Listeners\DeleteSynologyBackupForEntries;
use App\Listeners\FolderTotalSizeSubscriber;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Common\Auth\Events\UserCreated;
use App\Listeners\HandleDeletedWorkspace;
use Common\Notifications\SubscribeUserToNotifications;
use Common\Workspaces\Events\WorkspaceDeleted;
use Common\Workspaces\Listeners\AttachWorkspaceToUser;
use App\Models\File;
use App\Models\FileEntry;
use App\Models\User;
use App\Services\Admin\GetAnalyticsHeaderData;
use App\Services\AppBootstrapData;
use App\Services\Entries\SetPermissionsOnEntry;
use Common\Admin\Analytics\Actions\GetAnalyticsHeaderDataAction;
use Common\Core\Bootstrap\BootstrapData;
use Common\Files\FileEntry as CommonFileEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use App\Models\Folder;
use App\Models\ShareableLink;
use App\Policies\DriveFileEntryPolicy;
use App\Policies\ShareableLinkPolicy;
use App\Services\Storage\SynologyBackupService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Common\Files\Events\FileEntriesDeleted;
use Common\Files\Events\FileUploaded;

const WORKSPACED_RESOURCES = [FileEntry::class];
const WORKSPACE_HOME_ROUTE = '/drive';

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Model::preventLazyLoading(!app()->isProduction());

        Relation::enforceMorphMap([
            FileEntry::MODEL_TYPE => FileEntry::class,
            User::MODEL_TYPE => User::class,
        ]);

        Gate::policy(CommonFileEntry::class, DriveFileEntryPolicy::class);
        Gate::policy(File::class, DriveFileEntryPolicy::class);
        Gate::policy(Folder::class, DriveFileEntryPolicy::class);
        Gate::policy(ShareableLink::class, ShareableLinkPolicy::class);
    }

    public function register()
    {
        $this->app->bind(
            GetAnalyticsHeaderDataAction::class,
            GetAnalyticsHeaderData::class,
        );

        $this->app->bind(BootstrapData::class, AppBootstrapData::class);

        $this->app->bind(CommonFileEntry::class, FileEntry::class);
        $this->app->singleton(SynologyBackupService::class);

        $this->app->singleton(
            SetPermissionsOnEntry::class,
            fn() => new SetPermissionsOnEntry(),
        );

        Event::listen(Login::class, AttachWorkspaceToUser::class);
        Event::listen(Registered::class, AttachWorkspaceToUser::class);
        Event::listen(WorkspaceDeleted::class, HandleDeletedWorkspace::class);
        Event::listen(FileUploaded::class, BackupUploadedFileToSynology::class);
        Event::listen(
            FileEntriesDeleted::class,
            DeleteSynologyBackupForEntries::class,
        );

        Event::listen(UserCreated::class, function (UserCreated $event) {
            app(SubscribeUserToNotifications::class)->execute(
                $event->user,
                null,
            );
        });

        Event::subscribe(FolderTotalSizeSubscriber::class);
    }
}
