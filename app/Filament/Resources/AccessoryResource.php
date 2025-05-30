<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccessoryResource\Pages;
use App\Filament\Resources\AccessoryResource\RelationManagers;
use App\Models\Accessory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccessoryResource extends Resource
{
    protected static ?string $model = Accessory::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Aksesoris Pesanan';

    public static function getModelLabel(): string
    {
        return 'Aksesoris Pesanan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Daftar Aksesoris Pesanan';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Pesanan';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
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
            'index' => Pages\ListAccessories::route('/'),
            'create' => Pages\CreateAccessory::route('/create'),
            'edit' => Pages\EditAccessory::route('/{record}/edit'),
        ];
    }
}
