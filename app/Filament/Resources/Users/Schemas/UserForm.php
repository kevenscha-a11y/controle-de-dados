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
                    ->rules([
                        // Evita erro 500 por violação de unique no banco
                        fn ($livewire) => Rule::unique('users', 'email')->ignore($livewire->record?->id),
                    ])
                    ->maxLength(120),
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
                                // Seleciona somente colunas necessárias para evitar DISTINCT * em tabelas com JSON
                                return $query->select(['organizations.id', 'organizations.name'])->orderBy('organizations.name');
                            }
                            if (method_exists($current, 'hasRole') && $current->hasRole('organization-manager')) {
                                $orgIds = $current->organizations()->pluck('organizations.id')->toArray();
                                return $query->select(['organizations.id', 'organizations.name'])
                                    ->whereIn('organizations.id', $orgIds)
                                    ->orderBy('organizations.name');
                            }
                            $orgIds = $current->organizations()->pluck('organizations.id')->toArray();
                            return $query->select(['organizations.id', 'organizations.name'])
                                ->whereIn('organizations.id', $orgIds)
                                ->orderBy('organizations.name');
                        }
                    )
                    // Preload/Options: evita SELECT DISTINCT * gerado internamente pelo relacionamento
                    ->options(function () {
                        $current = Auth::user();
                        if (! $current) {
                            return [];
                        }
                        $base = Organization::query()->select(['organizations.id', 'organizations.name'])
                            ->orderBy('organizations.name');
                        if (! (method_exists($current, 'hasRole') && $current->hasRole('super-admin'))) {
                            $orgIds = $current->organizations()->pluck('organizations.id')->toArray();
                            $base->whereIn('organizations.id', $orgIds);
                        }
                        return $base->limit(50)->pluck('organizations.name', 'organizations.id')->toArray();
                    })
                    // Busca customizada para evitar DISTINCT * no Postgres (colunas JSON)
                    ->getSearchResultsUsing(function (string $search) {
                        $current = Auth::user();
                        $base = Organization::query()->select(['organizations.id', 'organizations.name'])
                            ->when(strlen($search) > 0, function ($q) use ($search) {
                                // Compatível com Postgres e demais drivers
                                return $q->whereRaw('LOWER(organizations.name) LIKE ?', ['%' . strtolower($search) . '%']);
                            })
                            ->orderBy('organizations.name')
                            ->limit(50);

                        if (! $current) {
                            return [];
                        }
                        if (! (method_exists($current, 'hasRole') && $current->hasRole('super-admin'))) {
                            $orgIds = $current->organizations()->pluck('organizations.id')->toArray();
                            $base->whereIn('organizations.id', $orgIds);
                        }

                        return $base->pluck('organizations.name', 'organizations.id')->toArray();
                    })
                    // Labels dos valores selecionados, sem DISTINCT e sem selecionar colunas JSON
                    ->getOptionLabelsUsing(function (array $values) {
                        if (empty($values)) {
                            return [];
                        }
                        return Organization::query()
                            ->select(['organizations.id', 'organizations.name'])
                            ->whereIn('organizations.id', $values)
                            ->pluck('organizations.name', 'organizations.id')
                            ->toArray();
                    })
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
