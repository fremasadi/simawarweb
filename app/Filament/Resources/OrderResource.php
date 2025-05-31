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
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Set;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ViewField;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\MultiSelect;
use App\Models\Accessory;  // <== Import model di sini

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
                    Radio::make('image_source')
                        ->label('Sumber Gambar')
                        ->options([
                            'existing' => 'Pilih dari Model yang Ada',
                            'upload' => 'Upload Baru'
                        ])
                        ->default('upload')
                        ->inline()
                        ->live()
                        ->columnSpanFull(),

                    Select::make('temp_image_model_id')
                        ->label('Pilih Model Gambar')
                        ->options(function () {
                            return \App\Models\ImageModel::all()->mapWithKeys(function ($model) {
                                return [$model->id => $model->name];
                            });
                        })
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get) => $get('image_source') === 'existing')
                        ->live()
                        ->placeholder('Pilih model untuk melihat preview...')
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                $imageModel = \App\Models\ImageModel::find($state);
                                if ($imageModel && $imageModel->image) {
                                    // Set path gambar ke field photo
                                    $set('photo', $imageModel->image);
                                }
                            } else {
                                $set('photo', null);
                            }
                        }),

                    \Filament\Forms\Components\Placeholder::make('image_preview')
                        ->label('Preview Gambar')
                        ->visible(fn (Get $get) => $get('image_source') === 'existing' && $get('temp_image_model_id'))
                        ->content(function (Get $get) {
                            if ($get('temp_image_model_id')) {
                                $imageModel = \App\Models\ImageModel::find($get('temp_image_model_id'));
                                if ($imageModel && $imageModel->image) {
                                    $imageUrl = Storage::disk('public')->url($imageModel->image);
                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="space-y-3">
                                            <div class="border rounded-lg p-4 bg-gray-50">
                                                <img src="' . $imageUrl . '" alt="' . $imageModel->name . '" 
                                                     class="w-full max-w-md mx-auto rounded border shadow-sm">
                                                <p class="text-center text-sm text-gray-600 mt-2 font-medium">
                                                    Estimasi Harga: Rp ' . number_format($imageModel->price ?? 0, 0, ',', '.') . '
                                                </p>
                                                <div class="flex flex-col items-center mt-3 space-y-1">
                                                    <a href="' . $imageUrl . '" target="_blank" 
                                                       class="text-sm text-blue-600 hover:underline">
                                                        Buka di Tab Baru
                                                    </a>
                                                </div>
                                            </div>
                                        </div>'
                                    );
                                }
                            }
                            return '';
                        }),

                    FileUpload::make('photo')
                        ->label('Upload Foto')
                        ->image()
                        ->imagePreviewHeight('120')
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeMode('cover')
                        ->disk('public')
                        ->directory('order_images')
                        ->visibility('public')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->enableOpen()
                        ->previewable(true)
                        ->visible(fn (Get $get) => $get('image_source') === 'upload')
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                // Reset temp field saat upload
                                $set('temp_image_model_id', null);
                            }
                        }),
                ])
                ->addActionLabel('Tambah Gambar')
                ->columnSpanFull()
                ->defaultItems(1)
                ->reorderable()
                ->collapsible(false)
                ->createItemButtonLabel('Tambah Gambar')
                ->columns(1)
                ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                    // Hanya simpan field photo saja, hapus field lainnya
                    return collect($data)->map(function ($item) {
                        return ['photo' => $item['photo'] ?? null];
                    })->filter(function ($item) {
                        return !empty($item['photo']);
                    })->values()->toArray();
                }),

            Forms\Components\Select::make('customer_id')
                ->label('Pilih Customer')
                ->options(\App\Models\Customer::all()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, \Filament\Forms\Set $set) {
                    $customer = \App\Models\Customer::find($state);
                    if ($customer) {
                        $set('name', $customer->name);
                        $set('phone', $customer->phone);
                        $set('address', $customer->address);
                    }
                }),
            
            Forms\Components\TextInput::make('name')
                ->label('Nama Pemesanan')
                ->required()
                ->maxLength(255)
                ->readonly(),

            Forms\Components\Textarea::make('address')
                ->label('Alamat Pemesanan')
                ->required()
                ->maxLength(255)
                ->readonly(),

            Forms\Components\TextInput::make('phone')
                ->label('No.Telefon Pemesan')
                ->tel()
                ->required('No. Telefon Pemesan wajib diisi.')
                ->maxLength(255)
                ->readonly(),

            MultiSelect::make('accessories_list')
                ->label('Pilih Accessories')
                ->options(function () {
                    return Accessory::all()->mapWithKeys(function ($item) {
                        return [$item->id => $item->name . ' (Rp ' . number_format($item->price, 0, ',', '.') . ')'];
                    })->toArray();
                })
                ->placeholder('Pilih accessories')
                ->searchable()
                ->columnSpanFull()
                ->helperText('Pilih satu atau lebih accessories yang terkait dengan order'),
            
            Forms\Components\TextInput::make('description')
                ->label('Deskripsi')
                ->maxLength(255),

            Select::make('ditugaskan_ke')
                ->label('Ditugaskan Ke')
                ->relationship(
                    name: 'user',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query) => $query
                        ->where('role', 'karyawan')
                        ->whereDoesntHave('orders', function (Builder $subQuery) {
                            $subQuery->where('status', 'dikerjakan');
                        }),
                )
                ->searchable()
                ->preload()
                ->required(),

            Select::make('sizemodel_id')
                ->label('Pilih Model Ukuran')
                ->options(\App\Models\SizeModel::pluck('name', 'id'))
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    if (!$state) return;
                    
                    $sizeModel = \App\Models\SizeModel::find($state);
                    $quantity = (int) $get('quantity') ?: 1;

                    if ($sizeModel && $sizeModel->deadline) {
                        preg_match('/(\d+)/', $sizeModel->deadline, $matches);
                        $baseDays = (int) ($matches[1] ?? 1);
                        $totalDays = $baseDays * $quantity;
                        $newDeadline = Carbon::now()->addDays($totalDays);
                        $set('deadline', $newDeadline->format('Y-m-d H:i:s'));
                        
                        if (isset($sizeModel->size)) {
                            $set('size', $sizeModel->size);
                        }
                    }
                }),

            TextInput::make('quantity')
                ->label('Jumlah')
                ->numeric()
                ->default(1)
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get) {
                    $sizeModelId = $get('sizemodel_id');
                    if (!$sizeModelId) return;
                    
                    $sizeModel = \App\Models\SizeModel::find($sizeModelId);
                    $quantity = (int) $state ?: 1;

                    if ($sizeModel && $sizeModel->deadline) {
                        preg_match('/(\d+)/', $sizeModel->deadline, $matches);
                        $baseDays = (int) ($matches[1] ?? 1);
                        $totalDays = $baseDays * $quantity;
                        $newDeadline = Carbon::now()->addDays($totalDays);
                        $set('deadline', $newDeadline->format('Y-m-d H:i:s'));
                    }
                }),

            DateTimePicker::make('deadline')
                ->label('Batas Waktu')
                ->required()
                ->displayFormat('d/m/Y H:i')
                ->format('Y-m-d H:i:s')
                ->readonly(),

            Forms\Components\TextInput::make('price')
                ->label('Harga')
                ->tel()
                ->required('Harga wajib diisi.')
                ->maxLength(255),

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
                        $cleanFieldName = trim($fieldName);
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
        ]);
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
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->searchable(),
                
                // Tables\Columns\TextColumn::make('phone')
                //     ->label('Nomor Telepon') // Menambahkan label
                //     ->searchable(),
                // Tables\Columns\TextColumn::make('quantity')
                //     ->label('Jumlah') // Menambahkan label
                //     ->numeric()
                //     ->sortable(),
                    
                
                // Tables\Columns\TextColumn::make('sizeModel.name') // Mengambil name dari relasi
                //     ->label('Model Ukuran') // Ubah label kolom ke bahasa Indonesia
                //     ->sortable(),
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
                // Tables\Actions\EditAction::make(),

                // Tombol "Selesai" hanya muncul jika statusnya "dikerjakan"
                
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
            // 'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
