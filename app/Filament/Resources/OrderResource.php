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
                Select::make('image_id')
                ->label('Pilih Gambar')
                ->options(ImageModel::pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $image = ImageModel::find($state);
                        $set('selected_image', $image?->image_url);
                    } else {
                        $set('selected_image', null);
                    }
                }),

            Forms\Components\Placeholder::make('preview')
                ->label('Preview Gambar')
                ->content(function ($get) {
                    $imageUrl = $get('selected_image');
                    if (!$imageUrl) return 'Tidak ada gambar yang dipilih';

                    return view('components.image-preview-content', ['imageUrl' => $imageUrl]);
                })
                ->columns(1), // Ini akan membuat section selebar mungkin
                Forms\Components\TextInput::make('name')
                    ->label('Nama Pemesanan')
                    ->required()
                    ->maxLength(255),
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
                    ->required()
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
                        return []; // Jika sizemodel_id belum dipilih, kembalikan schema kosong
                    }

                    // Ambil data size dari tabel size_models
                    $sizeModel = \App\Models\SizeModel::find($sizemodelId);
                    if (!$sizeModel) {
                        return [];
                    }

                    // Periksa apakah size adalah string JSON atau sudah array
                    $sizeFields = is_string($sizeModel->size) ? json_decode($sizeModel->size, true) : $sizeModel->size;

                    // Buat field input untuk setiap ukuran
                    $fields = [];
                    foreach ($sizeFields as $fieldName) {
                        $fields[] = TextInput::make("size.$fieldName")
                            ->label($fieldName)
                            ->numeric()
                            ->required();
                    }

                    // Tampilkan field dalam grid
                    return [
                        Grid::make(3)
                            ->schema($fields)
                    ];
                })
                ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pesanan') // Menambahkan label
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada') // Menambahkan label
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui Pada') // Menambahkan label
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
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
                    ->action(fn ($record) => $record->update(['status' => 'selesai'])) // Update status ke "selesai"
                    ->requiresConfirmation() // Konfirmasi sebelum update
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
