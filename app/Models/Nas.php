<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Nas extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang digunakan oleh model.
     * Secara bawaan Eloquent akan membaca 'nas', namun ditegaskan untuk keamanan skema.
     *
     * @var string
     */
    protected $table = 'nas';

    /**
     * Field yang dapat diisi secara mass-assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nasname',
        'shortname',
        'type',
        'ports',
        'secret',
        'server',
        'community',
        'description',
    ];

    /**
     * Tipe data casting untuk atribut tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ports' => 'integer',
    ];

    /**
     * Sembunyikan timestamps asli dari serialisasi JSON bawaan
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Catatan Tambahan (FreeRADIUS Native Table Setup)
    |--------------------------------------------------------------------------
    | Jika Anda menggunakan tabel 'nas' bawaan murni dari database FreeRADIUS
    | yang tidak memiliki kolom 'created_at' dan 'updated_at', un-comment baris di bawah:
    |
    | public $timestamps = false;
    |
    | Jika nama Primary Key pada tabel native FreeRADIUS menggunakan 'id',
    | maka bawaan Eloquent sudah cocok.
    */

    /**
     * Tambahkan atribut virtual 'last_update' ke output JSON
     */
    protected $appends = [
        'last_updated',
    ];

    /**
     * Properti untuk menyimpan status restart FreeRADIUS
     */
    public ?string $radiusStatus = null;

    /**
     * Accessor untuk menghasilkan atribut 'last_update'
     * Mengambil nilai updated_at, jika null gunakan created_at.
     */
    public function getLastUpdatedAttribute(): ?string
    {
        $time = $this->updated_at ?? $this->created_at;

        return $time ? $time->format('Y-m-d H:i:s') : null;
    }

    /**
     * Booted Method untuk handle Default Description dan Event Reload FreeRADIUS
     */
    protected static function booted(): void
    {
        // 1. Set default description jika kosong saat saving (create & update)
        static::saving(function (Nas $nas) {
            if (empty(trim((string) $nas->description))) {
                $nas->description = 'managed by API FreeRadius';
            }
        });

        // 2. Trigger Exec Reload FreeRADIUS
        $restartRadius = function (Nas $nas) {
            $output = [];
            $returnCode = 0;

            exec("sudo /usr/local/bin/restart_freeradius.sh 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                $nas->radiusStatus = 'konfigurasi sudah siap';

                Log::info("FreeRADIUS reloaded successfully after NAS change.", [
                    'nas_id'   => $nas->id,
                    'nasname'  => $nas->nasname,
                    'output'   => implode("\n", $output)
                ]);
            } else {
                $nas->radiusStatus = 'konfigurasi belum siap, silahkan restart freeradius';

                Log::error("Failed to reload FreeRADIUS after NAS change.", [
                    'nas_id'      => $nas->id,
                    'return_code' => $returnCode,
                    'output'      => implode("\n", $output)
                ]);
            }
        };

        static::created($restartRadius);
        static::updated($restartRadius);
        static::deleted($restartRadius);
    }
}
