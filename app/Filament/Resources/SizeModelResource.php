<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SizeModelResource\Pages;
use App\Filament\Resources\SizeModelResource\RelationManagers;
use App\Models\SizeModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Components\Text; // Import komponen Text
use Filament\Forms\Set;
use Filament\Forms\Components\Placeholder;  // Ganti StaticText dengan Placeholder
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Button;

class SizeModelResource extends Resource
{
    protected static ?string $model = SizeModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Ukuran Model';

    public static function getModelLabel(): string
    {
        return 'Ukuran Model';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Daftar Ukuran Model';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Pesanan';
    }

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            TextInput::make('name')
                ->label('Nama Model')
                ->required()
                ->maxLength(255),

            Hidden::make('size')
                ->default(function ($record) {
                    return $record ? $record->size : [];
                }),

            Section::make('Ukuran')
                ->schema([
                    Grid::make(1)
                        ->schema([


                            // Tampilkan ukuran yang sudah ada
                            Grid::make(3)
                                ->schema(function (Get $get) {
                                    $currentSize = $get('size') ?? [];
                                    if (is_string($currentSize)) {
                                        $currentSize = json_decode($currentSize, true) ?? [];
                                    }

                                    return collect($currentSize)
                                        ->map(function ($ukuran, $index) {
                                            return Grid::make()
                                                ->schema([
                                                    Placeholder::make("size_display_{$index}")
                                                        ->label('Ukuran')
                                                        ->content($ukuran),
                                                    Actions::make([
                                                        Action::make("hapus_ukuran_{$index}")
                                                            ->label('Hapus')
                                                            ->color('danger')
                                                            ->icon('heroicon-m-trash')
                                                            ->requiresConfirmation()
                                                            ->action(function (Get $get, Set $set) use ($index) {
                                                                $currentSize = $get('size') ?? [];
                                                                if (is_string($currentSize)) {
                                                                    $currentSize = json_decode($currentSize, true) ?? [];
                                                                }

                                                                unset($currentSize[$index]);
                                                                $currentSize = array_values($currentSize);

                                                                $set('size', $currentSize);
                                                            }),
                                                    ])
                                                ])
                                                ->columns(2);
                                        })
                                        ->toArray();
                                }),

                                TextInput::make('new_size')
                                ->label('Tambah Ukuran Baru')
                                ->placeholder('Masukkan nama ukuran')
                                ->maxLength(255),

                            Actions::make([
                                Action::make('tambahUkuran')
                                    ->label('Tambah')
                                    ->requiresConfirmation()
                                    ->action(function (Get $get, Set $set) {
                                        $newSize = $get('new_size');
                                        $currentSize = $get('size') ?? [];

                                        if (!empty($newSize)) {
                                            if (is_string($currentSize)) {
                                                $currentSize = json_decode($currentSize, true) ?? [];
                                            }

                                            $currentSize = array_merge($currentSize, [$newSize]);
                                            $set('size', $currentSize);
                                            $set('new_size', '');
                                        }
                                    }),
                            ]),


                        ])
                ])
                ->columnSpanFull()
        ]);
}

    // Method untuk memproses data sebelum disimpan
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Pastikan data size disimpan sebagai array
        $data['size'] = $data['size'] ?? [];
        return $data;
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('created_at')
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('updated_at')
                //     ->dateTime()
                //     ->sortable()
                //     ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListSizeModels::route('/'),
            'create' => Pages\CreateSizeModel::route('/create'),
            'edit' => Pages\EditSizeModel::route('/{record}/edit'),
        ];
    }
}
