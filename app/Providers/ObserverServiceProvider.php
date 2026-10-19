<?php
namespace App\Providers;

use App\Models\{Attachment, Client, User};
use App\Observers\{AttachmentObserver, ClientObserver, UserObserver};
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Client::observe(ClientObserver::class);
        foreach ([\App\Models\ClientContact::class, \App\Models\ClientAsset::class, \App\Models\ClientInteraction::class, \App\Models\ClientConsent::class] as $model) {
            $model::observe(\App\Observers\ClientRegistryObserver::class);
        }
        Attachment::observe(AttachmentObserver::class);
        User::observe(UserObserver::class);
        \App\Models\ClientVehicle::observe(\App\Observers\ClientInsuranceObserver::class);
        \App\Models\InsurancePolicy::observe(\App\Observers\ClientInsuranceObserver::class);
        \App\Models\SupplierAgency::observe(\App\Observers\SupplierAgencyObserver::class);
        \App\Models\SupplierCompany::observe(\App\Observers\SupplierAgencyObserver::class);
    }
}
