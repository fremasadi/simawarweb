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
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Filters\Filter;

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
                // Section Preview - hanya tampil saat preview mode
                Section::make('Preview Order')
                    ->description('Pastikan semua data sudah benar sebelum menyimpan')
                    ->schema([
                        Placeholder::make('preview_content')
                            ->label('')
                            ->content(function (Get $get, $livewire) {
                                // Cek jika sedang dalam preview mode
                                if (!($livewire->isPreviewMode ?? false)) {
                                    return '';
                                }
                                
                                // Ambil data dari form state
                                $data = $livewire->data ?? [];
                                
                                // Generate HTML preview
                                $html = '<div class="bg-white border rounded-lg p-6 space-y-4">';
                                
                                // Header
                                $html .= '<div class="border-b pb-3 mb-4">';
                                $html .= '<h3 class="text-lg font-bold text-gray-900">Ringkasan Order</h3>';
                                $html .= '</div>';
                                
                                // Customer Info & Order Info dalam grid
                                $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
                                
                                // Customer Info
                                $html .= '<div class="space-y-3">';
                                $html .= '<h4 class="font-semibold text-gray-800 border-b pb-1">Informasi Customer</h4>';
                                $html .= '<div class="space-y-2 text-sm">';
                                $html .= '<div class="flex"><span class="font-medium w-20">Nama:</span><span>' . ($data['name'] ?? '-') . '</span></div>';
                                $html .= '<div class="flex"><span class="font-medium w-20">No. Telepon:</span><span>' . ($data['phone'] ?? '-') . '</span></div>';
                                $html .= '<div class="flex flex-col"><span class="font-medium">Alamat:</span><span class="ml-0 mt-1 text-gray-600">' . ($data['address'] ?? '-') . '</span></div>';
                                $html .= '</div>';
                                $html .= '</div>';
                                
                                // Order Info
                                $html .= '<div class="space-y-3">';
                                $html .= '<h4 class="font-semibold text-gray-800 border-b pb-1">Detail Order</h4>';
                                $html .= '<div class="space-y-2 text-sm">';
                                $html .= '<div class="flex"><span class="font-medium w-20">Jumlah:</span><span>' . ($data['quantity'] ?? 1) . ' pcs</span></div>';
                                $html .= '<div class="flex"><span class="font-medium w-20">Harga:</span><span>Rp ' . number_format($data['price'] ?? 0, 0, ',', '.') . '</span></div>';
                                
                                if (!empty($data['deadline'])) {
                                    $html .= '<div class="flex"><span class="font-medium w-20">Deadline:</span><span>' . \Carbon\Carbon::parse($data['deadline'])->format('d/m/Y H:i') . '</span></div>';
                                } else {
                                    $html .= '<div class="flex"><span class="font-medium w-20">Deadline:</span><span>-</span></div>';
                                }
                                
                                if (!empty($data['description'])) {
                                    $html .= '<div class="flex flex-col"><span class="font-medium">Deskripsi:</span><span class="ml-0 mt-1 text-gray-600">' . $data['description'] . '</span></div>';
                                }
                                $html .= '</div>';
                                $html .= '</div>';
                                
                                $html .= '</div>'; // End grid
                                
                                // Accessories jika ada
                                if (!empty($data['accessories_list']) && is_array($data['accessories_list'])) {
                                    $html .= '<div class="mt-4 pt-4 border-t">';
                                    $html .= '<h4 class="font-semibold text-gray-800 mb-2">Accessories</h4>';
                                    $html .= '<div class="flex flex-wrap gap-2">';
                                    foreach ($data['accessories_list'] as $accessoryId) {
                                        $accessory = \App\Models\Accessory::find($accessoryId);
                                        if ($accessory) {
                                            $html .= '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">';
                                            $html .= $accessory->name . ' (Rp ' . number_format($accessory->price, 0, ',', '.') . ')';
                                            $html .= '</span>';
                                        }
                                    }
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }
                                
                                // Ukuran jika ada
                                if (!empty($data['size']) && is_array($data['size'])) {
                                    $html .= '<div class="mt-4 pt-4 border-t">';
                                    $html .= '<h4 class="font-semibold text-gray-800 mb-2">Ukuran</h4>';
                                    $html .= '<div class="grid grid-cols-3 gap-3 text-sm">';
                                    foreach ($data['size'] as $key => $value) {
                                        if (!empty($value)) {
                                            $label = ucwords(str_replace('_', ' ', $key));
                                            $html .= '<div class="flex justify-between bg-gray-50 px-2 py-1 rounded">';
                                            $html .= '<span class="font-medium">' . $label . ':</span>';
                                            $html .= '<span>' . $value . ' cm</span>';
                                            $html .= '</div>';
                                        }
                                    }
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }
                                
                                // Total
                                $html .= '<div class="mt-6 pt-4 border-t">';
                                $html .= '<div class="bg-green-50 border border-green-200 rounded-lg p-4">';
                                $html .= '<div class="flex justify-between items-center">';
                                $html .= '<span class="text-lg font-semibold text-gray-800">Total Keseluruhan:</span>';
                                $html .= '<span class="text-2xl font-bold text-green-600">Rp ' . number_format($data['price'] ?? 0, 0, ',', '.') . '</span>';
                                $html .= '</div>';
                                $html .= '</div>';
                                $html .= '</div>';
                                
                                $html .= '</div>'; // End main container
                                
                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->dehydrated(false)
                    ])
                    ->visible(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    })
                    ->collapsible(false)
                    ->columnSpanFull(),
    
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
                            ->columnSpanFull()
                            ->dehydrated(false)
                            ->disabled(function ($livewire) {
                                return $livewire->isPreviewMode ?? false;
                            }),
    
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
                            ->dehydrated(false)
                            ->disabled(function ($livewire) {
                                return $livewire->isPreviewMode ?? false;
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $imageModel = \App\Models\ImageModel::find($state);
                                    if ($imageModel && $imageModel->image) {
                                        $set('photo', $imageModel->image);
                                    }
                                } else {
                                    $set('photo', null);
                                }
                            }),
    
                        \Filament\Forms\Components\Placeholder::make('image_preview')
                            ->label('Preview Gambar')
                            ->visible(fn (Get $get) => $get('image_source') === 'existing' && $get('temp_image_model_id'))
                            ->dehydrated(false)
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
                            ->disabled(function ($livewire) {
                                return $livewire->isPreviewMode ?? false;
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
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
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
    
                    Select::make('customer_id')
                    ->label('Pilih Customer')
                    ->options(
                        \App\Models\Customer::all()->mapWithKeys(function ($customer) {
                            return [$customer->id => "[ID: {$customer->id}] {$customer->name}"];
                        })
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    })
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
                    ->readonly(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    })
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
    
                Forms\Components\Textarea::make('address')
                    ->label('Alamat Pemesanan')
                    ->required()
                    ->maxLength(255)
                    ->readonly(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    })
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
    
                Forms\Components\TextInput::make('phone')
                    ->label('No.Telefon Pemesan')
                    ->tel()
                    ->required('No. Telefon Pemesan wajib diisi.')
                    ->maxLength(255)
                    ->readonly(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    })
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
    
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
                    ->helperText('Pilih satu atau lebih accessories yang terkait dengan order')
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
                
                Forms\Components\TextInput::make('description')
                    ->label('Deskripsi')
                    ->maxLength(255)
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
    
                    Select::make('ditugaskan_ke')
                    ->label('Ditugaskan Ke')
                    ->relationship(
                        name: 'user',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query
                            ->where('role', 'karyawan')
                            ->whereDoesntHave('orders', function (Builder $subQuery) {
                                $subQuery->where('status', 'dikerjakan');
                            }, '>', 1), // HANYA jika jumlah > 1 yang dikerjakan
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
                
    
                Select::make('sizemodel_id')
                    ->label('Pilih Model Ukuran')
                    ->options(\App\Models\SizeModel::pluck('name', 'id'))
                    ->required()
                    ->live()
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    })
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
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    })
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
                    ->readonly()
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
    
                Forms\Components\TextInput::make('price')
                    ->label('Harga')
                    ->numeric()
                    ->required('Harga wajib diisi.')
                    ->disabled(function ($livewire) {
                        return $livewire->isPreviewMode ?? false;
                    }),
    
                // Hidden field untuk status default
                Forms\Components\Hidden::make('status')
                    ->default('dikerjakan'),
    
                // Section untuk menampilkan field ukuran dinamis
                Section::make('Ukuran')
                    ->schema(function (Get $get, $livewire) {
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
                                ->required('wajib diisi.')
                                ->disabled(function () use ($livewire) {
                                    return $livewire->isPreviewMode ?? false;
                                });
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
                // Filter Nama Pemesan
                Tables\Filters\Filter::make('name')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Cari Nama Pemesan'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['name'], fn ($q) => 
                            $q->where('name', 'like', '%' . $data['name'] . '%')
                        );
                    }),
            
                // Filter Tanggal Deadline
                Tables\Filters\Filter::make('deadline')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => 
                                $q->whereDate('deadline', '>=', $date)
                            )
                            ->when($data['until'], fn ($q, $date) => 
                                $q->whereDate('deadline', '<=', $date)
                            );
                    }),
            ])
            
            // Tambahkan ini di bagian actions dalam method table()
