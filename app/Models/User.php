<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public function canAccessPanel(Panel $panel): bool
    {
        // Se for super-admin, libera tudo
        if ($this->hasRole('super-admin')) {
            return true;
        }

        // Se tiver qualquer outra role, também libera (o Shield cuida das permissões específicas depois)
        // Se você quiser restringir o acesso ao painel apenas para quem tem roles, descomente a linha abaixo:
        // return $this->roles()->exists();

        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

        protected static function boot()
        {
            parent::boot();

            // Impedir que não-super-admin atribua role super-admin indevidamente
            static::saving(function (User $user) {
                $actor = auth()->user();
                if ($actor && method_exists($actor, 'hasRole') && ! $actor->hasRole('super-admin')) {
                    // Se o usuário já existe e tentarem sincronizar a role super-admin via formulário/relationship
                    if ($user->exists) {
                        // Remover pending super-admin do relation atribuído (post-save sincroniza via Filament)
                        // Não temos acesso direto às roles selecionadas antes do sync aqui, então após salvar garantimos a limpeza abaixo.
                    }
                }
            });

            static::saved(function (User $user) {
                $actor = auth()->user();
                if ($actor && method_exists($actor, 'hasRole') && ! $actor->hasRole('super-admin')) {
                    // Se por algum motivo a role super-admin foi atribuída, removê-la
                    if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                        $user->removeRole('super-admin');
                    }
                }
            });

        }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class)
            ->select('organizations.*'); // Especifica explicitamente as colunas da tabela organizations
    }

}
