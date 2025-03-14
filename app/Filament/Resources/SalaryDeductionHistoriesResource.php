<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryDeductionHistoriesResource\Pages;
use App\Filament\Resources\SalaryDeductionHistoriesResource\RelationManagers;
use App\Models\SalaryDeductionHistories;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalaryDeductionHistoriesResource extends Resource
{
    protected static ?string $model = SalaryDeductionHistories::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('salary_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('attendance_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('deduction_type')
                    ->required(),
                Forms\Components\TextInput::make('late_minutes')
                    ->numeric(),
                Forms\Components\TextInput::make('deduction_amount')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('deduction_per_minute')
                    ->numeric(),
                Forms\Components\TextInput::make('reduction_if_absent')
                    ->numeric(),
                Forms\Components\DatePicker::make('deduction_date')
                    ->required(),
                Forms\Components\Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salary_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('attendance_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deduction_type'),
                Tables\Columns\TextColumn::make('late_minutes')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deduction_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deduction_per_minute')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reduction_if_absent')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deduction_date')
                    ->date()
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
            'index' => Pages\ListSalaryDeductionHistories::route('/'),
            'create' => Pages\CreateSalaryDeductionHistories::route('/create'),
            'edit' => Pages\EditSalaryDeductionHistories::route('/{record}/edit'),
        ];
    }
}