// Atau jika ingin langsung dengan HTML inline:
->actions([
    Tables\Actions\Action::make('view')
        ->label('Detail')
        ->color('info')
        ->icon('heroicon-o-eye')
        ->modalHeading('Detail Pesanan')
        ->modalWidth('7xl')
        ->modalContent(function (Order $record) {
            $html = '<div class="bg-white p-6 space-y-6">';
            
            // Header
            $html .= '<div class="border-b pb-4 mb-6">';
            $html .= '<h2 class="text-2xl font-bold text-gray-900">Pesanan #' . $record->id . '</h2>';
            $html .= '<p class="text-gray-600 mt-1">Status: <span class="font-semibold text-blue-600">' . ucfirst($record->status) . '</span></p>';
            $html .= '</div>';
            
            // Images Section
            if ($record->images && count($record->images) > 0) {
                $html .= '<div class="mb-6">';
                $html .= '<h3 class="text-lg font-semibold text-gray-800 mb-3">Foto Model</h3>';
                $html .= '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                
                foreach ($record->images as $image) {
                    if ($image['photo']) {
                        $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($image['photo']);
                        $html .= '<div class="border rounded-lg overflow-hidden shadow-sm">';
                        $html .= '<img src="' . $imageUrl . '" alt="Foto Model" class="w-full h-48 object-cover">';
                        $html .= '<div class="p-2 bg-gray-50">';
                        $html .= '<a href="' . $imageUrl . '" target="_blank" class="text-sm text-blue-600 hover:underline">Lihat Ukuran Penuh</a>';
                        $html .= '</div>';
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
                $html .= '</div>';
            }
            
            // Customer & Order Info Grid
            $html .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';
            
            // Customer Info
            $html .= '<div class="bg-gray-50 rounded-lg p-4">';
            $html .= '<h3 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Informasi Customer</h3>';
            $html .= '<div class="space-y-2">';
            $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Nama:</span><span class="font-medium">' . ($record->name ?? '-') . '</span></div>';
            $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">No. Telepon:</span><span class="font-medium">' . ($record->phone ?? '-') . '</span></div>';
            $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Alamat:</span><span class="font-medium">' . ($record->address ?? '-') . '</span></div>';
            $html .= '</div>';
            $html .= '</div>';
            
            // Order Info
            $html .= '<div class="bg-blue-50 rounded-lg p-4">';
            $html .= '<h3 class="text-lg font-semibold text-gray-800 mb-3 border-b pb-2">Detail Pesanan</h3>';
            $html .= '<div class="space-y-2">';
            $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Jumlah:</span><span class="font-medium">' . ($record->quantity ?? 1) . ' pcs</span></div>';
            $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Harga:</span><span class="font-medium text-green-600">Rp ' . number_format($record->price ?? 0, 0, ',', '.') . '</span></div>';
            
            if ($record->deadline) {
                $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Deadline:</span><span class="font-medium">' . \Carbon\Carbon::parse($record->deadline)->format('d/m/Y H:i') . '</span></div>';
            }
            
            if ($record->description) {
                $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Deskripsi:</span><span class="font-medium">' . $record->description . '</span></div>';
            }
            
            if ($record->user) {
                $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Ditugaskan Ke:</span><span class="font-medium">' . $record->user->name . '</span></div>';
            }
            
            if ($record->sizeModel) {
                $html .= '<div class="flex flex-col"><span class="text-sm font-medium text-gray-600">Model Ukuran:</span><span class="font-medium">' . $record->sizeModel->name . '</span></div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '</div>'; // End grid
            
            // Accessories
            if ($record->accessories_list && is_array($record->accessories_list) && count($record->accessories_list) > 0) {
                $html .= '<div class="bg-yellow-50 rounded-lg p-4">';
                $html .= '<h3 class="text-lg font-semibold text-gray-800 mb-3">Accessories</h3>';
                $html .= '<div class="flex flex-wrap gap-2">';
                foreach ($record->accessories_list as $accessoryId) {
                    $accessory = \App\Models\Accessory::find($accessoryId);
                    if ($accessory) {
                        $html .= '<span class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">';
                        $html .= $accessory->name . ' (Rp ' . number_format($accessory->price, 0, ',', '.') . ')';
                        $html .= '</span>';
                    }
                }
                $html .= '</div>';
                $html .= '</div>';
            }
            
            // Size Details
            if ($record->size && is_array($record->size) && count(array_filter($record->size)) > 0) {
                $html .= '<div class="bg-green-50 rounded-lg p-4">';
                $html .= '<h3 class="text-lg font-semibold text-gray-800 mb-3">Detail Ukuran</h3>';
                $html .= '<div class="grid grid-cols-2 md:grid-cols-3 gap-4">';
                foreach ($record->size as $key => $value) {
                    if (!empty($value)) {
                        $label = ucwords(str_replace('_', ' ', $key));
                        $html .= '<div class="bg-white border border-green-200 rounded-lg p-3">';
                        $html .= '<div class="text-sm font-medium text-gray-600">' . $label . '</div>';
                        $html .= '<div class="text-lg font-semibold text-green-700">' . $value . ' cm</div>';
                        $html .= '</div>';
                    }
                }
                $html .= '</div>';
                $html .= '</div>';
            }
            
            // Timeline
            $html .= '<div class="bg-gray-50 rounded-lg p-4">';
            $html .= '<h3 class="text-lg font-semibold text-gray-800 mb-3">Timeline</h3>';
            $html .= '<div class="space-y-2 text-sm">';
            $html .= '<div class="flex justify-between"><span class="text-gray-600">Dibuat:</span><span class="font-medium">' . $record->created_at->format('d/m/Y H:i') . '</span></div>';
            if ($record->updated_at != $record->created_at) {
                $html .= '<div class="flex justify-between"><span class="text-gray-600">Terakhir Diupdate:</span><span class="font-medium">' . $record->updated_at->format('d/m/Y H:i') . '</span></div>';
            }
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '</div>'; // End main container
            
            return new \Illuminate\Support\HtmlString($html);
        })
        ->modalActions([
            Tables\Actions\Action::make('close')
                ->label('Tutup')
                ->color('gray')
                ->close(),
        ]),
])
            
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
