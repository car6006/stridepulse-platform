<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('athletes.index')" :current="request()->routeIs('athletes.*')" wire:navigate>
                    {{ __('Athletes') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('devices.index')" :current="request()->routeIs('devices.*')" wire:navigate>
                    {{ __('Devices') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('garmin-setup.index')" :current="request()->routeIs('garmin-setup.*')" wire:navigate>
                    {{ __('Garmin setup') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('live-sessions.index')" :current="request()->routeIs('live-sessions.*')" wire:navigate>
                    {{ __('Live sessions') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <x-desktop-user-menu />
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')">
                    <flux:sidebar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard')  }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('athletes.index')" :current="request()->routeIs('athletes.*')" wire:navigate>
                        {{ __('Athletes') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('devices.index')" :current="request()->routeIs('devices.*')" wire:navigate>
                        {{ __('Devices') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('garmin-setup.index')" :current="request()->routeIs('garmin-setup.*')" wire:navigate>
                        {{ __('Garmin setup') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('tracking-sessions.create')" :current="request()->routeIs('tracking-sessions.*')" wire:navigate>
                        {{ __('Start session') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('live-sessions.index')" :current="request()->routeIs('live-sessions.*')" wire:navigate>
                        {{ __('Live sessions') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />
        </flux:sidebar>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
