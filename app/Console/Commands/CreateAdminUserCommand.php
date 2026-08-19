<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('tifa:admin-create {--name= : Nama lengkap administrator} {--email= : Alamat email administrator} {--password= : Kata sandi administrator}')]
#[Description('Buat akun administrator baru untuk panel administrasi TIFAA')]
class CreateAdminUserCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Pembuatan Akun Administrator TIFAA');

        $name = (string) ($this->option('name') ?? $this->ask('Nama Lengkap'));
        $email = (string) ($this->option('email') ?? $this->ask('Alamat Email'));
        $password = (string) ($this->option('password') ?? $this->secret('Kata Sandi (min. 8 karakter)'));

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Nama administrator wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email sudah terdaftar.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->components->info("Akun administrator [{$user->email}] berhasil dibuat.");

        return self::SUCCESS;
    }
}
