<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderBonusResource\Pages;
use App\Filament\Resources\OrderBonusResource\RelationManagers;
use App\Models\OrderBonus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderBonusResource extends Resource
{
    protected static ?string $model = OrderBonus::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Bonus Karyawan';

    public static function getModelLabel(): string
    {
        return 'Bonus Karyawan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Daftar Bonus';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Gaji';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('salary_id')
                    ->numeric(),
                Forms\Components\TextInput::make('bonus_amount')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.name')
                ->label('Nama Pemesan')

                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                ->label('Nama Karyawan')

                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bonus_amount')
                    ->label('Total Bonus')
                    ->numeric()
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
            'index' => Pages\ListOrderBonuses::route('/'),
            // 'create' => Pages\CreateOrderBonus::route('/create'),
            // 'edit' => Pages\EditOrderBonus::route('/{record}/edit'),
        ];
    }
}
