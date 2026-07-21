<?php

namespace App\Filament\Resources\Companies;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'empresas';

    protected static ?string $navigationLabel = 'Empresas';

    protected static ?int $navigationSort = 40;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Filament::getTenant()->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(CompanyForm::components());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document')
                    ->label('CNPJ / CPF')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('assets_count')
                    ->label('Ativos')
                    ->counts('assets')
                    ->badge()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('income_12m')
                    ->label('Receitas 12m')
                    ->alignEnd()
                    ->color('success')
                    ->getStateUsing(fn (Company $record): float => $record->incomeLastTwelveMonths())
                    ->money('BRL'),
                TextColumn::make('expenses_12m')
                    ->label('Despesas 12m')
                    ->alignEnd()
                    ->color('danger')
                    ->getStateUsing(fn (Company $record): float => $record->expensesLastTwelveMonths())
                    ->money('BRL'),
                TextColumn::make('net_result_12m')
                    ->label('Resultado 12m')
                    ->alignEnd()
                    ->badge()
                    ->getStateUsing(fn (Company $record): float => $record->netResultLastTwelveMonths())
                    ->formatStateUsing(fn (float $state): string => ($state >= 0 ? '+' : '-').'R$ '.number_format(abs($state), 2, ',', '.'))
                    ->color(fn (float $state): string => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label('Cidade')
                    ->formatStateUsing(fn (?string $state, Company $record): string => trim(($state ?? '').($record->state ? "/{$record->state}" : ''), '/'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Cadastrada em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('Nenhuma empresa cadastrada')
            ->emptyStateDescription('Cadastre suas empresas para associar ativos a elas (ex: o trator da Fazenda X Ltda).')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
