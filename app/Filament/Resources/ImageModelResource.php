<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImageModelResource\Pages;
use App\Filament\Resources\ImageModelResource\RelationManagers;
use App\Models\ImageModel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ImageModelResource extends Resource
{
    protected static ?string $model = ImageModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Gambar Model';

    public static function getModelLabel(): string
    {
        return 'Gambar Model';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Daftar Gambar Model';
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
                    ->label('Nama Model')
                    ->required()
                    ->maxLength(255),
                Forms\Components\FileUpload::make('image')
                    ->label('Gambar Model')
                    ->image()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                ->label('Nama Model')

                    ->searchable(),
                Tables\Columns\ImageColumn::make('image')
                ->label('Gambar Model'),
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
            'index' => Pages\ListImageModels::route('/'),
            'create' => Pages\CreateImageModel::route('/create'),
            'edit' => Pages\EditImageModel::route('/{record}/edit'),
        ];
    }
}
