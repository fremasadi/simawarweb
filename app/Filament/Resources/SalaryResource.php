<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryResource\Pages;
use App\Filament\Resources\SalaryResource\RelationManagers;
use App\Models\Salary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalaryResource extends Resource
{
    protected static ?string $model = Salary::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Gaji Karyawan';

    public static function getModelLabel(): string
    {
        return 'Gaji Karyawan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Daftar Gaji';
    }


    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Gaji';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('user_name')
                    ->label('Nama Pengguna')
                    ->content(fn ($record) => $record->user?->name ?? '-'),
                // Forms\Components\Select::make('salary_setting_id')
                //     ->relationship('salarySetting', 'name')
                //     ->required(),
                Forms\Components\TextInput::make('total_salary')
                    ->label('Total gaji')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('total_deduction')
                    ->label('Total Potongan')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                    Forms\Components\Select::make('status')
                    ->label('Status Pembayaran')
                    ->options([
                        'pending' => 'Tertunda',
                        'paid' => 'Selesai',
                    ])
                    ->required(),
                // Forms\Components\Textarea::make('note')
                //     ->columnSpanFull(),
                Forms\Components\DatePicker::make('pay_date')
                    ->label('Tanggal Pembayaran')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama')
                    ->numeric()
                    ->sortable(),
                    Tables\Columns\TextColumn::make('salarySetting.salary')
                    ->label('Gaji')
                    ->numeric()
                    ->sortable()
                    ->money('Rp.'), // Opsional: format sebagai mata uang
                Tables\Columns\TextColumn::make('total_deduction')
                    ->label('Potongan')
                    ->numeric()
                    ->sortable()
                    ->money('Rp.'), // Opsional: format sebagai mata uang
                    Tables\Columns\TextColumn::make('total_salary')
                    ->label('Total Gaji')
                    ->numeric()
                    ->sortable()
                    ->money('Rp.'), // Opsional: format sebagai mata uang
                    Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'pending' => 'Tertunda',
                            'paid' => 'Selesai',
                            default => ucfirst($state),
                        };
                    }),
                                Tables\Columns\TextColumn::make('pay_date')
                    ->label('waktu pembayaran')
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
                Tables\Filters\SelectFilter::make('bulan')
                    ->label('Filter Bulan')
                    ->options(
                        collect(range(1, 12))->mapWithKeys(function ($month) {
                            return [$month => \Carbon\Carbon::create()->month($month)->translatedFormat('F')];
                        })->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query, $month) {
                            return $query->whereMonth('pay_date', $month);
                        });
                    }),
            
                Tables\Filters\SelectFilter::make('tahun')
                    ->label('Filter Tahun')
                    ->options(
                        Salary::query()
                            ->selectRaw('YEAR(pay_date) as year')
                            ->distinct()
                            ->orderBy('year', 'desc')
                            ->pluck('year', 'year')
                            ->toArray()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query, $year) {
                            return $query->whereYear('pay_date', $year);
                        });
                    }),
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
            'index' => Pages\ListSalaries::route('/'),
            'create' => Pages\CreateSalary::route('/create'),
            'edit' => Pages\EditSalary::route('/{record}/edit'),
        ];
    }
}
