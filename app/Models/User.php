<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'pinfl', 'passport', 'salary_rate', 'balance', 'face_encoding_id', 'last_entry', 'last_exit', 'photo_path', 'phone', 'theme_color', 'language', 'theme_style', 'telegram_chat_id', 'label_font_size', 'label_color', 'dropdown_bg_color', 'dropdown_text_color', 'gender', 'birth_date', 'employment_date', 'status', 'specialty_id', 'contract_type', 'characteristics', 'passport_front', 'passport_back', 'additional_documents'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

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

    public function transactions()
    {
        return $this->hasMany(UserTransaction::class);
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }
}
