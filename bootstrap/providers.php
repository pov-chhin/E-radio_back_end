<?php

use App\Providers\AppServiceProvider;
use Src\Catalog\Providers\CatalogServiceProvider;
use Src\CmsAdmin\Providers\CmsAdminServiceProvider;
use Src\Notification\Providers\NotificationServiceProvider;
use Src\RadioSceaper\Providers\RadioSceaperServiceProvider;
use Src\RadioStream\Providers\RadioStreamServiceProvider;
use Src\Setting\Providers\SettingServiceProvider;
use Src\Social\Providers\SocialServiceProvider;
use Src\User\Providers\UserServiceProvider;
use Src\UserManagement\Providers\UserManagementServiceProvider;

return [
    AppServiceProvider::class,
    SettingServiceProvider::class,
    CatalogServiceProvider::class,
    NotificationServiceProvider::class,
    RadioStreamServiceProvider::class,
    RadioSceaperServiceProvider::class,
    SocialServiceProvider::class,
    UserServiceProvider::class,
    UserManagementServiceProvider::class,
    CmsAdminServiceProvider::class,
];
