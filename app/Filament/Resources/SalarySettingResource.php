<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalarySettingResource\Pages;
use App\Filament\Resources\SalarySettingResource\RelationManagers;
use App\Models\SalarySetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class SalarySettingResource extends Resource
{
    protected static ?string $model = SalarySetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Pengaturan Gaji';

    public static function getModelLabel(): string
    {
        return 'Pengaturan Gaji Karyawan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pengaturan Daftar Gaji';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Pengaturan';
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // TextInput::make('name')
                // ->label('Nama Setting Gaji')
                // ->required()
                // ->maxLength(255),

            TextInput::make('salary')
                ->label('Gaji Pokok')
                ->numeric()
                ->required()
                ->prefix('Rp')
                ->rules(['min:0']),

            // Select::make('periode')
            //     ->label('Periode Penggajian')
            //     ->options([
            //         'daily' => 'Harian',
            //         'weekly' => 'Mingguan',
            //         'monthly' => 'Bulanan',
            //     ])
            //     ->required(),

            TextInput::make('reduction_if_absent')
                ->label('Potongan Jika Tidak Hadir')
                ->numeric()
                ->required()
                ->prefix('Rp')
                ->rules(['min:0']),

            // TextInput::make('permit_reduction')
            //     ->label('Potongan Jika Izin')
            //     ->numeric()
            //     ->required()
            //     ->prefix('Rp')
            //     ->rules(['min:0']),
            Forms\Components\TextInput::make('deduction_per_minute')
                ->label('Potongan Telat Permenit')
                ->numeric()
                ->required()
                ->prefix('Rp')
                ->rules(['min:0']),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Tables\Columns\TextColumn::make('name')
                //     ->label('Nama Setting Gaji')
                //     ->searchable(),
                Tables\Columns\TextColumn::make('salary')
                    ->label('Gaji Pokok')
                    ->numeric()
                    ->sortable(),
                // Tables\Columns\TextColumn::make('periode')
                //     ->label('Periode Penggajian'),
                Tables\Columns\TextColumn::make('reduction_if_absent')
                    ->label('Potongan Jika Tidak Hadir')
                    ->numeric()
                    ->sortable(),
                // Tables\Columns\TextColumn::make('permit_reduction')
                //     ->label('Potongan Jika Izin')
                //     ->numeric()
                //     ->sortable(),
                Tables\Columns\TextColumn::make('deduction_per_minute')
                    ->label('Potongan Telat Permenit')
                    ->numeric()
                    ->sortable(),
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
            'index' => Pages\ListSalarySettings::route('/'),
            'create' => Pages\CreateSalarySetting::route('/create'),
            'edit' => Pages\EditSalarySetting::route('/{record}/edit'),
        ];
    }
}
