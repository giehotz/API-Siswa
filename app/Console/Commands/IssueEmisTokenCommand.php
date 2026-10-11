<?php

namespace App\Console\Commands;

use App\Models\Institution;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('emis:issue-token {institution : ID internal atau emis_institution_id madrasah} {--name=EMIS-Extension-Sync : Nama label token} {--abilities=emis:sync,students:read : Daftar kemampuan token dipisah koma}')]
#[Description('Terbitkan token Sanctum baru untuk sinkronisasi ekstensi EMIS atau integrasi API madrasah')]
class IssueEmisTokenCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = $this->argument('institution');
        $tokenName = (string) $this->option('name');
        $abilitiesRaw = (string) $this->option('abilities');
        $abilities = array_filter(array_map('trim', explode(',', $abilitiesRaw)));

        /** @var Institution|null $institution */
        $institution = Institution::query()
            ->where('emis_institution_id', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (! $institution) {
            $this->error("Madrasah dengan ID / EMIS ID [{$identifier}] tidak ditemukan!");

            return self::FAILURE;
        }

        // Terbitkan token Sanctum dengan abilities yang ditentukan
        $token = $institution->createToken($tokenName, $abilities);

        $this->newLine();
        $this->info('=== TOKEN SINKRONISASI / API EMIS BERHASIL DITERBITKAN ===');
        $this->line("Madrasah        : <comment>{$institution->name}</comment>");
        $this->line("EMIS ID         : <comment>{$institution->emis_institution_id}</comment>");
        $this->line("NSM / NPSN      : <comment>{$institution->nsm} / {$institution->npsn}</comment>");
        $this->line('Abilities       : <comment>'.implode(', ', $abilities).'</comment>');
        $this->newLine();
        $this->line('Salin token di bawah ini ke pengaturan popup Ekstensi Chrome:');
        $this->line("<fg=green;options=bold>{$token->plainTextToken}</>");
        $this->newLine();

        return self::SUCCESS;
    }
}
