<?php

namespace App\Models\Tenants;

use App\Filament\Tenant\Resources\Traits\HasUploadFileField;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @mixin IdeHelperAbout
 * @property int $id
 * @property string|null $shop_name
 * @property string|null $shop_location
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $npwp
 * @property string $currency
 * @property string|null $business_type
 * @property string|null $other_business_type
 * @property string|null $photo
 * @property-read string|null $logo_url
 */
class About extends Model
{
    use HasFactory,
        HasUploadFileField;

    protected $guarded = ['id'];

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->photo)) {
            return null;
        }

        if (Str::startsWith($this->photo, ['http://', 'https://'])) {
            return $this->photo;
        }

        $uploadDisk = config('filesystems.upload_disk', 'public');

        return Storage::disk($uploadDisk)->url($this->photo);
    }

    public static function form(): array
    {
        return [
            FileUpload::make('photo')
                ->label(__('Logo Perusahaan / Toko'))
                ->disk(config('filesystems.upload_disk'))
                ->directory('profile')
                ->imageResizeMode('cover')
                ->imageCropAspectRatio('1:1')
                ->imageEditor()
                ->image()
                ->helperText(__('Format: PNG/JPG/WebP. Logo ini akan digunakan pada kop struk transaksi dan aplikasi.'))
                ->getUploadedFileUsing(function ($file, string|array|null $storedFileNames, $component) {
                    $static = new static;

                    return $static->getUploadedFileUsing($component, $file, $storedFileNames);
                })
                ->imageEditorMode(2)
                ->translateLabel(),
            TextInput::make('shop_name')
                ->label(__('Nama Perusahaan / Toko'))
                ->placeholder(__('Contoh: PT Berkah Kasir Mandiri'))
                ->required()
                ->translateLabel(),
            TextInput::make('npwp')
                ->label(__('NPWP (Nomor Pokok Wajib Pajak)'))
                ->placeholder(__('Contoh: 01.234.567.8-901.000'))
                ->nullable()
                ->translateLabel(),
            TextInput::make('phone')
                ->label(__('Nomor Telepon'))
                ->placeholder(__('Contoh: 0812-3456-7890 / (021) 555-1234'))
                ->tel()
                ->nullable()
                ->translateLabel(),
            TextInput::make('email')
                ->label(__('Email Perusahaan'))
                ->placeholder(__('Contoh: info@perusahaan.com'))
                ->email()
                ->nullable()
                ->translateLabel(),
            TextInput::make('website')
                ->label(__('Website'))
                ->placeholder(__('Contoh: https://www.perusahaan.com'))
                ->nullable()
                ->translateLabel(),
            Select::make('business_type')
                ->label(__('Jenis Usaha'))
                ->translateLabel()
                ->options([
                    'retail' => __('Retail'),
                    'wholesale' => __('Wholesale'),
                    'fnb' => __('F&B'),
                    'fashion' => __('Fashion'),
                    'pharmacy' => __('Pharmacy'),
                    'other' => __('Other'),
                ])
                ->live()
                ->required(),
            TextInput::make('other_business_type')
                ->label(__('Lainnya'))
                ->visible(fn (Get $get): bool => $get('business_type') == 'other')
                ->required(fn (Get $get): bool => $get('business_type') == 'other')
                ->string(),
            Textarea::make('shop_location')
                ->label(__('Alamat Lengkap Perusahaan / Toko'))
                ->placeholder(__('Contoh: Jl. Sudirman No. 123, Kel. Gambir, Jakarta Pusat'))
                ->required()
                ->translateLabel(),
            Actions::make([
                Action::make('Save')
                    ->translateLabel()
                    ->requiresConfirmation()
                    ->action('saveAbout'),
            ]),
        ];
    }
}
