<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\ImageModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Components\FileUpload;
use App\Filament\Components\PortraitImageUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Group;
use Illuminate\Support\Facades\Http;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Pesanan';

    public static function getModelLabel(): string
    {
        return 'Pesanan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Daftar Pesanan';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Pesanan';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Repeater::make('images')
                    ->label('Foto Model')
                    ->schema([
                        FileUpload::make('photo')
                            ->label('Photo')
                            ->image()
                            ->imagePreviewHeight('120') // Ukuran preview kecil
                            ->imageCropAspectRatio('1:1') // Jadi persegi
                            ->imageResizeMode('cover')
                            ->disk('public')
                            ->directory('order_images')
                            ->visibility('public')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->enableOpen()
                            ->previewable(true),
                    ])
                    ->addActionLabel('Tambah') // Tombol tambah yang bisa diklik
                    ->columnSpanFull()
                    ->grid(3) // Menampilkan dalam bentuk grid
                    ->defaultItems(0) // Tidak ada data awal
                    ->reorderable()
                    ->collapsible(false)
                    ->createItemButtonLabel('Tambah')
                    ->columns(1),
                Forms\Components\TextInput::make('name')->validationAttribute('full name')
                    ->label('Nama Pemesanan')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Masukkan nama pemesanan')
                    ->validationMessages([
                        'required' => 'Nama pemesanan wajib diisi.',
                    ]),                
                Forms\Components\Textarea::make('address')
                    ->label('Alamat Pemesanan')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('deadline')
                    ->label('Batas Waktu')
                    ->required(),
                Forms\Components\TextInput::make('phone')
                    ->label('No.Telefon Pemesan')
                    ->tel()
                    ->required('No. Telefon Pemesan wajib diisi.')
                    ->maxLength(255),
                Forms\Components\TextInput::make('quantity')
                    ->label('Jumlah')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('sizemodel_id')
                    ->label('Pilih Model Ukuran')
                    ->options(\App\Models\SizeModel::pluck('name', 'id')) // Ambil data dari tabel size_models
                    ->required()
                    ->live() // Aktifkan live update
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        // Ambil data ukuran berdasarkan sizemodel_id yang dipilih
                        $sizeModel = \App\Models\SizeModel::find($state);
                        if ($sizeModel) {
                            $set('size', $sizeModel->size); // Set nilai size ke form
                        }
                    }),

                // Section untuk menampilkan field ukuran dinamis
                Section::make('Ukuran')
                ->schema(function (Get $get) {
                    $sizemodelId = $get('sizemodel_id');
                    if (!$sizemodelId) {
                        return []; 
                    }

                    $sizeModel = \App\Models\SizeModel::find($sizemodelId);
                    if (!$sizeModel) {
                        return [];
                    }

                    $sizeFields = is_string($sizeModel->size) ? json_decode($sizeModel->size, true) : $sizeModel->size;

                    $fields = [];
                    foreach ($sizeFields as $fieldName) {
                        // Bersihkan nama field dari spasi di awal dan akhir
                        $cleanFieldName = trim($fieldName);
                        
                        // Buat key yang aman untuk form
                        $safeKey = str_replace(' ', '_', strtolower($cleanFieldName));
                        
                        $fields[] = TextInput::make("size.{$safeKey}")
                            ->label($cleanFieldName)
                            ->numeric()
                            ->required('wajib diisi.');
                        }

                    return [
                        Grid::make(3)
                            ->schema($fields)
                    ];
                })
                ->columnSpanFull()
            ])
            ->submitAction(null) // pastikan tidak pakai form default submit
            ->extraAttributes(['novalidate' => true]); // ini yang penting
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pemesan') // Menambahkan label
                    ->searchable(),
                Tables\Columns\TextColumn::make('deadline')
                    ->label('Batas Waktu') // Menambahkan label
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Nomor Telepon') // Menambahkan label
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Jumlah') // Menambahkan label
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status') // Menambahkan label
                    ->searchable(),
                Tables\Columns\TextColumn::make('sizeModel.name') // Mengambil name dari relasi
                    ->label('Model Ukuran') // Ubah label kolom ke bahasa Indonesia
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name') // Mengambil nama dari relasi user
                    ->label('Ditugaskan Ke')
                    ->sortable()
                    ->searchable(),
                // Tables\Columns\TextColumn::make('created_at')
                //     ->label('Dibuat Pada') // Menambahkan label
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('updated_at')
                //     ->label('Diperbarui Pada') // Menambahkan label
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Tombol "Selesai" hanya muncul jika statusnya "dikerjakan"
                Tables\Actions\Action::make('selesai')
                    ->label('Selesai')
                    ->icon('heroicon-o-check-circle') // Ikon centang
                    ->color('success') // Warna hijau
                    ->visible(fn ($record) => $record->status === 'dikerjakan') // Hanya tampil jika status "dikerjakan"
                    ->action(function ($record) {
                        $record->update(['status' => 'selesai']);

                        // Kirim WA lewat Fonnte
                        try {
                            $response = Http::withHeaders([
                                'Authorization' => 'R5uHqhjeppTQbDefuzxY', // Ganti token sesuai
                            ])->post('https://api.fonnte.com/send', [
                                'target' => $record->phone,
                                'message' => "Pesanan Anda di Rumah Jahit Mawar telah selesai dan sudah bisa diambil. Terima kasih atas kepercayaan Anda!",
                                'countryCode' => '62',
                            ]);

                            if ($response->failed()) {
                                logger()->error('Gagal kirim WA (selesai): ' . $response->body());
                            }
                        } catch (\Exception $e) {
                            logger()->error('Error kirim WA (selesai): ' . $e->getMessage());
                        }
                    })
                    ->requiresConfirmation() // Konfirmasi sebelum update
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
