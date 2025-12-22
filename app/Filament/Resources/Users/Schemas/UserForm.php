<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\Organization;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->string()
                    ->maxLength(100),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->string()
                    ->maxLength(120)
                    // Evita erro 500 por violar a constraint única no banco
                    ->rule(fn ($record) => Rule::unique('users', 'email')->ignore($record?->id)),
                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->string()
                    ->minLength(8)
                    ->maxLength(100)
                    ->dehydrateStateUsing(fn($state) => bcrypt($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord),
                Select::make('role')
                    ->label('Função')
                    ->options(function () {
                        $current = Auth::user();
                        $all = Role::query()->pluck('name', 'id')->toArray();
                        if ($current && method_exists($current, 'hasRole') && $current->hasRole('super-admin')) {
                            return $all;
                        }
                        return collect($all)->reject(fn($name) => $name === 'super-admin')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->afterStateUpdated(function ($state, $livewire) {
                    })
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function ($component, $record, $state) {
                        if ($state) {
                            $role = Role::find($state);
                            if ($role) {
                                $actor = Auth::user();
                                if ($role->name === 'super-admin' && (! $actor || ! $actor->hasRole('super-admin'))) {
                                    return;
                                }
                                $record->syncRoles([$role->name]);
                            }
                        } else {
                            $record->syncRoles([]);
                        }
                    }),

                Select::make('organizations')
                    ->label('Organizações')
                    ->helperText('Selecione as organizações às quais o usuário pertence')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->relationship(
                        name: 'organizations',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query) {
                            $current = Auth::user();
                            if (! $current) {
                                return $query->whereRaw('0=1');
                            }
                            if (method_exists($current, 'hasRole') && $current->hasRole('super-admin')) {
                                return $query;
                            }
                            if (method_exists($current, 'hasRole') && $current->hasRole('organization-manager')) {
                                $orgIds = $current->organizations()->pluck('organizations.id')->toArray();
                                return $query->whereIn('organizations.id', $orgIds);
                            }
                            $orgIds = $current->organizations()->pluck('organizations.id')->toArray();
                            return $query->whereIn('organizations.id', $orgIds);
                        }
                    )
                    ->default(function () {
                        $current = Auth::user();
                        if (! $current) {
                            return [];
                        }
                        if (method_exists($current, 'hasRole') && $current->hasRole('organization-manager')) {
                            $orgIds = $current->organizations()->pluck('organizations.id')->toArray();
                            if (count($orgIds) === 1) {
                                return [$orgIds[0]];
                            }
                        }
                        return [];
                    })
                    ->required(fn() => Auth::user()?->hasRole('organization-manager')),
            ]);
    }
}
