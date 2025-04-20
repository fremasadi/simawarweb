<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreSettingResource\Pages;
use App\Filament\Resources\StoreSettingResource\RelationManagers;
use App\Models\StoreSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;

class StoreSettingResource extends Resource
{
    protected static ?string $model = StoreSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Pengaturan Toko';

    public static function getModelLabel(): string
    {
        return 'Pengaturan Toko';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pengaturan Toko';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Pengaturan';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TimePicker::make('open_time')
                    ->label('Jam Buka')
                    ->required()
                    ->seconds(false), // Tidak perlu input detik

                TimePicker::make('close_time')
                    ->label('Jam Tutup')
                    ->required()
                    ->seconds(false),

                // Toggle::make('is_open')
                //     ->label('Status Toko')
                //     ->onIcon('heroicon-s-check')
                //     ->offIcon('heroicon-s-x')
                //     ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('open_time')
                    ->label('Waktu Buka')
                ,
                Tables\Columns\TextColumn::make('close_time')
                    ->label('Waktu Tutup')
                ,
                // Tables\Columns\IconColumn::make('is_open')
                //     ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListStoreSettings::route('/'),
            'create' => Pages\CreateStoreSetting::route('/create'),
            'edit' => Pages\EditStoreSetting::route('/{record}/edit'),
        ];
    }
}
