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

    protected static ?string $navigationLabel = 'Riwayat Potongan Gaji';

    public static function getModelLabel(): string
    {
        return 'Riwayat Potongan Gaji';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Daftar Potongan Gaji';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Gaji';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('user.name') // Mengambil nama dari relasi User
                ->label('Nama Karyawan')
                ->sortable(),
            Tables\Columns\TextColumn::make('deduction_date')
                ->label('Tanggal Potongan')
                ->date()
                ->sortable(),

            // Tables\Columns\TextColumn::make('salary.name') // Mengambil nama dari relasi Salary
            //     ->label('Nama Gaji')
            //     ->sortable(),

            // Tables\Columns\TextColumn::make('attendance.name') // Mengambil nama dari relasi Attendance
            //     ->label('Nama Kehadiran')
            //     ->sortable(),

            Tables\Columns\TextColumn::make('deduction_type')
                ->label('Tipe Potongan'),

            Tables\Columns\TextColumn::make('late_minutes')
                ->label('Menit Keterlambatan')
                ->numeric()
                ->sortable(),


            Tables\Columns\TextColumn::make('deduction_per_minute')
                ->label('Potongan Per Menit')
                ->numeric()
                ->sortable(),

            Tables\Columns\TextColumn::make('reduction_if_absent')
                ->label('Potongan Tidak Hadir')
                ->numeric()
                ->sortable(),
                Tables\Columns\TextColumn::make('deduction_amount')
                ->label('Jumlah Potongan')
                ->numeric()
                ->sortable(),


            // Tables\Columns\TextColumn::make('created_at')
            //     ->label('Dibuat Pada')
            //     ->dateTime()
            //     ->sortable()
            //     ->toggleable(isToggledHiddenByDefault: true),

            // Tables\Columns\TextColumn::make('updated_at')
            //     ->label('Diperbarui Pada')
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
            'index' => Pages\ListSalaryDeductionHistories::route('/'),
            // 'create' => Pages\CreateSalaryDeductionHistories::route('/create'),
            // 'edit' => Pages\EditSalaryDeductionHistories::route('/{record}/edit'),
        ];
    }
}
