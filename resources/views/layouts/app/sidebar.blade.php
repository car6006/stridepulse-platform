<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('athletes.index')" :current="request()->routeIs('athletes.*')" wire:navigate>
                        {{ __('Athletes') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('devices.index')" :current="request()->routeIs('devices.*')" wire:navigate>
                        {{ __('Devices') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="layout-grid" :href="route('devices.unclaimed')" :current="request()->routeIs('devices.unclaimed')" wire:navigate>
                        {{ __('Unclaimed devices') }}
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
                    <flux:sidebar.item icon="layout-grid" :href="route('operations.logs')" :current="request()->routeIs('operations.*')" wire:navigate>
                        {{ __('Operations') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
